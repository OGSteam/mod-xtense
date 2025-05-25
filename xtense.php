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

header("Access-Control-Allow-Origin: {$origin} ");
header('Access-Control-Max-Age: 86400');    // cache for 1 day
header('Access-Control-Request-Headers: Content-Type');    // cache for 1 day
header("Content-Type: text/plain");
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
$user_data = xtense_check_auth($received_game_data['password']);
$user_data = xtense_check_user_rights($user_data);

$call = new CallbackHandler();

// Xtense : Ajout de la version et du type de barre utilisée par l'utilisateur
$current_user_id = $user_data['id'];
$db->sql_query("UPDATE " . TABLE_USER . " SET `xtense_version` = '" . $received_game_data['toolbar_version'] . "', `xtense_type` = '" . $received_game_data['toolbar_type'] . "' WHERE `id` =  $current_user_id");
$toolbar_info = $received_game_data['toolbar_type'] . " V" . $received_game_data['toolbar_version'];

// Récupération des données de jeu
$data = json_decode($received_game_data['data'], true);

// Meilleur Endroit pour voir ce que l'on récupère de l'extension :-)
//print_r($data);


switch ($received_game_data['type']) {
    case 'overview':
        { //PAGE OVERVIEW
            if (!$user_data['grant']['empire']) {
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

                $planet_name = filter_var($data['planetName'], FILTER_DEFAULT);
                $planet_id = filter_var($data['planetId'], FILTER_VALIDATE_INT);
                $ressources = filter_var_array($data['ressources'], FILTER_VALIDATE_INT);
                $temperature_min = filter_var($data['temperature_min'], FILTER_VALIDATE_INT);
                $temperature_max = filter_var($data['temperature_max'], FILTER_VALIDATE_INT);
                $fields = filter_var($data['fields'], FILTER_VALIDATE_INT);

                $coords = Check::coords($data['coords']);
                $planet_type = ((int)$data['planetType'] == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
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
                    $currentOgspyUserId = (int)$user_data['id']; // ID de l'utilisateur OGSpy

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
                                (`player_id`, `id`, `galaxy`, `system`, `row`, `name`, `fields`, `boosters`, `temperature_min`, `temperature_max`)
                            VALUES
                                ({$gamePlayerId}, {$planet_id}, {$g}, {$s}, {$r}, '{$planet_name}', {$fields}, '{$boosters}', {$temperature_min}, {$temperature_max})
                            ON DUPLICATE KEY UPDATE
                                name = VALUES(name),
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

        if (!$user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['coords']);
            $planet_name = filter_var($data['planetName']);
            $planet_type = filter_var($data['planetType']);
            $planet_id = filter_var($data['planetId'], FILTER_VALIDATE_INT);
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
                $buildingColumns[] = $code;
                $buildingValues[] = isset($buildings[$code]) ? (int)$buildings[$code] : 0;
            }

            // Préparation des champs pour la clause ON DUPLICATE KEY UPDATE
            $updatePairs = [];
            $updatePairs[] = "name = VALUES(name)"; // CHANGED and RENAMED
            $updatePairs[] = "galaxy = VALUES(galaxy)"; // ADDED
            $updatePairs[] = "system = VALUES(system)"; // ADDED
            $updatePairs[] = "row = VALUES(row)";       // ADDED

            foreach ($database['buildings'] as $code) {
                $updatePairs[] = "`$code` = VALUES(`$code`)"; // CHANGED to use VALUES()
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
        if (!$user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords_str = filter_var($data['coords']);
            $planet_name = filter_var($data['planetName']);
            $planet_type_int = filter_var($data['planetType'], FILTER_VALIDATE_INT);
            $planet_id = filter_var($data['planetId'], FILTER_VALIDATE_INT);
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
            $columns[] = 'id'; $values[] = $planet_id;
            $columns[] = 'galaxy'; $values[] = (int)$g;
            $columns[] = 'system'; $values[] = (int)$s;
            $columns[] = 'row'; $values[] = (int)$r;
            $columns[] = 'name'; $values[] = $db->sql_escape_string($planet_name);
            $columns[] = 'type'; $values[] = $db->sql_escape_string($astro_object_type_str);

            // Colonnes pour les pourcentages de ressources
            // Clé JSON => Colonne DB
            $resource_settings_map = [
                'M_percentage'   => 'M_percentage',
                'C_Percentage'   => 'C_Percentage',
                'D_percentage'   => 'D_percentage',
                'CES_percentage' => 'CES_percentage',
                'CEF_percentage' => 'CEF_percentage',
                'SAT_percentage' => 'Sat_percentage', // Correction de casse pour la DB
                'FOR_percentage' => 'FOR_percentage'
            ];

            foreach ($resource_settings_map as $json_key => $db_col_name) {
                $columns[] = $db_col_name;
                // Si la clé n'est pas dans les données JSON, mettre 0 (comme pour 'buildings')
                $values[] = isset($resourceSettings_data[$json_key]) ? (int)$resourceSettings_data[$json_key] : 0;
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
        if (!$user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['coords']);
            $planet_name = filter_var($data['planetName']);
            $planet_id = filter_var($data['planetId'], FILTER_VALIDATE_INT);
            $planet_type = filter_var($data['planetType']);

            $defense = $data['defense'];
            //Stop si donnée manquante
            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Defense: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            list($g, $s, $r) = explode(':', $coords); // Extraire les coordonnées
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
            $planet_type_str = ((int)$planet_type == TYPE_PLANET ? 'planet' : 'moon');

            // S'assurer que l'objet astro existe dans la base de données
            $astro_object_query = $db->sql_query("SELECT id FROM " . TABLE_USER_BUILDING . "
                                              WHERE galaxy = {$g} AND system = {$s} AND row = {$r} AND type = '{$planet_type_str}'");

            // Si l'objet astro n'existe pas, on le crée
            if ($db->sql_numrows($astro_object_query) == 0) {
                $db->sql_query("INSERT INTO " . TABLE_USER_BUILDING . "
                            (id, type, galaxy, system, row, name, player_id)
                            VALUES
                            ({$planet_id}, '{$planet_type_str}', {$g}, {$s}, {$r}, '{$planet_name}', {$user_data['player_id']})");
            } else {
                // Mise à jour du nom de la planète si nécessaire
                $db->sql_query("UPDATE " . TABLE_USER_BUILDING . "
                            SET name = '{$planet_name}'
                            WHERE galaxy = {$g} AND system = {$s} AND row = {$r} AND type = '{$planet_type_str}'");
            }

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

        if (!$user_data['grant']['empire']) {
            $io->set(['type' => 'plugin grant', 'access' => 'empire']);
            $io->status(0);
        } else {
            $coords = filter_var($data['coords']);
            $planet_name = filter_var($data['planetName']);
            $planet_type = filter_var($data['planetType']);
            $researchs = $data['researchs'];

            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Researchs: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);

            // Vérifier que l'ID du joueur est disponible
            if (!isset($user_data['player_id']) || $user_data['player_id'] <= 0) {
                throw new UnexpectedValueException("Researchs: Player ID not available");
            }

            $player_id = (int)$user_data['player_id'];

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

        if (!$user_data['grant']['empire']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'empire'
            ));
            $io->status(0);
        } else {
            $coords = filter_var($data['coords']);
            $planet_name = filter_var($data['planetName']);
            $planet_type = filter_var($data['planetType']);
            $fleet = $data['fleet'];
            if (!isset($coords, $planet_name, $planet_type)) {
                throw new UnexpectedValueException("Fleet: Missing Planet Details");
            }
            $coords = Check::coords($coords);
            $planet_type = ((int)$planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);

            // Flotte à mettre à insérer si table disponible ? Pour l'instant on ne fait rien :-)
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
        if (!$user_data['grant']['system']) {
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

            $query = "SELECT `row` FROM " . TABLE_UNIVERSE . " WHERE `galaxy` = {$galaxy}  AND `system` =  {$system}";
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
                $v['ally_id'] = (isset($v['ally_id']) ? (int)$v['ally_id'] : -1);
                $v['ally_id'] = ((int)$v['ally_id'] == 0) ? -1 : $v['ally_id'];

                //Lors de l'insert ou de l'update il y a l'insert ou l'update de la table game_ally et game_player
                // phase transitoire avec doublon d information antre table universe(1) et game_player(2)
                //Table universe(1)
                // UPSERT pour la table universe
                $db->sql_query("INSERT INTO " . TABLE_UNIVERSE . "
                    (`galaxy`, `system`, `row`, `name`, `player`, `player_id`, `ally`, `ally_id`, `status`, `last_update`, `last_update_user_id`, `moon`)
                    VALUES
                    ({$galaxy}, {$system}, {$row}, '{$v['planet_name']}', '{$v['player_name']}', {$v['player_id']}, '{$v['ally_tag']}', {$v['ally_id']}, '{$statusTemp}', " . time() . ", {$user_data['user_id']}, '{$v['moon']}')
                    ON DUPLICATE KEY UPDATE
                    `name` = '{$v['planet_name']}',
                    `player` = '{$v['player_name']}',
                    `player_id` = {$v['player_id']},
                    `ally` = '{$v['ally_tag']}',
                    `ally_id` = {$v['ally_id']},
                    `status` = '{$statusTemp}',
                    `last_update` = " . time() . ",
                    `last_update_user_id` = {$user_data['user_id']},
                    `moon` = '{$v['moon']}'");

                // UPSERT pour la table game_player
                if ($v['player_id'] != -1) {

                    $currentTime = time();
                    $playerId = (int)$v['player_id'];
                    $playerName = $db->sql_escape_string($v['player_name']);
                    $status = $db->sql_escape_string($statusTemp);
                    $allyId = (int)$v['ally_id'];

                    $db->sql_query("INSERT INTO " . TABLE_GAME_PLAYER . "
                                (`player_id`, `player`, `status`, `ally_id`, `datadate`)
                                VALUES
                                ($playerId, '$playerName', '$status', $allyId, $currentTime)
                                ON DUPLICATE KEY UPDATE
                                `player` = '$playerName',
                                `status` = '$status',
                                `ally_id` = $allyId,
                                `datadate` = $currentTime");
                }
                //La table game ally ne peut se mettre à jour, champs ally non alimenté (toutes les infos sont dans page rank)
            }

            if (!empty($delete)) {
                $toDelete = array();
                foreach ($delete as $n) {
                    $toDelete[] = $galaxy . ':' . $system . ':' . $n;
                }

                $db->sql_query("UPDATE " . TABLE_PARSEDSPY . " SET `active` = 0 WHERE `coordinates` IN ('" . implode("', '", $toDelete) . "')");
            }

            $db->sql_query("UPDATE " . TABLE_USER . " SET `planet_imports` = `planet_imports` + 15 WHERE `user_id` = " . $user_data['user_id']);

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

            update_statistic('planetimport_ogs', 15);
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

        if (!$user_data['grant']['ranking']) {
            $io->set(array(
                'type' => 'plugin grant',
                'access' => 'ranking'
            ));
            $io->status(0);
        } else {
            if ($type1 != ('player' || 'ally')) {
                throw new UnexpectedValueException("Ranking: Unexpected Ranking type1");
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
                $data['player_name'] = filter_var($data['player_name']);
                $data['ally_tag'] = filter_var($data['ally_tag']);

                if (isset($data['points'])) {
                    $data['points'] = filter_var($data['points'], FILTER_SANITIZE_NUMBER_INT);
                }

                if (isset($data['ally_id'])) {
                    $data['ally_id'] = filter_var($data['ally_id'], FILTER_SANITIZE_NUMBER_INT);
                    if ($data['ally_id'] === '') {
                        $data['ally_id'] = -1;
                    }
                }

                if (isset($data['player_id'])) {
                    $data['player_id'] = filter_var($data['player_id'], FILTER_SANITIZE_NUMBER_INT);
                    if ($data['player_id'] === '') {
                        $data['player_id'] = -1;
                    }
                }

                if ($table == TABLE_RANK_PLAYER_MILITARY) {
                    $query[] = "({$timestamp}, {$data['rank']}, '{$data['player_name']}' , {$data['player_id']}, '{$data['ally_tag']}', {$data['ally_id']}, {$data['points']}, {$user_data['user_id']}, {$data['nb_spacecraft']} )";
                } else {
                    $query[] = "({$timestamp}, {$data['rank']}, '{$data['player_name']}' , {$data['player_id']}, '{$data['ally_tag']}', {$data['ally_id']}, {$data['points']}, {$user_data['user_id']} )";
                }
                $total++;
                $datas[] = $data;
            }
            if (!empty($query)) {
                if ($table == TABLE_RANK_PLAYER_MILITARY) {
                    $db->sql_query("REPLACE INTO " . $table . " (`datadate`, `rank`, `player`, `player_id`, `ally`, `ally_id`, `points`, `sender_id`, `nb_spacecraft`) VALUES " . implode(',', $query));
                } else {
                    $db->sql_query("REPLACE INTO " . $table . " (`datadate`, `rank`, `player`, `player_id`, `ally`, `ally_id`, `points`, `sender_id`) VALUES " . implode(',', $query));
                }
            }
        } else {
            $fields = 'datadate, rank, ally, ally_id, points, sender_id, number_member, points_per_member';
            foreach ($n as $data) {
                $data['ally_tag'] = filter_var($data['ally_tag']);

                if (!isset($data['ally_id'])) {
                    throw new UnexpectedValueException("Ranking Ally: Alliance Id not found");
                }
                $data['ally_id'] = filter_var($data['ally_id'], FILTER_SANITIZE_NUMBER_INT);

                if (!isset($data['points'])) {
                    throw new UnexpectedValueException("Ranking Ally: No points sent");
                }
                $data['points'] = filter_var($data['points'], FILTER_SANITIZE_NUMBER_INT);

                if (!isset($data['mean'])) {
                    throw new UnexpectedValueException("Ranking Ally: No mean found");
                }
                $data['mean'] = filter_var($data['mean'], FILTER_SANITIZE_NUMBER_INT);

                if (!isset($data['members'])) {
                    throw new UnexpectedValueException("Ranking Ally: Nb players not found");
                }
                $data['members'] = filter_var($data['members'], FILTER_SANITIZE_NUMBER_INT);


                $query[] = "({$timestamp}, {$data['rank']} , '{$data['ally_tag']}' , {$data['ally_id']} , {$data['points']} , {$user_data['user_id']} , {$data['members']} ,{$data['mean']} )";
                $datas[] = $data;
                $total++;
            }

            if (!empty($query)) {
                $db->sql_query("REPLACE INTO " . $table . " (" . $fields . ") VALUES " . implode(',', $query));
            }

            $db->sql_query("UPDATE " . TABLE_USER . " SET rank_imports = rank_imports + " . $total . " WHERE user_id = " . $user_data['user_id']);
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

        update_statistic('rankimport_ogs', 100);
        add_log('ranking', array('type1' => $type1, 'type2' => $type2, 'offset' => $offset, 'time' => $timestamp, 'toolbar' => $toolbar_info));

        break;

    case 'rc': //PAGE RC
    case 'rc_shared':
        $json = filter_var($data['json']);
        $ogapilnk = filter_var($data['ogapilnk']);

        if (!isset($json)) {
            throw new UnexpectedValueException("Combat Report: JSON Report not sent");
        }
        $ogapilnk = $ogapilnk ?? '';

        if (!$user_data['grant']['messages']) {
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
        if (!$user_data['grant']['ranking']) {
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

        if (!$user_data['grant']['messages']) {
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
                    if (!isset($line['coords'],
                        $line['content'],
                        $line['planetName'],
                        $line['proba'],
                        $line['activity'])) {
                        throw new UnexpectedValueException("Shared Spy: Incomplete Metadata ");
                    }

                    $coords = Check::coords($line['coords']);
                    $content = filter_var_array($line['content']);
                    $playerName = filter_var($line['playerName']);
                    $planetName = filter_var($line['planetName']);
                    $moon = filter_var($line['isMoon']);
                    $proba = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT);
                    $activite = filter_var($line['activity'], FILTER_SANITIZE_NUMBER_INT);
                    $date = filter_var($line['date'], FILTER_SANITIZE_NUMBER_INT);

                    $proba = $proba > 100 ? 100 : $proba;
                    $activite = $activite > 59 ? 59 : $activite;
                    $spy = array(
                        'proba' => $proba,
                        'activite' => $activite,
                        'coords' => explode(':', $coords),
                        'content' => $content,
                        'time' => $date,
                        'player_name' => $playerName,
                        'planet_name' => $planetName
                    );
                    $call->add($line['type'], $spy);

                    $spyDB = [];
                    foreach ($databaseSpyId as $arr) {
                        $spyDB = array_merge($spyDB, $arr);
                    }
                    $coords = $spy['coords'][0] . ':' . $spy['coords'][1] . ':' . $spy['coords'][2];
                    $matches = array();
                    $data = array();
                    $values = $fields = '';

                    $fields .= 'planet_name, coordinates, sender_id, proba, activite, dateRE';
                    $values .= '"' . trim($spy['planet_name']) . '", "' . $coords . '", ' . $user_data['user_id'] . ', ' . $spy['proba'] . ', ' . $spy['activite'] . ', ' . $spy['time'] . ' ';

                    foreach ($spy['content'] as $code => $value) {
                        // La table RE ne supporte pas les CDR dans le rapport
                        if ($code === 701 || $code === 702) {
                            continue;
                        }
                        $field = $spyDB[$code];
                        $fields .= ', `' . $field . '`';
                        $values .= ', ' . $value;
                    }
                    //log_('debug', "INSERT INTO " . TABLE_PARSEDSPY . " ( " . $fields . ") VALUES (" . $values . ")");
                    $spy_time = $spy['time'];
                    $test = $db->sql_numrows($db->sql_query("SELECT `id_spy` FROM " . TABLE_PARSEDSPY . " WHERE `coordinates` = '$coords' AND `dateRE` = '$spy_time'"));
                    if (!$test) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDSPY . " ( " . $fields . ") VALUES (" . $values . ")");
                        $query = $db->sql_query('SELECT `last_update`' . ($moon ? '_moon' : '') . ' FROM ' . TABLE_UNIVERSE . ' WHERE `galaxy` = ' . $spy['coords'][0] . ' AND `system` = ' . $spy['coords'][1] . ' AND `row` = ' . $spy['coords'][2]);
                        //log_('debug', 'SELECT last_update' . ($moon ? '_moon' : '') . ' FROM ' . TABLE_UNIVERSE . ' WHERE galaxy = ' . $spy['coords'][0] . ' AND system = ' . $spy['coords'][1] . ' AND row = ' . $spy['coords'][2]);
                        if ($db->sql_numrows($query) > 0) {
                            $assoc = $db->sql_fetch_assoc($query);
                            if ($assoc['last_update' . ($moon ? '_moon' : '')] < $spy_time) {
                                if ($moon) {
                                    (isset($spy['content'][42]) ? $phalanx = $spy['content'][42] : $phalanx = 0);
                                    (isset($spy['content'][43]) ? $gate = $spy['content'][43] : $gate = 0);
                                    //log_('debug', "Lune détectée avec phalange $phalanx et porte $gate");
                                    $db->sql_query('UPDATE ' . TABLE_UNIVERSE . ' SET `moon` = "1", `phalanx` = ' . $phalanx . ', `gate` = "' . $gate . '", `last_update_moon` = ' . $date . ', `last_update_user_id` = ' . $user_data['user_id'] . ' WHERE `galaxy` = ' . $spy['coords'][0] . ' AND `system` = ' . $spy['coords'][1] . ' AND `row` = ' . $spy['coords'][2]);
                                } else { //we do nothing if buildings are not in the report
                                    $db->sql_query('UPDATE ' . TABLE_UNIVERSE . ' SET `name` = "' . $spy['planet_name'] . '", `last_update_user_id` = ' . $user_data['user_id'] . ' WHERE `galaxy` = ' . $spy['coords'][0] . ' AND `system` = ' . $spy['coords'][1] . ' AND `row` = ' . $spy['coords'][2]);
                                }
                            }
                        }
                        $db->sql_query('UPDATE ' . TABLE_USER . ' SET `spy_imports` = spy_imports + 1 WHERE `user_id` = ' . $user_data['user_id']);
                        update_statistic('spyimport_ogs', '1');
                        add_log('messages', array('added_spy' => $spy['planet_name'], 'added_spy_coords' => $coords, 'toolbar' => $toolbar_info));
                    }
                    break;

                case 'ennemy_spy': //RAPPORT ESPIONNAGE ENNEMIS
                    if (!isset($line['from'], $line['to'], $line['proba'], $line['date'])) {
                        throw new UnexpectedValueException("Ennemy Spy: Incomplete Metadata ");
                    }

                    $line['proba'] = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT);
                    $line['from'] = Check::coords($line['from']);
                    $line['to'] = Check::coords($line['to']);

                    $query = "SELECT spy_id FROM " . TABLE_PARSEDSPYEN . " WHERE sender_id = '" . $user_data['user_id'] . "' AND dateSpy = '{$line['date']}'";
                    if ($db->sql_numrows($db->sql_query($query)) == 0) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDSPYEN . " (`dateSpy`, `from`, `to`, `proba`, `sender_id`) VALUES ('" . $line['date'] . "', '" . $line['from'] . "', '" . $line['to'] . "', '" . $line['proba'] . "', '" . $user_data['user_id'] . "')");
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

                    $query = "SELECT `id_rec` FROM " . TABLE_PARSEDREC . " WHERE `sender_id` = '" . $user_data['user_id'] . "' AND `dateRec` = '{$line['date']}'";
                    if ($db->sql_numrows($db->sql_query($query)) == 0) {
                        $db->sql_query("INSERT INTO " . TABLE_PARSEDREC . " (`dateRec`, `coordinates`, `nbRec`, `M_total`, `C_total`, `M_recovered`, `C_recovered`, `sender_id`) VALUES ('" . $line['date'] . "', '" . $line['coords'] . "', '" . $line['nombre'] . "', '" . $line['M_total'] . "', '" . $line['C_total'] . "', '" . $line['M_recovered'] . "', '" . $line['C_recovered'] . "', '" . $user_data['user_id'] . "')");
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
