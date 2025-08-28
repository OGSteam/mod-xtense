<?php

/**
 * Parser pour le traitement de la vue d'ensemble (overview).
 */
function parse_overview($data, $xtense_user_data, $db, $toolbar_info) {
    $player_details = $data['player'];
    $uni_details = $data['uni'];

    $gamePlayerId = (int)$player_details['player_id'];
    if ($gamePlayerId > 0) {
        $gamePlayerName = $db->sql_escape_string($player_details['player_name']);
        $officerCommander = (int)$player_details['player_officer_commander'];
        $officerAmiral = (int)$player_details['player_officer_amiral'];
        $officerEngineer = (int)$player_details['player_officer_engineer'];
        $officerGeologist = (int)$player_details['player_officer_geologist'];
        $officerTechnocrate = (int)$player_details['player_officer_technocrate'];
        $ogameTimestamp = (int)$uni_details['uni_time'];
        $currentOgspyUserId = (int)$xtense_user_data['id'];

        $queryGamePlayer = "
            INSERT INTO " . TABLE_GAME_PLAYER . " (
                `id`, `name`, `class`,
                `off_commandant`, `off_amiral`, `off_ingenieur`, `off_geologue`, `off_technocrate`,
                `datadate`,
                `status`, `ally_id`
            ) VALUES (
                {$gamePlayerId},
                '{$gamePlayerName}',
                '',
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
                `datadate` = VALUES(`datadate`);";
        $db->sql_query($queryGamePlayer);
    }

    // Met à jour TABLE_USER pour stocker le player_id (ID du joueur dans le jeu)
    $db->sql_query("UPDATE " . TABLE_USER . " SET `player_id` = {$gamePlayerId} WHERE `id` = {$currentOgspyUserId}");
}
