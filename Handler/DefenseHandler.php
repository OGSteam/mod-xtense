<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class DefenseHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $coordinates = filter_var($data['planet']['coords']);
        $planetName = filter_var($data['planet']['name']);
        $planetId = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
        $planetType = filter_var($data['planet']['type']);
        $defense = $data['defense'] ?? [];
        if (!isset($coordinates, $planetName, $planetType)) {
            throw new UnexpectedValueException('Defense: Missing Planet Details');
        }

        $parsed = $this->parseCoordinates($coordinates);
        $planetType = $this->resolvePlanetType($planetType);
        $planetTypeString = $this->planetTypeToString($planetType);
        $escapedName = $this->db->sql_escape_string($planetName);
        $playerId = (int)($this->userData['player_id'] ?? -1);

        $this->db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
            (id, type, galaxy, system, row, name, player_id)
            VALUES ({$planetId}, '{$planetTypeString}', {$parsed['galaxy']}, {$parsed['system']}, {$parsed['row']}, '{$escapedName}', {$playerId})
            ON DUPLICATE KEY UPDATE name = '{$escapedName}'");

        $fields = [];
        $values = [];
        $updates = [];
        $callbackDefense = [];
        foreach ($this->database['defense'] as $code) {
            if (isset($defense[$code])) {
                $level = (int)$defense[$code];
                $fields[] = "`$code`";
                $values[] = $level;
                $updates[] = "`$code` = {$level}";
                $callbackDefense[$code] = $level;
            }
        }

        if ($fields !== []) {
            $this->db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER_DEFENSE . "
                (astro_object_id, " . implode(', ', $fields) . ")
                VALUES ({$planetId}, " . implode(', ', $values) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates));
        }

        $this->setPageUpdatedResponse('defense', $parsed['coords']);
        $this->registerCallback('defense', [
            'coords' => explode(':', $parsed['coords']),
            'planet_type' => $planetType,
            'planet_name' => $planetName,
            'defense' => $callbackDefense,
        ]);
        $this->logAction('defense', ['coords' => $parsed['coords'], 'planet_name' => $planetName]);
    }

    public function getType(): string
    {
        return 'defense';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
