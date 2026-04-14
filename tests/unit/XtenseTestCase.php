<?php

use PHPUnit\Framework\TestCase;

/**
 * Base test class for all xtense handler and utility tests.
 *
 * Responsibilities:
 * - Loads xtense PHP source files once (guarded with class_exists check).
 * - Defines all TABLE_* constants needed by xtense handlers.
 * - Stubs out core OGSpy functions not available in the unit-test context
 *   (generate_config_cache, booster_encode, booster_encodev).
 * - Sets up a fresh SpyDatabase, Monolog stub, real Io, and SpyCallbackHandler
 *   before each test, and populates the globals that xtense functions depend on.
 * - Provides fixture-loading helpers and an IO-response helper.
 */
abstract class XtenseTestCase extends TestCase
{
    protected SpyDatabase        $db;
    protected $log;             // Monolog\Logger stub
    protected Io                 $io;
    protected SpyCallbackHandler $callbackHandler;

    protected array $serverConfig = [
        'num_of_galaxies' => 9,
        'num_of_systems'  => 499,
    ];

    protected array $userData = [
        'id'        => 1,
        'name'      => 'TestUser',
        'player_id' => 42,
        'grant'     => [
            'empire'   => true,
            'system'   => true,
            'ranking'  => true,
            'messages' => true,
        ],
    ];

    /**
     * Mirrors the $database array from mod/xtense/includes/config.php.
     */
    protected array $xtenseDatabase = [
        'ressources'       => ['metal', 'cristal', 'deuterium', 'energie', 'activite'],
        'ressources_p'     => ['M_percentage', 'C_Percentage', 'D_percentage', 'CES_percentage', 'CEF_percentage', 'SAT_percentage', 'FOR_percentage'],
        'buildings'        => ['M', 'C', 'D', 'CES', 'CEF', 'UdR', 'UdN', 'CSp', 'SAT', 'HM', 'HC', 'HD', 'FOR', 'Lab', 'Ter', 'Dock', 'Silo', 'DdR', 'BaLu', 'Pha', 'PoSa'],
        'labo'             => ['Esp', 'Ordi', 'Armes', 'Bouclier', 'Protection', 'NRJ', 'Hyp', 'RC', 'RI', 'PH', 'Laser', 'Ions', 'Plasma', 'RRI', 'Graviton', 'Astrophysique'],
        'defense'          => ['LM', 'LLE', 'LLO', 'CG', 'LP', 'AI', 'PB', 'GB', 'MIC', 'MIP'],
        'fleet'            => ['PT', 'GT', 'CLE', 'CLO', 'CR', 'VB', 'VC', 'REC', 'SE', 'BMD', 'DST', 'EDLM', 'TRA', 'FAU', 'ECL'],
        'fleet_production' => ['SAT', 'FOR'],
    ];

    protected string $toolbarInfo = 'GM-FF V3.1.4';

    // -------------------------------------------------------------------------
    // Class-level bootstrap — runs once per test class
    // -------------------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Guard: files only need to be loaded once across all test classes.
        if (class_exists('Io', false)) {
            return;
        }

        // --- Stub core OGSpy functions not available in unit-test context ----
        // These must be defined BEFORE xtense files are required so that any
        // file that references them via require_once won't cause a fatal error.

        if (!function_exists('generate_config_cache')) {
            // phpcs:ignore
            function generate_config_cache(): void {}
        }
        if (!function_exists('booster_encode')) {
            // phpcs:ignore
            function booster_encode(array $boosters): string { return ''; }
        }
        if (!function_exists('booster_encodev')) {
            // phpcs:ignore
            function booster_encodev(int ...$args): string { return ''; }
        }

        // --- Constants required by xtense guards ----------------------------
        if (!defined('IN_SPYOGAME')) define('IN_SPYOGAME', true);
        if (!defined('IN_XTENSE'))   define('IN_XTENSE', true);

        // --- Globals needed by xtense/includes/config.php -------------------
        $GLOBALS['table_prefix'] = 'ogspy_';
        $GLOBALS['root']         = 'xtense';

