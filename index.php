<?php global $server_config, $user_data, $db, $lang, $php_start, $callInstall;

/**
 * @package Xtense 2
 * @author Unibozu
 * @version 1.0
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

// Constants for URLs and reusable text
const FIREFOX_URL = 'https://addons.mozilla.org/fr/firefox/addon/xtense-we/';
const CHROME_URL = 'https://chrome.google.com/webstore/detail/xtense-gm/mkcgnadlbcakpmmmdfijdekknodapcgl?hl=fr';


if (!function_exists('json_decode')) {
    die("Xtense cannot work without the PHP Module JSON Library");
}

require_once("views/page_header.php");

list($version, $root) = $db->sql_fetch_row($db->sql_query("SELECT `version`, `root` FROM " . TABLE_MOD . " WHERE `action` = 'xtense'"));

//Language File
if (!isset($ui_lang)) { // Checks the ui_lang value from parameters file
    $ui_lang = $pub_lang ?? "fr";
    //If no language is available in id.php file we take fr by default
}

require_once("mod/$root/includes/config.php");
require_once("mod/$root/includes/functions.php");
require_once("mod/$root/includes/Check.php");
require_once("mod/$root/lang/" . $ui_lang . "/lang_xtense.php");


if (isset($pub_action_xtense) && $pub_action_xtense = 'renew_token') {

    user_profile_token_updater($user_data['user_id']);
    unset($pub_action_xtense);
}
$my_user_token = get_user_profile_token($user_data['user_id']);


$checkboxes = array('allow_connections', 'strict_admin', 'log_reverse', 'plugin_root', 'log_empire', 'log_system', 'log_spy', 'log_ranking', 'log_ally_list', 'log_messages', 'spy_autodelete');

if (isset($pub_universe)) {
    $universe = Check::universe($pub_universe);
    if ($universe === false) $universe = 'https://sxx-yy.ogame.gameforge.com';

    $replace = '';
    foreach ($checkboxes as $name) {
        $server_config['xtense_' . $name] = (isset($_POST[$name]) ? 1 : 0);
        $replace .= ' ,("xtense_' . $name . '", "' . $server_config['xtense_' . $name] . '")';
    }

    $db->sql_query('REPLACE INTO ' . TABLE_CONFIG . ' (`config_name`, `config_value`) VALUES ("xtense_universe", "' . $universe . '")' . $replace);
    generate_config_cache();
    $server_config['xtense_universe'] = $universe;

    $update = true;
}

if (isset($pub_groups_id)) {
    $ids = explode('-', (string)$pub_groups_id);
    $groups = array();

    foreach ($ids as $group_id) {
        $system = (isset($_POST['system_' . $group_id]) ? 1 : 0);
        $ranking = (isset($_POST['ranking_' . $group_id]) ? 1 : 0);
        $empire = (isset($_POST['empire_' . $group_id]) ? 1 : 0);
        $messages = (isset($_POST['messages_' . $group_id]) ? 1 : 0);

        $db->sql_query('REPLACE INTO ' . TABLE_XTENSE_GROUPS . ' (`group_id`,  `system`, `ranking`, `empire`, `messages`) VALUES (' . $group_id . ', ' . $system . ',     ' . $ranking . ', ' . $empire . ', ' . $messages . ')');
    }

    $update = true;
}


$query = $db->sql_query('SELECT g.`group_id`, `group_name`,  `system`, `ranking`, `empire`, `messages` FROM ' . TABLE_GROUP . ' g LEFT JOIN ' . TABLE_XTENSE_GROUPS . ' x ON x.`group_id` = g.`group_id` ORDER BY g.`group_name`');
$groups = array();
$groups_id = array();

while ($data = $db->sql_fetch_assoc($query)) {
    if ($data['system'] == null) {
        $data['system'] = $data['spy'] = $data['ranking'] = $data['empire'] = $data['messages'] = 0;
    }

    $groups[] = $data;
    $groups_id[] = $data['group_id'];

}


if (isset($pub_toggle, $pub_state)) {
    $mod_id = (int)$pub_toggle;
    $state = $pub_state == 1 ? 1 : 0;
    $db->sql_query('UPDATE ' . TABLE_XTENSE_CALLBACKS . ' SET `active` = ' . $state . ' WHERE id = ' . $mod_id);
    $update = true;
    }


    $query = $db->sql_query('SELECT c.`id`, c.`type`, c.`active` AS callback_active, m.`title`, m.`active`, m.`version` FROM ' . TABLE_XTENSE_CALLBACKS . ' c LEFT JOIN ' . TABLE_MOD . ' m ON m.`id` = c.`mod_id` ORDER BY m.`title`');
    $callbacks = array();
    $calls_id = array();

    $data_names = array('spy' => 'Rapports d\'espionnage', 'rc_cdr' => 'Rapports de recyclage', 'msg' => 'Messages de joueurs', 'ally_msg' => 'Messages d\'alliances', 'expedition' => 'Rapports d\'expeditions', 'trade' => 'Livraisons Amies', 'trade_me' => 'Mes Livraisons', 'overview' => 'Vue générale', 'ennemy_spy' => 'Espionnages ennemis', 'system' => 'Systèmes solaires', 'ally_list' => 'Liste des joueurs d\'alliance', 'buildings' => 'Bâtiments', 'research' => 'Laboratoire', 'fleet' => 'Flotte', 'fleetSending' => 'Départ de flotte', 'defense' => 'Défense', 'rc' => 'Rapports de combat', 'ranking_player_fleet' => 'Statistiques (flotte) des joueurs', 'ranking_player_points' => 'Statistiques (points) des joueurs', 'ranking_player_research' => 'Statistiques (recherches) des joueurs', 'ranking_ally_fleet' => 'Statistiques (flotte) des alliances', 'ranking_ally_points' => 'Statistiques (points) des alliances', 'ranking_ally_research' => 'Statistiques (recherches) des alliances', 'hostiles' => 'Flottes Hostiles');

    while ($data = $db->sql_fetch_assoc($query)) {
        $data['type'] = $data_names[$data['type']];
        $callbacks[] = $data;
        $calls_id[] = $data['id'];
    }

$php_end = benchmark();
$php_timing = $php_end - $php_start;
$db->sql_close();

// End for Prerequisites and Saves


?>
        <div class="body" id="content">
            <div class="og-msg ">
                <h3 class="og-title"><?=$lang['MOD_XTENSE_ADMINTITLE']?></h3>
                <p class="og-content"><?= $lang['MOD_XTENSE_DESCRIPTION']; ?></p>
            </div>

            <div class="ogspy-mod-header">
                <h2><?=$lang['MOD_XTENSE_DOWNLOAD'];?></h2>
            </div>

            <?php if (isset($update)) { ?>
            <p class="og-msg"><?= $lang['MOD_XTENSE_UPDATE_DONE']; ?></p>
            <?php } ?>

            <table class="og-table og-full-table">
                <thead>
                </thead>
                <tbody>
                <tr>
                    <td>
                        <?= $lang['MOD_XTENSE_FIREFOX'] ?>:
                        <a href="<?= FIREFOX_URL ?>" target="_blank" rel="noopener">
                            <?= $lang['MOD_XTENSE_FIREFOX_LINK'] ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?= $lang['MOD_XTENSE_CHROME'] ?>:
                        <a href="<?= CHROME_URL ?>" target="_blank" rel="noopener">
                            <?= $lang['MOD_XTENSE_CHROME_LINK'] ?>
                        </a>

                    </td>
                </tr>

                </tbody>
            </table>
            <form action="?action=xtense&page=config" method="post" name="form" id="form">
            <div class="ogspy-mod-header">
                <h2><?=$lang['MOD_XTENSE_SERVER_NAME'];?></h2>
            </div>
            <div class="flex-container justify-center">
                    <input type="text" size="30" maxlength="40" id="universe" name="universe" value="<?php echo $server_config['xtense_universe']; ?>" />
            </div>
            <br>
            <div class="ogspy-mod-header">
                <h2><?=$lang['MOD_XTENSE_CONNECTION_DETAILS'];?></h2>
            </div>
            <div class="flex-container justify-center">
                <p><strong><?php echo ($lang['MOD_XTENSE_URL_PLUGIN']); ?></strong></p>
                <p>
                    <label for="plugin_url"></label><input type="text" class="infos" id="plugin_url" name="plugin_url" value="" onclick="this.select();" readonly />
                </p>
                <p><label for="plugin"><strong><?php echo ($lang['MOD_XTENSE_PASSWORD']); ?></strong></label></p>
                <p>
                    <label for="plugin_password"></label><input type="text" class="infos" id="plugin_password" name="plugin_password" value="<?php echo $my_user_token ?>" onclick="this.select();" readonly />
                </p>
                <p><?php echo ($lang['MOD_XTENSE_PSEUDO_PASSWORD']); ?></p>
            </div>
                <br>

                <div id="actions" class="flex-container justify-center">
                    <p>
                        <a class="og-button" href="?action=xtense&action_xtense=renew_token">
                            <?php echo ($lang['MOD_XTENSE_RENEW_TOKEN']); ?>
                        </a>
                    </p>
                </div>

            <script>
                let groups_id = [<?php echo implode(', ', $groups_id); ?>];
            </script>

            <div class="ogspy-mod-header">
                <h2><?= $lang['MOD_XTENSE_GROUPS_DEFINITION'];?></h2>
            </div>

                <p><span onclick="setAllCheckboxStatus(true);" style="cursor:pointer;"><?php echo ($lang['MOD_XTENSE_TICKALL']); ?></span>
                    / <span onclick="setAllCheckboxStatus(false);" style="cursor:pointer;"><?php echo ($lang['MOD_XTENSE_UNTICKALL']); ?></span></p>

                    <input type="hidden" name="groups_id" id="groups_id" value="<?php echo implode('-', $groups_id); ?>" />
                    <table class="og-table og-full-table">
                        <thead>
                        <tr>
                            <th><?php echo ($lang['MOD_XTENSE_TITLE']); ?></th>
                            <th><?php echo ($lang['MOD_XTENSE_SOLARSYSTEMS']); ?></th>
                            <th><?php echo ($lang['MOD_XTENSE_RANKINGS']); ?></th>
                            <th><?php echo ($lang['MOD_XTENSE_EMPIRE']); ?></th>
                            <th><?php echo ($lang['MOD_XTENSE_MESSAGES']); ?></th>
                            <th>(X)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groups as $l) { ?>
                            <tr>
                                <td><?php echo $l['group_name']; ?></td>

                                <td><label for="system_<?php echo $l['group_id']; ?>"></label><input type="checkbox" name="system_<?php echo $l['group_id']; ?>" id="system_<?php echo $l['group_id']; ?>" <?php if ($l['system'] == 1) echo 'checked="checked"'; ?> /></td>
                                <td><label for="ranking_<?php echo $l['group_id']; ?>"></label><input type="checkbox" name="ranking_<?php echo $l['group_id']; ?>" id="ranking_<?php echo $l['group_id']; ?>" <?php if ($l['ranking'] == 1) echo 'checked="checked"'; ?> /></td>
                                <td><label for="empire_<?php echo $l['group_id']; ?>"></label><input type="checkbox" name="empire_<?php echo $l['group_id']; ?>" id="empire_<?php echo $l['group_id']; ?>" <?php if ($l['empire'] == 1) echo 'checked="checked"'; ?> /></td>
                                <td><label for="messages_<?php echo $l['group_id']; ?>"></label><input type="checkbox" name="messages_<?php echo $l['group_id']; ?>" id="messages_<?php echo $l['group_id']; ?>" <?php if ($l['messages'] == 1) echo 'checked="checked"'; ?> /></td>
                                <td><label>
                                        <input type="checkbox" onclick="check_row(<?php echo $l['group_id']; ?>, this);" />
                                    </label>
                                </td>
                            </tr>
                        <?php } ?>

                        <tr class="bottom">
                            <th>(X)</th>
                            <th class="c"><label>
                                    <input type="checkbox" onclick="check_col('system', this);" />
                                </label></th>
                            <th class="c"><label>
                                    <input type="checkbox" onclick="check_col('ranking', this);" />
                                </label></th>
                            <th class="c"><label>
                                    <input type="checkbox" onclick="check_col('empire', this);" />
                                </label></th>
                            <th class="c"><label>
                                    <input type="checkbox" onclick="check_col('messages', this);" />
                                </label></th>
                            <th></th>
                        </tr>
                        </tbody>
                    </table>

            <div class="ogspy-mod-header">
                <h2><?=$lang['MOD_XTENSE_CALLBACK_LIST_DESC'];?></h2>
            </div>

                    <input type="hidden" name="calls_id" id="calls_id" value="<?php echo implode('-', $calls_id); ?>" />
                    <table class="og-table og-full-table">
                    <thead>
                    <tr>
                        <th><?php echo ($lang['MOD_XTENSE_CALLBACK_MODNAME']); ?></th>
                        <th><?php echo ($lang['MOD_XTENSE_CALLBACK_DATATYPE']); ?></th>
                        <th><?php echo ($lang['MOD_XTENSE_CALLBACK_STATUSMOD']); ?></th>
                        <th><?php echo ($lang['MOD_XTENSE_CALLBACK_STATUSLINK']); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($callbacks)) { ?>
                            <tr>
                                <td colspan="5"><em><?php echo ($lang['MOD_XTENSE_CALLBACK_NONE']); ?></em></td>
                            </tr>
                        <?php } ?>

                        <?php foreach ($callbacks as $l) { ?>
                            <tr>
                                <td><?php echo $l['id']; ?></td>
                                <td><?php echo $l['title']; ?> (<?php echo $l['version']; ?>)</td>
                                <td><?php echo $l['type']; ?></td>
                                <td class="c"><?php echo ($l['active'] == 1 ? $lang['MOD_XTENSE_MOD_ENABLED'] : $lang['MOD_XTENSE_MOD_DISABLED']); ?></td>
                                <td class="c"><?php echo ($l['callback_active'] == 1 ? $lang['MOD_XTENSE_CALLBACK_ENABLED'] : $lang['MOD_XTENSE_CALLBACK_DISABLED']); ?></td>
                                <td>
                                    <a href="index.php?action=xtense&amp;page=mods&amp;toggle=<?php echo $l['id']; ?>&amp;state=<?php echo $l['callback_active'] == 1 ? 0 : 1; ?>" title="<?php echo ($l['callback_active'] == 1 ? $lang['MOD_XTENSE_CALLBACK_DISABLED'] : $lang['MOD_XTENSE_CALLBACK_ENABLED']); ?> l'appel"><?php icon($l['callback_active'] == 1 ? 'reset' : 'valid'); ?></a>
                                </td>
                            </tr>
                        <?php } ?>

                    </tbody>
                    </table>
            <p class="flex-container justify-center">
                <button class="og-button og-button-success submit" type="submit"><?php echo ($lang['MOD_XTENSE_SEND']); ?></button>
                <button class="og-button reset" type="reset"><?php echo ($lang['MOD_XTENSE_CANCEL']); ?></button>
            </p>
            </form>
            <div class="og-msg ">
                <h3 class="og-title"><?=$lang['MOD_XTENSE_AUTHOR']?></h3>
                <p class="og-content"><?= $lang['MOD_XTENSE_FORUM']; ?> : <a href="https://forum.ogsteam.eu/"  target="_blank" rel="noopener"><?php echo ($lang['MOD_XTENSE_TITLE']); ?></a></p>
                <p class="og-content"><a href="https://wiki.ogsteam.eu/doku.php?id=fr:ogspy:documentationxtense" target="_blank" rel="noopener"><?php echo ($lang['MOD_XTENSE_INSTALL_HELP']); ?></a></p>
                <p class="og-content"><a href="https://github.com/OGSteam/mod-xtense/releases/"><?php echo ($lang['MOD_XTENSE_CHANGELOG']); ?></a></p>
            </div>
        </div>
<script src="mod/xtense/js/config.js"></script>
<script>
    const PLUGIN_URL_FIELD_NAME = "plugin_url"; // Extraction de constante
    const xtenseUrl = getXtensePluginUrl(); // Renommage et extraction de la variable

    // Parcours explicite des champs pour définir leur valeur
   document.getElementsByName(PLUGIN_URL_FIELD_NAME).forEach(function (element) {
       element.value = xtenseUrl;
   });
</script>
