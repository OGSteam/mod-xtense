<?php
/**
 * @package Xtense 2
 * @author DarkNoon
 * @licence GNU
 */

use Ogsteam\Ogspy\Model\Tokens_Model;
use Ogsteam\Ogspy\Model\Config_Model;

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

/**
 * Vérifie les versions et la configuration avant l'authentification.
 *
 * Cette fonction vérifie si les versions de la barre d'outils et du plugin sont compatibles,
 * si le plugin est actif, si le serveur est actif, et si l'univers fourni correspond à celui
 * configuré. Elle met à jour la configuration de l'univers si nécessaire.
 *
 * @param string $toolbar_version Version de la barre d'outils.
 * @param string $mod_min_version Version minimale requise du plugin.
 * @param int $active Indique si le plugin est actif (1 pour actif, 0 pour inactif).
 * @param string $univers URL de l'univers fourni.
 * @return void Envoie une réponse via `$io` en cas d'erreur ou de non-conformité.
 */
function xtense_check_before_auth($toolbar_version, $mod_min_version, $active, $univers)
{
    global $server_config, $io, $db;

    if (version_compare($toolbar_version, TOOLBAR_MIN_VERSION, '<')) {
        $io->set(array(
            'type' => 'wrong version',
            'target' => 'toolbar',
            'version' => TOOLBAR_MIN_VERSION
        ));
        $io->send(0, true);
    }

    if (version_compare($mod_min_version, PLUGIN_VERSION, '>')) {
        $io->set(array(
            'type' => 'wrong version',
            'target' => 'plugin',
            'version' => PLUGIN_VERSION
        ));
        $io->send(0, true);
    }

    if ($active != 1) {
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

    // Vérifier et mettre à jour xtense_universe s'il a sa valeur par défaut (vide)
    // et si l'univers fourni est valide.
    if ($server_config['xtense_universe'] == 'https://sxx-fr.ogame.gameforge.com') {
        // Mettre à jour la configuration dans la base de données
        $config_model = new Config_Model();
        $config_model->update_one($univers, 'xtense_universe');

        // Mettre à jour la configuration en mémoire pour la requête actuelle
        $server_config['xtense_universe'] = $univers;

        generate_config_cache();
    }

    if (strtolower($server_config['xtense_universe']) != strtolower($univers)) {
        $io->set(array(
            'type' => 'plugin univers',
        ));
        $io->send(0, true);
    }

 }

/**
 * Vérifie l'authentification d'un utilisateur via un jeton.
 *
 * Cette fonction utilise un jeton pour identifier un utilisateur et vérifier son statut.
 * Si l'utilisateur est inactif ou si le jeton est invalide, une réponse est envoyée via `$io`.
 *
 * @param string $token Jeton d'authentification.
 * @return array|bool Retourne les données de l'utilisateur si le jeton est valide, sinon `false`.
 * @throws Exception En cas d'erreur lors de la vérification du jeton.
 */
function xtense_check_auth ($token){

    global $db, $io;
    $Tokens_Model = new Tokens_Model();
    $token_user_id = $Tokens_Model->get_userid_from_token($token, "PAT");

    if($token_user_id !== false) {

        $query = $db->sql_query("SELECT `id`, `name`, `active`, `player_id` FROM " . TABLE_USER . " WHERE `id` = {$token_user_id}");

        $user_data = $db->sql_fetch_assoc($query);

        if ( $user_data !== false) {

            if ($user_data['active'] == 0) {
                $io->set(array(
                    'type' => 'user active'
                ));
                $io->send(0, true);
            }

            $user_data['grant'] = array('system' => 0, 'ranking' => 0, 'empire' => 0);
            return $user_data;
        }

    } else {
            $io->set(array(
                'type' => 'token'
            ));
            $io->send(0, true);

    }
    return false;
}
/**
 * Vérifie les droits d'un utilisateur et retourne ses permissions.
 *
 * Cette fonction récupère les droits d'accès d'un utilisateur en fonction de son groupe
 * et les retourne sous forme de tableau. Elle peut également envoyer les informations
 * du serveur et les droits de l'utilisateur si une vérification publique du serveur est demandée.
 *
 * @param array $user_data Tableau associatif contenant les informations de l'utilisateur,
 *                         incluant son identifiant (`id`).
 * @return array Tableau associatif contenant les informations de l'utilisateur mises à jour
 *               avec ses droits d'accès (`grant`).
 */
function xtense_check_user_rights($user_data) {

    global $db, $server_config, $io;

    // Verification des droits de l'user
    $query = $db->sql_query("
    SELECT `system`, `ranking`, `empire`, `messages`
    FROM " . TABLE_USER_GROUP . " u
    LEFT JOIN " . TABLE_GROUP . " g ON g.`id` = u.`group_id`
    LEFT JOIN " . TABLE_XTENSE_GROUPS . " x ON x.`group_id` = g.`id`
    WHERE u.`user_id` = '" . $user_data['id'] . "'
");    $user_data['grant'] = $db->sql_fetch_assoc($query);

    // Si Xtense demande la verification du serveur, renvoi des droits de l'utilisateur
    if (isset($pub_server_check)) {
        $io->set(array(
            'version' => $server_config['version'],
            'servername' => $server_config['servername'],
            'grant' => $user_data['grant']
        ));
        $io->send(1, true);
    }

    return $user_data;

}
