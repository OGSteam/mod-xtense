<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");
global $db,$table_prefix;

$mod_folder = "xtense";
$mod_name = "xtense";

$db->sql_query("ALTER TABLE ".TABLE_PARSEDREC." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".TABLE_XTENSE_CALLBACKS." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".TABLE_XTENSE_GROUPS." CONVERT TO CHARACTER SET utf8");
$db->sql_query("ALTER TABLE ".TABLE_PARSEDSPYEN." CONVERT TO CHARACTER SET utf8");


$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE config_name LIKE "xtense_log_ogspy"');
$db->sql_query('DELETE FROM '.TABLE_CONFIG.' WHERE config_name LIKE "xtense_keep_log"');

update_mod($mod_folder, $mod_name);

?>