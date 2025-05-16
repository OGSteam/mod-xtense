<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

/**
 * @param $name
 */
function icon($name): void
{
    echo "<img src='mod/xtense/img/icons/$name.png' class='icon' style='vertical-align: middle' alt='$name' >";
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
                fwrite($fp, '/*'.date('d/m/Y H:i:s').'*/'.'[Xtense]'.'['.$data['toolbar'].'] '.$user_data['name'].' '.$message."\n");
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
