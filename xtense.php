<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

define('IN_SPYOGAME', true);
define('IN_XTENSE', true);

date_default_timezone_set(date_default_timezone_get());

if (preg_match('#mod#', getcwd())) chdir('../../');
$_SERVER['SCRIPT_FILENAME'] = str_replace(basename(__FILE__), 'index.php', preg_replace('#\/mod\/(.*)\/#', '/', $_SERVER['SCRIPT_FILENAME']));
include("common.php");
list($root, $active) = $db->sql_fetch_row($db->sql_query("SELECT root, active FROM ".TABLE_MOD." WHERE action = 'xtense'"));

define('DEBUG', isset($pub_debug) && $_SERVER['REMOTE_ADDR'] == '127.0.0.1');
if (DEBUG) header('Content-type: text/plain');


require_once("mod/{$root}/includes/config.php");
require_once("mod/{$root}/includes/functions.php");
require_once("mod/{$root}/includes/CallbackHandler.php");
require_once("mod/{$root}/includes/Callback.php");
require_once("mod/{$root}/includes/Io.php");
require_once("mod/{$root}/includes/Check.php");

set_error_handler('error_handler');
$start_time = get_microtime();

$io = new Io();
$time = time()-60*4;
if ($time > mktime(0,0,0) && $time < mktime(8,0,0)) $timestamp = mktime(0,0,0);
if ($time > mktime(8,0,0) && $time < mktime(16,0,0)) $timestamp = mktime(8,0,0);
if ($time > mktime(16,0,0) && $time < (mktime(0,0,0)+60*60*24)) $timestamp = mktime(16,0,0);

if (isset($pub_toolbar_version, $pub_toolbar_type, $pub_mod_min_version, $pub_user, $pub_password, $pub_univers) == false) die("hack");

if (version_compare($pub_toolbar_version, TOOLBAR_MIN_VERSION, '<')) {
	$io->set(array(
		'type' => 'wrong version',
		'target' => 'toolbar',
		'version' => TOOLBAR_MIN_VERSION
	));
	$io->send(0, true);
}

if(version_compare($pub_mod_min_version, PLUGIN_VERSION, '>')) {
	$io->set(array(
		'type' => 'wrong version',
		'target' => 'plugin',
		'version' => PLUGIN_VERSION
	));
	$io->send(0, true);
}

if($active != 1){
	$io->set(array('type' => 'plugin config'));
	$io->send(0, true);
}

if ($server_config['server_active'] == 0) {
	$io->set(array(
		'type' => 'server active',
		'reason' => $server_config['reason']
	));
	$io->send(0, true);
}

if ($server_config['xtense_allow_connections'] == 0) {
	$io->set(array(
		'type' => 'plugin connections',
	));
	$io->send(0, true);
}

if (strtolower($server_config['xtense_universe']) != strtolower($pub_univers)) {
	$io->set(array(
		'type' => 'plugin univers',
	));
	$io->send(0, true);
}

$query = $db->sql_query('SELECT user_id, user_name, user_password, user_active, user_stat_name FROM '.TABLE_USER.' WHERE user_name = "'.quote($pub_user).'"');
if (!$db->sql_numrows($query)) {
	$io->set(array(
		'type' => 'username'
	));
	$io->send(0, true);
} else {
	$user_data = $db->sql_fetch_assoc($query);

	if ($pub_password != $user_data['user_password']) {
		$io->set(array(
			'type' => 'password'
		));
		$io->send(0, true);
	}

	if ($user_data['user_active'] == 0) {
		$io->set(array(
			'type' => 'user active'
		));
		$io->send(0, true);
	}
	
	$user_data['grant'] = array('system' => 0, 'ranking' => 0, 'empire' => 0, 'messages' => 0);
}

// Verification des droits de l'user
$query = $db->sql_query("SELECT system, ranking, empire, messages FROM ".TABLE_USER_GROUP." u LEFT JOIN ".TABLE_GROUP." g ON g.group_id = u.group_id LEFT JOIN ".TABLE_XTENSE_GROUPS." x ON x.group_id = g.group_id WHERE u.user_id = '".$user_data['user_id']."'");
$user_data['grant'] = $db->sql_fetch_assoc($query);
 

// Si Xtense demande la verification du serveur, renvoi des droits de l'utilisateur
if (isset($pub_server_check)) {
	$io->set(array(
		'version' => $server_config['version'],
		'servername' => $server_config['servername'],
		'grant' => $user_data['grant']
	));
	$io->send(1, true);
}

if(isset($pub_type)){
    $page_type = filter_var($pub_type, FILTER_SANITIZE_STRING);
}else die("hack");

$call = new CallbackHandler();

//nombre de messages
$io->set(array('new_messages' => 0));

// Xtense : Ajout de la version et du type de barre utilisée par l'utilisateur
$db->sql_query("UPDATE " . TABLE_USER . " SET xtense_version='" . $pub_toolbar_version . "', xtense_type='" . $pub_toolbar_type . "' WHERE user_id = ".$user_data['user_id']);
$toolbar_info = $pub_toolbar_type . " V" . $pub_toolbar_version;

