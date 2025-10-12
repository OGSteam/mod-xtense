<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");
global $db;


$mod_folder = "xtense";
$mod_name = "xtense";

$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE `name` LIKE "xtense_log"');
$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE `name` LIKE "xtense_keep_log"');

update_mod($mod_folder, $mod_name);
generate_all_cache();
