<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");


/**
 * Verification de l'empire (Mise à jour, rajout, empire plein)
 *
 * @param int $type
 * @param string $coords
 * @return mixed(bool/int)
 */

function home_check($type, $coords) {
	global $db, $user_data;

    $empty_planets = range(101, 199);
    $empty_moons = range(201, 299);
    $planets = $moons = [];
	$offset = ($type == TYPE_PLANET ? 100 : 200);

	$query = $db->sql_query("SELECT `planet_id`, `coordinates` FROM ".TABLE_USER_BUILDING." WHERE `user_id` = ".$user_data['user_id']." ORDER BY `planet_id`");
	while ($data = $db->sql_fetch_assoc($query)) {
        $id = $data['planet_id'];
        $coords = $data['coordinates'];
        if ($id < 200) {
            $planets[$id] = $coords;
            unset($empty_planets[$id], $empty_moons[$id + 100]);
        } else {
            $moons[$id] = $coords;
            unset($empty_moons[$id], $empty_planets[$id - 100]);
        }
	}
	foreach ($planets as $id => $p) {
		if ($p == $coords || $coords == "unknown") {
			// Si c'est une lune on check si une lune existe déjà
			if ($type == TYPE_MOON) {
				if (isset($moons[$id+100])) return array('update', 'id' => $id+100);
				else return array('add', 'id' => $id+100);
			}

			return array('update', 'id' => $id);
		}
	}

	// Si une lune correspond a la planete, on place la planete sous la lune
	foreach ($moons as $id => $m) {
		if ($m == $coords) {
			return array($type == TYPE_PLANET ? 'add' : 'update', 'id' => $id-200+$offset);
		}
	}

	if ($type == TYPE_PLANET) {
		if (count($empty_planets) == 0) return array('full');
		foreach ($empty_planets as $p) return array('add', 'id' => $p+$offset);
	}
	else {
		if (count($empty_moons) == 0) return array('full');
		foreach ($empty_moons as $p) return array('add', 'id' => $p+$offset);
	}
}


/**
 * @param      $type
 * @param null $data
 */
function add_log($type, $data) {
	global $server_config, $user_data, $root;
	$message = '';
	if(!isset($data['toolbar'])) {$data['toolbar'] = "";}
	if ($type == 'buildings' || $type == 'overview' || $type == 'defense' || $type == 'research' || $type == 'fleet'||$type == 'info') {
		if ($type == 'buildings') 	$message = 'envoie les batiments de sa planète '.$data['planet_name'].' ('.$data['coords'].')';
		if ($type == 'overview') 	$message = 'envoie les informations de sa planète '.$data['planet_name'].' ('.$data['coords'].')';
		if ($type == 'defense') 	$message = 'envoie les defenses de sa planète '.$data['planet_name'].' ('.$data['coords'].')';
		if ($type == 'research') 	$message = 'envoie ses recherches';
		if ($type == 'fleet') 		$message = 'envoie la flotte de sa planète '.$data['planet_name'].' ('.$data['coords'].')';
		if ($type == 'info')		$message = $data['message'];
	}

	if ($type == 'system') {
		$message = 'envoie le système solaire '.$data['coords'];
	}

	if ($type == 'ranking') {
		$message = 'envoie le classement '.$data['type2'].' des '.$data['type1'].' ('.$data['offset'].'-'.($data['offset']+99).') : '.date('H', $data['time']).'h';
	}

	if ($type == 'ally_list') {
		$message = 'envoie la liste des membres de l\'alliance '.$data['tag'];
	}

	if ($type == 'rc') {
		$message = 'envoie un rapport de combat';
	}

	if ($type == 'messages') {
		$message = 'envoie sa page de messages';

		$extra = array();
		if (array_key_exists('msg', $data)) $extra[] = 'messages : '.$data['msg'];
		if (array_key_exists('ally_msg', $data)) $extra[] = $data['ally_msg'].' messages d\'alliance';
		if (array_key_exists('ennemy_spy', $data)) $extra[] = $data['ennemy_spy'].' espionnages ennemis';
		if (array_key_exists('rc_cdr', $data)) $extra[] = $data['rc_cdr'].' rapports de recyclages';
		if (array_key_exists('expedition', $data)) $extra[] = $data['expedition'].' rapports d\'expedition';
		if (array_key_exists('added_spy', $data)) $extra[] = ' Rapport d\'espionnage ajouté : '.$data['added_spy_coords'];
		if (array_key_exists('ignored_spy', $data)) $extra[] = $data['ignored_spy'].' rapports d\'espionnage ignorés';

		if (!empty($extra)) $message .= ' ('.implode(', ', $extra).')';
	}
	if (!empty($message)) {
		$dir = date('ymd');

        $file = 'log_'.date('ymd').'.log';
        if (!file_exists('journal/'.$dir)) @mkdir('journal/'.$dir);
        if (file_exists('journal/'.$dir)) {
            @chmod('journal/'.$dir, 0777);
            $fp = @fopen('journal/'.$dir.'/'.$file, 'a+');
            if ($fp) {
                fwrite($fp, '/*'.date('d/m/Y H:i:s').'*/'.'[Xtense]'.'['.$data['toolbar'].'] '.$user_data['user_name'].' '.$message."\n");
                fclose($fp);
                @chmod('journal/'.$dir.'/'.$file, 0777);
            }
        }

    }
}


/**
 * @param $stats
 * @param $value
 */
function update_statistic($stats, $value){
    global $db;
    $query = sprintf(
        "INSERT INTO %s (statistic_name, statistic_value) VALUES ('%s', %d)
        ON DUPLICATE KEY UPDATE statistic_value = statistic_value + %d",
        TABLE_STATISTIC,
        $db->sql_escape_string($stats),
        $value,
        $value
    );

    $db->sql_query($query);
}

/**
 * @param $boosterdata
 * @param $current_time
 * @return null|\tableau
 */
function update_boosters($boosterdata, $current_time ){

	$boosters = booster_decode();

	foreach($boosterdata as $booster) {
		if(!booster_is_uuid($booster[0])) {
			log_("mod","Booster Inconnu");
		} else {
			if(!isset($booster[1]))
				$boosters = booster_uuid($boosters, $booster[0]);
			else
				$boosters = booster_uuid($boosters, $booster[0], booster_lire_date($booster[1]) + $current_time);

		}
	}/*$booster_table = array('booster_m_val', 'booster_m_date', 'booster_c_val', 'booster_c_date', 'booster_d_val', 'booster_d_date', 'extention_p', 'extention_m');*/
	return $boosters;
}
