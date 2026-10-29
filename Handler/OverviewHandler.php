<?php

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

class OverviewHandler extends AbstractHandler
{
    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $playerDetails = filter_var_array($data['playerdetails'], [
            'player_name' => FILTER_DEFAULT,
            'player_id' => FILTER_DEFAULT,
            'playerclass_explorer' => FILTER_DEFAULT,
            'playerclass_miner' => FILTER_DEFAULT,
            'playerclass_warrior' => FILTER_VALIDATE_INT,
            'player_officer_commander' => FILTER_VALIDATE_INT,
            'player_officer_amiral' => FILTER_VALIDATE_INT,
            'player_officer_engineer' => FILTER_VALIDATE_INT,
            'player_officer_geologist' => FILTER_VALIDATE_INT,
            'player_officer_technocrate' => FILTER_VALIDATE_INT,
        ]);

        $universeDetails = filter_var_array($data['unidetails'], [
            'uni_version' => FILTER_DEFAULT,
            'uni_url' => FILTER_DEFAULT,
            'uni_lang' => FILTER_DEFAULT,
            'uni_name' => FILTER_DEFAULT,
            'uni_time' => FILTER_VALIDATE_INT,
            'uni_speed' => FILTER_VALIDATE_INT,
            'uni_speed_fleet_peaceful' => FILTER_VALIDATE_INT,
            'uni_speed_fleet_war' => FILTER_VALIDATE_INT,
            'uni_speed_fleet_holding' => FILTER_VALIDATE_INT,
            'uni_donut_g' => FILTER_VALIDATE_INT,
            'uni_donut_s' => FILTER_VALIDATE_INT,
        ]);

        $planetName = filter_var($data['planet']['name'], FILTER_DEFAULT);
        $planetId = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
        $resources = filter_var_array($data['ressources'], FILTER_VALIDATE_INT);
        $temperatureMin = filter_var($data['temperature_min'], FILTER_VALIDATE_INT);
        $temperatureMax = filter_var($data['temperature_max'], FILTER_VALIDATE_INT);
        $fields = filter_var($data['fields'], FILTER_VALIDATE_INT);
        $coordinates = $this->parseCoordinates($data['planet']['coords'])['coords'];
        $planetType = $this->resolvePlanetType($data['planet']['type']);
        $ogameTimestamp = $universeDetails['uni_time'];

        $playerClass = 'none';
        if ($playerDetails['playerclass_miner'] == 1) {
            $playerClass = 'COL';
        } elseif ($playerDetails['playerclass_warrior'] == 1) {
            $playerClass = 'GEN';
        } elseif ($playerDetails['playerclass_explorer'] == 1) {
            $playerClass = 'EXP';
        }

        $gamePlayerId = (int)$playerDetails['player_id'];
        $currentOgspyUserId = (int)$this->userData['id'];
        if ($gamePlayerId > 0) {
            $gamePlayerName = $this->db->sql_escape_string($playerDetails['player_name']);
            $officerCommander = (int)$playerDetails['player_officer_commander'];
            $officerAmiral = (int)$playerDetails['player_officer_amiral'];
            $officerEngineer = (int)$playerDetails['player_officer_engineer'];
            $officerGeologist = (int)$playerDetails['player_officer_geologist'];
            $officerTechnocrate = (int)$playerDetails['player_officer_technocrate'];

            $query = "
                INSERT INTO " . TABLE_GAME_PLAYER . " (
                    `id`, `name`, `class`,
                    `off_commandant`, `off_amiral`, `off_ingenieur`, `off_geologue`, `off_technocrate`,
                    `datadate`, `status`, `ally_id`
                ) VALUES (
                    {$gamePlayerId}, '{$gamePlayerName}', '{$playerClass}',
                    {$officerCommander}, {$officerAmiral}, {$officerEngineer}, {$officerGeologist}, {$officerTechnocrate},
                    {$ogameTimestamp}, '', -1
                )
                ON DUPLICATE KEY UPDATE
                    `name` = VALUES(`name`),
                    `class` = VALUES(`class`),
                    `off_commandant` = VALUES(`off_commandant`),
                    `off_amiral` = VALUES(`off_amiral`),
                    `off_ingenieur` = VALUES(`off_ingenieur`),
                    `off_geologue` = VALUES(`off_geologue`),
                    `off_technocrate` = VALUES(`off_technocrate`),
                    `datadate` = VALUES(`datadate`)";
            $this->db->sql_query($query);
        }

        $this->db->sql_query("UPDATE " . TABLE_USER . " SET `player_id` = {$gamePlayerId} WHERE `id` = {$currentOgspyUserId}");
        $this->db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_uni', '{$universeDetails['uni_speed']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $this->db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_peaceful', '{$universeDetails['uni_speed_fleet_peaceful']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $this->db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_war', '{$universeDetails['uni_speed_fleet_war']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $this->db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_holding', '{$universeDetails['uni_speed_fleet_holding']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
        generate_config_cache();

        if (isset($data['boosters'])) {
            $boosters = booster_encode(update_boosters($data['boosters'], $ogameTimestamp));
        } else {
            $boosters = booster_encodev(0, 0, 0, 0, 0, 0, 0, 0);
        }

        [$galaxy, $system, $position] = explode(':', $coordinates);
        $this->db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
            (`player_id`, `id`, `type`, `galaxy`, `system`, `row`, `name`, `fields`, `boosters`, `temperature_min`, `temperature_max`)
            VALUES ({$gamePlayerId}, {$planetId}, '{$planetType}', {$galaxy}, {$system}, {$position}, '{$planetName}', {$fields}, '{$boosters}', {$temperatureMin}, {$temperatureMax})
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                name = VALUES(name),
                player_id = VALUES(player_id),
                fields = VALUES(fields),
                boosters = VALUES(boosters),
                temperature_min = VALUES(temperature_min),
                temperature_max = VALUES(temperature_max),
                galaxy = VALUES(galaxy),
                system = VALUES(system),
                row = VALUES(row)");

        $this->setPageUpdatedResponse('overview', $coordinates);
        $this->registerCallback('overview', [
            'coords' => explode(':', $coordinates),
            'planet_type' => $planetType,
            'planet_name' => $planetName,
            'fields' => $fields,
            'temperature_min' => $temperatureMin,
            'temperature_max' => $temperatureMax,
            'ressources' => $resources,
        ]);
        $this->logAction('overview', ['coords' => $coordinates, 'planet_name' => $planetName]);
    }

    public function getType(): string
    {
        return 'overview';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }
}
