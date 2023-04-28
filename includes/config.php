<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

global $table_prefix;

define('TABLE_XTENSE_GROUPS', $table_prefix.'xtense_groups');
define('TABLE_XTENSE_CALLBACKS', $table_prefix.'xtense_callbacks');
define('TABLE_PARSEDREC', $table_prefix.'parsedRec');
define('TABLE_PARSEDSPYEN', $table_prefix.'parsedSpyEn');

define('TYPE_PLANET', 0);
define('TYPE_MOON', 1);

if (file_exists ("../mod/{$root}/version.txt"))
	list($mod_name, $mod_version, $mod_install, $ogspy_min_version, $toolbar_min_version) = file("../mod/{$root}/version.txt");
else
	list($mod_name, $mod_version, $mod_install, $ogspy_min_version, $toolbar_min_version) = file("mod/{$root}/version.txt");
	
define('PLUGIN_VERSION', trim($mod_version));
define('TOOLBAR_MIN_VERSION', trim($toolbar_min_version));

$database = array(
	'ressources' => array('metal','cristal','deuterium','energie','activite'),
	'ressources_p' => array('M_percentage','C_Percentage','D_percentage','CES_percentage','CEF_percentage','SAT_percentage','FOR_percentage'),
	'buildings' => array('M', 'C', 'D', 'CES', 'CEF', 'UdR', 'UdN', 'CSp', 'SAT', 'HM', 'HC', 'HD', 'FOR', 'Lab', 'Ter','Dock', 'Silo', 'DdR', 'BaLu', 'Pha', 'PoSa'),
	'labo' => array('Esp', 'Ordi', 'Armes', 'Bouclier', 'Protection', 'NRJ', 'Hyp', 'RC', 'RI', 'PH', 'Laser', 'Ions', 'Plasma', 'RRI', 'Graviton', 'Astrophysique'),
	'defense' => array('LM', 'LLE', 'LLO', 'CG', 'LP', 'AI', 'PB', 'GB', 'MIC', 'MIP'),
	'fleet' => array('PT', 'GT', 'CLE', 'CLO', 'CR', 'VB', 'VC', 'REC', 'SE', 'BMD', 'SAT', 'DST', 'EDLM', 'FOR','TRA','FAU', 'ECL')
);

$databaseSpyId = array(
    'ressources' => array( 601 => 'metal',602 => 'cristal',603 => 'deuterium',604 => 'energie'),
    //'debris' => array(701 =>'metal',702 =>'cristal',703 =>'deuterium'), Not Supported by OGSpy yet
    'buildings' => array( 1 =>'M', 2 =>'C', 3 =>'D', 4 =>'CES', 12 =>'CEF', 14 =>'UdR', 15 =>'UdN', 21 =>'CSp', 22 =>'HM', 23 =>'HC', 24 =>'HD', 31 =>'Lab', 33 =>'Ter',34 =>'DdR', 44 =>'Silo', 36 =>'Dock', 41 =>'BaLu', 42 =>'Pha', 43 =>'PoSa'),
    'labo' => array(106 =>'Esp', 108 =>'Ordi', 109 =>'Armes', 110 =>'Bouclier', 111 =>'Protection', 113 =>'NRJ', 114 =>'Hyp', 115 =>'RC', 117 =>'RI', 118 =>'PH', 120 =>'Laser', 121 =>'Ions', 122 =>'Plasma', 123 =>'RRI', 124 =>'Astrophysique', 199 =>'Graviton' ),
    'defense' => array(401 =>'LM', 402 =>'LLE', 403 =>'LLO', 404 =>'CG', 405 =>'AI', 406 =>'LP', 407 =>'PB', 408 =>'GB', 502 =>'MIC', 503 =>'MIP'),
    'fleet' => array(202 =>'PT', 203 =>'GT', 204 =>'CLE', 205 =>'CLO', 206 =>'CR', 207 =>'VB', 208 =>'VC', 209 =>'REC', 210 =>'SE', 211 =>  'BMD', 212 =>'SAT', 213 =>'DST', 214 =>'EDLM', 215 => 'TRA', 217 =>'FOR', 218 => 'FAU', 219 =>  'ECL')
);

$callbackTypesNames = array(
	'overview','system','ally_list','buildings','research','fleet','fleetSending','defense','spy','ennemy_spy','rc',
	'rc_cdr', 'msg', 'ally_msg', 'expedition','ranking'
);


