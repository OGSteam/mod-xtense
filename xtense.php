<?php
global $db, $database, $server_config, $databaseSpyId, $log;

/**
 * @package Xtense 2
 * @author Unibozu
 * @licence GNU
 */

const IN_SPYOGAME = true;
const IN_XTENSE = true;

date_default_timezone_set(@date_default_timezone_get());

if (str_contains(getcwd(), 'mod')) {
    chdir('../../');
}
$_SERVER['SCRIPT_FILENAME'] = str_replace(
    basename(__FILE__),
    'index.php',
    preg_replace('#/mod/(.*)/#', '/', $_SERVER['SCRIPT_FILENAME'])
);
include_once 'common.php';
list($root, $active) = $db->sql_fetch_row($db->sql_query(
    "SELECT `root`, `active` FROM " . TABLE_MOD . " WHERE `action` = 'xtense'"
));

// Xtense authenticates in the POST body and does not use browser cookies.
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('X-Content-Type-Options: nosniff');

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($requestMethod !== 'POST') {
    header('Allow: POST, OPTIONS');
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(405);
    echo json_encode(['status' => 0, 'error' => 'Method Not Allowed']);
    exit;
}

require_once "mod/{$root}/includes/config.php";
require_once "mod/{$root}/includes/functions.php";
require_once "mod/{$root}/includes/CallbackHandler.php";
require_once "mod/{$root}/includes/Callback.php";
require_once "mod/{$root}/includes/Io.php";
require_once "mod/{$root}/includes/Check.php";
require_once "mod/{$root}/includes/auth.php";
require_once "mod/{$root}/includes/AbstractHandler.php";

$handlerClasses = [
    'OverviewHandler',
    'BuildingsHandler',
    'ResourceSettingsHandler',
    'DefenseHandler',
    'ResearchsHandler',
    'FleetHandler',
    'SystemHandler',
    'RankingHandler',
    'CombatReportHandler',
    'AllyListHandler',
    'MessageHandler',
];
foreach ($handlerClasses as $handlerClass) {
    require_once "mod/{$root}/Handler/{$handlerClass}.php";
}

$startTime = microtime(true);
$io = new Io();
$time = time() - 60 * 4;
if ($time > mktime(0, 0, 0) && $time < mktime(8, 0, 0)) {
    $timestamp = mktime(0, 0, 0);
}
if ($time > mktime(8, 0, 0) && $time < mktime(16, 0, 0)) {
    $timestamp = mktime(8, 0, 0);
}
if ($time > mktime(16, 0, 0) && $time < (mktime(0, 0, 0) + 60 * 60 * 24)) {
    $timestamp = mktime(16, 0, 0);
}

$json = file_get_contents('php://input');
$receivedContent = json_decode($json, true);
if (!is_array($receivedContent)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status' => 0, 'error' => 'Invalid JSON payload']);
    exit;
}

$receivedGameData = filter_var_array($receivedContent, [
    'type' => FILTER_SANITIZE_SPECIAL_CHARS,
    'toolbar_version' => FILTER_SANITIZE_SPECIAL_CHARS,
    'toolbar_type' => FILTER_SANITIZE_SPECIAL_CHARS,
    'mod_min_version' => FILTER_SANITIZE_SPECIAL_CHARS,
    'univers' => FILTER_VALIDATE_URL,
    'password' => FILTER_DEFAULT,
    'data' => FILTER_DEFAULT,
]);

if (!isset($receivedGameData['type'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['status' => 0, 'error' => 'Xtense data not provided']);
    exit;
}

xtense_check_before_auth(
    $receivedGameData['toolbar_version'],
    $receivedGameData['mod_min_version'],
    $active,
    $receivedGameData['univers']
);
$xtense_user_data = xtense_check_auth($receivedGameData['password']);
$xtense_user_data = xtense_check_user_rights($xtense_user_data);
$call = new CallbackHandler();

$currentUserId = $xtense_user_data['id'];
$db->sql_query("UPDATE " . TABLE_USER . " SET `xtense_version` = '" . $receivedGameData['toolbar_version'] . "', `xtense_type` = '" . $receivedGameData['toolbar_type'] . "' WHERE `id` = {$currentUserId}");
$toolbarInfo = $receivedGameData['toolbar_type'] . ' V' . $receivedGameData['toolbar_version'];
$data = json_decode($receivedGameData['data'], true);

try {
    $handlerClass = match ($receivedGameData['type']) {
        'overview' => 'OverviewHandler',
        'buildings' => 'BuildingsHandler',
        'resourceSettings' => 'ResourceSettingsHandler',
        'defense' => 'DefenseHandler',
        'researchs' => 'ResearchsHandler',
        'fleet' => 'FleetHandler',
        'system' => 'SystemHandler',
        'ranking' => 'RankingHandler',
        'rc', 'rc_shared' => 'CombatReportHandler',
        'ally_list' => 'AllyListHandler',
        'messages' => 'MessageHandler',
        default => throw new UnexpectedValueException('Game Data category not found ' . $receivedGameData['type']),
    };

    $handlerData = $data;
    if ($handlerClass === 'CombatReportHandler') {
        $handlerData['_xtense_request_type'] = $receivedGameData['type'];
        $handlerData['_xtense_raw_payload'] = $json;
    } elseif ($handlerClass === 'MessageHandler') {
        $handlerData['_xtense_raw_payload'] = $json;
        $handlerData['_xtense_return_as'] = $pub_returnAs ?? null;
    }

    $handler = new $handlerClass(
        $db,
        $log,
        $io,
        $call,
        $server_config,
        $xtense_user_data,
        $database,
        $toolbarInfo
    );
    $handler->handle($handlerData);
    $call->apply();
} catch (\Throwable $e) {
    $log->error('Xtense error: ' . $e->getMessage(), ['exception' => $e]);
    $io->set('error', $e->getMessage());
    $io->status(0);
}

$io->set('execution', str_replace(',', '.', round((microtime(true) - $startTime) * 1000, 2)));
$io->send();
$db->sql_close();
exit;