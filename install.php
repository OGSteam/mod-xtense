<?php
global $db;

/**
 * @package Xtense
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

global $table_prefix;

define('TABLE_XTENSE_GROUPS', $table_prefix.'xtense_groups');
define('TABLE_XTENSE_CALLBACKS', $table_prefix.'xtense_callbacks');

$install_ogspy = false;
$is_ok = false;
$mod_folder = "xtense";
$root = "xtense";
$is_ok = install_mod($mod_folder);

if ($is_ok) {

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
		$db->sql_query("REPLACE INTO " . TABLE_CONFIG . " (name, value) VALUES
			('xtense_universe', 'https://sxx-fr.ogame.gameforge.com'),
			('xtense_spy_autodelete', '1')
		");
        generate_all_cache();
		$db->sql_query("REPLACE INTO " .TABLE_XTENSE_GROUPS. " (`group_id`, `system`, `ranking`, `empire`, `messages`) VALUES
			('1', '1', '1', '1', '1')");
}
