<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

/**
 * Affiche une icône HTML correspondant au nom donné.
 *
 * Cette fonction génère une balise `<img>` pour afficher une icône
 * en fonction du nom fourni. L'icône est située dans le dossier `mod/xtense/img/icons/`.
 *
 * @param string $name Nom de l'icône (sans extension).
 * @return void
 */
function icon($name): void
{
    echo "<img src='mod/xtense/img/icons/$name.png' class='icon' style='vertical-align: middle' alt='$name' >";
}

/**
 * Ajoute un message de log basé sur le type et les données fournies.
 *
 * Cette fonction génère un message de log décrivant une action effectuée
 * par l'utilisateur, comme l'envoi de données sur une planète, un système solaire,
 * ou des statistiques. Le message est ensuite enregistré dans le système de logs.
 *
 * @param string $type Type de l'action (ex. 'buildings', 'overview', 'ranking').
 * @param array|null $data Données associées à l'action (ex. coordonnées, nom de planète).
 * @return void
 */
function add_log($type, $data) {
    global $log, $xtense_user_data;
    $message = '';
    if (!isset($data['toolbar'])) {
        $data['toolbar'] = "";
    }
    if ($type == 'buildings' || $type == 'overview' || $type == 'defense' || $type == 'research' || $type == 'fleet' || $type == 'info') {
        if ($type == 'buildings') $message = 'envoie les batiments de sa planète ' . $data['planet_name'] . ' (' . $data['coords'] . ')';
        if ($type == 'overview') $message = 'envoie les informations de sa planète ' . $data['planet_name'] . ' (' . $data['coords'] . ')';
        if ($type == 'defense') $message = 'envoie les defenses de sa planète ' . $data['planet_name'] . ' (' . $data['coords'] . ')';
        if ($type == 'research') $message = 'envoie ses recherches';
        if ($type == 'fleet') $message = 'envoie la flotte de sa planète ' . $data['planet_name'] . ' (' . $data['coords'] . ')';
        if ($type == 'info') $message = $data['message'];
    }

    if ($type == 'system') {
        $message = 'envoie le système solaire ' . $data['coords'];
    }

    if ($type == 'ranking') {
        $message = 'envoie le classement ' . $data['type2'] . ' des ' . $data['type1'] . ' (' . $data['offset'] . '-' . ($data['offset'] + 99) . ') : ' . date('H', $data['time']) . 'h';
    }

    if ($type == 'ally_list') {
        $message = 'envoie la liste des membres de l\'alliance ' . $data['tag'];
    }

    if ($type == 'rc') {
        $message = 'envoie un rapport de combat';
    }

    if ($type == 'messages') {
        $message = 'envoie sa page de messages';

        $extra = array();
        if (array_key_exists('msg', $data)) $extra[] = 'messages : ' . $data['msg'];
        if (array_key_exists('ally_msg', $data)) $extra[] = $data['ally_msg'] . ' messages d\'alliance';
        if (array_key_exists('ennemy_spy', $data)) $extra[] = $data['ennemy_spy'] . ' espionnages ennemis';
        if (array_key_exists('rc_cdr', $data)) $extra[] = $data['rc_cdr'] . ' rapports de recyclages';
        if (array_key_exists('expedition', $data)) $extra[] = $data['expedition'] . ' rapports d\'expedition';
        if (array_key_exists('added_spy', $data)) $extra[] = ' Rapport d\'espionnage ajouté : ' . $data['added_spy_coords'];
        if (array_key_exists('ignored_spy', $data)) $extra[] = $data['ignored_spy'] . ' rapports d\'espionnage ignorés';

        if (!empty($extra)) $message .= ' (' . implode(', ', $extra) . ')';
    }
    if (!empty($message)) {

        $log->info("[Xtense] $message", [
            'username' => $xtense_user_data['name'] // Remplacement par une variable valide
        ]);
        $log->debug("[Xtense] Data Details $type", [
            'type' => $type,
            'data' => $data
        ]);

    }
}


/**
 * Met à jour une statistique dans la base de données.
 *
 * Cette fonction insère ou met à jour une statistique dans la table des statistiques.
 * Si la statistique existe déjà, sa valeur est incrémentée.
 *
 * @param string $stats Nom de la statistique.
 * @param int $value Valeur à ajouter à la statistique.
 * @return void
 */
function update_statistic($stats, $value){
    global $db;
    $query = "INSERT INTO " . TABLE_STATISTIC . " (statistic_name, statistic_value)
          VALUES ('" . $db->sql_escape_string($stats) . "', $value)
          ON DUPLICATE KEY UPDATE statistic_value = statistic_value + $value";
    $db->sql_query($query);
}

/**
 * Met à jour les boosters en fonction des données fournies.
 *
 * Cette fonction met à jour les boosters actifs en fonction des données fournies.
 * Elle vérifie si les boosters sont valides et met à jour leur date d'expiration.
 *
 * @param array $boosterdata Données des boosters (UUID et date).
 * @param int $current_time Temps actuel en secondes.
 * @return array|null Tableau des boosters mis à jour ou `null` en cas d'erreur.
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
