<?php
global $db, $database, $server_config, $databaseSpyId, $log;

/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

const IN_SPYOGAME = true;
const IN_XTENSE = true;


date_default_timezone_set(@date_default_timezone_get());

$currentFolder = getcwd();
if (str_contains(getcwd(), 'mod')) {
    chdir('../../');
}
$_SERVER['SCRIPT_FILENAME'] = str_replace(basename(__FILE__), 'index.php', preg_replace('#/mod/(.*)/#', '/', $_SERVER['SCRIPT_FILENAME']));
include_once("common.php");
list($root, $active) = $db->sql_fetch_row($db->sql_query("SELECT `root`, `active` FROM " . TABLE_MOD . " WHERE `action` = 'xtense'"));

// Récupérer l'origine et vérifier qu'elle existe
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Si c'est localhost, s'assurer qu'il a le bon format (http ou https)
if ($origin === 'localhost' || $origin === '') {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $origin = $scheme . 'localhost';

    // Si un port est spécifié dans la requête, l'ajouter
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443') {
        $origin .= ':' . $_SERVER['SERVER_PORT'];
    }
}

header('Access-Control-Max-Age: 86400');   // cache for 1 day
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Request-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST");
header('X-Content-Type-Options: nosniff');

require_once("mod/$root/includes/config.php");
require_once("mod/$root/includes/functions.php");
require_once("mod/$root/includes/CallbackHandler.php");
require_once("mod/$root/includes/Callback.php");
require_once("mod/$root/includes/Io.php");
require_once("mod/$root/includes/Check.php");
require_once("mod/$root/includes/auth.php");

$start_time = microtime(true);
$io = new Io();
$time = time() - 60 * 4;
if ($time > mktime(0, 0, 0) && $time < mktime(8, 0, 0)) {
    $timestamp = mktime(0, 0, 0);
}
if ($time > mktime(8, 0, 0) && $time < mktime(16, 0, 0)) {
    $timestamp = mktime(8, 0, 0);
}
if ($time > mktime(16, 0, 0) && $time < (mktime(0, 0, 0) + 60 * 60 * 24)) {
    $timestamp = mktime(16, 0, 0);
}

$json = file_get_contents('php://input');
$received_content = json_decode($json, true);
//print_r($received_content);

$args = array(
    'type' => FILTER_SANITIZE_ENCODED,
    'toolbar_version' => FILTER_SANITIZE_ENCODED,
    'toolbar_type' => FILTER_SANITIZE_ENCODED,
    'mod_min_version' => FILTER_SANITIZE_ENCODED,
    'univers' => FILTER_VALIDATE_URL,
    'password' => FILTER_DEFAULT,
    'data' => FILTER_REQUIRE_SCALAR
);

$received_game_data = filter_var_array($received_content, $args);
//print_r($received_game_data);

if (!isset($received_game_data['type'])) {
    throw new UnexpectedValueException("Xtense data not provided");
}

xtense_check_before_auth($received_game_data['toolbar_version'], $received_game_data['mod_min_version'], $active, $received_game_data['univers']);
$xtense_user_data = xtense_check_auth($received_game_data['password']);
$xtense_user_data = xtense_check_user_rights($xtense_user_data);

$call = new CallbackHandler();

// Xtense : Ajout de la version et du type de barre utilisée par l'utilisateur
$current_user_id = $xtense_user_data['id'];
$db->sql_query("UPDATE " . TABLE_USER . " SET `xtense_version` = '" . $received_game_data['toolbar_version'] . "', `xtense_type` = '" . $received_game_data['toolbar_type'] . "' WHERE `id` =  $current_user_id");
$toolbar_info = $received_game_data['toolbar_type'] . " V" . $received_game_data['toolbar_version'];

// Récupération des données de jeu
$data = json_decode($received_game_data['data'], true);

// Meilleur Endroit pour voir ce que l'on récupère de l'extension :-)
//print_r($data);


