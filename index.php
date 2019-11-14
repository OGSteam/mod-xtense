<?php
/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

list($version, $root) = $db->sql_fetch_row($db->sql_query("SELECT version, root FROM " . TABLE_MOD . " WHERE action = 'xtense'"));

//Language File
if (!isset($ui_lang)) { // Checks the ui_lang value from parameters file
    if (isset($pub_lang)) {
        $ui_lang = $pub_lang; //This value is used during installation
    } else
        $ui_lang = "fr";
    //If no language is available in id.php file we take fr by default
}

require_once("mod/{$root}/includes/config.php");
require_once("mod/{$root}/includes/functions.php");
require_once("mod/{$root}/includes/Check.php");
require_once("mod/{$root}/lang/" . $ui_lang . "/lang_xtense.php");

$page = 'infos';
if (isset($pub_page)) {
    // Pages publiques
    if ($pub_page == 'about') $page = $pub_page;

    // Pages admin
    if ($user_data['user_admin'] == 1 || ($user_data['user_coadmin'] == 1 && $server_config['xtense_strict_admin'] == 0)) {
        if ($pub_page == 'config' || $pub_page == 'group' || $pub_page == 'mods' || $pub_page == 'infos') $page = $pub_page;
    }
}

if ($page == 'infos') {

    if (isset($pub_action_xtense) && $pub_action_xtense = 'renew_token') {

        user_profile_token_updater($user_data['user_id']);
        unset($pub_action_xtense);
        }
    $my_user_token = get_user_profile_token($user_data['user_id']);

}

if ($page == 'config') {
    $checkboxes = array('allow_connections', 'strict_admin', 'log_reverse', 'plugin_root', 'log_empire', 'log_system', 'log_spy', 'log_ranking', 'log_ally_list', 'log_messages', 'spy_autodelete');

    if (isset($pub_universe)) {
        $universe = Check::universe($pub_universe);
        if ($universe === false) $universe = 'https://sxx-yy.ogame.gameforge.com';

        $replace = '';
        foreach ($checkboxes as $name) {
            $server_config['xtense_' . $name] = (isset($_POST[$name]) ? 1 : 0);
            $replace .= ' ,("xtense_' . $name . '", "' . $server_config['xtense_' . $name] . '")';
        }

        $db->sql_query('REPLACE INTO ' . TABLE_CONFIG . ' (config_name, config_value) VALUES ("xtense_universe", "' . $universe . '")' . $replace);
        generate_config_cache();
        $server_config['xtense_universe'] = $universe;

        $update = true;
    }

    if (isset($pub_do)) {

        if ($pub_do == 'repair') {
            $db->sql_query('DELETE FROM ' . TABLE_USER_BUILDING . ' WHERE planet_id < 1');
            $db->sql_query('DELETE FROM ' . TABLE_USER_DEFENCE . ' WHERE planet_id < 1');
            $action = 'repair';
        }

        if ($pub_do == 'install_callbacks') {
            require_once('includes/check_callbacks.php');
            $installed_callbacks = count($callInstall['success']);
            $total_callbacks = count($callInstall['success']) + count($callInstall['errors']);
            $action = 'install_callbacks';
        }
    }
}

if ($page == 'group') {
    if (isset($pub_groups_id)) {
        $ids = explode('-', (string)$pub_groups_id);
        $groups = array();

        foreach ($ids as $group_id) {
            $system = (isset($_POST['system_' . $group_id]) ? 1 : 0);
            $ranking = (isset($_POST['ranking_' . $group_id]) ? 1 : 0);
            $empire = (isset($_POST['empire_' . $group_id]) ? 1 : 0);
            $messages = (isset($_POST['messages_' . $group_id]) ? 1 : 0);

            $db->sql_query('REPLACE INTO ' . TABLE_XTENSE_GROUPS . ' (group_id,  system, ranking, empire, messages) VALUES (' . $group_id . ', ' . $system . ', 	' . $ranking . ', ' . $empire . ', ' . $messages . ')');
        }

        $update = true;
    }


    $query = $db->sql_query('SELECT g.group_id, group_name,  system, ranking, empire, messages FROM ' . TABLE_GROUP . ' g LEFT JOIN ' . TABLE_XTENSE_GROUPS . ' x ON x.group_id = g.group_id ORDER BY g.group_name ASC');
    $groups = array();
    $groups_id = array();

    while ($data = $db->sql_fetch_assoc($query)) {
        if ($data['system'] == null) {
            $data['system'] = $data['spy'] = $data['ranking'] = $data['empire'] = $data['messages'] = 0;
        }

        $groups[] = $data;
        $groups_id[] = $data['group_id'];
    }
}