        // --- Define TABLE_* constants (mirrors includes/config.php) ---------
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
            'TABLE_RANK_PLAYER_ECO'               => $p . 'game_rank_player_economics',
            'TABLE_RANK_PLAYER_TECHNOLOGY'        => $p . 'game_rank_player_technology',
            'TABLE_RANK_PLAYER_MILITARY'          => $p . 'game_rank_player_military',
            'TABLE_RANK_PLAYER_MILITARY_BUILT'    => $p . 'game_rank_player_military_built',
            'TABLE_RANK_PLAYER_MILITARY_LOOSE'    => $p . 'game_rank_player_military_loose',
            'TABLE_RANK_PLAYER_MILITARY_DESTRUCT' => $p . 'game_rank_player_military_destruct',
            'TABLE_RANK_PLAYER_HONOR'             => $p . 'game_rank_player_honor',
            'TABLE_RANK_ALLY_POINTS'              => $p . 'game_rank_ally_points',
            'TABLE_RANK_ALLY_ECO'                 => $p . 'game_rank_ally_economics',
            'TABLE_RANK_ALLY_TECHNOLOGY'          => $p . 'game_rank_ally_technology',
            'TABLE_RANK_ALLY_MILITARY'            => $p . 'game_rank_ally_military',
            'TABLE_RANK_ALLY_MILITARY_BUILT'      => $p . 'game_rank_ally_military_built',
            'TABLE_RANK_ALLY_MILITARY_LOOSE'      => $p . 'game_rank_ally_military_loose',
            'TABLE_RANK_ALLY_MILITARY_DESTRUCT'   => $p . 'game_rank_ally_military_destruct',
            'TABLE_RANK_ALLY_HONOR'               => $p . 'game_rank_ally_honor',
        ] as $name => $value) {
            if (!defined($name)) define($name, $value);
        }

        // --- Load xtense source files ----------------------------------------
        $base = dirname(__DIR__, 2); // resolves to mod/xtense/ from tests/unit/

        // Test infrastructure (must come before xtense source files that use them)
        require_once __DIR__ . '/SpyDatabase.php';
        require_once __DIR__ . '/SpyCallbackHandler.php';

        // Xtense includes
        require_once $base . '/includes/config.php';   // TYPE_PLANET, TYPE_MOON, $database
        require_once $base . '/includes/Io.php';
        require_once $base . '/includes/Check.php';
        require_once $base . '/includes/CallbackHandler.php';
        require_once $base . '/includes/Callback.php';
        require_once $base . '/includes/functions.php';
        require_once $base . '/includes/AbstractHandler.php';

        // Load handler files if they already exist (Phase 2+)
        $handlerDir = $base . '/Handler/';
        foreach (['Overview', 'Buildings', 'ResourceSettings', 'Defense', 'Researchs',
                  'Fleet', 'System', 'Ranking', 'CombatReport', 'AllyList', 'Message'] as $name) {
            $file = $handlerDir . $name . 'Handler.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Per-test setup
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->db              = new SpyDatabase();
        $this->log             = $this->createStub(\Monolog\Logger::class);
        $this->io              = new Io();
        $this->callbackHandler = new SpyCallbackHandler();

        // Globals consumed by add_log(), update_statistic(), Check::coords()
        $GLOBALS['db']               = $this->db;
        $GLOBALS['log']              = $this->log;
        $GLOBALS['server_config']    = $this->serverConfig;
        $GLOBALS['xtense_user_data'] = $this->userData;

        // Fixed timestamp for ranking handler tests
        $GLOBALS['timestamp'] = mktime(0, 0, 0, 1, 1, 2026);
    }

    // -------------------------------------------------------------------------
    // Fixture helpers
    // -------------------------------------------------------------------------

    /**
     * Load a fixture JSON file from tests/fixtures/ and return its decoded array.
     */
    protected function loadFixture(string $name): array
    {
        $path    = __DIR__ . '/../fixtures/' . $name . '.json';
        $content = file_get_contents($path);
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded, "Fixture '$name.json' is not valid JSON");
        return $decoded;
    }

    /**
     * Load a fixture and decode its inner 'data' field — the actual game payload
     * sent by the browser extension.
     */
    protected function getDecodedData(string $fixtureName): array
    {
        $fixture = $this->loadFixture($fixtureName);
        $data    = json_decode($fixture['data'], true);
        $this->assertIsArray($data, "Fixture '$fixtureName.json' 'data' field is not valid JSON");
        return $data;
    }

    // -------------------------------------------------------------------------
    // Response / assertion helpers
    // -------------------------------------------------------------------------

    /**
     * Capture the current IO state as a decoded array.
     * Calls Io::send() internally; use only once per test to avoid double-output.
     */
    protected function getIoResponse(): array
    {
        ob_start();
        $this->io->send();
        return json_decode(ob_get_clean(), true) ?? [];
    }

    /**
     * Instantiate a handler class with the standard test dependencies.
     */
    protected function createHandler(string $className): AbstractHandler
    {
        return new $className(
            $this->db,
            $this->log,
            $this->io,
            $this->callbackHandler,
            $this->serverConfig,
            $this->userData,
            $this->xtenseDatabase,
            $this->toolbarInfo
        );
    }

    /**
     * Instantiate a handler with a specific grant disabled, for grant-denial tests.
     */
    protected function createHandlerWithoutGrant(string $className, string $grant): AbstractHandler
    {
        $userData                    = $this->userData;
        $userData['grant'][$grant]   = false;
        return new $className(
            $this->db,
            $this->log,
            $this->io,
            $this->callbackHandler,
            $this->serverConfig,
            $userData,
            $this->xtenseDatabase,
            $this->toolbarInfo
        );
    }
}
