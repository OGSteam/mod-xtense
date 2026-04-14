<?php

/**
 * PHPUnit bootstrap for the Xtense test suite.
 *
 * This file is executed BEFORE PHPUnit parses any test class, so all classes
 * that test files reference at the class-definition level (extends, type hints)
 * must be loaded here.
 *
 * Responsibilities:
 *  - Composer autoload (PHPUnit, Monolog, …)
 *  - Define IN_SPYOGAME / IN_XTENSE guards
 *  - Define all TABLE_* constants required by xtense handlers
 *  - Stub core OGSpy functions not available in the unit-test context
 *  - Load test-support classes (SpyDatabase, SpyCallbackHandler, XtenseTestCase)
 *  - Load all xtense source files (Io, Check, AbstractHandler, …)
 *  - Conditionally load Phase-2+ handler files if they already exist
 */

// ---------------------------------------------------------------------------
// 1. Composer autoload
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../../vendor/autoload.php';

// ---------------------------------------------------------------------------
// 2. Entry-point guards expected by xtense source files
// ---------------------------------------------------------------------------
if (!defined('IN_SPYOGAME')) define('IN_SPYOGAME', true);
if (!defined('IN_XTENSE'))   define('IN_XTENSE',   true);

// ---------------------------------------------------------------------------
// 3. Globals consumed by xtense/includes/config.php and helpers
// ---------------------------------------------------------------------------
$GLOBALS['table_prefix'] = 'ogspy_';
$GLOBALS['root']         = 'xtense';

// ---------------------------------------------------------------------------
// 4. TABLE_* constants (mirrors includes/config.php)
// ---------------------------------------------------------------------------
$p = 'ogspy_';
foreach ([
    'TABLE_CONFIG'                        => $p . 'config',
    'TABLE_USER'                          => $p . 'user',
    'TABLE_MOD'                           => $p . 'mod',
    'TABLE_STATISTIC'                     => $p . 'statistics',
    'TABLE_USER_BUILDING'                 => $p . 'game_astro_object',
    'TABLE_GAME_PLAYER'                   => $p . 'game_player',
    'TABLE_GAME_ALLY'                     => $p . 'game_ally',
    'TABLE_GAME_PLAYER_DEFENSE'           => $p . 'game_player_defense',
    'TABLE_GAME_PLAYER_FLEET'             => $p . 'game_player_fleet',
    'TABLE_USER_TECHNOLOGY'               => $p . 'game_player_technology',
    'TABLE_RANK_PLAYER_POINTS'            => $p . 'game_rank_player_points',
    'TABLE_RANK_PLAYER_ECO'              => $p . 'game_rank_player_economics',
    'TABLE_RANK_PLAYER_TECHNOLOGY'        => $p . 'game_rank_player_technology',
    'TABLE_RANK_PLAYER_MILITARY'          => $p . 'game_rank_player_military',
    'TABLE_RANK_PLAYER_MILITARY_BUILT'    => $p . 'game_rank_player_military_built',
    'TABLE_RANK_PLAYER_MILITARY_LOOSE'    => $p . 'game_rank_player_military_loose',
    'TABLE_RANK_PLAYER_MILITARY_DESTRUCT' => $p . 'game_rank_player_military_destruct',
    'TABLE_RANK_PLAYER_HONOR'             => $p . 'game_rank_player_honor',
    'TABLE_RANK_ALLY_POINTS'              => $p . 'game_rank_ally_points',
    'TABLE_RANK_ALLY_ECO'                => $p . 'game_rank_ally_economics',
    'TABLE_RANK_ALLY_TECHNOLOGY'          => $p . 'game_rank_ally_technology',
    'TABLE_RANK_ALLY_MILITARY'            => $p . 'game_rank_ally_military',
    'TABLE_RANK_ALLY_MILITARY_BUILT'      => $p . 'game_rank_ally_military_built',
    'TABLE_RANK_ALLY_MILITARY_LOOSE'      => $p . 'game_rank_ally_military_loose',
    'TABLE_RANK_ALLY_MILITARY_DESTRUCT'   => $p . 'game_rank_ally_military_destruct',
    'TABLE_RANK_ALLY_HONOR'               => $p . 'game_rank_ally_honor',
] as $constName => $constValue) {
    if (!defined($constName)) define($constName, $constValue);
}

// ---------------------------------------------------------------------------
// 5. Stub core OGSpy functions that xtense handlers call but that live outside
//    the mod directory and are therefore unavailable in the unit-test context.
// ---------------------------------------------------------------------------
if (!function_exists('generate_config_cache')) {
    function generate_config_cache(): void {}
}
if (!function_exists('booster_encode')) {
    function booster_encode(array $boosters): string { return ''; }
}
if (!function_exists('booster_encodev')) {
    function booster_encodev(int ...$args): string { return ''; }
}

// ---------------------------------------------------------------------------
// 6. Xtense source files  (must come before test-infrastructure classes that
//    extend them, e.g. SpyCallbackHandler extends CallbackHandler)
// ---------------------------------------------------------------------------
$base = dirname(__DIR__); // resolves to mod/xtense/

require_once $base . '/includes/config.php';      // TYPE_PLANET, TYPE_MOON, $database
require_once $base . '/includes/Io.php';
require_once $base . '/includes/Check.php';
require_once $base . '/includes/CallbackHandler.php';
require_once $base . '/includes/Callback.php';
require_once $base . '/includes/functions.php';
require_once $base . '/includes/AbstractHandler.php';

// ---------------------------------------------------------------------------
// 7. Test-infrastructure classes (loaded after xtense sources they depend on)
// ---------------------------------------------------------------------------
$unitDir = __DIR__ . '/unit/';
require_once $unitDir . 'SpyDatabase.php';
require_once $unitDir . 'SpyCallbackHandler.php';

// ---------------------------------------------------------------------------
// 8. Phase-2+ handler files — loaded only when they exist so Phase-C tests
//    skip gracefully before the handlers are implemented.
// ---------------------------------------------------------------------------
$handlerDir = $base . '/Handler/';
foreach (['Overview', 'Buildings', 'ResourceSettings', 'Defense', 'Researchs',
          'Fleet', 'System', 'Ranking', 'CombatReport', 'AllyList', 'Message'] as $handlerName) {
    $handlerFile = $handlerDir . $handlerName . 'Handler.php';
    if (file_exists($handlerFile)) {
        require_once $handlerFile;
    }
}

// ---------------------------------------------------------------------------
// 9. Base test-case class (depends on both xtense sources and spy helpers)
// ---------------------------------------------------------------------------
require_once $unitDir . 'XtenseTestCase.php';
