<?php
global $db, $root;
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

global $de,$table_prefix;
$mod_uninstall_name = "xtense";
$mod_uninstall_table = $table_prefix."xtense_groups".','.$table_prefix."xtense_callbacks".','.$table_prefix."parsedRec".','.$table_prefix."parsedSpyEn";
uninstall_mod ($mod_uninstall_name, $mod_uninstall_table);

$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE `config_name` LIKE "xtense_%"');
generate_config_cache();

