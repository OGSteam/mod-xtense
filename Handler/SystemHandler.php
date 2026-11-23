<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class SystemHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $galaxy = filter_var($data['galaxy'], FILTER_SANITIZE_NUMBER_INT);
        $system = filter_var($data['system'], FILTER_SANITIZE_NUMBER_INT);
        if (!isset($galaxy, $system)) {
            throw new UnexpectedValueException('Galaxy: Missing Planet position');
        }

        $galaxy = (int)$galaxy;
        $system = (int)$system;
        if ($galaxy > $this->server_config['num_of_galaxies'] || $system > $this->server_config['num_of_systems']) {
            $this->io->set(['type' => 'plugin univers', 'access' => 'system']);
            $this->io->status(0);
            return;
        }

        $rows = $data['rows'];
        $systemData = [];
        for ($position = 1; $position < 16; $position++) {
            if (isset($rows[$position])) {
                $line = $rows[$position];
                $line['player_name'] = filter_var($line['player_name']);
                $line['planet_name'] = filter_var($line['planet_name']);
                $line['planet_id'] = filter_var($line['planet_id'], FILTER_VALIDATE_INT);
                $line['ally_tag'] = filter_var($line['ally_tag']);
                if (isset($line['debris'])) {
                    $line['debris'] = filter_var_array($line['debris'], [
                        'metal' => FILTER_SANITIZE_NUMBER_INT,
                        'crystal' => FILTER_SANITIZE_NUMBER_INT,
                        'deuterium' => FILTER_SANITIZE_NUMBER_INT,
                    ]);
                }
                if (isset($line['status'])) {
                    $line['status'] = filter_var($line['status']);
                }
                $systemData[$position] = $line;
                continue;
            }

            $systemData[$position] = [
                'planet_id' => '',
                'planet_name' => '',
                'player_name' => '',
                'status' => '',
                'ally_tag' => '',
                'debris' => ['metal' => 0, 'cristal' => 0, 'deuterium' => 0],
                'moon' => 0,
                'activity' => '',
            ];
        }

        foreach ($systemData as $position => $line) {
            $status = Check::player_status_forbidden($line['status']) ? '' : $line['status'];
            $playerId = isset($line['player_id']) ? (int)$line['player_id'] : -1;
            $planetId = isset($line['planet_id']) ? (int)$line['planet_id'] : -1;
            $moon = isset($line['moon']) ? (int)$line['moon'] : -1;
            $moonId = isset($line['moon_id']) ? (int)$line['moon_id'] : -1;
            $allyId = isset($line['ally_id']) ? (int)$line['ally_id'] : -1;
            $allyTag = $line['ally_tag'] ?? '';
            $currentTime = time();

            $this->db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                (`id`, `galaxy`, `system`, `row`, `name`, `player_id`, `last_update`, `last_update_user_id`)
                VALUES ({$planetId}, {$galaxy}, {$system}, {$position}, '{$line['planet_name']}', {$playerId}, {$currentTime}, " . (int)$this->userData['id'] . ")
                ON DUPLICATE KEY UPDATE
                    `name` = '{$line['planet_name']}',
                    `player_id` = {$playerId},
                    `last_update` = {$currentTime},
                    `last_update_user_id` = " . (int)$this->userData['id']);

            if ($moonId !== -1 && $moon === 1) {
                $this->db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                    (`id`, `type`, `galaxy`, `system`, `row`, `name`, `player_id`, `last_update_moon`, `last_update_user_id`)
                    VALUES ({$moonId}, 'moon', {$galaxy}, {$system}, {$position}, '{$line['planet_name']} - Moon', {$playerId}, {$currentTime}, " . (int)$this->userData['id'] . ")
                    ON DUPLICATE KEY UPDATE
                        `name` = '{$line['planet_name']} - Moon',
                        `player_id` = {$playerId},
                        `last_update_moon` = {$currentTime},
                        `last_update_user_id` = " . (int)$this->userData['id']);
            }

            if ($playerId !== -1) {
                $playerName = $this->db->sql_escape_string($line['player_name']);
                $escapedStatus = $this->db->sql_escape_string($status);
                $this->db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER . "
                    (`id`, `name`, `status`, `ally_id`, `datadate`)
                    VALUES ({$playerId}, '{$playerName}', '{$escapedStatus}', {$allyId}, {$currentTime})
                    ON DUPLICATE KEY UPDATE
                        `name` = '{$playerName}',
                        `status` = '{$escapedStatus}',
                        `ally_id` = {$allyId},
                        `datadate` = {$currentTime}");
            }

            if ($allyId > 0) {
                $this->db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . " (`id`, `tag`, `datadate`)
                    VALUES ({$allyId}, '{$allyTag}', {$currentTime})
                    ON DUPLICATE KEY UPDATE `tag` = '{$allyTag}', `datadate` = {$currentTime}");
            }
        }

        $this->db->sql_query("UPDATE " . TABLE_USER . " SET `planet_imports` = `planet_imports` + 15 WHERE `id` = " . (int)$this->userData['id']);
        $this->registerCallback('system', [
            'data' => $rows,
            'galaxy' => $galaxy,
            'system' => $system,
        ]);
        $this->io->set(['type' => 'system', 'galaxy' => $galaxy, 'system' => $system]);
        update_statistic('planetimports', 15);
        $this->logAction('system', ['coords' => $galaxy . ':' . $system]);
    }

    public function getType(): string
    {
        return 'system';
    }

    public function getRequiredGrant(): string
    {
        return 'system';
    }
}