switch ($received_game_data['type']) {
    case 'overview':
        { //PAGE OVERVIEW
            if (!$xtense_user_data['grant']['empire']) {
                $io->set(array(
                    'type' => 'plugin grant',
                    'access' => 'empire'
                ));
                $io->status(0);
            } else {

                $player_details = filter_var_array($data['playerdetails'], [
                    'player_name' => FILTER_DEFAULT,
                    'player_id' => FILTER_DEFAULT,
                    'playerclass_explorer' => FILTER_DEFAULT,
                    'playerclass_miner' => FILTER_DEFAULT,
                    'playerclass_warrior' => FILTER_VALIDATE_INT,
                    'player_officer_commander' => FILTER_VALIDATE_INT,
                    'player_officer_amiral' => FILTER_VALIDATE_INT,
                    'player_officer_engineer' => FILTER_VALIDATE_INT,
                    'player_officer_geologist' => FILTER_VALIDATE_INT,
                    'player_officer_technocrate' => FILTER_VALIDATE_INT
                ]);

                $uni_details = filter_var_array(
                    $data['unidetails'],
                    [
                        'uni_version' => FILTER_DEFAULT,
                        'uni_url' => FILTER_DEFAULT,
                        'uni_lang' => FILTER_DEFAULT,
                        'uni_name' => FILTER_DEFAULT,
                        'uni_time' => FILTER_VALIDATE_INT,
                        'uni_speed' => FILTER_VALIDATE_INT, // speed_uni
                        'uni_speed_fleet_peaceful' => FILTER_VALIDATE_INT,
                        'uni_speed_fleet_war' => FILTER_VALIDATE_INT,
                        'uni_speed_fleet_holding' => FILTER_VALIDATE_INT,
                        'uni_donut_g' => FILTER_VALIDATE_INT,
                        'uni_donut_s' => FILTER_VALIDATE_INT
                    ]
                );

                $planet_name = filter_var($data['planet']['name'], FILTER_DEFAULT);
                $planet_id = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
                $ressources = filter_var_array($data['ressources'], FILTER_VALIDATE_INT);
                $temperature_min = filter_var($data['temperature_min'], FILTER_VALIDATE_INT);
                $temperature_max = filter_var($data['temperature_max'], FILTER_VALIDATE_INT);
                $fields = filter_var($data['fields'], FILTER_VALIDATE_INT);

                $coords = Check::coords($data['planet']['coords']);
                $planet_type = ((int)$data['planet']['type'] == 0 ? TYPE_PLANET : TYPE_MOON);
                $ogame_timestamp = $uni_details['uni_time'];

                $userclass = 'none';
                if ($player_details['playerclass_miner'] == 1) {
                    $userclass = 'COL';
                } elseif ($player_details['playerclass_warrior'] == 1) {
                    $userclass = 'GEN';
                } elseif ($player_details['playerclass_explorer'] == 1) {
                    $userclass = 'EXP';
                }

                // Met à jour les informations du joueur dans la table ogspy_game_player (TABLE_GAME_PLAYER)
                // et lie l'ID du joueur de jeu à l'utilisateur OGSpy dans la table ogspy_user (TABLE_USER)
                $gamePlayerId = (int)$player_details['player_id'];
                if ($gamePlayerId > 0) { // S'assurer que player_id est valide
                    $gamePlayerName = $db->sql_escape_string($player_details['player_name']);
                    $officerCommander = (int)$player_details['player_officer_commander'];
                    $officerAmiral = (int)$player_details['player_officer_amiral'];
                    $officerEngineer = (int)$player_details['player_officer_engineer'];
                    $officerGeologist = (int)$player_details['player_officer_geologist'];
                    $officerTechnocrate = (int)$player_details['player_officer_technocrate'];
                    $ogameTimestamp = (int)$uni_details['uni_time'];
                    $currentOgspyUserId = (int)$xtense_user_data['id']; // ID de l'utilisateur OGSpy

                    $queryGamePlayer = "
                        INSERT INTO " . TABLE_GAME_PLAYER . " (
                            `id`, `name`, `class`,
                            `off_commandant`, `off_amiral`, `off_ingenieur`, `off_geologue`, `off_technocrate`,
                            `datadate`,
                            `status`, `ally_id`
                        ) VALUES (
                            {$gamePlayerId},
                            '{$gamePlayerName}',
                            '{$userclass}',
                            {$officerCommander},
                            {$officerAmiral},
                            {$officerEngineer},
                            {$officerGeologist},
                            {$officerTechnocrate},
                            {$ogameTimestamp},
                            '',
                            -1
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
                    $db->sql_query($queryGamePlayer);
                }

                // Met à jour TABLE_USER pour stocker le player_id (ID du joueur dans le jeu)
                $db->sql_query("UPDATE " . TABLE_USER . " SET `player_id` = {$gamePlayerId} WHERE `id` = {$currentOgspyUserId}");

                //Uni Speed
                $db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_uni', '{$uni_details['uni_speed']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
                //Uni Speed Peaceful
                $db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_peaceful', '{$uni_details['uni_speed_fleet_peaceful']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
                //Uni Speed War
                $db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_war', '{$uni_details['uni_speed_fleet_war']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
                //Uni Speed holding
                $db->sql_query("INSERT INTO " . TABLE_CONFIG . " (name, value) VALUES ('speed_fleet_holding', '{$uni_details['uni_speed_fleet_holding']}') ON DUPLICATE KEY UPDATE value = VALUES(value)");
                //Update Config Cache
                generate_config_cache();

                //boosters
                if (isset($data['boosters'])) {
                    $boosters = update_boosters($data['boosters'], $ogame_timestamp); /*Merge des différents boosters*/
                    $boosters = booster_encode($boosters); /*Conversion de l'array boosters en string*/
                } else {
                    $boosters = booster_encodev(0, 0, 0, 0, 0, 0, 0, 0); /* si aucun booster détecté*/
                }
                //Empire
                list($g, $s, $r) = explode(':', $coords);
                $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                                (`player_id`, `id`, `type`, `galaxy`, `system`, `row`, `name`, `fields`, `boosters`, `temperature_min`, `temperature_max`)
                            VALUES
                                ({$gamePlayerId}, {$planet_id}, '{$planet_type}' , {$g}, {$s}, {$r},'{$planet_name}', {$fields}, '{$boosters}', {$temperature_min}, {$temperature_max})
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

                $io->set(array(
                    'type' => 'home updated',
                    'page' => 'overview',
                    'planet' => $coords
                ));
            }

            // Appel fonction de callback
            $call->add('overview', array(
                'coords' => explode(':', $coords),
                'planet_type' => $planet_type,
                'planet_name' => $planet_name,
                'fields' => $fields,
                'temperature_min' => $temperature_min,
                'temperature_max' => $temperature_max,
                'ressources' => $ressources
            ));

            add_log('overview', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
        }


        break;

    case
    'buildings': //PAGE BATIMENTS

        if (!$xtense_user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['planet']['coords']);
            $planet_name = filter_var($data['planet']['name']);
            $planet_type = filter_var($data['planet']['type']);
            $planet_id = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Buildings- Missing data");
            }
            $buildings = $data['buildings'];

            $coords = Check::coords($coords);
            list($g, $s, $r) = explode(':', $coords); // ADDED: Parse coordinates
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);

            // Construction d'une requête UPSERT pour les bâtiments de la planète
            $buildingColumns = [];
            $buildingValues = [];

            // Préparation des colonnes de base

            $buildingColumns[] = 'id'; // ADDED
            $buildingValues[] = $planet_id;        // ADDED
            $buildingColumns[] = 'galaxy'; // ADDED
            $buildingValues[] = $g;        // ADDED
            $buildingColumns[] = 'system'; // ADDED
            $buildingValues[] = $s;        // ADDED
            $buildingColumns[] = 'row';    // ADDED
            $buildingValues[] = $r;        // ADDED

            $buildingColumns[] = 'name'; // RENAMED from 'planet_name'
            $buildingValues[] = $planet_name;

            // Ajout des bâtiments
            foreach ($database['buildings'] as $code) {
                if (isset($buildings[$code])) {
                    $buildingColumns[] = $code;
                    $buildingValues[] = (int)$buildings[$code];
                }
            }

            // Préparation des champs pour la clause ON DUPLICATE KEY UPDATE
            $updatePairs = [];
            $updatePairs[] = "name = VALUES(name)"; // CHANGED and RENAMED
            $updatePairs[] = "galaxy = VALUES(galaxy)"; // ADDED
            $updatePairs[] = "system = VALUES(system)"; // ADDED
            $updatePairs[] = "row = VALUES(row)";       // ADDED

            foreach ($database['buildings'] as $code) {
                if (isset($buildings[$code])) {
                    $updatePairs[] = "`$code` = VALUES(`$code`)"; // CHANGED to use VALUES()
                }
            }

            // Construction de la requête complète
            $query = "INSERT INTO " . TABLE_USER_BUILDING . " (`" . implode('`, `', $buildingColumns) . "`)
                      VALUES (" . implode(', ', array_map(function ($val) {
                    return is_numeric($val) ? $val : "'$val'";
                }, $buildingValues)) . ")
                      ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);

            $db->sql_query($query);

            $io->set(array(
                'type' => 'home updated',
                'page' => 'buildings',
                'planet' => $coords
            ));
        }

        $call->add('buildings', array(
            'coords' => explode(':', $coords),
            'planet_type' => $planet_type,
            'planet_name' => $planet_name,
            'buildings' => $buildings
        ));

        add_log('buildings', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));

        break;

    case 'resourceSettings':
        if (!$xtense_user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords_str = filter_var($data['planet']['coords']);
            $planet_name = filter_var($data['planet']['name']);
            $planet_type_int = filter_var($data['planet']['type'], FILTER_VALIDATE_INT);
            $planet_id = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
            $resourceSettings_data = isset($data['resourceSettings']) && is_array($data['resourceSettings']) ? $data['resourceSettings'] : null;

            if (
                !isset($coords_str, $planet_name, $data['planetType']) ||
                $planet_id === false || $planet_type_int === false || $resourceSettings_data === null
            ) {
                throw new UnexpectedValueException("ResourceSettings: Missing or invalid planet details or resourceSettings data");
            }

            $coords = Check::coords($coords_str);
            list($g, $s, $r) = explode(':', $coords);

            // Constante pour le callback (ex: TYPE_PLANET, TYPE_MOON)
            $planet_type_const = ($planet_type_int == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
            // Chaîne pour la base de données ('planet', 'moon')
            $astro_object_type_str = ($planet_type_int == TYPE_PLANET ? 'planet' : 'moon');

            $columns = [];
            $values = [];

            // Colonnes de base pour TABLE_USER_BUILDING (ogspy_game_astro_object)
            $columns[] = 'id';
            $values[] = $planet_id;
            $columns[] = 'galaxy';
            $values[] = (int)$g;
            $columns[] = 'system';
            $values[] = (int)$s;
            $columns[] = 'row';
            $values[] = (int)$r;
            $columns[] = 'name';
            $values[] = $db->sql_escape_string($planet_name);
            $columns[] = 'type';
            $values[] = $db->sql_escape_string($astro_object_type_str);

            // Colonnes pour les pourcentages de ressources
            // Clé JSON => Colonne DB
            $resource_settings_map = [
                'M_percentage' => 'M_percentage',
                'C_Percentage' => 'C_Percentage',
                'D_percentage' => 'D_percentage',
                'CES_percentage' => 'CES_percentage',
                'CEF_percentage' => 'CEF_percentage',
                'SAT_percentage' => 'Sat_percentage', // Correction de casse pour la DB
                'FOR_percentage' => 'FOR_percentage'
            ];

            foreach ($resource_settings_map as $json_key => $db_col_name) {
                $columns[] = $db_col_name;
                // Si la clé n'est pas dans les données JSON, mettre 0 (comme pour 'buildings')
                $values[] = isset($resourceSettings_data[$json_key]) ? (int)$resourceSettings_data[$json_key] : 100;
            }

            // Préparation des champs pour la clause ON DUPLICATE KEY UPDATE
            $updatePairs = [];
            $updatePairs[] = "name = VALUES(name)";
            $updatePairs[] = "galaxy = VALUES(galaxy)";
            $updatePairs[] = "system = VALUES(system)";
            $updatePairs[] = "row = VALUES(row)";
            $updatePairs[] = "type = VALUES(type)";

            foreach ($resource_settings_map as $json_key => $db_col_name) {
                $updatePairs[] = "`$db_col_name` = VALUES(`$db_col_name`)";
            }

            // Construction de la requête complète
            $query = "INSERT INTO " . TABLE_USER_BUILDING . " (`" . implode('`, `', $columns) . "`)
                      VALUES (" . implode(', ', array_map(function ($val) {
                    // Les valeurs numériques sont directes, les chaînes (déjà échappées) sont entourées d'apostrophes
                    return is_numeric($val) ? $val : "'" . $val . "'";
                }, $values)) . ")
                      ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);

            $db->sql_query($query);

            $io->set(array(
                'type' => 'home updated',
                'page' => 'buildings', // Conformément au code existant pour resourceSettings et à la demande de reproduire 'buildings'
                'planet' => $coords
            ));

            // Appel fonction de callback
            $call->add('resourceSettings', array( // Nom de callback spécifique
                'coords' => explode(':', $coords),
                'planet_type' => $planet_type_const, // Utiliser la constante (ex: TYPE_PLANET)
                'planet_name' => $planet_name,
                'resourceSettings' => $resourceSettings_data // Renvoyer les données JSON originales
            ));

            add_log('resourceSettings', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
        }

        break;

    case 'defense': //PAGE DEFENSE
        if (!$xtense_user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['planet']['coords']);
            $planet_name = filter_var($data['planet']['name']);
            $planet_id = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
            $planet_type = filter_var($data['planet']['type']);

            $defense = $data['defense'];
            //Stop si donnée manquante
            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Defense: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            list($g, $s, $r) = explode(':', $coords); // Extraire les coordonnées
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
            $planet_type_str = ((int)$planet_type == TYPE_PLANET ? 'planet' : 'moon');

            $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
            (id, type, galaxy, system, row, name, player_id)
            VALUES
            ({$planet_id}, '{$planet_type_str}', {$g}, {$s}, {$r}, '{$planet_name}', {$xtense_user_data['player_id']})
            ON DUPLICATE KEY UPDATE
            name = '{$planet_name}'");

            // Récupérer l'ID de l'objet astro (nécessaire pour la table défense)
            $astro_id_result = $db->sql_query("SELECT id FROM " . TABLE_USER_BUILDING . "
                                          WHERE galaxy = {$g} AND system = {$s} AND row = {$r} AND type = '{$planet_type_str}'");
            $astro_object_id = $db->sql_fetch_row($astro_id_result)[0] ?? $planet_id;

            // Préparer les champs pour la requête d'insertion/mise à jour des défenses
            $defense_fields = [];
            $defense_values = [];
            $update_pairs = [];

            foreach ($database['defense'] as $code) {
                if (isset($defense[$code])) {
                    $defense_fields[] = "`$code`";
                    $defense_values[] = (int)$defense[$code];
                    $update_pairs[] = "`$code` = " . (int)$defense[$code];
                }
            }

            // Construction de la requête UPSERT pour les défenses
            if (!empty($defense_fields)) {
                $fields_str = implode(', ', $defense_fields);
                $values_str = implode(', ', $defense_values);

                $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER_DEFENSE . "
                            (astro_object_id, {$fields_str})
                            VALUES
                            ({$astro_object_id}, {$values_str})
                            ON DUPLICATE KEY UPDATE
                            " . implode(", ", $update_pairs));
            }

            $io->set(array(
                'type' => 'home updated',
                'page' => 'defense',
                'planet' => $coords
            ));

            // Préparer les données pour le callback
            $defenses = array();
            foreach ($database['defense'] as $code) {
                if (isset($defense[$code])) {
                    $defenses[$code] = (int)$defense[$code];
                }
            }

            $call->add('defense', [
                'coords' => explode(':', $coords),
                'planet_type' => $planet_type,
                'planet_name' => $planet_name,
                'defense' => $defenses
            ]);

            add_log('defense', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
        }
        break;

    case 'researchs': //PAGE RECHERCHE

        if (!$xtense_user_data['grant']['empire']) {
            $io->set(['type' => 'plugin grant', 'access' => 'empire']);
            $io->status(0);
        } else {
            $coords = filter_var($data['planet']['coords']);
            $planet_name = filter_var($data['planet']['name']);
            $planet_type = filter_var($data['planet']['type']);
            $researchs = $data['researchs'];

            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Researchs: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);

            // Vérifier que l'ID du joueur est disponible
            if (!isset($xtense_user_data['player_id']) || $xtense_user_data['player_id'] <= 0) {
                throw new UnexpectedValueException("Researchs: Player ID not available");
            }

            $player_id = (int)$xtense_user_data['player_id'];

            // Préparer les champs pour la requête d'insertion/mise à jour des technologies
            $fields = [];
            $values = [];
            $update_parts = [];

            foreach ($database['labo'] as $code) {
                if (isset($researchs[$code])) {
                    $fields[] = "`$code`";
                    $values[] = (int)$researchs[$code];
                    $update_parts[] = "`$code` = " . (int)$researchs[$code];
                }
            }

            // Construction de la requête UPSERT pour les technologies
            if (!empty($fields)) {
                $fields_str = implode(', ', $fields);
                $values_str = implode(', ', $values);

                $db->sql_query("INSERT INTO " . TABLE_USER_TECHNOLOGY . "
                    (`player_id`, {$fields_str})
                    VALUES
                    ({$player_id}, {$values_str})
                    ON DUPLICATE KEY UPDATE
                    " . implode(", ", $update_parts));
            }

            $io->set(array(
                'type' => 'home updated',
                'page' => 'labo',
                'planet' => $coords
            ));

            $call->add('research', array(
                'research' => $researchs
            ));

            add_log('research', array('toolbar' => $toolbar_info));
        }
        break;

    case 'fleet': //PAGE FLOTTE

        if (!$xtense_user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['planet']['coords']);
            $planet_name = filter_var($data['planet']['name']);
            $planet_type = filter_var($data['planet']['type']);
            $planet_id = filter_var($data['planet']['id'], FILTER_VALIDATE_INT);
            $fleet = $data['fleet'];

            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Fleet: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            list($g, $s, $r) = explode(':', $coords); // Extraire les coordonnées
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
            $planet_type_str = ((int)$planet_type == TYPE_PLANET ? 'planet' : 'moon');

            $query = "INSERT INTO " . TABLE_USER_BUILDING . " (id, type, galaxy, system, row, name, player_id)
                           VALUES ({$planet_id}, '{$planet_type_str}', {$g}, {$s}, {$r}, '{$planet_name}', {$xtense_user_data['player_id']})
                           ON DUPLICATE KEY
                           UPDATE name = '{$planet_name}'";

            $db->sql_query($query);

            // Récupérer l'ID de l'objet astro (nécessaire pour la table flotte)
            $astro_id_result = $db->sql_query("SELECT id FROM " . TABLE_USER_BUILDING . "
                                          WHERE galaxy = {$g} AND system = {$s} AND row = {$r} AND type = '{$planet_type_str}'");
            $astro_object_id = $db->sql_fetch_row($astro_id_result)[0] ?? $planet_id;

            // Préparer les champs pour la requête d'insertion/mise à jour de la flotte
            $fleet_fields = [];
            $fleet_values = [];
            $update_pairs = [];

            foreach ($database['fleet'] as $code) {
                if (isset($fleet[$code])) {
                    $fleet_fields[] = "`$code`";
                    $fleet_values[] = (int)$fleet[$code];
                    $update_pairs[] = "`$code` = " . (int)$fleet[$code];
                }
            }

            // Construction de la requête UPSERT pour la flotte
            if (!empty($fleet_fields)) {
                $fields_str = implode(', ', $fleet_fields);
                $values_str = implode(', ', $fleet_values);

                $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER_FLEET . " (astro_object_id, {$fields_str})
                            VALUES
                            ({$astro_object_id}, {$values_str})
                            ON DUPLICATE KEY UPDATE
                            " . implode(", ", $update_pairs));
            }

            // Mettre à jour les informations de la flotte de production

            foreach ($database['fleet_production'] as $code) {
                if (isset($fleet[$code])) {
                    $fleet_production_fields[] = "`$code`";
                    $fleet_production_values[] = (int)$fleet[$code];
                    $update_prod_pairs[] = "`$code` = " . (int)$fleet[$code];
                }
            }

            // Construction de la requête UPSERT pour la flotte
            if (!empty($fleet_production_fields)) {
                $fields_str = implode(', ', $fleet_production_fields);
                $values_str = implode(', ', $fleet_production_values);

                $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                            (id, {$fields_str})
                            VALUES
                            ({$astro_object_id}, {$values_str})
                            ON DUPLICATE KEY UPDATE
                            " . implode(", ", $update_prod_pairs));
            }


            $io->set(array(
                'type' => 'home updated',
                'page' => 'fleet',
                'planet' => $coords
            ));

            $call->add('fleet', array(
                'coords' => explode(':', $coords),
                'planet_type' => $planet_type,
                'planet_name' => $planet_name,
                'fleet' => $fleet
            ));

            add_log('fleet', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
        }
        break;

    case 'system': //PAGE SYSTEME SOLAIRE
        if (!$xtense_user_data['grant']['system']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'system'
            ));
            $io->status(0);
        } else {
            $galaxy = filter_var($data['galaxy'], FILTER_SANITIZE_NUMBER_INT);
            $system = filter_var($data['system'], FILTER_SANITIZE_NUMBER_INT);
            if (!isset($galaxy, $system)) {
                throw new UnexpectedValueException("Galaxy: Missing Planet position");
            }

            if ($galaxy > $server_config['num_of_galaxies'] || $system > $server_config['num_of_systems']) {
                $io->set(array(
                    'type' => 'plugin univers',
                    'access' => 'system'
                ));
                $io->status(0);
            }

            $delete = [];
            $update = [];

            $query = "SELECT `row` FROM " . TABLE_USER_BUILDING . " WHERE `galaxy` = {$galaxy}  AND `system` =  {$system}";
            $check = $db->sql_query($query);
            while ($value = $db->sql_fetch_assoc($check)) {
                $update[$value['row']] = true;
            }

            $rows = $data['rows'];
            // Recupération des données
            for ($i = 1; $i < 16; $i++) {
                if (isset($rows[$i])) {
                    $line = $rows[$i];

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
                    $system_data[$i] = $line;
                } else {
                    $delete[] = $i;
                    $system_data[$i] = array(
                        'planet_id' => '',
                        'planet_name' => '',
                        'player_name' => '',
                        'status' => '',
                        'ally_tag' => '',
                        'debris' => array('metal' => 0, 'cristal' => 0, 'deuterium' => 0),
                        'moon' => 0,
                        'activity' => ''
                    );
                }
            }

            foreach ($system_data as $row => $v) {
                $statusTemp = (Check::player_status_forbidden($v['status']) ? "" : $v['status']); //On supprime les status qui sont subjectifs

                //default player_id/ally_id à -1 (cf shemas SQL)
                $v['player_id'] = (isset($v['player_id']) ? (int)$v['player_id'] : -1);
                $v['planet_id'] = (isset($v['planet_id']) ? (int)$v['planet_id'] : -1);
                $v['moon'] = (isset($v['moon']) ? (int)$v['moon'] : -1);
                $v['moon_id'] = (isset($v['moon_id']) ? (int)$v['moon_id'] : -1);
                $v['ally_id'] = (isset($v['ally_id']) ? (int)$v['ally_id'] : -1);
                $v['ally_tag'] = $v['ally_tag'] ?? '';

                $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                    (`id`, `galaxy`, `system`, `row`, `name`, `player_id`, `last_update`, `last_update_user_id`)
                    VALUES
                    ({$v['planet_id']},{$galaxy}, {$system}, {$row}, '{$v['planet_name']}', {$v['player_id']},  " . time() . ", {$xtense_user_data['id']} )
                    ON DUPLICATE KEY UPDATE
                    `name` = '{$v['planet_name']}',
                    `player_id` = {$v['player_id']},
                    `last_update` = " . time() . ",
                    `last_update_user_id` = {$xtense_user_data['id']}");

                // Création des lignes pour les lunes

                if (isset($v['moon_id']) && $v['moon'] == 1) {
                    $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                        (`id`, `type`, `galaxy`, `system`, `row`, `name`, `player_id`, `last_update_moon`, `last_update_user_id`)
                        VALUES
                        ({$v['moon_id']}, 'moon',{$galaxy}, {$system}, {$row}, '{$v['planet_name']}  - Moon', {$v['player_id']}, " . time() . ", {$xtense_user_data['id']} )
                        ON DUPLICATE KEY UPDATE
                        `name` = '{$v['planet_name']} - Moon',
                        `player_id` = {$v['player_id']},
                        `last_update_moon` = " . time() . ",
                        `last_update_user_id` = {$xtense_user_data['id']}");
                }


                // UPSERT pour la table game_player pour mettre à jour le statut
                if ($v['player_id'] != -1) {

                    $currentTime = time();
                    $playerId = (int)$v['player_id'];
                    $playerName = $db->sql_escape_string($v['player_name']);
                    $status = $db->sql_escape_string($statusTemp);
                    $allyId = (int)$v['ally_id'];

                    $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER . "
                                (`id`, `name`, `status`, `ally_id`, `datadate`)
                                VALUES
                                ($playerId, '$playerName', '$status', $allyId, $currentTime)
                                ON DUPLICATE KEY UPDATE
                                `name` = '$playerName',
                                `status` = '$status',
                                `ally_id` = $allyId,
                                `datadate` = $currentTime");
                }
                //La table game ally ne peut se mettre à jour, champs ally non alimenté (toutes les infos sont dans page rank)
                // UPSERT pour la table game_player pour mettre à jour le statut
                if ($v['ally_id'] > 0) {

                    $currentTime = time();
                    $allytag = $v['ally_tag'];
                    $allyId = $v['ally_id'];

                    $db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . "
                                (`id`, `tag`, `datadate`)
                                VALUES
                                ($allyId, '$allytag', $currentTime)
                                ON DUPLICATE KEY UPDATE
                                `tag` = '$allytag',
                                `datadate` = $currentTime");
                }
            }

            if (!empty($delete)) {
                $toDelete = array();
                foreach ($delete as $n) {
                    $toDelete[] = $galaxy . ':' . $system . ':' . $n;
                }
            }

            $db->sql_query("UPDATE " . TABLE_USER . " SET `planet_imports` = `planet_imports` + 15 WHERE `id` = " . $xtense_user_data['id']);

            $call->add('system', array(
                'data' => $data['rows'],
                'galaxy' => $galaxy,
                'system' => $system
            ));

            $io->set(array(
                'type' => 'system',
                'galaxy' => $galaxy,
                'system' => $system
            ));

            update_statistic('planetimports', 15);
            add_log('system', array('coords' => $galaxy . ':' . $system, 'toolbar' => $toolbar_info));
        }
        break;

    case 'ranking': //PAGE STATS

        $type1 = filter_var($data['type1']);
        $type2 = filter_var($data['type2']);
        $type3 = filter_var($data['type3']) ?? 0;
        $offset = filter_var($data['offset'], FILTER_SANITIZE_NUMBER_INT);
        $date = filter_var($data['time']);

        if (!isset($type1, $type2, $offset, $data['n'], $date)) {
            throw new UnexpectedValueException("Rankings: Incomplete Ranking");
        }

        if (!$xtense_user_data['grant']['ranking']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'ranking'
            ));
            $io->status(0);
        } else {
            if ($type1 != ('player' || 'ally')) {
                throw new UnexpectedValueException("Ranking: Unexpected Ranking type for type1");
            }
        }
        //Vérification Offset
        if ((($offset - 1) % 100) != 0) {
            throw new UnexpectedValueException("Ranking: Offset not found");
        }

        $n = $data['n'];
        $total = 0;
        $count = count($n);

        if ($type1 == 'player') {
            $table = match ($type2) {
                'points' => TABLE_RANK_PLAYER_POINTS,
                'economy' => TABLE_RANK_PLAYER_ECO,
                'research' => TABLE_RANK_PLAYER_TECHNOLOGY,
                'fleet' => match ($type3) {
                    '5' => TABLE_RANK_PLAYER_MILITARY_BUILT,
                    '6' => TABLE_RANK_PLAYER_MILITARY_DESTRUCT,
                    '4' => TABLE_RANK_PLAYER_MILITARY_LOOSE,
                    '7' => TABLE_RANK_PLAYER_HONOR,
                    default => throw new OutOfRangeException("Ranking Player: Unexpected Ranking type for type3: " . $type3),
                },
                default => throw new UnexpectedValueException("Ranking Player: Unexpected Ranking type for type2: " . $type2),
            };
        } else {
            $table = match ($type2) {
                'points' => TABLE_RANK_ALLY_POINTS,
                'economy' => TABLE_RANK_ALLY_ECO,
                'research' => TABLE_RANK_ALLY_TECHNOLOGY,
                'fleet' => match ($type3) {
                    '5' => TABLE_RANK_ALLY_MILITARY_BUILT,
                    '6' => TABLE_RANK_ALLY_MILITARY_DESTRUCT,
                    '4' => TABLE_RANK_ALLY_MILITARY_LOOSE,
                    '7' => TABLE_RANK_ALLY_HONOR,
                    default => throw new OutOfRangeException("Ranking Ally: Unexpected Ranking type for type3: " . $type3),
                },
                default => throw new UnexpectedValueException("Ranking Ally: Unexpected Ranking type for type2: " . $type2),
            };
        }

        $query = array();

        if ($type1 == 'player') {
            foreach ($n as $data) {

                // Vérification des données essentielles
                $data['player_name'] = filter_var($data['player_name']);
                $data['ally_tag'] = filter_var($data['ally_tag']);

                if (!empty($data['points'])) {
                    $data['points'] = (int)$data['points'];
                }

                $data['ally_id'] = !empty($data['ally_id']) ? (int)$data['ally_id'] : -1;
                $data['player_id'] = !empty($data['player_id']) ? (int)$data['player_id'] : -1;

                //Compléter table GAME PLAYER pour les joueurs qui n'ont pas de compte

                if ($data['player_id'] > 0 && !empty($data['player_name'])) {
                    $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER . "
                   (id, name, ally_id, datadate)
                   VALUES
                   ({$data['player_id']}, '{$data['player_name']}', {$data['ally_id']}, " . time() . ")
                   ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   ally_id = VALUES(ally_id),
                   datadate = VALUES(datadate)");
                }
                //Compléter table GAME ALLY pour les alliances qui n'ont pas de compte
                // Insert or update alliance data in the GAME ALLY table
                if ($data['ally_id'] > 0 && !empty($data['ally_tag'])) {
                    $db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . "
                       (id, tag, datadate)
                       VALUES
                       ({$data['ally_id']}, '{$data['ally_tag']}', " . time() . ")
                       ON DUPLICATE KEY UPDATE
                       tag = VALUES(tag),
                       datadate = VALUES(datadate)");
                }


                if ($table == TABLE_RANK_PLAYER_MILITARY) {
                    $query[] = "({$timestamp}, {$data['rank']}, '{$data['player_name']}' , {$data['player_id']}, '{$data['ally_tag']}', {$data['ally_id']}, {$data['points']}, {$xtense_user_data['id']}, {$data['nb_spacecraft']} )";
                } else {
                    $query[] = "({$timestamp}, {$data['rank']}, '{$data['player_name']}' , {$data['player_id']}, '{$data['ally_tag']}', {$data['ally_id']}, {$data['points']}, {$xtense_user_data['id']} )";
                }
                $total++;
                $datas[] = $data;

                if (!empty($query)) {
                    if ($table == TABLE_RANK_PLAYER_MILITARY) {
                        $db->sql_query("REPLACE INTO " . $table . " (`datadate`, `rank`, `player`, `player_id`, `ally`, `ally_id`, `points`, `sender_id`, `nb_spacecraft`) VALUES " . implode(',', $query));
                    } else {
                        $db->sql_query("REPLACE INTO " . $table . " (`datadate`, `rank`, `player`, `player_id`, `ally`, `ally_id`, `points`, `sender_id`) VALUES " . implode(',', $query));
                    }
                }
            }
        } else {
            $fields = 'datadate, rank, ally, ally_id, points, sender_id, number_member, points_per_member';
            foreach ($n as $data) {
                $data['ally_tag'] = filter_var($data['ally_tag']);
                $data['ally_name'] = filter_var($data['ally']);
                $data['ally_id'] = filter_var($data['ally_id'] ?? throw new UnexpectedValueException("Ranking Ally: Alliance Id not found"), FILTER_SANITIZE_NUMBER_INT);
                $data['points'] = filter_var($data['points'] ?? throw new UnexpectedValueException("Ranking Ally: No points sent"), FILTER_SANITIZE_NUMBER_INT);
                $data['mean'] = filter_var($data['mean'] ?? throw new UnexpectedValueException("Ranking Ally: No mean found"), FILTER_SANITIZE_NUMBER_INT);
                $data['members'] = filter_var($data['members'] ?? throw new UnexpectedValueException("Ranking Ally: Nb players not found"), FILTER_SANITIZE_NUMBER_INT);

                // Insert or update alliance data in the GAME ALLY table
                if ($data['ally_id'] > 0 && !empty($data['ally_tag'] && !empty($data['ally_name']))) {
                    $db->sql_query("INSERT INTO " . TABLE_GAME_ALLY . "
                       (id, name, tag, datadate)
                       VALUES
                       ({$data['ally_id']}, '{$data['ally_name']}', '{$data['ally_tag']}', " . time() . ")
                       ON DUPLICATE KEY UPDATE
                          name = VALUES(name),
                            tag = VALUES(tag),
                       datadate = VALUES(datadate)");
                }


                $query[] = "({$timestamp}, {$data['rank']} , '{$data['ally_tag']}' , {$data['ally_id']} , {$data['points']} , {$xtense_user_data['id']} , {$data['members']} ,{$data['mean']} )";
                $datas[] = $data;
                $total++;
            }

            if (!empty($query)) {
                $db->sql_query("REPLACE INTO " . $table . " (" . $fields . ") VALUES " . implode(',', $query));
            }

            $db->sql_query("UPDATE " . TABLE_USER . " SET rank_imports = rank_imports + " . $total . " WHERE id = " . $xtense_user_data['id']);
        }

        $type2 = (($type2 == 'fleet') ? $type2 . $type3 : $type2);

        $call->add('ranking_' . $type1 . '_' . $type2, array(
            'data' => $datas,
            'offset' => $offset,
            'time' => $timestamp
        ));

        $io->set(array(
            'type' => 'ranking',
            'type1' => $type1,
            'type2' => $type2,
            'offset' => $offset
        ));

        update_statistic('rankimports', 100);
        add_log('ranking', array('type1' => $type1, 'type2' => $type2, 'offset' => $offset, 'time' => $timestamp, 'toolbar' => $toolbar_info));

        break;

    case 'rc'://PAGE RC
    case 'rc_shared':
        $json = filter_var($data['json']);
        $ogapilnk = filter_var($data['ogapilnk']);

        if (!isset($json)) {
            throw new UnexpectedValueException("Combat Report: JSON Report not sent");
        }
        $ogapilnk = $ogapilnk ?? '';

        if (!$xtense_user_data['grant']['messages']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'messages'
            ));
            $io->status(0);
        } else {
            $call->add('rc', array(
                'json' => $json,
                'api' => $ogapilnk
            ));

            $jsonObj = json_decode($json);

            $exist = $db->sql_fetch_row($db->sql_query("SELECT `id_rc` FROM " . TABLE_PARSEDRC . " WHERE `dateRC` = '" . $jsonObj->event_timestamp . "'"));

            if (!isset($exist[0])) {
                $winner = match ($jsonObj->result) {
                    'draw' => 'N',
                    'attacker' => 'A',
                    'defender' => 'D',
                    default => throw new UnexpectedValueException("Combat Report: Result not found"),
                };
                $nbRounds = count($jsonObj->combatRounds) - 1;
                $moon = (int)($jsonObj->moon->genesis);
                $coordinates = "{$jsonObj->coordinates->galaxy}:{$jsonObj->coordinates->system}:{$jsonObj->coordinates->position}";

                $db->sql_query(
                    "INSERT INTO " . TABLE_PARSEDRC . " (
                        `dateRC`, `coordinates`, `nb_rounds`, `victoire`, `pertes_A`, `pertes_D`, `gain_M`, `gain_C`, `gain_D`, `debris_M`, `debris_C`, `lune`
                    ) VALUES (
                     '{$jsonObj->event_timestamp}',
                     '{$coordinates}',
                      '{$nbRounds}',
                      '{$winner}',
                      '{$jsonObj->statistic->lostUnitsAttacker}',
                      '{$jsonObj->statistic->lostUnitsDefender}',
                      '{$jsonObj->loot->metal}',
                      '{$jsonObj->loot->crystal}',
                      '{$jsonObj->loot->deuterium}',
                      '{$jsonObj->debris->metal}',
                      '{$jsonObj->debris->crystal}',
                      '{$moon}'
                    )"
                );
                $id_rc = $db->sql_insertid();

                $attackers = array();
                foreach ($jsonObj->attacker as $attacker) {
                    $attackers[$attacker->fleetID] = array(
                        'coords' => $attacker->ownerCoordinates,
                        'planetType' => $attacker->ownerPlanetType,
                        'name' => $attacker->ownerName,
                        'armor' => $attacker->armorPercentage,
                        'weapon' => $attacker->weaponPercentage,
                        'shield' => $attacker->shieldPercentage
                    );
                }
                $defenders = array();
                foreach ($jsonObj->defender as $defender) {
                    $defenders[] = array(
                        'coords' => $attacker->ownerCoordinates,
                        'planetType' => $defender->ownerPlanetType,
                        'name' => $defender->ownerName,
                        'armor' => $defender->armorPercentage,
                        'weapon' => $defender->weaponPercentage,
                        'shield' => $defender->shieldPercentage
                    );
                }

                for ($i = 0; $i <= $nbRounds; $i++) {
                    $round = $jsonObj->combatRounds[$i];

                    if (!isset($round->statistic))
                        $a_nb = $a_shoot = $a_bcl = $d_nb = $d_shoot = $d_bcl = 0;
                    else {
                        $a_nb = $round->statistic->hitsAttacker;
                        $d_nb = $round->statistic->hitsDefender;
                        $a_shoot = $round->statistic->fullStrengthAttacker;
                        $d_shoot = $round->statistic->fullStrengthDefender;
                        $a_bcl = $round->statistic->absorbedDamageAttacker;
                        $d_bcl = $round->statistic->absorbedDamageDefender;
                    }

                    $db->sql_query(
                        "INSERT INTO " . TABLE_PARSEDRCROUND . " (
                            `id_rc`, `numround`, `attaque_tir`, `attaque_puissance`, `defense_bouclier`, `attaque_bouclier`, `defense_tir`, `defense_puissance`
                        ) VALUE (
                            '{$id_rc}', '{$i}', '" . $a_nb . "', '" . $a_shoot . "', '" . $d_bcl . "', '" . $a_bcl . "', '" . $d_nb . "', '" . $d_shoot . "'
                        )"
                    );
                    $id_rcround = $db->sql_insertid();

                    /*'SmallCargo': 202,
         'LargeCargo': 203,
         'LightFighter': 204,
         'HeavyFighter': 205,
         'Cruiser': 206,
         'Battleship': 207,
         'ColonyShip': 208,
         'Recycler': 209,
         'EspionageProbe': 210,
         'Bomber': 211,
         'SolarSatellite': 212,
         'Destroyer': 213,
         'Deathstar': 214,
         'Battlecruiser': 215,

RocketLauncher': 401,
           'LightLaser': 402,
           'HeavyLaser': 403,
           'GaussCannon': 404,
           'IonCannon': 405,
           'PlasmaTurret': 406,
           'SmallShieldDome': 407,
           'LargeShieldDome': 408,
           'AntiBallisticMissiles': 502,
           'InterplanetaryMissiles': 503,*/
                    $shipList = array(
                        '202' => 'PT', '203' => 'GT', '204' => 'CLE', '205' => 'CLO', '206' => 'CR', '207' => 'VB', '208' => 'VC', '209' => 'REC',
                        '210' => 'SE', '211' => 'BMD', '212' => 'SAT', '213' => 'DST', '214' => 'EDLM', '215' => 'TRA', '217' => 'FOR', '218' => 'FAU', '219' => 'ECL',
                        '401' => 'LM', '402' => 'LLE', '403' => 'LLO', '404' => 'CG', '405' => 'AI', '406' => 'LP', '407' => 'PB', '408' => 'GB', '502' => 'MIC', '503' => 'MIP'
                    );

                    foreach ($round->attackerShips as $fleetId => $attackerRound) {
                        $attackerFleet = array_fill_keys($database['fleet'], 0);
                        foreach ((array)$attackerRound as $ship => $nbShip)
                            $attackerFleet[$shipList[$ship]] = $nbShip;
                        // On efface les sat qui attaquent
                        unset($attackerFleet['SAT']);

                        $attacker = $attackers[$fleetId];
                        $fleet = '';
                        foreach (array('PT', 'GT', 'CLE', 'CLO', 'CR', 'VB', 'VC', 'REC', 'SE', 'BMD', 'DST', 'EDLM', 'TRA', 'FAU', 'ECL') as $ship)
                            $fleet .= ", " . $attackerFleet[$ship];

                        $db->sql_query("INSERT INTO " . TABLE_ROUND_ATTACK . " (`id_rcround`, `player`, `coordinates`, `Armes`, `Bouclier`, `Protection`,
                        `PT`, `GT`, `CLE`, `CLO`, `CR`, `VB`, `VC`, `REC`, `SE`, `BMD`,  `DST`, `EDLM`, `TRA`, `FAU`, `ECL`) VALUE ('{$id_rcround}', '"
                            . $attacker['name'] . "', '"
                            . $attacker['coords'] . "', '"
                            . $attacker['weapon'] . "', '"
                            . $attacker['shield'] . "', '"
                            . $attacker['armor'] . "'"
                            . $fleet . ")");
                    }

                    foreach ($round->defenderShips as $fleetId => $defenderRound) {
                        $defenderFleet = array_fill_keys(array_merge($database['fleet'], $database['defense']), 0);
                        foreach ((array)$defenderRound as $ship => $nbShip)
                            $defenderFleet[$shipList[$ship]] = $nbShip;

                        $defender = $defenders[0];

                        $columns = array(
                            'PT', 'GT', 'CLE', 'CLO', 'CR', 'VB', 'VC', 'REC', 'SE', 'BMD', 'SAT', 'DST', 'EDLM', 'TRA', 'FOR', 'FAU', 'ECL',
                            'LM', 'LLE', 'LLO', 'CG', 'AI', 'LP', 'PB', 'GB'
                        );

                        $query = "INSERT INTO " . TABLE_ROUND_DEFENSE . " (`id_rcround`, `player`, `coordinates`, `Armes`, `Bouclier`, `Protection` ";
                        foreach ($columns as $column) {
                            $query .= ", `{$column}`";
                        }
                        $query .= ") VALUE ('{$id_rcround}', '"
                            . $defender['name'] . "', '"
                            . $defender['coords'] . "', '"
                            . $defender['weapon'] . "', '"
                            . $defender['shield'] . "', '"
                            . $defender['armor'] . "'";
                        foreach ($columns as $ship) {
                            $query .= ", " . $defenderFleet[$ship];
                        }
                        $query .= ")";

                        $db->sql_query($query);
                    }
                }
            }

            $io->set(array(
                'type' => $received_game_data['type'],
            ));

            add_log($received_game_data['type'], array('toolbar' => $toolbar_info));
        }
        break;

    case 'ally_list': //PAGE ALLIANCE
        if (!$xtense_user_data['grant']['ranking']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'ranking'
            ));
            $io->status(0);
        } else {
            if (!isset($data['tag'], $data['allyList'])) {
                throw new UnexpectedValueException("Allylist: Missing Value");
            }

            if (!isset($data['tag'])) {
                break;
            } //Pas d'alliance
            $tag = filter_var($data['tag']);
            $list = array();

            foreach ($data['allyList'] as $data) {

                if (!isset($data['player'], $data['points'], $data['rank'], $data['coords'])) {
                    throw new UnexpectedValueException("Allylist: Missing Ally Detailed list");
                }

                $list[] = array(
                    'pseudo' => filter_var($data['player']),
                    'points' => filter_var($data['points'], FILTER_SANITIZE_NUMBER_INT),
                    'coords' => explode(':', $data['coords']),
                    'rang' => filter_var($data['rank'], FILTER_SANITIZE_NUMBER_INT)
                );
            }

            $call->add('ally_list', array(
                'list' => $list,
                'tag' => $tag
            ));

            $io->set(array(
                'type' => 'ally_list',
                'tag' => $tag
            ));

            add_log('ally_list', array(
                'tag' => $tag,
                'toolbar' => $toolbar_info
            ));
        }
        break;

    case 'messages': //PAGE MESSAGES

        if (!$xtense_user_data['grant']['messages']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'messages'
            ));
            $io->status(0);
        } else {
            $line = $data;
            switch ($line['type']) {
                case 'msg': //MESSAGE PERSO
                    if (!isset($line['coords'], $line['from'], $line['subject'], $line['message'])) {
                        throw new UnexpectedValueException("Personal Message: Incomplete Metadata ");
                    }
                    $line['coords'] = Check::coords($line['coords']);
                    $line['from'] = filter_var($line['from']);
                    $line['message'] = filter_var($line['message']);
                    $line['subject'] = filter_var($line['subject']);

                    $msg = array(
                        'coords' => explode(':', $line['coords']),
                        'from' => $line['from'],
                        'subject' => $line['subject'],
                        'message' => $line['message'],
                        'time' => $line['date']
                    );
                    $call->add('msg', $msg);
                    break;

                case 'ally_msg': //MESSAGE ALLIANCE

                    if (!isset($line['from'], $line['tag'], $line['message'])) {
                        throw new UnexpectedValueException("Alliance Message: Incomplete Metadata ");
                    }

                    $line['from'] = filter_var($line['from']);
                    $line['tag'] = filter_var($line['tag']);
                    $line['message'] = filter_var($line['message']);

                    $ally_msg = array(
                        'from' => $line['from'],
                        'tag' => $line['tag'],
                        'message' => $line['message'],
                        'time' => $line['date']
                    );
                    $call->add('ally_msg', $ally_msg);
                    break;

                case 'spy': //RAPPORT ESPIONNAGE
                case 'spy_shared':
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
                        throw new UnexpectedValueException("Spy: Incomplete Metadata ");
                    }

                    $coords = Check::coords($line['planet']['coordinates']); // Modifié: Source des coordonnées
                    // Construction de $content à partir des nouvelles sections du JSON
                    $content = array();
                    if (isset($line['resources']) && is_array($line['resources'])) {
                        $content = $content + $line['resources'];
                    }
                    if (isset($line['buildings']) && is_array($line['buildings'])) {
                        $content =  $content + $line['buildings'];
                    }
                    if (isset($line['lfBuildings']) && is_array($line['lfBuildings'])) {
                        $content =  $content + $line['lfBuildings'];
                    }
                    if (isset($line['research']) && is_array($line['research'])) {
                        $content =  $content + $line['research'];
                    }
                    if (isset($line['lfResearch']) && is_array($line['lfResearch'])) {
                        $content =  $content + $line['lfResearch'];
                    }
                    if (isset($line['fleet']) && is_array($line['fleet'])) {
                        $content =  $content + $line['fleet'];
                    }
                    if (isset($line['defense']) && is_array($line['defense'])) {
                        $content =  $content + $line['defense'];
                    }

                    $playerName = filter_var($line['player']['name']);
                    $planetName = filter_var($line['planet']['name']);
                    $moon = ($line['planet']['type'] === "3");
                    $proba = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT); // Modifié: Source de proba
                    $activite = filter_var($line['activity'], FILTER_SANITIZE_NUMBER_INT); // Modifié: Source de activity
                    $date = filter_var($line['date'], FILTER_SANITIZE_NUMBER_INT); // Source de date (supposée existante)

                    $proba = $proba > 100 ? 100 : $proba;
                    $activite = $activite > 59 ? 59 : $activite;
                    $spy = array(
                        'proba' => $proba,
                        'activite' => $activite,
                        'coords' => $coords,
                        'content' => $content,
                        'time' => $date,
                        'player_name' => $playerName,
                        'planet_name' => $planetName,
                        'planet_id' => $line['planet']['id']
                    );
                    $call->add($line['type'], $spy);

                    $spyDB = [];
                    foreach ($databaseSpyId as $arr) {
                        $spyDB = $spyDB + $arr; // Fusionner les tableaux pour obtenir les codes d'espionnage
                    }

                    $log->debug("spyDB: " . print_r($spyDB, true));

                    // Initialisation des tableaux pour les champs et les valeurs
                    $query_fields = [];
                    $query_values = [];

                    // Champs de base et valeurs correspondantes
                    // Note: les noms de champs ici ne sont pas entourés de backticks car implode les ajoutera si nécessaire,
                    // ou ils sont déjà des noms valides. Si des backticks sont requis pour tous, ajustez ici.
                    $query_fields = [
                        'astro_object_id', 'planet_name', 'metal', 'cristal', 'deuterium',
                        'sender_id', 'proba', 'activite', 'dateRE'
                    ];
                    $query_values = [
                        $spy['planet_id'],
                        '"' . $spy['planet_name'] . '"',
                        (isset($spy['content']['metal']) ? (int)$spy['content']['metal'] : 0),
                        (isset($spy['content']['crystal']) ? (int)$spy['content']['crystal'] : 0),
                        (isset($spy['content']['deuterium']) ? (int)$spy['content']['deuterium'] : 0),
                        $xtense_user_data['id'],
                        $spy['proba'],
                        $spy['activite'],
                        $spy['time']
                    ];

                    // On traite le contenu additionnel du rapport d'espionnage
                        // Exclure les champs de base déjà traités (metal, crystal, deuterium)
                    foreach ($spyDB as $code => $name) {
                        $log->debug('Traitement du code d\'espionnage: ' . $code . ' avec la valeur: ' . $name);
                        if ($name === 'metal' || $name === 'crystal' || $name === 'deuterium') {
                            continue;
                        }
                        if (isset($spy['content'][$code])) { // Vérifier si le code est mappé $spy['content']
                            $field = $name;
                            $query_fields[] = '`' . $field . '`'; // Ajoute le nom du champ protégé par des backticks
                            $query_values[] = (int)$spy['content'][$code];     // Ajoute la valeur convertie en entier
                        } else {
                            $log->warning("Code d\'espionnage vide: $name");
                        }
                    }

                    // Construction des chaînes finales pour la requête SQL
                    $fields_for_db = implode(', ', $query_fields);
                    $values_for_db = implode(', ', $query_values);

                    $spy_time = $spy['time'];
                    $test = $db->sql_numrows($db->sql_query("SELECT `id` FROM " . TABLE_PARSEDSPY . " WHERE `astro_object_id` = '{$spy['planet_id']}' AND `dateRE` = '$spy_time'"));
                    if (!$test) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDSPY . " ( " . $fields_for_db . ") VALUES (" . $values_for_db . ")");


                        // On cherche l'ID du joueur espionné
                        $spied_player_id = -1;
                        $spied_player_id_result = $db->sql_query("SELECT `id` FROM " . TABLE_GAME_PLAYER . " WHERE `name` = '" . $db->sql_escape_string($spy['player_name']) . "'");
                        if ($db->sql_numrows($spied_player_id_result) > 0) {
                            $spied_player_id = (int)$db->sql_fetch_row($spied_player_id_result)[0];
                        }

                        list($g, $s, $r) = explode(':', $spy['coords']);
                        $planet_type = $moon ? 'moon' : 'planet';

                        // Préparation de la mise à jour de TABLE_USER_BUILDING (astro_objects)
                        $update_pairs = [];
                        $insert_fields = ['id', 'galaxy', 'system', 'row', 'type', 'name', 'last_update', 'last_update_user_id'];
                        $insert_values = [
                            (int)$spy['planet_id'],
                            (int)$g,
                            (int)$s,
                            (int)$r,
                            "'" . $planet_type . "'",
                            "'" . $db->sql_escape_string($spy['planet_name']) . "'",
                            (int)$spy['time'],
                            (int)$xtense_user_data['id']
                        ];

                        $update_pairs[] = 'name = VALUES(name)';
                        $update_pairs[] = 'last_update = VALUES(last_update)';
                        $update_pairs[] = 'last_update_user_id = VALUES(last_update_user_id)';
                        $update_pairs[] = 'type = VALUES(type)';
                        $update_pairs[] = 'galaxy = VALUES(galaxy)';
                        $update_pairs[] = 'system = VALUES(system)';
                        $update_pairs[] = 'row = VALUES(row)';

                        if ($spied_player_id !== -1) {
                            $insert_fields[] = 'player_id';
                            $insert_values[] = (int)$spied_player_id;
                            $update_pairs[] = 'player_id = VALUES(player_id)';
                        }

                        $log->debug("Building to Update in Astro Table: ",[$line['buildings']] );
                        $log->debug("Building Database to Update in Astro Table: ",[$databaseSpyId['buildings']] );
                        // Bâtiments
                        if (isset($line['buildings']) && is_array($line['buildings'])) {
                            foreach ($databaseSpyId['buildings'] as $code => $name) {
                                if (isset($line['buildings'][$code])) {
                                    $insert_fields[] = $name;
                                    $insert_values[] = (int)$line['buildings'][$code];
                                    $update_pairs[] = "`" . $name . "` = VALUES(`" . $name . "`)";
                                }
                            }
                        }
                        $log->debug("Building to Update: ",[$insert_fields, $insert_values] );


                        $query = "INSERT INTO " . TABLE_USER_BUILDING . " (`" . implode('`, `', $insert_fields) . "`)
                                  VALUES (" . implode(', ', $insert_values) . ")
                                  ON DUPLICATE KEY UPDATE " . implode(', ', $update_pairs);
                        $db->sql_query($query);

                        // Défenses
                        if (isset($line['defense']) && is_array($line['defense'])) {
                            $defense_fields = [];
                            $defense_values = [];
                            $defense_update_pairs = [];

                            foreach ($databaseSpyId['defense'] as $code => $name) {
                                if (isset($line['defense'][$code])) {
                                    $level = (int)$line['defense'][$code];
                                    $defense_fields[] = "`$name`";
                                    $defense_values[] = $level;
                                    $defense_update_pairs[] = "`$name` = $level";
                                }
                            }

                            if (!empty($defense_fields)) {
                                $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER_DEFENSE . " (astro_object_id, " . implode(', ', $defense_fields) . ")
                                            VALUES (" . (int)$spy['planet_id'] . ", " . implode(', ', $defense_values) . ")
                                            ON DUPLICATE KEY UPDATE " . implode(", ", $defense_update_pairs));
                            }
                        }

                        // Flotte
                        if (isset($line['fleet']) && is_array($line['fleet'])) {
                            $fleet_fields = [];
                            $fleet_values = [];
                            $fleet_update_pairs = [];

                            foreach ($databaseSpyId['fleet'] as $code => $name) {
                                if (isset($line['fleet'][$code])) {
                                    $level = (int)$line['fleet'][$code];
                                    $fleet_fields[] = "`$name`";
                                    $fleet_values[] = $level;
                                    $fleet_update_pairs[] = "`$name` = $level";
                                }
                            }

                            if (!empty($fleet_fields)) {
                                $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER_FLEET . " (astro_object_id, " . implode(', ', $fleet_fields) . ")
                                            VALUES (" . (int)$spy['planet_id'] . ", " . implode(', ', $fleet_values) . ")
                                            ON DUPLICATE KEY UPDATE " . implode(", ", $fleet_update_pairs));
                            }
                        }

                        // Recherches
                        if ($spied_player_id !== -1 && isset($line['research']) && is_array($line['research'])) {
                            $research_fields = [];
                            $research_values = [];
                            $research_update_parts = [];

                            foreach ($databaseSpyId['labo'] as $code => $name) {
                                if (isset($line['research'][$code])) {
                                    $level = (int)$line['research'][$code];
                                    $research_fields[] = "`$name`";
                                    $research_values[] = $level;
                                    $research_update_parts[] = "`$name` = $level";
                                }
                            }

                            if (!empty($research_fields)) {
                                $db->sql_query("INSERT INTO " . TABLE_USER_TECHNOLOGY . " (`player_id`, " . implode(', ', $research_fields) . ")
                                            VALUES (" . (int)$spied_player_id . ", " . implode(', ', $research_values) . ")
                                            ON DUPLICATE KEY UPDATE " . implode(", ", $research_update_parts));
                            }
                        }

                        // On met à jour le nombre d'importations de rapports espionnage

                        $db->sql_query('UPDATE ' . TABLE_USER . ' SET `spy_imports` = spy_imports + 1 WHERE `id` = ' . $xtense_user_data['id']);
                        update_statistic('spyimports', '1');
                        // ATTENTION: $spy[\'planet_name\'] étant vide, le log sera incomplet.
                        add_log('messages', array('added_spy' => $spy['planet_name'], 'added_spy_coords' => $spy['coords'], 'toolbar' => $toolbar_info));
                    } else {
                        $log->debug("Spy report already exists for planet_id: " . $spy['planet_id'] . " at time: " . $spy['time']);
                    }
                    break;

                case 'ennemy_spy': //RAPPORT ESPIONNAGE ENNEMIS
                    if (!isset($line['from'], $line['to'], $line['proba'], $line['date'])) {
                        throw new UnexpectedValueException("Ennemy Spy: Incomplete Metadata ");
                    }

                    $line['proba'] = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT);
                    $line['from'] = Check::coords($line['from']);
                    $line['to'] = Check::coords($line['to']);

                    $query = "SELECT spy_id FROM " . TABLE_PARSEDSPYEN . " WHERE sender_id = '" . $xtense_user_data['user_id'] . "' AND dateSpy = '{$line['date']}'";
                    if ($db->sql_numrows($db->sql_query($query)) == 0) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDSPYEN . " (`dateSpy`, `from`, `to`, `proba`, `sender_id`) VALUES ('" . $line['date'] . "', '" . $line['from'] . "', '" . $line['to'] . "', '" . $line['proba'] . "', '" . $xtense_user_data['user_id'] . "')");
                    }
                    $ennemy_spy = array(
                        'from' => explode(':', $line['from']),
                        'to' => explode(':', $line['to']),
                        'proba' => (int)$line['proba'],
                        'time' => $line['date']
                    );
                    $call->add('ennemy_spy', $ennemy_spy);
                    add_log('info', array('toolbar' => $toolbar_info, 'message' => "a été espionné avec une probabilité de  " . $line['proba']));
                    break;

                case 'rc_cdr': //RAPPORT RECYCLAGE
                    if (!isset($line['nombre'],
                        $line['coords'],
                        $line['M_recovered'],
                        $line['C_recovered'],
                        $line['M_total'],
                        $line['C_total'],
                        $line['date'])) {
                        throw new UnexpectedValueException("Harvesting Report: Incomplete Metadata ");
                    }

                    $line['nombre'] = filter_var($line['nombre'], FILTER_SANITIZE_NUMBER_INT);
                    $line['coords'] = Check::coords($line['coords']);
                    $line['M_recovered'] = filter_var($line['M_recovered'], FILTER_SANITIZE_NUMBER_INT);
                    $line['C_recovered'] = filter_var($line['C_recovered'], FILTER_SANITIZE_NUMBER_INT);
                    $line['M_total'] = filter_var($line['M_total'], FILTER_SANITIZE_NUMBER_INT);
                    $line['C_total'] = filter_var($line['C_total'], FILTER_SANITIZE_NUMBER_INT);

                    $query = "SELECT `id_rec` FROM " . TABLE_PARSEDREC . " WHERE `sender_id` = '" . $xtense_user_data['user_id'] . "' AND `dateRec` = '{$line['date']}'";
                    if ($db->sql_numrows($db->sql_query($query)) == 0) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDREC . " (`dateRec`, `coordinates`, `nbRec`, `M_total`, `C_total`, `M_recovered`, `C_recovered`, `sender_id`) VALUES ('" . $line['date'] . "', '" . $line['coords'] . "', '" . $line['nombre'] . "', '" . $line['M_total'] . "', '" . $line['C_total'] . "', '" . $line['M_recovered'] . "', '" . $line['C_recovered'] . "', '" . $xtense_user_data['user_id'] . "')");
                    }
                    $rc_cdr = array(
                        'nombre' => (int)$line['nombre'],
                        'coords' => explode(':', $line['coords']),
                        'M_reco' => (int)$line['M_recovered'],
                        'C_reco' => (int)$line['C_recovered'],
                        'M_total' => (int)$line['M_total'],
                        'C_total' => (int)$line['C_total'],
                        'time' => $line['date']
                    );
                    $call->add('rc_cdr', $rc_cdr);
                    break;

                case 'expedition': //RAPPORT EXPEDITION
                case 'expedition_shared':

                    if (!isset($line['coords'], $line['content'])) {
                        throw new UnexpectedValueException("Expedition Message: Incomplete Metadata ");
                    }

                    $line['content'] = filter_var($line['content']);
                    $line['coords'] = Check::coords($line['coords'], 1); //On ajoute 1 car c'est une expédition

                    $expedition = array(
                        'time' => $line['date'],
                        'coords' => explode(':', $line['coords']),
                        'content' => $line['content']
                    );
                    $call->add($line['type'], $expedition);
                    break;
                default:
                    throw new UnexpectedValueException("Message category not found " . $line['type']);
            }
            $io->set(array(
                'type' => (isset($pub_returnAs) && $pub_returnAs == 'spy' ? 'spy' : 'messages')
            ));
        }
        break;

    default:
        throw new UnexpectedValueException("Game Data category not found " . $received_game_data['type']);
}

$call->apply();

$io->set('execution', str_replace(',', '.', round((microtime(true) - $start_time) * 1000, 2)));
$io->send();
$db->sql_close();

exit();
