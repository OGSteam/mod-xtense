<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class ResourceSettingsHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $coordinates = filter_var($data['planet']['coords']);
        $planetName = filter_var($data['planet']['name']);
        $planetType = filter_var($data['planet']['type'], FILTER_VALIDATE_INT);
        $planetId = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
        $resourceSettings = isset($data['resourceSettings']) && is_array($data['resourceSettings'])
            ? $data['resourceSettings']
            : null;

        if (!isset($coordinates, $planetName, $planetType)
            || $planetId === false
            || $planetType === false
            || $resourceSettings === null
        ) {
            throw new UnexpectedValueException('ResourceSettings: Missing or invalid planet details or resourceSettings data');
        }

        $parsed = $this->parseCoordinates($coordinates);
        $coordinates = $parsed['coords'];
        $planetTypeConstant = $this->resolvePlanetType($planetType);
        $planetTypeString = $this->planetTypeToString($planetTypeConstant);

        $settingsMap = [
            'M_percentage' => 'M_percentage',
            'C_Percentage' => 'C_Percentage',
            'D_percentage' => 'D_percentage',
            'CES_percentage' => 'CES_percentage',
            'CEF_percentage' => 'CEF_percentage',
            'SAT_percentage' => 'Sat_percentage',
            'FOR_percentage' => 'FOR_percentage',
        ];

        $columns = ['id', 'galaxy', 'system', 'row', 'name', 'type'];
        $values = [
            $planetId,
            $parsed['galaxy'],
            $parsed['system'],
            $parsed['row'],
            $this->db->sql_escape_string($planetName),
            $this->db->sql_escape_string($planetTypeString),
        ];
        foreach ($settingsMap as $jsonKey => $databaseColumn) {
            $columns[] = $databaseColumn;
            $values[] = isset($resourceSettings[$jsonKey]) ? (int)$resourceSettings[$jsonKey] : 100;
        }

        $updatePairs = ['name', 'galaxy', 'system', 'row', 'type'];
        $updatePairs = array_map(static function ($column) {
            return "$column = VALUES($column)";
        }, $updatePairs);
        foreach ($settingsMap as $databaseColumn) {
            $updatePairs[] = "`$databaseColumn` = VALUES(`$databaseColumn`)";
        }

        $query = "INSERT INTO " . TABLE_USER_BUILDING . " (`" . implode('`, `', $columns) . "`)
            VALUES (" . implode(', ', array_map(static function ($value) {
                return is_numeric($value) ? $value : "'" . $value . "'";
            }, $values)) . ")
            ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);
        $this->db->sql_query($query);

        // The existing Xtense protocol reports resource settings as a buildings page update.
        $this->setPageUpdatedResponse('buildings', $coordinates);
        $this->registerCallback('resourceSettings', [
            'coords' => explode(':', $coordinates),
            'planet_type' => $planetTypeConstant,
            'planet_name' => $planetName,
            'resourceSettings' => $resourceSettings,
        ]);
        $this->logAction('resourceSettings', ['coords' => $coordinates, 'planet_name' => $planetName]);
    }

    public function getType(): string
    {
        return 'resourceSettings';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