switch ($page_type){
	case 'overview': //PAGE OVERVIEW
        if (isset($pub_coords, $pub_planet_name, $pub_planet_type, $pub_fields, $pub_temperature_min, $pub_temperature_max, $pub_ressources) == false) die("hack");
		if (!$user_data['grant']['empire']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'empire'
			));
			$io->status(0);
		} else {
			$pub_coords = Check::coords($pub_coords);
            $planet_name = filter_var($pub_planet_name, FILTER_SANITIZE_STRING);
						
			$coords 			= $pub_coords;
			$planet_type 		= ((int)$pub_planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
			$fields				= (int)$pub_fields;
			$temperature_min	= (int)$pub_temperature_min;
			$temperature_max	= (int)$pub_temperature_max;
			$ressources			= $pub_ressources;
			
			
			$home = home_check($planet_type, $coords);
			
			if ($home[0] == 'full') {
				$io->set(array(
						'type' => 'home full'
				));
				$io->status(0);
			} else {
				if ($home[0] == 'update') {
					$db->sql_query('UPDATE '.TABLE_USER_BUILDING.' SET planet_name = "'.$planet_name.'", `fields` = '.$fields.', temperature_min = '.$temperature_min.', temperature_max = '.$temperature_max.'  WHERE planet_id = '.$home['id'].' AND user_id = '.$user_data['user_id']);
				} else {
					$db->sql_query('INSERT INTO '.TABLE_USER_BUILDING.' (user_id, planet_id, coordinates, planet_name, `fields`, temperature_min, temperature_max) VALUES ('.$user_data['user_id'].', '.$home['id'].', "'.$coords.'", "'.$planet_name.'", '.$fields.', '.$pub_temperature_min.', '.$pub_temperature_max.')');
				}
				
				$io->set(array(
							'type' => 'home updated',
							'page' => 'overview'
				));
			}
			
			// Appel fonction de callback
			$call->add('overview', array(
						'coords' => explode(':', $coords),
						'planet_type' => $planet_type,
						'planet_name' => $planet_name,
						'fields' => $fields,
						'temperature_min' => $temperature_min,
						'temperature_max' => $temperature_max,
						'ressources' => $ressources
			));
			
			add_log('overview', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
		}
	break;

	case 'buildings': //PAGE BATIMENTS
		if (isset($pub_coords, $pub_planet_name, $pub_planet_type) == false) die("hack");
        
		if (!$user_data['grant']['empire']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'empire'
			));
			$io->status(0);
		} else {
			$pub_coords = Check::coords($pub_coords);
            $planet_name = filter_var($pub_planet_name, FILTER_SANITIZE_STRING);
			
			$coords 		= $pub_coords;
			$planet_type 	= ((int)$pub_planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
			$planet_name 	= $pub_planet_name;
			
			$home = home_check($planet_type, $coords);
			
			if ($home[0] == 'full') {
				$io->set(array(
						'type' => 'home full'
				));
				$io->status(0);
			} elseif ($home[0] == 'update') {
				$set = '';
				foreach ($database['buildings'] as $code) {
					if(isset(${'pub_'.$code}))
						$set .= ', '.$code.' = '.${'pub_'.$code};//avec la nouvelle version d'Ogame, on n'Ã©crase que si on a vraiment 0
				}
				
				$db->sql_query('UPDATE '.TABLE_USER_BUILDING.' SET planet_name = "'.$planet_name.'"'.$set.' WHERE planet_id = '.$home['id'].' AND user_id = '.$user_data['user_id']);
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'buildings'
				));
			} else {
				$set = '';
		
				foreach ($database['buildings'] as $code) {
					$set .= ', '.(isset(${'pub_'.$code}) ? (int)${'pub_'.$code} : 0);
				}
				
				$db->sql_query('INSERT INTO '.TABLE_USER_BUILDING.' (user_id, planet_id, coordinates, planet_name, '.implode(',', $database['buildings']).') VALUES ('.$user_data['user_id'].', '.$home['id'].', "'.$coords.'", "'.$planet_name.'"'.$set.')');
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'buildings'
				));
			}
			
			$buildings = array();
			foreach ($database['buildings'] as $code) {
				if (isset(${'pub_'.$code})) {
					$buildings[$code] = (int)${'pub_'.$code};
				}
			}
			
			$call->add('buildings', array(
						'coords' => explode(':', $coords),
						'planet_type' => $planet_type,
						'planet_name' => $planet_name,
						'buildings' => $buildings
			));
			
			add_log('buildings', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
		}
	break;

	case 'defense': //PAGE DEFENSE
		if (isset($pub_coords, $pub_planet_name, $pub_planet_type) == false) die("hack");
		
		if (!$user_data['grant']['empire']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'empire'
			));
			$io->status(0);
		} else {
            $pub_coords = Check::coords($pub_coords);
            $planet_name = filter_var($pub_planet_name, FILTER_SANITIZE_STRING);
			
			$coords 		= $pub_coords;
			$planet_type 	= ((int)$pub_planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
			$planet_name 	= $pub_planet_name;
			
			$home = home_check($planet_type, $coords);
			
			if ($home[0] == 'full') {
				$io->set(array(
						'type' => 'home full'
				));
				$io->status(0);
			} elseif ($home[0] == 'update') {
				$fields = '';
				$values = '';
				foreach ($database['defense'] as $code) {
					if (isset(${'pub_'.$code})) {
						$fields .= ', '.$code;
						$values .= ', '.(int)${'pub_'.$code};
					}
				}
				
				$db->sql_query('REPLACE INTO '.TABLE_USER_DEFENCE.' (user_id, planet_id'.$fields.') VALUES ('.$user_data['user_id'].', '.$home['id'].$values.')');
				$db->sql_query('UPDATE '.TABLE_USER_BUILDING.' SET planet_name = "'.$planet_name.'" WHERE user_id = '.$user_data['user_id'].' AND planet_id = '.$home['id']);
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'defense'
				));
			} else {
				$fields = '';
				$set = '';
				
				foreach ($database['defense'] as $code) {
					if (isset(${'pub_'.$code})) {
						$fields .= ', '.$code;
						$set .= ', '.(int)${'pub_'.$code};
					}
				}
				
				$db->sql_query('INSERT INTO '.TABLE_USER_BUILDING.' (user_id, planet_id, coordinates, planet_name) VALUES ('.$user_data['user_id'].', '.$home['id'].', "'.$coords.'", "'.$planet_name.'")');
				$db->sql_query('INSERT INTO '.TABLE_USER_DEFENCE.' (user_id, planet_id'.$fields.') VALUES ('.$user_data['user_id'].', '.$home['id'].$set.')');
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'defense'
				));
			}
			
			$defenses = array();
			foreach ($database['defense'] as $code) {
				if (isset(${'pub_'.$code})) {
					$defenses[$code] = (int)${'pub_'.$code};
				}
			}
			
			$call->add('defense', array(
						'coords' => explode(':', $coords),
						'planet_type' => $planet_type,
						'planet_name' => $planet_name,
						'defense' => $defenses
			));
			
			add_log('defense', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
		}
	break;

	case 'researchs': //PAGE RECHERCHE
    if (isset($pub_coords, $pub_planet_name, $pub_planet_type) == false) die("hack");
    
      
		if (!$user_data['grant']['empire']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'empire'
			));
			$io->status(0);
		} else {
            
			if ($db->sql_numrows($db->sql_query('SELECT user_id FROM '.TABLE_USER_TECHNOLOGY.' WHERE user_id = '.$user_data['user_id']))) {
				$set = array();
				foreach ($database['labo'] as $code) {
					if (isset(${'pub_'.$code})) {
						$set[] = $code.' = '.(int)${'pub_'.$code};
					}
				}
				
				if (!empty($set))
					$db->sql_query('UPDATE '.TABLE_USER_TECHNOLOGY.' SET '.implode(', ', $set).' WHERE user_id = '.$user_data['user_id']);
			} else {
				$fields = '';
				$set = '';
				
				foreach ($database['labo'] as $code) {
					if (isset(${'pub_'.$code})) {
						$fields .= ', '.$code;
						$set .= ', "'.(int)${'pub_'.$code}.'"';
					}
				}
				
				if (!empty($fields))
					$db->sql_query('INSERT INTO '.TABLE_USER_TECHNOLOGY.' (user_id'.$fields.') VALUES ('.$user_data['user_id'].$set.')');
			}
			
			$io->set(array(
					'type' => 'home updated',
					'page' => 'labo'
			));
			
			$research = array();
			foreach ($database['labo'] as $code) {
				if (isset(${'pub_'.$code})) {
					$research[$code] = (int)${'pub_'.$code};
				}
			}
			
			$call->add('research', array(
						'research' => $research
			));
			
			add_log('research', array('toolbar' => $toolbar_info));
		}
	break;

	case 'fleet': //PAGE FLOTTE
		if (isset($pub_coords, $pub_planet_name, $pub_planet_type) == false) die("hack");
        
		if (!$user_data['grant']['empire']) {
				$io->set(array(
						'type' => 'grant',
						'access' => 'empire'
				));
				$io->status(0);
		} else {
            $pub_coords = Check::coords($pub_coords);
            $planet_name = filter_var($pub_planet_name, FILTER_SANITIZE_STRING);
			
			$coords 		= $pub_coords;
			$planet_type 	= ((int)$pub_planet_type == TYPE_PLANET ? TYPE_PLANET : TYPE_MOON);
			$planet_name 	= $pub_planet_name;
			if (isset($pub_SAT)) $ss = $pub_SAT;
			if(!isset($ss)) $ss = "";
			
			$home = home_check($planet_type, $coords);
					
			if ($home[0] == 'full') {
				$io->set(array(
						'type' => 'home full'
				));
				$io->status(0);
			} elseif ($home[0] == 'update') {
				$db->sql_query('UPDATE '.TABLE_USER_BUILDING.' SET planet_name = "'.$planet_name.'" WHERE user_id = '.$user_data['user_id'].' AND planet_id = '.$home['id']);
				
				if (isset($pub_SAT)) $db->sql_query('UPDATE '.TABLE_USER_BUILDING.' SET planet_name = "'.$planet_name.'", Sat = \''.$ss.'\' WHERE planet_id = '.$home['id'].' AND user_id = '.$user_data['user_id']);
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'fleet'
				));
			} else {
				if (isset($pub_SAT)) $db->sql_query('INSERT INTO '.TABLE_USER_BUILDING.' (user_id, planet_id, coordinates, planet_name, Sat) VALUES ('.$user_data['user_id'].', '.$home['id'].', "'.$coords.'", "'.$planet_name.'", '.$ss.')');
				
				$io->set(array(
						'type' => 'home updated',
						'page' => 'fleet'
				));
			}
			
			$fleet = array();
			foreach ($database['fleet'] as $code) {
				if (isset(${'pub_'.$code})) {
					$fleet[$code] = (int)${'pub_'.$code};
				}
			}
			
			$call->add('fleet', array(
					'coords' => explode(':', $coords),
					'planet_type' => $planet_type,
					'planet_name' => $planet_name,
					'fleet' => $fleet
			));
			
			add_log('fleet', array('coords' => $coords, 'planet_name' => $planet_name, 'toolbar' => $toolbar_info));
		}
	break;

	case 'system': //PAGE SYSTEME SOLAIRE
        if (isset($pub_galaxy, $pub_system) == false) die("hack");

		if (!$user_data['grant']['system']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'system'
			));
			$io->status(0);
		} else {
			
			if ($pub_galaxy > $server_config['num_of_galaxies'] || $pub_system > $server_config['num_of_systems']);
			{
			$galaxy 	= (int)$pub_galaxy;
			$system 	= (int)$pub_system;
			$rows 		= (isset($pub_row) ? $pub_row : array());
			$data 		= array();
			$delete		= array();
			$update		= array();
			
			$check = $db->sql_query('SELECT row FROM '.TABLE_UNIVERSE.' WHERE galaxy = '.$galaxy.' AND system = '.$system.'');
			while($value = $db->sql_fetch_assoc($check))
				$update[$value['row']] = true;
            }
		     // Recupération des données
			for ($i = 1; $i < 16; $i++) {
				if (isset($rows[$i])) {
					$line = $rows[$i];
                    // Filtrage des data
					$line['player_name'] =  filter_var($line['player_name'], FILTER_SANITIZE_STRING);
					$line['planet_name'] =  filter_var($line['planet_name'], FILTER_SANITIZE_STRING);
					$line['ally_tag']    = filter_var($line['ally_tag'], FILTER_SANITIZE_STRING);
					
					if(isset($line['debris'])) filter_var($line['debris'], FILTER_SANITIZE_STRING);
					if(isset($line['status'])) filter_var($line['status'], FILTER_SANITIZE_STRING);

					$data[$i] = $line;
				}
				else {
					$delete[] = $i;
					$data[$i] = array(
							'planet_name' => '',
							'player_name' => '',
							'status' => '',
							'ally_tag' => '',
							'debris' =>  Array('metal' => 0, 'cristal' => 0),
							'moon' => 0,
							'activity' => ''
					);
				}
			}
		
			foreach ($data as $row => $v) {
				$statusTemp = (Check::player_status_forbidden($v['status']) ? "" : quote($v['status'])); //On supprime les status qui sont subjectifs
				if(!isset($update[$row]))
					$db->sql_query('INSERT INTO '.TABLE_UNIVERSE.' (galaxy, system, row, name, player, ally, status, last_update, last_update_user_id, moon)
						VALUES ('.$galaxy.', '.$system.', '.$row.', "'.quote($v['planet_name']).'", "'.quote($v['player_name']).'", "'.quote($v['ally_tag']).'", "'.$statusTemp.'", '.$time.', '.$user_data['user_id'].', "'.quote($v['moon']).'")');
				else {
					$db->sql_query(
						'UPDATE '.TABLE_UNIVERSE.' SET name = "'.quote($v['planet_name']).'", player = "'.quote($v['player_name']).'", ally = "'.quote($v['ally_tag']).'", status = "'.$statusTemp.'", moon = "'.$v['moon'].'", last_update = '.$time.', last_update_user_id = '.$user_data['user_id']
						.' WHERE galaxy = '.$galaxy.' AND system = '.$system.' AND row = '.$row
					);
				}
			}	
			
			if (!empty($delete)) {
				$toDelete = array();
				foreach ($delete as $n) {
					$toDelete[] = $galaxy.':'.$system.':'.$n;
				}
				
				$db->sql_query('UPDATE '.TABLE_PARSEDSPY.' SET active = "0" WHERE coordinates IN ("'.implode('", "', $toDelete).'")');
			}
			
			$db->sql_query('UPDATE '.TABLE_USER.' SET planet_added_ogs = planet_added_ogs + 15 WHERE user_id = '.$user_data['user_id']);
			
			$call->add('system', array(
					'data' => $data,
					'galaxy' => $galaxy,
					'system' => $system
			));
			
			$io->set(array(
					'type' => 'system',
					'galaxy' => $galaxy,
					'system' => $system
			));
			
			update_statistic('planetimport_ogs',15);
			add_log('system', array('coords' => $galaxy.':'.$system, 'toolbar' => $toolbar_info));
		}
	break;

	case 'ranking': //PAGE STATS
    if (isset($pub_type1, $pub_type2, $pub_offset, $pub_n, $pub_time) == false) die("Classement incomplet");
		
		if (!$user_data['grant']['ranking']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'ranking'
			));
			$io->status(0);
		} else {

            if ($pub_type1 != ('player' || 'ally')) die ("type 1 non défini");
            if ($pub_type2 != ('points' || 'fleet' || 'research' ||'economy')) die ("type 2 non défini");
            if (isset($pub_type3)){
                if(!empty($pub_type3)){
                    if(!($pub_type3 >= 4 && $pub_type3 <= 7 )) die ("type 3 non défini");
                }
            }
            //Vérification Offset
            if((($pub_offset-1) % 100) != 0) die("Erreur Offset");
			
			$type1		= $pub_type1;
			$type2 		= $pub_type2;
			$type3 		= $pub_type3;
			$time		= (int)$pub_time;
			$offset 	= (int)$pub_offset;
			$n 		= (array)$pub_n;
			$total		= 0;
			$count		= count($n);
			
			if ($type1 == 'player') {
				switch($type2) {
					case 'points':  	$table =TABLE_RANK_PLAYER_POINTS; //Type2 =0
											break;
					case 'economy':		$table = TABLE_RANK_PLAYER_ECO;//Type2 =1
											break;
					case 'research':	$table = TABLE_RANK_PLAYER_TECHNOLOGY;//Type2 =2
											break;
					case 'fleet':  		//Type2 =3
								   		switch($type3) {
								   			case '5': 	$table = TABLE_RANK_PLAYER_MILITARY_BUILT;break;
								   			case '6':	$table = TABLE_RANK_PLAYER_MILITARY_DESTRUCT;break;
								   			case '4':	$table = TABLE_RANK_PLAYER_MILITARY_LOOSE;break;
								   			case '7':   $table = TABLE_RANK_PLAYER_HONOR;break;
								   			default: $table = TABLE_RANK_PLAYER_MILITARY;break;
								   		}
							
											break;
					default:		 	$table = TABLE_RANK_PLAYER_POINTS;
											break;
				}
			} else {
				switch($type2) {
					case 'points': $table = TABLE_RANK_ALLY_POINTS;
										break;
					case 'economy': $table = TABLE_RANK_ALLY_ECO;
										break;
					case 'research':	$table = TABLE_RANK_ALLY_TECHNOLOGY;
										break;
					case 'fleet'://Type2 =3
								   		switch($type3) {
								   			case '5': 	$table = TABLE_RANK_ALLY_MILITARY_BUILT;break;
								   			case '6':	$table = TABLE_RANK_ALLY_MILITARY_DESTRUCT;break;
								   			case '4':	$table = TABLE_RANK_ALLY_MILITARY_LOOSE;break;
								   			case '7':   $table = TABLE_RANK_ALLY_HONOR;break;
								   			default: $table = TABLE_RANK_ALLY_MILITARY;break;
								   		}
										break;
					default:			$table = TABLE_RANK_ALLY_POINTS;
										break;
				}
			}
			
			$query = array();
			
			if ($type1 == 'player') {
				foreach ($n as $i => $val) {
					$data = $n[$i];

                    $data['player_name'] = filter_var($data['player_name'], FILTER_SANITIZE_STRING);
                    $data['ally_tag'] = filter_var($data['ally_tag'], FILTER_SANITIZE_STRING);
                    
					if(isset($data['points'])){
                        $data['points'] = filter_var($data['points'], FILTER_SANITIZE_NUMBER_INT);
                    }else die ("Erreur Pas de points pour le joueur !");
           
                    
                    
					if ($table == TABLE_RANK_PLAYER_MILITARY) { 
						$query[] = '('.$timestamp.', '.$i.', "'.quote($data['player_name']).'", "'.quote($data['ally_tag']).'", '.((int)$data['points']).', '.$user_data['user_id'].', '.((int)$data['nb_spacecraft']).')';
					} else {
						$query[] = '('.$timestamp.', '.$i.', "'.quote($data['player_name']).'", "'.quote($data['ally_tag']).'", '.((int)$data['points']).', '.$user_data['user_id'].')';
					}
					$total ++;
                    $datas[] = $data;
				}
				if (!empty($query))
					if ($table == TABLE_RANK_PLAYER_MILITARY) {
						$db->sql_query('REPLACE INTO '.$table.' (datadate, rank, player, ally, points, sender_id, nb_spacecraft) VALUES '.implode(',', $query));
					} else {
						$db->sql_query('REPLACE INTO '.$table.' (datadate, rank, player, ally, points, sender_id) VALUES '.implode(',', $query));
					}
			} else {
				$fields = 'datadate, rank, ally, points, sender_id, number_member';
				foreach ($n as $i => $val) {
					$data = $n[$i];
					$data['ally_tag'] = filter_var($data['ally_tag'], FILTER_SANITIZE_STRING);
                    
					if(isset($data['points'])){
                        $data['points'] = filter_var($data['points'], FILTER_SANITIZE_NUMBER_INT);
                    }else die ("Erreur Pas de points pour le joueur !");
                    
					$query[] = '('.$timestamp.', '.$i.', "'.$data['ally_tag'].'", '.((int)$data['points']).', '.$user_data['user_id'].','.((int)$data['members'][0]).')';
					$datas[] = $data;
					$total ++;
				}
				if (!empty($query)) {
					$db->sql_query('REPLACE INTO '.$table.' ('.$fields.') VALUES '.implode(',', $query));
				}
			}
			
			$db->sql_query('UPDATE '.TABLE_USER.' SET rank_added_ogs = rank_added_ogs + '.$total.' WHERE user_id = '.$user_data['user_id']);
			
			$type2 = (($type2 == 'fleet') ? $type2.$type3 : $type2);
			
			$call->add('ranking_'.$type1.'_'.$type2, array(
					'data' => $datas,
					'offset' => $offset,
					'time' => $time
			));
			
			$io->set(array(
					'type' => 'ranking',
					'type1' => $type1,
					'type2' => $type2,
					'offset' => $offset
			));
			
			update_statistic('rankimport_ogs',100);
			add_log('ranking', array('type1' => $type1, 'type2' => $type2, 'offset' => $offset, 'time' => $time, 'toolbar' => $toolbar_info));
		}
	break;

	case 'rc': //PAGE RC
    
        if (isset($pub_date, $pub_win, $pub_count, $pub_result, $pub_moon, $pub_n, $pub_rawdata) == false) die("hack");
		
		if(!isset($pub_rounds)) $pub_rounds = Array(1 => Array(
				'a_nb' => 0,
				'a_shoot' => 0,
				'd_bcl' => 0,
				'a_bcl' => 0,
				'd_nb' => 0,
				'd_shoot' => 0
			));
	
		if (!$user_data['grant']['messages']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'messages'
			));
			$io->status(0);
		} else {
			$call->add('rc', array(
					'date' => $pub_date,
					'win' => $pub_win,
					'count' => $pub_count,
					'result' => $pub_result,
					'moon' => $pub_moon,
					'moonprob' => $pub_moonprob,
					'rounds' => $pub_rounds,
					'n' => $pub_n,
					'rawdata' => $pub_rawdata
			));
			
			$id_rcround = Array();
			
						
			$exist = $db->sql_fetch_row($db->sql_query("SELECT id_rc FROM ".TABLE_PARSEDRC." WHERE dateRC = '".$pub_date."'"));
			if(!$exist[0]){
				$db->sql_query("INSERT INTO ".TABLE_PARSEDRC." (
						`dateRC`, `nb_rounds`, `victoire`, `pertes_A`, `pertes_D`, `gain_M`, `gain_C`, `gain_D`, `debris_M`, `debris_C`, `lune`
					) VALUES (
					 '{$pub_date}', '{$pub_count}', '{$pub_win}', '".$pub_result['a_lost']."', '".$pub_result['d_lost']."', '".$pub_result['win_metal']."', '".$pub_result['win_cristal']."', '".$pub_result['win_deut']."', '".$pub_result['deb_metal']."', '".$pub_result['deb_cristal']."', '{$pub_moon}'
					)"
				);
				$id_rc = $db->sql_insertid();
				
				foreach($pub_rounds as $i => $round){
					$db->sql_query("INSERT INTO ".TABLE_PARSEDRCROUND." (
							`id_rc`, `numround`, `attaque_tir`, `attaque_puissance`, `defense_bouclier`, `attaque_bouclier`, `defense_tir`, `defense_puissance`
						) VALUE (
							'{$id_rc}', '{$i}', '".$round['a_nb']."', '".$round['a_shoot']."', '".$round['d_bcl']."', '".$round['a_bcl']."', '".$round['d_nb']."', '".$round['d_shoot']."'
						)"
					);
					$id_rcround[$i] = $db->sql_insertid();
				}
				//Ne pas le faire si destruction attaquant ou dÃ©fenseur au 1er tour, ou match nul au 1er tour
				if ($pub_count>1) {
					$i++;
					$db->sql_query("INSERT INTO ".TABLE_PARSEDRCROUND." (
								`id_rc`, `numround`, `attaque_tir`, `attaque_puissance`, `defense_bouclier`, `attaque_bouclier`, `defense_tir`, `defense_puissance`
							) VALUE (
								'{$id_rc}', '{$i}', 0, 0, 0, 0, 0, 0
							)"
						);
						$id_rcround[$i] = $db->sql_insertid();
				}
				
				$j = 0;
				foreach ($pub_n as $i => $n){
					$j = floor($i / (count($pub_n) / count($id_rcround))) + 1;
					$fields = '';
					$values = '';
					
					if (array_key_exists('content',$n)){
						foreach ($n['content'] as $field => $value){
							$fields .= ", `{$field}`";
							$values .= ", '{$value}'";
						}
					}
					
					$db->sql_query("INSERT INTO ".(($n['type'] == "D") ? TABLE_ROUND_DEFENSE : TABLE_ROUND_ATTACK)." (
							`id_rcround`, `player`, `coordinates`, `Armes`, `Bouclier`, `Protection`".$fields."
						) VALUE (
							'".$id_rcround[$j]."', '".$n['player']."', '".$n['coords']."', '".$n['weapons']['arm']."', '".$n['weapons']['bcl']."', '".$n['weapons']['coq']."'".$values."
						)"
					);
					
					if($n['type'] == "D"){
						if(!isset($update))
							$update = $db->sql_query("UPDATE ".TABLE_PARSEDRC." SET coordinates = '".$n['coords']."' WHERE id_rc = '{$id_rc}'");
					}
				}
			}
			
			$io->set(array(
					'type' => 'rc',
			));
			
			add_log('rc');
		}
	break;

	case 'ally_list': //PAGE ALLIANCE

		if (isset($pub_tag, $pub_n) == false) die("hack");
        
		if (!$user_data['grant']['ranking']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'ranking'
			));
			$io->status(0);
		} else {
			if(!isset($tag)) break; //Pas d'alliance
            $tag = filter_var($data['$pub_tag'], FILTER_SANITIZE_STRING);
            
			
			$list = array();
			$n = (array)$pub_n;

			foreach ($n as $i => $val) {
				$data = $n[$i];
				
				if(isset($data['player'], $data['points'], $data['rank'],$data['coords']) == false) die("hack");
                
				$list[] = array(
						'pseudo' => filter_var($data['player'], FILTER_SANITIZE_STRING),
						'points' => $data['points'],
						'coords' => explode(':', $data['coords']),
						'rang' => $data['rank']
				);
			}
			
			$call->add('ally_list', array(
					'list' => $list,
					'tag' => $tag
			));
			
			$io->set(array(
					'type' => 'ally_list',
					'tag' => $tag
			));
			
			add_log('ally_list', array(
					'tag' => $tag,
                    'toolbar' => $toolbar_info
			));
		}
	break;

	case 'trader': //PAGE MARCHAND
		$call->add('trader', array());
		$io->set(array(
					'type' => 'trader'
			));
	break;
	/*
	case 'hostiles': // Hostiles
		$line = $pub_data;
		$line['attacker_name'] = filter_var($line['attacker_name'], FILTER_SANITIZE_STRING);
		$line['origin_attack_name'] = filter_var($line['origin_attack_name'], FILTER_SANITIZE_STRING);
		$line['destination_name'] = filter_var($line['destination_name'], FILTER_SANITIZE_STRING);
		$line['composition'] = filter_var($line['composition'], FILTER_SANITIZE_STRING);
		
		$hostile = array('id' => $line['id'],
						'id_vague' => $line['id_vague'],
						'player_id' => $line['player_id'],
						'ally_id' => $line['ally_id'],
						'arrival_time' => $line['arrival_time'],
						'destination_name' => $line['destination_name'],
						'id_vague' => $line['id_vague'],
						'attacker' => $line['attacker_name'],
						'origin_planet' => $line['origin_attack_name'],
						'origin_coords' => $line['origin_attack_coords'],
						'cible_planet' => $line['destination_name'],
						'cible_coords' => $line['destination_coords'],
						'composition_flotte' => $line['composition'],
						'clean' => $line['clean']
		);
		$call->add('hostiles', $hostile);	
		$io->set(array('function' => 'hostiles',
					   		'type' => 'hostiles'
		));
		add_log('info', array('toolbar' => $toolbar_info, 'message' => "envoie une flotte hostile de " . $line['attacker_name']));
	break;
		
	case 'checkhostiles': // Verification des flotttes Hostiles
		$user_attack="";
		$query = "SELECT DISTINCT(hos.user_id) AS user_id, user_name "
				."FROM " . TABLE_USER . " user, ".$table_prefix."hostiles hos "
				."WHERE user.user_id=hos.user_id";
		$result = $db->sql_query($query);
		$isAttack=0;

		while(list($user_id,$user_name)=$db->sql_fetch_row($result)){			
			$user_attack .= $user_name;
			$user_attack .= " ";
			$isAttack=1;
		}
		
		$io->set(array('type' => 'checkhostiles',
							'check' => $isAttack,
							'user' => $user_attack
		));
		add_log('info', array('toolbar' => $toolbar_info, 'message' => "vérifie les flottes hostiles de la communauté"));
	break;
	*/	
	case 'messages': //PAGE MESSAGES
        if (isset($pub_data) == false) die("hack");
		
		if (!$user_data['grant']['messages']) {
			$io->set(array(
					'type' => 'grant',
					'access' => 'messages'
			));
			$io->status(0);
		} else {
			$line = $pub_data;
			switch($line['type']){
				case 'msg': //MESSAGE PERSO
					if (isset($line['coords'], $line['from'], $line['subject'], $line['message']) == false) die("hack");
                    $line['coords'] = Check::coords($line['coords']);
                    $line['from'] = filter_var($line['from'], FILTER_SANITIZE_STRING);
					$line['message'] = filter_var($line['message'], FILTER_SANITIZE_STRING);
					$line['subject'] = filter_var($line['subject'], FILTER_SANITIZE_STRING);
                    
                    $msg = array(
							'coords' => explode(':', $line['coords']),
							'from' => $line['from'],
							'subject' => $line['subject'],
							'message' => $line['message'],
							'time' => $line['date']
					);
					$call->add('msg', $msg);
				break;
				
				case 'ally_msg': //MESSAGE ALLIANCE
									
                    if (isset($line['from'], $line['tag'], $line['message']) == false) die("hack");
                   
                    $line['from'] = filter_var($line['from'], FILTER_SANITIZE_STRING);
                    $line['tag'] = filter_var($line['tag'], FILTER_SANITIZE_STRING);
					$line['message'] = filter_var($line['message'], FILTER_SANITIZE_STRING);
                    
					$ally_msg = array(
							'from' => $line['from'],
							'tag' => $line['tag'],
							'message' => $line['message'],
							'time' => $line['date']
					);
					$call->add('ally_msg', $ally_msg);
				break;
				
				case 'spy': //RAPPORT ESPIONNAGE
                                    if (isset($line['coords'], $line['content'], $line['playerName'], $line['planetName'], $line['proba'], $line['activity']) == false) die("hack");
                                   
                                    $line['coords'] = Check::coords($line['coords']);

                                        $line['content'] = filter_var_array($line['content'], FILTER_SANITIZE_STRING);

					$line['playerName'] = filter_var($line['playerName'], FILTER_SANITIZE_STRING);
					$line['planetName'] = filter_var($line['planetName'], FILTER_SANITIZE_STRING);
					$line['proba'] = filter_var($line['proba'],FILTER_SANITIZE_NUMBER_INT);
					$line['activity'] = filter_var($line['activity'], FILTER_SANITIZE_STRING);
					
					$proba = (int)$line['proba'];
					$proba = $proba > 100 ? 100 : $proba;
					$activite = (int)$line['activity'];
					$activite = $activite > 59 ? 59 : $activite;
					$spy = array(
							'proba' => $proba,
							'activite' => $activite,
							'coords' => explode(':', $line['coords']),
							'content' => $line['content'],
							'time' => $line['date'],
							'player_name' => $line['playerName'],
							'planet_name' => $line['planetName']
					);
                                        $call->add('spy', $spy);
					
					$spyDB = array();
					foreach ($database as $arr) {
						foreach ($arr as $v) $spyDB[$v] = 1;
					}
					
					$coords = $spy['coords'][0].':'.$spy['coords'][1].':'.$spy['coords'][2];

					$moon = ($line['moon'] > 0 ? 1 : 0);
					$matches = array();
					$data = array();
					$values = $fields = '';
					
						$fields .= 'planet_name, coordinates, sender_id, proba, activite, dateRE';
						$values .= '"'.trim($spy['planet_name']).'", "'.$coords.'", '.$user_data['user_id'].', '.$spy['proba'].', '.$spy['activite'].', '.$spy['time'].' ';
					
					foreach($spy['content'] as $field => $value){
						$fields .= ', `'.$field.'`';
						$values .= ', '.$value;
					}
					
					$test = $db->sql_numrows($db->sql_query('SELECT id_spy FROM '.TABLE_PARSEDSPY.' WHERE coordinates = "'.$coords.'" AND dateRE = '.$spy['time']));
					if (!$test) {
						$db->sql_query('INSERT INTO '.TABLE_PARSEDSPY.' ( '.$fields.') VALUES ('.$values.')');					
						$query = $db->sql_query('SELECT last_update'.($moon ? '_moon' : '').' FROM '.TABLE_UNIVERSE.' WHERE galaxy = '.$spy['coords'][0].' AND system = '.$spy['coords'][1].' AND row = '.$spy['coords'][2]);
						if ($db->sql_numrows($query)) {
							$assoc = $db->sql_fetch_assoc($query);
							if ($assoc['last_update'.($moon ? '_moon' : '')] < $spy['time']) {
								if ($moon)
									$db->sql_query('UPDATE '.TABLE_UNIVERSE.' SET moon = "1", phalanx = '.($spy['content']['Pha'] > 0 ? $spy['content']['Pha'] : 0).', gate = "'.($spy['content']['PoSa'] > 0 ? 1 : 0).'", last_update_moon = '.$line['date'].', last_update_user_id = '.$user_data['user_id'].' WHERE galaxy = '.$spy['coords'][0].' AND system = '.$spy['coords'][1].' AND row = '.$spy['coords'][2]);
								else//we do nothing if buildings are not in the report
									$db->sql_query('UPDATE '.TABLE_UNIVERSE.' SET name = "'.$spy['planet_name'].'", last_update_user_id = '.$user_data['user_id'].' WHERE galaxy = '.$spy['coords'][0].' AND system = '.$spy['coords'][1].' AND row = '.$spy['coords'][2]);
							}
						}
						$db->sql_query('UPDATE '.TABLE_USER.' SET spy_added_ogs = spy_added_ogs + 1 WHERE user_id = '.$user_data['user_id']);
						update_statistic('spyimport_ogs', '1');
						add_log('messages', array( 'added_spy' => $spy['planet_name'],'added_spy_coords'  => $coords, 'toolbar' => $toolbar_info));
					}
				break;
				
				case 'ennemy_spy': //RAPPORT ESPIONNAGE ENNEMIS
					if (isset($line['from'], $line['to'], $line['proba'], $line['date']) == false) die("hack");
                                       
					$line['proba'] = filter_var($line['proba'], FILTER_SANITIZE_NUMBER_INT);
                    $line['from'] = Check::coords($line['from']);
                    $line['to'] = Check::coords($line['to']);                    
					
					$query = "SELECT spy_id FROM ".TABLE_PARSEDSPYEN." WHERE sender_id = '".$user_data['user_id']."' AND dateSpy = '{$line['date']}'";
					if($db->sql_numrows($db->sql_query($query)) == 0)
						$db->sql_query("INSERT INTO ".TABLE_PARSEDSPYEN." (`dateSpy`, `from`, `to`, `proba`, `sender_id`) VALUES ('".$line['date']."', '".$line['from']."', '".$line['to']."', '".$line['proba']."', '".$user_data['user_id']."')");
					
					$ennemy_spy = array(
							'from' => explode(':', $line['from']),
							'to' => explode(':', $line['to']),
							'proba' => (int)$line['proba'],
							'time' => $line['date']
					);
					$call->add('ennemy_spy', $ennemy_spy);
                                        add_log('info', array('toolbar' => $toolbar_info, 'message' => "a été espionné avec une probabilité de  " . $line['proba']));
				break;
				
				case 'rc_cdr': //RAPPORT RECYCLAGE
                    if (isset($line['nombre'], $line['coords'], $line['M_recovered'], $line['C_recovered'], $line['M_total'], $line['C_total'], $line['date']) == false) die("hack");
                                       
					$line['nombre'] = filter_var($line['nombre'], FILTER_SANITIZE_NUMBER_INT);
                    $line['coords'] = Check::coords($line['coords']);
                    $line['M_recovered'] = filter_var($line['M_recovered'], FILTER_SANITIZE_NUMBER_INT);
                    $line['C_recovered'] = filter_var($line['C_recovered'], FILTER_SANITIZE_NUMBER_INT);
                    $line['M_total'] = filter_var($line['M_total'], FILTER_SANITIZE_NUMBER_INT);
                    $line['C_total'] = filter_var($line['C_total'], FILTER_SANITIZE_NUMBER_INT);

					$query = "SELECT id_rec FROM ".TABLE_PARSEDREC." WHERE sender_id = '".$user_data['user_id']."' AND dateRec = '{$line['date']}'";
					if($db->sql_numrows($db->sql_query($query)) == 0)
						$db->sql_query("INSERT INTO ".TABLE_PARSEDREC." (`dateRec`, `coordinates`, `nbRec`, `M_total`, `C_total`, `M_recovered`, `C_recovered`, `sender_id`) VALUES ('".$line['date']."', '".$line['coords']."', '".$line['nombre']."', '".$line['M_total']."', '".$line['C_total']."', '".$line['M_recovered']."', '".$line['C_recovered']."', '".$user_data['user_id']."')");
					
					$rc_cdr = array(
							'nombre' => (int)$line['nombre'],
							'coords' => explode(':', $line['coords']),
							'M_reco' => (int)$line['M_recovered'],
							'C_reco' => (int)$line['C_recovered'],
							'M_total' => (int)$line['M_total'],
							'C_total' => (int)$line['C_total'],
							'time' => $line['date']
					);
					$call->add('rc_cdr', $rc_cdr);
				break;
				
				case 'expedition': //RAPPORT EXPEDITION
                    if (isset($line['coords'], $line['content']) == false) die("hack");
                                      
					$line['content'] = filter_var_array($line['content'], FILTER_SANITIZE_STRING);
                    $line['coords'] = Check::coords($line['coords'], 1); //On ajoute 1 car c'est une expédition
					
					$expedition = array(
							'time' => $line['date'],
							'coords' => explode(':', $line['coords']),
							'content' => $line['content']
					);
					$call->add('expedition', $expedition);
				break;
				
				case 'trade': // LIVRAISONS AMIES
                     if (isset($line['date'], $line['trader'], $line['trader_planet'], $line['trader_planet_coords'], $line['planet'], $line['planet_coords'], $line['metal'], $line['cristal'], $line['deuterium']) == false) die("hack");
                     
					$line['trader'] = filter_var($line['trader'], FILTER_SANITIZE_STRING);
					$line['planet'] = filter_var($line['planet'], FILTER_SANITIZE_STRING);
					
					$trade = array(
							'time' => $line['date'],
							'trader' => $line['trader'],
							'trader_planet' => $line['trader_planet'],
							'trader_planet_coords' => $line['trader_planet_coords'],
							'planet' => $line['planet'],
							'planet_coords' => $line['planet_coords'],
							'metal' => $line['metal'],
							'cristal' => $line['cristal'],
							'deuterium' => $line['deuterium']
					);
					$call->add('trade', $trade);
					add_log('info', array('toolbar' => $toolbar_info, 'message' => "envoie une livraison amie provenant de " . $line['trader']));
				break;
				
				case 'trade_me': // MES LIVRAISONS

                    if (isset($line['date'], $line['planet_dest'], $line['planet_dest_coords'], $line['trader'], $line['metal'], $line['cristal'], $line['deuterium']) == false) die("hack");
					$line['trader'] = filter_var($line['trader'], FILTER_SANITIZE_STRING);
					$line['planet'] = filter_var($line['planet'], FILTER_SANITIZE_STRING);
					
					$trade_me = array(
							'time' => $line['date'],
							'planet_dest' => $line['planet_dest'],
							'planet_dest_coords' => $line['planet_dest_coords'],
							'planet' => $line['planet'],
							'planet_coords' => $line['planet_coords'],
							'trader' => $line['trader'],
							'metal' => $line['metal'],
							'cristal' => $line['cristal'],
							'deuterium' => $line['deuterium']
					);
					$call->add('trade_me', $trade_me);
					add_log('info', array('toolbar' => $toolbar_info, 'message' => "envoie une de ses livraison effectuée pour " . $line['trader']));
				break;
			}
			
			$io->set(array(
					'type' => (isset($pub_returnAs) && $pub_returnAs == 'spy' ? 'spy' : 'messages')
			));
		}
		
	break;
	
	case 'android': // Récupération des données pour android		  
		Check::data(isset($pub_action));
				
		switch($pub_action){
			case 'attacks':
				/*******************************************************
				 ***  Récuperation des données venant du mod Hostiles ***
				 ********************************************************/	
				//On vérifie que le mod Hostile est activé
				$queryModHostile = "SELECT `active` FROM `".TABLE_MOD."` WHERE `action`='hostiles' AND `active`='1' LIMIT 1";
				$hostile=array();
				if ($db->sql_numrows($db->sql_query($queryModHostile)) > 0) {
					$isAttack=0;$user_attack="";
					
					$queryHostile = "SELECT hos.user_id AS user_id, user.user_name, hos.id_attack ".
							 "FROM " . TABLE_USER . " user ".
							 "INNER JOIN " . $table_prefix . "hostiles hos ON user.user_id = hos.user_id";
					
					$resultHostile = $db->sql_query($queryHostile);
					$nb_attaques = $db->sql_numrows($resultHostile);
					
					$i=1;
					$datas = array();
					//while(list($user_id,$user_name,$id_attack)=$db->sql_fetch_row($resultHostile)){
					if($nb_attaques > 0) {
						$queryHostileAttack = 	"SELECT hosattks.*, usr.user_stat_name, hos.arrival_time ".
												"FROM " . $table_prefix . "hostiles hos ".
												"INNER JOIN " . TABLE_USER . " usr ON usr.user_id = hos.user_id " .
												"INNER JOIN " . $table_prefix . "hostiles_attacks hosattks ON hosattks.id_attack = hos.id_attack ";
						$resultHostileUser = $db->sql_query($queryHostileAttack);
						
						//$io->set(array('queryHostileAttack' => $queryHostileAttack));
						$compo = array();
						while(list($id, $id_vague, $attacker, $origin_planet, $origin_coords, $cible_planet, $cible_coords, $user_stat_name, $arrival_time)=$db->sql_fetch_row($resultHostileUser)){					
							$queryCompo = 	"SELECT type_ship, nb_ship ".
											"FROM " . $table_prefix . "hostiles_composition " .
											"WHERE id_attack = '" . $id . "' AND id_vague = " . $id_vague . "";
							$resultCompo = $db->sql_query($queryCompo);
												
							while(list($sheep, $nb)=$db->sql_fetch_row($resultCompo)){
								$compo[] = array($sheep,$nb);
							}
							$datas[] = array($id, $user_stat_name, $id_vague, $attacker, $origin_planet, $origin_coords, $cible_planet, $cible_coords, $arrival_time, 'compo' => $compo);
							$compo = array();
						}
						$isAttack=1;
						$i++;
					}
					$io->set(array('hostile' => array('isAttack' => $isAttack, 'attaks' => $datas)));
					//$hostile = array('isAttack' => $isAttack, 'attaks' => $datas);
				}
				
			break;

			case 'ally': // Détails alliance
				/*******************************************************
				 ***  Récuperation des données d'alliance ***
				********************************************************/
				$alliance=array();
				$queryAllianceName = "SELECT DISTINCT(ally) FROM " . TABLE_UNIVERSE . " WHERE (player = '" . $user_data['user_stat_name'] ."' OR player = '" . $user_data['user_name'] ."') ORDER BY last_update DESC LIMIT 1";
				
				$resultAllianceName = $db->sql_query($queryAllianceName);
				
				while($name = $db->sql_fetch_row($resultAllianceName)){
					$alliance[]=array($name[0]);
					break;
				}		
				$io->set(array('alliance' => $alliance));		
			
			break;
	
			case 'spys':
				/*******************************************************
				***  Récuperation des données des espionnages ***
				********************************************************/
						
				//Gestion des dates
				$date = date("j");
				$mois = date("m");
				$annee = date("Y");
				
				//Si les dates d'affichage ne sont pas définies, on affiche par défaut les attaques du jours
				$pub_date_from = mktime(0, 0, 0, $mois, "1", $annee);
				$pub_date_to = mktime(23, 59, 59, $mois, $date, $annee);
				
				$pub_date_from = intval($pub_date_from);
				$pub_date_to = intval($pub_date_to);
				
				$querySpyPlayer =	"SELECT joueur, alliance, count(*) AS nb " . 
									"FROM " . $table_prefix . "QuiMeSonde " . 
									"WHERE sender_id = '" . $user_data['user_id'] . "' AND (datadate BETWEEN " . $pub_date_from . " AND " . $pub_date_to . ") " . 
									"GROUP BY joueur " . 
									"ORDER BY nb DESC " .
									"LIMIT 5";
						
				$spysPlayer = array();
				
				$result = $db->sql_query($querySpyPlayer);
				
				while($players = $db->sql_fetch_row($result)){
					$spysPlayer[] = array($players[0], $players[1], $players[2]);
				}
				$io->set(array('mostCuriousPlayer' => $spysPlayer));
						
				$querySpyAlliance = "SELECT alliance, count(*) AS nb " .
								 	"FROM " . $table_prefix . "QuiMeSonde " . 
								 	"WHERE sender_id = '" . $user_data['user_id'] . "' AND (datadate BETWEEN " . $pub_date_from . " AND " . $pub_date_to . ") " . 
								 	"GROUP BY alliance " . 
									"ORDER BY nb DESC " .
									"LIMIT 5";
				$result = $db->sql_query($querySpyAlliance);
				
				$spysAlliance = array();		
				while($alliances = $db->sql_fetch_row($result)){
					$spysAlliance[] = array($alliances[0], $alliances[1]);
				}
				$io->set(array('mostCuriousAlliance' => $spysAlliance));
				
			break;
			
			case 'rentas':
				/*******************************************************
				 ***  Récuperation des données des espionnages ***
				 ********************************************************/
				
				//Gestion des dates
				$date = date("j");
				$mois = date("m");
				$annee = date("Y");
				
				//Si les dates d'affichage ne sont pas définies, on affiche par défaut les attaques du jours
				if($pub_interval=='day'){
					$pub_date_from = mktime(0, 0, 0, $mois, $date, $annee);
					$pub_date_to = mktime(23, 59, 59, $mois, $date, $annee);
				} else if($pub_interval=='yesterday'){					
					$yesterday = $date-1;
					if($yesterday < 1) $yesterday = 1;
					
					$pub_date_from = mktime(0, 0, 0, $mois, $yesterday, $annee);
					$pub_date_to = mktime(23, 59, 59, $mois, $yesterday, $annee);
				} else if ($pub_interval=='week') {
					$septjours = $date-7;
					if($septjours < 1) $septjours = 1;
					
					$pub_date_from = mktime(0, 0, 0, $mois, $septjours, $annee);
					$pub_date_to = mktime(23, 59, 59, $mois, $date, $annee);
				} else {
					$pub_date_from = mktime(0, 0, 0, $mois, "1", $annee);
					$pub_date_to = mktime(23, 59, 59, $mois, $date, $annee);
				}
				
				$pub_date_from = intval($pub_date_from);
				$pub_date_to = intval($pub_date_to);
				
				
				$requete_renta_asgards = "SELECT usr.user_stat_name AS user, SUM(attack_metal) AS metal, SUM(attack_cristal) AS cristal, SUM(attack_deut) AS deuterium, SUM(attack_pertes) AS pertes, ((SUM(attack_metal) + SUM(attack_cristal) + SUM(attack_deut)) - SUM(attack_pertes)) AS gains ".
							"FROM ogspy_asgard_attaques_attaques attks ".
							"INNER JOIN ogspy_asgard_user_group usrgrp ON usrgrp.user_id = attks.attack_user_id ".
							"INNER JOIN ogspy_asgard_group grp ON grp.group_id = usrgrp.group_id ".
							"INNER JOIN ogspy_asgard_user usr ON usr.user_id = usrgrp.user_id ".
							"WHERE grp.group_name = 'Asgards' AND usr.user_stat_name != '' AND (attack_metal + attack_cristal + attack_deut) > 0 AND (attks.attack_date BETWEEN ".$pub_date_from." AND ".$pub_date_to.") ".
							"GROUP BY attks.attack_user_id ".
							"ORDER BY gains DESC";
				
				$rentasPlayers = array();
				
				$result_renta_asgards = $db->sql_query($requete_renta_asgards);
				
				while ($row = $db->sql_fetch_assoc($result_renta_asgards)) {
					$rentasPlayers[] = array($row['user'],$row['metal'],$row['cristal'],$row['deuterium'],$row['pertes'],$row['gains']);
				}
				$io->set(array('rentasPlayers' => $rentasPlayers));
				
			break;
			
			case 'server':
				
				/***********************************
				 ***  Construction de la réponse ***
				***********************************/
				//$io->set(array('server' => $server_config['servername'], 'type' => 'android', 'hostile' => $hostile, 'alliance' => $alliance, 'spys' => $spys));
				$io->set(array('server' => $server_config['servername']));
						
			break;
		}
		
		//add_log('info', array('toolbar' => $toolbar_info, 'message' => "vérifie les flottes hostiles de la communauté"));			}
	break;
		
	default:
		die('hack '.$pub_type);
}

$call->apply();

$io->set('execution', str_replace(',', '.', round((get_microtime() - $start_time)*1000, 2)));
$io->send();
$db->sql_close();

?>