if ($page == 'mods') {
    if (isset($pub_toggle, $pub_state)) {
        $mod_id = (int)$pub_toggle;
        $state = $pub_state == 1 ? 1 : 0;
        $db->sql_query('UPDATE ' . TABLE_XTENSE_CALLBACKS . ' SET active = ' . $state . ' WHERE id = ' . $mod_id);

        $update = true;
    }

    $query = $db->sql_query('SELECT c.id, c.type, c.active AS callback_active, m.title, m.active, m.version FROM ' . TABLE_XTENSE_CALLBACKS . ' c LEFT JOIN ' . TABLE_MOD . ' m ON m.id = c.mod_id ORDER BY m.title ASC');
    $callbacks = array();
    $calls_id = array();

    $data_names = array('spy' => 'Rapports d\'espionnage', 'rc_cdr' => 'Rapports de recyclage', 'msg' => 'Messages de joueurs', 'ally_msg' => 'Messages d\'alliances', 'expedition' => 'Rapports d\'expeditions', 'trade' => 'Livraisons Amies', 'trade_me' => 'Mes Livraisons', 'overview' => 'Vue générale', 'ennemy_spy' => 'Espionnages ennemis', 'system' => 'Systèmes solaires', 'ally_list' => 'Liste des joueurs d\'alliance', 'buildings' => 'Bâtiments', 'research' => 'Laboratoire', 'fleet' => 'Flotte', 'fleetSending' => 'Départ de flotte', 'defense' => 'Défense', 'rc' => 'Rapports de combat', 'ranking_player_fleet' => 'Statistiques (flotte) des joueurs', 'ranking_player_points' => 'Statistiques (points) des joueurs', 'ranking_player_research' => 'Statistiques (recherches) des joueurs', 'ranking_ally_fleet' => 'Statistiques (flotte) des alliances', 'ranking_ally_points' => 'Statistiques (points) des alliances', 'ranking_ally_research' => 'Statistiques (recherches) des alliances', 'hostiles' => 'Flottes Hostiles');

    while ($data = $db->sql_fetch_assoc($query)) {
        $data['type'] = $data_names[$data['type']];
        $callbacks[] = $data;
        $calls_id[] = $data['id'];
    }
}
$php_end = benchmark();
$php_timing = $php_end - $php_start;
$db->sql_close();
?>
<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo($lang['MOD_XTENSE_LANG']); ?>"
      lang="<?php echo($lang['MOD_XTENSE_LANG']); ?>">
<head>
    <title><?php echo $lang['MOD_XTENSE_TITLE'] . " " . $version; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <link rel="stylesheet" media="all" type="text/css" href="mod/<?php echo $root; ?>/style.css"/>
