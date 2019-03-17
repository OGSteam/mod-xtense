<?php
/**
 * @package Xtense 2
 * @author DarkNoon
 * @licence GNU
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");



function xtense_check_user_rights() {

    global $db, $server_config, $user_data, $io;
    // Verification des droits de l'user
    $query = $db->sql_query("SELECT system, ranking, empire, messages FROM " . TABLE_USER_GROUP . " u LEFT JOIN " . TABLE_GROUP . " g ON g.group_id = u.group_id LEFT JOIN " . TABLE_XTENSE_GROUPS . " x ON x.group_id = g.group_id WHERE u.user_id = '" . $user_data['user_id'] . "'");
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


}

function xtense_check_before_auth($toolbar_version, $mod_min_version, $active, $univers)
{

    global $server_config, $io;

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

    if ($server_config['xtense_allow_connections'] == 0) {
        $io->set(array(
            'type' => 'plugin connections',
        ));
        $io->send(0, true);
    }

    if (strtolower($server_config['xtense_universe']) != strtolower($univers)) {
        $io->set(array(
            'type' => 'plugin univers',
        ));
        $io->send(0, true);
    }

 }

/**
 * @param $user
 * @param $password
 * @throws Exception
 */
function xtense_check_auth ($user, $password){

    global $db, $io;

    $query = $db->sql_query('SELECT user_id, user_name, user_password, user_active, user_stat_name FROM ' . TABLE_USER . ' WHERE user_name = "' . quote($user) . '"');
    if (!$db->sql_numrows($query)) {
        $io->set(array(
            'type' => 'username'
        ));
        $io->send(0, true);
    } else {
        $user_data = $db->sql_fetch_assoc($query);

        $user_token = get_user_profile_token($user_data['user_id']); //New password format

        if($user_data['user_password'] == '' ) {

            if ( $password != $user_token) {
                $io->set(array(
                    'type' => 'token'
                ));
                $io->send(0, true);
            }

        }elseif ($password != $user_data['user_password']) {
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


}