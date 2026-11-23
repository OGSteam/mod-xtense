<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class BuildingsHandler extends AbstractHandler
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
        if (!isset($coordinates, $planetName, $planetType)) {
            throw new UnexpectedValueException('Buildings- Missing data');
        }

        $buildings = $data['buildings'];
        $coordinates = Check::coords($coordinates);
        [$galaxy, $system, $position] = explode(':', $coordinates);
        $planetType = $this->resolvePlanetType($planetType);

        $columns = ['id', 'galaxy', 'system', 'row', 'name'];
        $values = [$planetId, $galaxy, $system, $position, $planetName];
        foreach ($this->database['buildings'] as $code) {
            if (isset($buildings[$code])) {
                $columns[] = $code;
                $values[] = (int)$buildings[$code];
            }
        }

        $updatePairs = ['name = VALUES(name)', 'galaxy = VALUES(galaxy)', 'system = VALUES(system)', 'row = VALUES(row)'];
        foreach ($this->database['buildings'] as $code) {
            if (isset($buildings[$code])) {
                $updatePairs[] = "`$code` = VALUES(`$code`)";
            }
        }

        $query = "INSERT INTO " . TABLE_USER_BUILDING . " (`" . implode('`, `', $columns) . "`)
            VALUES (" . implode(', ', array_map(static function ($value) {
                return is_numeric($value) ? $value : "'$value'";
            }, $values)) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);
        $this->db->sql_query($query);

        $this->setPageUpdatedResponse('buildings', $coordinates);
        $this->registerCallback('buildings', [
            'coords' => explode(':', $coordinates),
            'planet_type' => $planetType,
            'planet_name' => $planetName,
            'buildings' => $buildings,
        ]);
        $this->logAction('buildings', ['coords' => $coordinates, 'planet_name' => $planetName]);
    }

    public function getType(): string
    {
        return 'buildings';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