</head>
<body>
<h1><?php echo($lang['MOD_XTENSE_ADMINTITLE']); ?></h1>
<script src="mod/<?php echo $root; ?>/js/config.js" type="text/javascript"></script>
<div id="wrapper">
    <ul id="menu">
        <li class="infos<?php if ($page == 'infos') echo ' active'; ?>">
            <div>
                <a href="index.php?action=xtense&amp;page=infos"><?php echo($lang['MOD_XTENSE_INFORMATIONS']); ?></a>
            </div>
        </li>

        <?php if ($user_data['user_admin'] == 1 || ($user_data['user_coadmin'] == 1 && $server_config['xtense_strict_admin'] == 0)) { ?>
            <li class="config<?php if ($page == 'config') echo ' active'; ?>">
                <div>
                    <a href="index.php?action=xtense&amp;page=config"><?php echo($lang['MOD_XTENSE_CONFIGURATION']); ?></a>
                </div>
            </li>
            <li class="user<?php if ($page == 'group') echo ' active'; ?>">
                <div>
                    <a href="index.php?action=xtense&amp;page=group"><?php echo($lang['MOD_XTENSE_PERMISSIONS']); ?></a>
                </div>
            </li>
            <li class="mods<?php if ($page == 'mods') echo ' active'; ?>">
                <div>
                    <a href="index.php?action=xtense&amp;page=mods"><?php echo($lang['MOD_XTENSE_MODS']); ?></a>
                </div>
            </li>
        <?php } ?>
        <li class="about<?php if ($page == 'about') echo ' active'; ?>">
            <div>
                <a href="index.php?action=xtense&amp;page=about"><?php echo($lang['MOD_XTENSE_ABOUT']); ?></a>
            </div>
        </li>
    </ul>

    <div id="content">
        <?php if ($page == 'infos') { ?>
            <p><?php echo($lang['MOD_XTENSE_DESCRIPTION']); ?></p>
            <h2><?php echo($lang['MOD_XTENSE_DOWNLOAD']); ?></h2><br>
            <p><?php echo($lang['MOD_XTENSE_FIREFOX']); ?> : <a
                    href="https://addons.mozilla.org/fr/firefox/addon/xtense-we/"
                    target="_blank"><?php echo($lang['MOD_XTENSE_FIREFOX_LINK']); ?></a></p>
            <p><?php echo($lang['MOD_XTENSE_CHROME']); ?> : <a
                    href="https://chrome.google.com/webstore/detail/xtense-gm/mkcgnadlbcakpmmmdfijdekknodapcgl?hl=fr"
                    target="_blank"><?php echo($lang['MOD_XTENSE_CHROME_LINK']); ?></a></p>
            <p><a href="https://wiki.ogsteam.fr/doku.php?id=fr:ogspy:documentationxtense"
                  target="_blank"><?php echo($lang['MOD_XTENSE_INSTALL_HELP']); ?></a></p><br>

            <h2><?php echo($lang['MOD_XTENSE_CONNECTION_DETAILS']); ?></h2>
            <p><label for="plugin"><strong><?php echo($lang['MOD_XTENSE_URL_PLUGIN']); ?></strong></label></p>
            <p class="c">
                <input type="text" class="infos" id="plugin_url" name="plugin" value=""
                       onclick="this.select();" readonly/>
            </p>            <p><label for="plugin"><strong><?php echo($lang['MOD_XTENSE_USER']); ?></strong></label></p>
            <p class="c">
                <input type="text" class="infos" id="plugin_user" name="name" value="<?php echo $user_data["user_name"]; ?>"
                       onclick="this.select();" readonly/>
            </p>            <p><label for="plugin"><strong><?php echo($lang['MOD_XTENSE_PASSWORD']); ?></strong></label></p>
            <p class="c">
                <input type="text" class="infos" id="plugin_password" name="password" value="<?php echo $my_user_token ?>"
                       onclick="this.select();" readonly/>
            </p>
            <p><?php echo($lang['MOD_XTENSE_PSEUDO_PASSWORD']); ?></p><br>
            <div id="actions">
                <h2><?php echo($lang['MOD_XTENSE_ACTIONS']); ?></h2>
                <p>
                    <a href="?action=xtense&action_xtense=renew_token" class="action"
                       title="Effectuer cette action">&nbsp;</a>
                    <?php echo($lang['MOD_XTENSE_RENEW_TOKEN']); ?>
                </p>
            </div>
            <script>
                document.getElementById("plugin_url").value = get_xtense_url().toString();
            </script>

        <?php } elseif ($page == 'config') { ?>

            <?php if (isset($update)) { ?>
                <p class="success"><?php echo($lang['MOD_XTENSE_UPDATE_DONE']); ?></p>
            <?php } ?>

            <?php if (isset($action)) { ?>
                <?php if ($action == 'repair') { ?>
                    <p class="success"><?php echo($lang['MOD_XTENSE_REPAIR_DONE']); ?></p>
                <?php } elseif ($action == 'install_callbacks') { ?>
                    <p class="success"><?php echo($lang['MOD_XTENSE_CALLBACK_SUMMARY'] . " (" . $installed_callbacks); ?>
                    / <?php echo $total_callbacks; ?>).
                    <?php if (!empty($callInstall['errors'])) { ?>
                        <label for="callback_sumary">
                            <button type="button" onclick="toggle_callback_info();"
                                    id="callback_button"><?php echo($lang['MOD_XTENSE_ERROR_DETAILS']); ?></button>
                        </label>
                        <span id="callback_info">
                        <h2><?php echo($lang['MOD_XTENSE_CALLBACK_LIST']); ?></h2>
                        <?php if (!empty($callInstall['success'])) { ?>
                            <p><em><?php echo($lang['MOD_XTENSE_INSTALLED_CALLBACKS']); ?></em></p>
                            <ul>
                                <?php foreach ($callInstall['success'] as $reason) { ?>
                                    <li><?php echo $reason; ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        <?php if (!empty($callInstall['errors'])) { ?>
                            <p><em><?php echo($lang['MOD_XTENSE_WRONG_CALLBACKS']); ?></em></p>
                            <ul>
                                <?php foreach ($callInstall['errors'] as $reason) { ?>
                                    <li><?php echo $reason; ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        </span>
                    <?php } ?>
                    </p>
                <?php } ?>
            <?php } ?>

            <form action="?action=xtense&amp;page=config" method="post" name="form" id="form">
                <div class="col">
                    <p>
                        <span class="chk"><input type="checkbox" id="allow_connections"
                                                 name="allow_connections"<?php echo($server_config['xtense_allow_connections'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                        <label for="allow_connections"><?php echo($lang['MOD_XTENSE_ALLOW_CONNECTIONS']); ?></label>
                    </p>
                    <p>
                        <span class="chk"><input type="checkbox" id="strict_admin"
                                                 name="strict_admin"<?php echo($server_config['xtense_strict_admin'] == 1 ? ' checked="checked"' : ''); ?>
                                                 onclick="if (this.checked && <?php echo (int)($user_data['user_coadmin'] && !$user_data['user_admin']); ?>) alert('Vous &ecirc;tes co-admin, si vous cochez cette option vous ne pourrez plus acceder &agrave; l&#039;administration de Xtense');"/></span>
                        <label for="strict_admin"><?php echo($lang['MOD_XTENSE_ALLOW_ADMIN_ONLY']); ?></label>
                    </p>
                    <p>
                        <span class="chk"><input type="checkbox" id="spy_autodelete"
                                                 name="spy_autodelete"<?php echo($server_config['xtense_spy_autodelete'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                        <label for="spy_autodelete"><?php echo($lang['MOD_XTENSE_SPYREPORTS_AUTODELETE']); ?></label>
                    </p>
                    <p>
                        <span class="chk"><input type="text" size="30" maxlength="40" id="universe" name="universe"
                                                 value="<?php echo $server_config['xtense_universe']; ?>"/></span>
                        <label for="universe"><?php echo($lang['MOD_XTENSE_SERVER_NAME']); ?></label>
                    </p>
                </div>

                <div>
                    <fieldset>
                        <legend><?php echo($lang['MOD_XTENSE_LOGS']); ?></legend>

                        <p>
                            <span class="chk"><input type="checkbox" id="log_system"
                                                     name="log_system"<?php echo($server_config['xtense_log_system'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_system"><?php echo($lang['MOD_XTENSE_SOLARSYSTEMS']); ?></label>
                        </p>
                        <p>
                            <span class="chk"><input type="checkbox" id="log_spy"
                                                     name="log_spy"<?php echo($server_config['xtense_log_spy'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_spy"><?php echo($lang['MOD_XTENSE_SPYREPORTS']); ?></label>
                        </p>
                        <p>
                            <span class="chk"><input type="checkbox" id="log_empire"
                                                     name="log_empire"<?php echo($server_config['xtense_log_empire'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_empire"><?php echo($lang['MOD_XTENSE_EMPIRE']); ?></label>
                        </p>
                        <p>
                            <span class="chk"><input type="checkbox" id="log_ranking"
                                                     name="log_ranking"<?php echo($server_config['xtense_log_ranking'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_ranking"><?php echo($lang['MOD_XTENSE_RANKINGS']); ?></label>
                        </p>
                        <p>
                            <span class="chk"><input type="checkbox" id="log_ally_list"
                                                     name="log_ally_list"<?php echo($server_config['xtense_log_ally_list'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_ally_list"><?php echo($lang['MOD_XTENSE_ALLIANCE_LIST']); ?></label>
                        </p>
                        <p>
                            <span class="chk"><input type="checkbox" id="log_messages"
                                                     name="log_messages"<?php echo($server_config['xtense_log_messages'] == 1 ? ' checked="checked"' : ''); ?> /></span>
                            <label for="log_messages"><?php echo($lang['MOD_XTENSE_MESSAGES']); ?></label>
                        </p>
                    </fieldset>
                </div>
                <div class="clear sep"></div>
                <div id="actions">
                    <h2><?php echo($lang['MOD_XTENSE_ACTIONS']); ?></h2>
                    <p>
                        <a href="?action=xtense&amp;page=config&amp;do=repair" class="action"
                           title="Effectuer cette action">&nbsp;</a>
                        <?php echo($lang['MOD_XTENSE_REPAIR_EMPIRE']); ?>
                    </p>

                    <p>
                        <a href="?action=xtense&amp;page=config&amp;do=install_callbacks" class="action"
                           title="Effectuer cette action">&nbsp;</a>
                        <?php echo($lang['MOD_XTENSE_INSTALL_CALLBACKS']); ?>
                    </p>
                </div>
                <div class="sep"></div>

                <p class="center">
                    <button class="submit" type="submit"><?php echo($lang['MOD_XTENSE_SEND']); ?></button>
                    <button class="reset" type="reset"><?php echo($lang['MOD_XTENSE_CANCEL']); ?></button>
                </p>

            </form>

        <?php } elseif ($page == 'group') { ?>


            <script>
                let groups_id = [<?php echo implode(', ', $groups_id); ?>];
            </script>

            <p><?php echo($lang['MOD_XTENSE_GROUPS_DEFINITION']); ?></p>

        <?php if (isset($update)) { ?>
            <p class="success"><?php echo($lang['MOD_XTENSE_UPDATE_DONE']); ?></p>
        <?php } ?>

            <p style="text-align:right;" class="p10"><span onclick="set_all(true);"
                                                           style="cursor:pointer;"><?php echo($lang['MOD_XTENSE_TICKALL']); ?></span>
                / <span onclick="set_all(false);"
                        style="cursor:pointer;"><?php echo($lang['MOD_XTENSE_UNTICKALL']); ?></span></p>

            <form action="?action=xtense&amp;page=group" method="post" name="form" id="form">
                <input type="hidden" name="groups_id" id="groups_id" value="<?php echo implode('-', $groups_id); ?>"/>
                <table width="100%">
                    <tr>
                        <th><?php echo($lang['MOD_XTENSE_TITLE']); ?></th>
                        <th width="12%" class="c"><?php echo($lang['MOD_XTENSE_SOLARSYSTEMS']); ?></th>
                        <th width="12%" class="c"><?php echo($lang['MOD_XTENSE_RANKINGS']); ?></th>
                        <th width="12%" class="c"><?php echo($lang['MOD_XTENSE_EMPIRE']); ?></th>
                        <th width="12%" class="c"><?php echo($lang['MOD_XTENSE_MESSAGES']); ?></th>
                        <th width="20" class="c"></th>
                    </tr>
                    <?php foreach ($groups as $l) { ?>
                        <tr>
                            <td><?php echo $l['group_name']; ?></td>

                            <td class="c"><input type="checkbox" name="system_<?php echo $l['group_id']; ?>"
                                                 id="system_<?php echo $l['group_id']; ?>" <?php if ($l['system'] == 1) echo 'checked="checked"'; ?> />
                            </td>
                            <td class="c"><input type="checkbox" name="ranking_<?php echo $l['group_id']; ?>"
                                                 id="ranking_<?php echo $l['group_id']; ?>" <?php if ($l['ranking'] == 1) echo 'checked="checked"'; ?> />
                            </td>
                            <td class="c"><input type="checkbox" name="empire_<?php echo $l['group_id']; ?>"
                                                 id="empire_<?php echo $l['group_id']; ?>" <?php if ($l['empire'] == 1) echo 'checked="checked"'; ?> />
                            </td>
                            <td class="c"><input type="checkbox" name="messages_<?php echo $l['group_id']; ?>"
                                                 id="messages_<?php echo $l['group_id']; ?>" <?php if ($l['messages'] == 1) echo 'checked="checked"'; ?> />
                            </td>
                            <td><input type="checkbox" onclick="check_row(<?php echo $l['group_id']; ?>, this);"/></td>
                        </tr>
                    <?php } ?>

                    <tr class="bottom">
                        <th></th>
                        <th class="c"><input type="checkbox" onclick="check_col('system', this);"/></th>
                        <th class="c"><input type="checkbox" onclick="check_col('ranking', this);"/></th>
                        <th class="c"><input type="checkbox" onclick="check_col('empire', this);"/></th>
                        <th class="c"><input type="checkbox" onclick="check_col('messages', this);"/></th>
                        <th></th>
                    </tr>
                </table>

                <div class="sep"></div>
                <p class="center">
                    <button class="submit" type="submit"><?php echo($lang['MOD_XTENSE_SEND']); ?></button>
                    <button class="reset" type="reset"><?php echo($lang['MOD_XTENSE_CANCEL']); ?></button>
                </p>
            </form>

        <?php }
        elseif ($page == 'mods') { ?>

        <p><?php echo($lang['MOD_XTENSE_CALLBACK_LIST_DESC']); ?></p><br/>
        <?php if (isset($update)) { ?>
            <p class="success"><?php echo($lang['MOD_XTENSE_UPDATE_DONE']); ?></p>
        <?php } ?>

        <form action="?action=xtense&amp;page=mods" method="post" name="form" id="form">
            <input type="hidden" name="calls_id" id="calls_id" value="<?php echo implode('-', $calls_id); ?>"/>
            <table width="100%">
                <tr>
                    <th class="c">#</th>
                    <th><?php echo($lang['MOD_XTENSE_CALLBACK_MODNAME']); ?></th>
                    <th width="40%"><?php echo($lang['MOD_XTENSE_CALLBACK_DATATYPE']); ?></th>
                    <th width="17%" class="c"><?php echo($lang['MOD_XTENSE_CALLBACK_STATUSMOD']); ?></th>
                    <th width="17%" class="c"><?php echo($lang['MOD_XTENSE_CALLBACK_STATUSLINK']); ?></th>
                    <th class="c" width="10"></th>
                </tr>
                <?php if (empty($callbacks)) { ?>
                    <tr>
                        <td class="c" colspan="5"><em><?php echo($lang['MOD_XTENSE_CALLBACK_NONE']); ?></em></td>
                    </tr>
                <?php } ?>

                <?php foreach ($callbacks as $l) { ?>
                    <tr>
                        <td><?php echo $l['id']; ?></td>
                        <td><?php echo $l['title']; ?> (<?php echo $l['version']; ?>)</td>
                        <td><?php echo $l['type']; ?></td>
                        <td class="c"><?php echo($l['active'] == 1 ? $lang['MOD_XTENSE_MOD_ENABLED'] : $lang['MOD_XTENSE_MOD_DISABLED']); ?></td>
                        <td class="c"><?php echo($l['callback_active'] == 1 ? $lang['MOD_XTENSE_CALLBACK_ENABLED'] : $lang['MOD_XTENSE_CALLBACK_DISABLED']); ?></td>
                        <td>
                            <a href="index.php?action=xtense&amp;page=mods&amp;toggle=<?php echo $l['id']; ?>&amp;state=<?php echo $l['callback_active'] == 1 ? 0 : 1; ?>"
                               title="<?php echo($l['callback_active'] == 1 ? $lang['MOD_XTENSE_CALLBACK_DISABLED'] : $lang['MOD_XTENSE_CALLBACK_ENABLED']); ?> l'appel"><?php icon($l['callback_active'] == 1 ? 'reset' : 'valid'); ?></a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <br/>
            <?php } elseif ($page == 'about') { ?>
                <p><?php echo($lang['MOD_XTENSE_AUTHOR']); ?></a></p>
                <p><?php echo($lang['MOD_XTENSE_FORUM']); ?> : <a href="https://forum.ogsteam.fr/"
                                                                  onclick="return winOpen(this);"
                                                                  target="_blank"><?php echo($lang['MOD_XTENSE_TITLE']); ?></a>
                </p>
                <p><?php echo($lang['MOD_XTENSE_ICONS']); ?> "Silk icons" <a
                        href="http://www.famfamfam.com/lab/icons/silk/">FamFamFam</a></p>

                <div class="sep"></div>
                <h2><?php echo($lang['MOD_XTENSE_CHANGELOG']); ?></h2>
                <p>
                    <a href="https://github.com/OGSteam/mod-xtense/releases/"><?php echo($lang['MOD_XTENSE_CHANGELOG_LINK']); ?></a>
                </p>

            <?php } ?>
    </div>
</div>
<div id="foot"><?php echo round($php_timing, 2); ?> ms - <a href="https://forum.ogsteam.fr/"
                                                            onclick="return winOpen(this);"
                                                            target="_blank"><?php echo($lang['MOD_XTENSE_SUPPPORT']); ?></a>
</div>
</body>
</html>
