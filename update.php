<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");
global $db,$table_prefix;

define("TABLE_XTENSE_CALLBACKS", $table_prefix . "xtense_callbacks");

$mod_folder = "xtense";
$mod_name = "xtense";

$db->sql_query("UPDATE " . TABLE_MOD . " SET menu = 'Xtense' WHERE title = 'xtense'");

$db->sql_query("ALTER TABLE ".$table_prefix."parsedRec"." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".TABLE_XTENSE_CALLBACKS." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".$table_prefix."xtense_groups"." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".$table_prefix."parsedSpyEn"." CONVERT TO CHARACTER SET utf8");


$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE `config_name` LIKE "xtense_log"');
$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE `config_name` LIKE "xtense_keep_log"');

$result = $db->sql_query("SELECT `version` FROM ".TABLE_MOD." WHERE `title` = 'xtense'");
list($version) = $db->sql_fetch_row($result);

if(version_compare($version, '2.7.2', '<')){
    $db->sql_query("ALTER TABLE ".TABLE_XTENSE_CALLBACKS." MODIFY `type` enum('overview','system','ally_list','buildings','research','fleet','fleetSending','defense','spy', 'spy_shared', 'ennemy_spy','rc', 'rc_shared','rc_cdr', 'msg', 'ally_msg', 'expedition','expedition_shared', 'ranking', 'trade', 'trade_me','hostiles') NOT NULL");
}

update_mod($mod_folder, $mod_name);

