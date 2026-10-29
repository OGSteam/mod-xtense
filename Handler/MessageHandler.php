<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class MessageHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $lineType = $data['type'] ?? '';
        $returnAsSpy = ($data['_xtense_return_as'] ?? null) === 'spy';
        unset($data['_xtense_return_as'], $data['_xtense_raw_payload']);
        switch ($lineType) {
            case 'msg':
                $this->handlePersonalMessage($data);
                break;
            case 'ally_msg':
                $this->handleAllyMessage($data);
                break;
            case 'spy':
            case 'spy_shared':
                $this->handleSpyReport($data);
                break;
            case 'ennemy_spy':
                $this->handleEnemySpy($data);
                break;
            case 'rc_cdr':
                $this->handleRecyclerReport($data);
                break;
            case 'expedition':
            case 'expedition_shared':
                $this->handleExpedition($data);
                break;
            default:
                throw new UnexpectedValueException('Message category not found ' . $lineType);
        }

        $this->io->set(['type' => ($returnAsSpy ? 'spy' : 'messages')]);
        $this->logAction('messages');
    }

    private function handlePersonalMessage(array $line): void
    {
        if (!isset($line['coords'], $line['from'], $line['subject'], $line['message'])) {
            throw new UnexpectedValueException('Personal Message: Incomplete Metadata ');
        }
        $coordinates = Check::coords($line['coords']);
        $this->registerCallback('msg', [
            'coords' => explode(':', $coordinates),
            'from' => filter_var($line['from']),
            'subject' => filter_var($line['subject']),
            'message' => filter_var($line['message']),
            'time' => $line['date'] ?? null,
        ]);
    }

    private function handleAllyMessage(array $line): void
    {
        if (!isset($line['from'], $line['tag'], $line['message'])) {
            throw new UnexpectedValueException('Alliance Message: Incomplete Metadata ');
        }
        $this->registerCallback('ally_msg', [
            'from' => filter_var($line['from']),
            'tag' => filter_var($line['tag']),
            'message' => filter_var($line['message']),
            'time' => $line['date'] ?? null,
        ]);
    }

    private function handleSpyReport(array $line): void
    {
        if (!isset(
            $line['player']['name'],
            $line['planet']['name'],
            $line['planet']['coordinates'],
            $line['planet']['id'],
            $line['resources'],
            $line['proba'],
            $line['activity'],
            $line['date']
        )) {
            throw new UnexpectedValueException('Spy: Incomplete Metadata ');
        }

        global $databaseSpyId;
        $coordinates = Check::coords($line['planet']['coordinates']);
        $content = [];
        foreach (['resources', 'buildings', 'lfBuildings', 'research', 'lfResearch', 'fleet', 'defense'] as $section) {
            if (!isset($line[$section]) || !is_array($line[$section])) {
                $line[$section] = [];
            }
            if ($line[$section] !== []) {
                $content += $line[$section];
            }
        }

        $spy = [
            'proba' => min(100, (int)filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT)),
            'activite' => min(59, (int)filter_var($line['activity'], FILTER_SANITIZE_NUMBER_INT)),
            'coords' => $coordinates,
            'content' => $content,
            'time' => (int)filter_var($line['date'], FILTER_SANITIZE_NUMBER_INT),
            'player_name' => filter_var($line['player']['name']),
            'planet_name' => filter_var($line['planet']['name']),
            'planet_id' => (int)$line['planet']['id'],
        ];
        $this->registerCallback($line['type'], $spy);

    $databaseSpyId = is_array($databaseSpyId ?? null) ? $databaseSpyId : [];
        $spyDatabase = [];
        foreach ($databaseSpyId as $mapping) {
            $spyDatabase += $mapping;
        }
        $fields = ['`astro_object_id`', '`planet_name`', '`metal`', '`crystal`', '`deuterium`', '`sender_id`', '`proba`', '`activite`', '`dateRE`'];
        $values = [
            $spy['planet_id'],
            "'" . $this->db->sql_escape_string($spy['planet_name']) . "'",
            (int)($content['metal'] ?? 0),
            (int)($content['crystal'] ?? 0),
            (int)($content['deuterium'] ?? 0),
            (int)$this->userData['id'],
            $spy['proba'],
            $spy['activite'],
            $spy['time'],
        ];
        foreach ($spyDatabase as $code => $column) {
            if (in_array($column, ['metal', 'crystal', 'deuterium'], true) || !isset($content[$code])) {
                continue;
            }
            $fields[] = '`' . $column . '`';
            $values[] = (int)$content[$code];
        }

        $exists = $this->db->sql_numrows($this->db->sql_query(
            "SELECT `id` FROM " . TABLE_PARSEDSPY . " WHERE `astro_object_id` = '{$spy['planet_id']}' AND `dateRE` = '{$spy['time']}'"
        ));
        if ($exists) {
            return;
        }

        $this->db->sql_query('INSERT INTO ' . TABLE_PARSEDSPY . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')');
        $spiedPlayerId = -1;
        $playerResult = $this->db->sql_query("SELECT `id` FROM " . TABLE_GAME_PLAYER . " WHERE `name` = '" . $this->db->sql_escape_string($spy['player_name']) . "'");
        if ($this->db->sql_numrows($playerResult) > 0) {
            $playerRow = $this->db->sql_fetch_row($playerResult);
            $spiedPlayerId = (int)$playerRow[0];
        }

        $parsed = $this->parseCoordinates($coordinates);
        $planetType = (($line['planet']['type'] ?? null) === '3') ? 'moon' : 'planet';
        $insertFields = ['id', 'galaxy', 'system', 'row', 'type', 'name', 'last_update', 'last_update_user_id'];
        $insertValues = [
            $spy['planet_id'], $parsed['galaxy'], $parsed['system'], $parsed['row'],
            "'{$planetType}'", "'" . $this->db->sql_escape_string($spy['planet_name']) . "'",
            $spy['time'], (int)$this->userData['id'],
        ];
        $updates = ['name = VALUES(name)', 'last_update = VALUES(last_update)', 'last_update_user_id = VALUES(last_update_user_id)', 'type = VALUES(type)', 'galaxy = VALUES(galaxy)', 'system = VALUES(system)', 'row = VALUES(row)'];
        if ($spiedPlayerId !== -1) {
            $insertFields[] = 'player_id';
            $insertValues[] = $spiedPlayerId;
            $updates[] = 'player_id = VALUES(player_id)';
        }
        $this->appendMappedUpsertFields($line['buildings'] ?? [], $databaseSpyId['buildings'] ?? [], $insertFields, $insertValues, $updates);
        $this->db->sql_query('INSERT INTO ' . TABLE_USER_BUILDING . ' (`' . implode('`, `', $insertFields) . '`) VALUES (' . implode(', ', $insertValues) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates));

        $this->appendCategoryUpsert($spy['planet_id'], $line['defense'] ?? [], $databaseSpyId['defense'] ?? [], TABLE_GAME_PLAYER_DEFENSE);
        $this->appendCategoryUpsert($spy['planet_id'], $line['fleet'] ?? [], $databaseSpyId['fleet'] ?? [], TABLE_GAME_PLAYER_FLEET);
        if ($spiedPlayerId !== -1) {
            $this->appendCategoryUpsert($spiedPlayerId, $line['research'] ?? [], $databaseSpyId['labo'] ?? [], TABLE_USER_TECHNOLOGY, 'player_id');
        }

        $this->db->sql_query('UPDATE ' . TABLE_USER . ' SET `spy_imports` = spy_imports + 1 WHERE `id` = ' . (int)$this->userData['id']);
        update_statistic('spyimports', '1');
        $this->logAction('messages', ['added_spy' => $spy['planet_name'], 'added_spy_coords' => $spy['coords']]);
    }

    private function appendMappedUpsertFields(array $data, array $mapping, array &$fields, array &$values, array &$updates): void
    {
        foreach ($mapping as $code => $column) {
            if (isset($data[$code])) {
                $fields[] = $column;
                $values[] = (int)$data[$code];
                $updates[] = "`{$column}` = VALUES(`{$column}`)";
            }
        }
    }

    private function appendCategoryUpsert(int $objectId, array $data, array $mapping, string $table, string $keyColumn = 'astro_object_id'): void
    {
        $fields = [];
        $values = [];
        $updates = [];
        foreach ($mapping as $code => $column) {
            if (isset($data[$code])) {
                $level = (int)$data[$code];
                $fields[] = "`{$column}`";
                $values[] = $level;
                $updates[] = "`{$column}` = {$level}";
            }
        }
        if ($fields === []) {
            return;
        }
        $this->db->sql_query("INSERT INTO {$table} ({$keyColumn}, " . implode(', ', $fields) . ') VALUES (' . $objectId . ', ' . implode(', ', $values) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates));
    }

    private function handleEnemySpy(array $line): void
    {
        if (!isset($line['from'], $line['to'], $line['proba'], $line['date'])) {
            throw new UnexpectedValueException('Ennemy Spy: Incomplete Metadata ');
        }
        $probability = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT);
        $from = Check::coords($line['from']);
        $to = Check::coords($line['to']);
        $userId = (int)($this->userData['user_id'] ?? $this->userData['id']);
        $query = "SELECT spy_id FROM " . TABLE_PARSEDSPYEN . " WHERE sender_id = '{$userId}' AND dateSpy = '{$line['date']}'";
        if ($this->db->sql_numrows($this->db->sql_query($query)) === 0) {
            $this->db->sql_query("INSERT INTO " . TABLE_PARSEDSPYEN . " (`dateSpy`, `from`, `to`, `proba`, `sender_id`) VALUES ('{$line['date']}', '{$from}', '{$to}', '{$probability}', '{$userId}')");
        }
        $this->registerCallback('ennemy_spy', [
            'from' => explode(':', $from),
            'to' => explode(':', $to),
            'proba' => (int)$probability,
            'time' => $line['date'],
        ]);
        add_log('info', ['toolbar' => $this->toolbarInfo, 'message' => 'a été espionné avec une probabilité de  ' . $probability]);
    }

    private function handleRecyclerReport(array $line): void
    {
        $required = ['nombre', 'coords', 'M_recovered', 'C_recovered', 'M_total', 'C_total', 'date'];
        foreach ($required as $field) {
            if (!isset($line[$field])) {
                throw new UnexpectedValueException('Harvesting Report: Incomplete Metadata ');
            }
        }
        $values = [];
        foreach (['nombre', 'M_recovered', 'C_recovered', 'M_total', 'C_total'] as $field) {
            $values[$field] = filter_var($line[$field], FILTER_SANITIZE_NUMBER_INT);
        }
        $coordinates = Check::coords($line['coords']);
        $userId = (int)($this->userData['user_id'] ?? $this->userData['id']);
        $query = "SELECT `id_rec` FROM " . TABLE_PARSEDREC . " WHERE `sender_id` = '{$userId}' AND `dateRec` = '{$line['date']}'";
        if ($this->db->sql_numrows($this->db->sql_query($query)) === 0) {
            $this->db->sql_query("INSERT INTO " . TABLE_PARSEDREC . " (`dateRec`, `coordinates`, `nbRec`, `M_total`, `C_total`, `M_recovered`, `C_recovered`, `sender_id`) VALUES ('{$line['date']}', '{$coordinates}', '{$values['nombre']}', '{$values['M_total']}', '{$values['C_total']}', '{$values['M_recovered']}', '{$values['C_recovered']}', '{$userId}')");
        }
        $this->registerCallback('rc_cdr', [
            'nombre' => (int)$values['nombre'],
            'coords' => explode(':', $coordinates),
            'M_reco' => (int)$values['M_recovered'],
            'C_reco' => (int)$values['C_recovered'],
            'M_total' => (int)$values['M_total'],
            'C_total' => (int)$values['C_total'],
            'time' => $line['date'],
        ]);
    }

    private function handleExpedition(array $line): void
    {
        if (!isset($line['coords'], $line['content'])) {
            throw new UnexpectedValueException('Expedition Message: Incomplete Metadata ');
        }
        $coordinates = Check::coords(filter_var($line['coords']), 1);
        $this->registerCallback($line['type'], [
            'time' => $line['date'] ?? null,
            'coords' => explode(':', $coordinates),
            'content' => filter_var($line['content']),
        ]);
    }

    public function getType(): string
    {
        return 'messages';
    }

    public function getRequiredGrant(): string
    {
        return 'messages';
    }
}
