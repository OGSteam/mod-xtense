<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 * @licence GNU
 */

namespace Ogsteam\Ogspy;

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

global $table_prefix;
$mod_uninstall_table = $table_prefix."xtense_groups".','.$table_prefix."xtense_callbacks".','.$table_prefix."parsedRec".','.$table_prefix."parsedSpyEn";

mod_remove_tables($mod_uninstall_table);

mod_del_option('xtense_allow_connections');
mod_del_option('xtense_log_empire');
mod_del_option('xtense_log_ranking');
mod_del_option('xtense_log_spy');
mod_del_option('xtense_log_system');
mod_del_option('xtense_log_ally_list');
mod_del_option('xtense_log_messages');
mod_del_option('xtense_log_reverse');
mod_del_option('xtense_strict_admin');
mod_del_option('xtense_universe');
mod_del_option('xtense_spy_autodelete');

generate_config_cache();

