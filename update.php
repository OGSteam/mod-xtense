<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

namespace Ogsteam\Ogspy;

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");
global $db,$table_prefix;

define("TABLE_XTENSE_CALLBACKS", $table_prefix . "xtense_callbacks");

$mod_name = "xtense";

mod_del_option("xtense_log_ogspy");
mod_del_option("xtense_keep_log");

$version = mod_version($mod_name);

if(version_compare($version, '2.7.2', '<')){
    $db->sql_query("ALTER TABLE ".TABLE_XTENSE_CALLBACKS." MODIFY `type` enum('overview','system','ally_list','buildings','research','fleet','fleetSending','defense','spy', 'spy_shared', 'ennemy_spy','rc', 'rc_shared','rc_cdr', 'msg', 'ally_msg', 'expedition','expedition_shared', 'ranking', 'trade', 'trade_me','hostiles') NOT NULL");
}


