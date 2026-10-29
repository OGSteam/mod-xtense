<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class FleetHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $coordinates = filter_var($data['planet']['coords']);
        $planetName = filter_var($data['planet']['name']);
        $planetType = filter_var($data['planet']['type']);
        $planetId = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
        $fleet = $data['fleet'] ?? [];
        if (!isset($coordinates, $planetName, $planetType)) {
            throw new UnexpectedValueException('Fleet: Missing Planet Details');
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

        $this->upsertValues(TABLE_GAME_PLAYER_FLEET, 'astro_object_id', $planetId, $this->database['fleet'], $fleet);
        $this->upsertValues(TABLE_USER_BUILDING, 'id', $planetId, $this->database['fleet_production'], $fleet);

        $this->setPageUpdatedResponse('fleet', $parsed['coords']);
        $this->registerCallback('fleet', [
            'coords' => explode(':', $parsed['coords']),
            'planet_type' => $planetType,
            'planet_name' => $planetName,
            'fleet' => $fleet,
        ]);
        $this->logAction('fleet', ['coords' => $parsed['coords'], 'planet_name' => $planetName]);
    }

    private function upsertValues(string $table, string $keyColumn, int|false $key, array $codes, array $data): void
    {
        $fields = [];
        $values = [];
        $updates = [];
        foreach ($codes as $code) {
            if (isset($data[$code])) {
                $level = (int)$data[$code];
                $fields[] = "`$code`";
                $values[] = $level;
                $updates[] = "`$code` = {$level}";
            }
        }
        if ($fields === []) {
            return;
        }

        $this->db->sql_query("INSERT INTO {$table} ({$keyColumn}, " . implode(', ', $fields) . ")
            VALUES ({$key}, " . implode(', ', $values) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updates));
    }

    public function getType(): string
    {
        return 'fleet';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
