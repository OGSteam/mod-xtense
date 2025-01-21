<?php
global $db;

/**
 * @package Xtense
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

$install_ogspy = false;
$is_ok = false;
$mod_folder = "xtense";
$root = "xtense";
$is_ok = install_mod($mod_folder);
$db->sql_query("UPDATE " . TABLE_MOD . " SET menu = 'Xtense' WHERE title = 'xtense'");


if ($is_ok) {
	if (file_exists(str_replace('install', '', getcwd()) . 'mod/' . $root . '/includes/config.php'))
	{
		require_once(str_replace('install', '', getcwd()) . 'mod/' . $root . '/includes/config.php');
		$install_ogspy = true;
	} else {
		require_once('mod/' . $root . '/includes/config.php');

		//---- Creation de la table des recyclages
		$db->sql_query("CREATE TABLE IF NOT EXISTS " . TABLE_PARSEDREC . " (
			`id_rec` INT( 255 ) NOT NULL AUTO_INCREMENT ,
			`dateRec` INT( 11 ) NOT NULL ,
			`coordinates` VARCHAR( 9 ) NOT NULL ,
			`nbRec` INT( 11 ) NOT NULL ,
			`M_total` INT( 11 ) NOT NULL ,
			`C_total` INT( 11 ) NOT NULL ,
			`M_recovered` INT( 11 ) NOT NULL ,
			`C_recovered` INT( 11 ) NOT NULL ,
			`sender_id` INT( 11 ) NOT NULL ,
			PRIMARY KEY ( `id_rec` )
		) DEFAULT CHARSET=utf8;");

		$db->sql_query("CREATE TABLE IF NOT EXISTS " . TABLE_PARSEDSPYEN . " (
            `spy_id` INT( 255 ) NOT NULL AUTO_INCREMENT ,
            `dateSpy` INT( 11 ) NOT NULL ,
            `from` VARCHAR( 9 ) NOT NULL ,
            `to` VARCHAR( 9 ) NOT NULL ,
            `proba` INT( 3 ) NOT NULL ,
            `sender_id` INT( 11 ) NOT NULL ,
            PRIMARY KEY ( `spy_id` )
        )DEFAULT CHARSET=utf8;");

		//---- Creation de la table des Callbacks
		$db->sql_query("CREATE TABLE IF NOT EXISTS `" . TABLE_XTENSE_CALLBACKS . "` (
			`id` int(3) NOT NULL auto_increment,
			`mod_id` int(3) NOT NULL,
			`function` varchar(30) NOT NULL,
			`type` enum('overview','system','ally_list','buildings','research','fleet','fleetSending','defense','spy', 'spy_shared','ennemy_spy','hostiles','rc', 'rc_shared', 'rc_cdr', 'msg', 'ally_msg', 'expedition', 'expedition_shared', 'trade', 'trade_me','ranking_player_fleet','ranking_player_points','ranking_player_research','ranking_ally_fleet','ranking_ally_points','ranking_ally_research') NOT NULL,
			`active` tinyint(1) NOT NULL default '1',
			PRIMARY KEY (`id`),
			UNIQUE KEY `mod_id` (`mod_id`,`type`),
			KEY `active` (`active`)
			) DEFAULT CHARSET=utf8;");

		$db->sql_query("CREATE TABLE IF NOT EXISTS `" . TABLE_XTENSE_GROUPS . "` (
			`group_id` int(4) NOT NULL,
			`system` tinyint(4) NOT NULL,
			`ranking` tinyint(4) NOT NULL,
			`empire` tinyint(4) NOT NULL,
			`messages` tinyint(4) NOT NULL,
			PRIMARY KEY  (`group_id`)
			) DEFAULT CHARSET=utf8;");

		//---- Creation configuration Xtense
		$db->sql_query("REPLACE INTO " . TABLE_CONFIG . " (config_name, config_value) VALUES
			('xtense_allow_connections', '1'),
			('xtense_log_empire', '0'),
			('xtense_log_ranking', '1'),
			('xtense_log_spy', '1'),
			('xtense_log_system', '1'),
			('xtense_log_ally_list', '1'),
			('xtense_log_messages', '1'),
			('xtense_log_reverse', '0'),
			('xtense_strict_admin', '0'),
			('xtense_universe', 'https://sxx-fr.ogame.gameforge.com'),
			('xtense_spy_autodelete', '1')
		");
		generate_config_cache();
		$db->sql_query("REPLACE INTO " .TABLE_XTENSE_GROUPS. " (`group_id`, `system`, `ranking`, `empire`, `messages`) VALUES
			('1', '1', '1', '1', '1')");

	}
}
