<?php
/**
 * @package Xtense 2
 * @licence GNU
 *
 * Base class for all Xtense data type handlers.
 * Provides shared helper methods that eliminate code duplication across handlers.
 */

if (!defined('IN_SPYOGAME')) die("Hacking Attempt!");

abstract class AbstractHandler
{
    protected $db;
    protected $log;
    protected Io $io;
    protected CallbackHandler $callbackHandler;
    protected array $server_config;
    protected array $userData;
    protected array $database;
    protected string $toolbarInfo;

    public function __construct(
        $db,
        $log,
        Io $io,
        CallbackHandler $callbackHandler,
        array $server_config,
        array $userData,
        array $database,
        string $toolbarInfo
    ) {
        $this->db = $db;
        $this->log = $log;
        $this->io = $io;
        $this->callbackHandler = $callbackHandler;
        $this->server_config = $server_config;
        $this->userData = $userData;
        $this->database = $database;
        $this->toolbarInfo = $toolbarInfo;
    }

    /**
     * Process the incoming data for this handler type.
     *
     * @param array $data The decoded JSON data from the browser extension.
     * @return void
     */
    abstract public function handle(array $data): void;

    /**
     * Get the handler type name (e.g., 'overview', 'buildings', 'system').
     *
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Get the required grant for this handler (e.g., 'empire', 'system', 'ranking', 'messages').
     *
     * @return string
     */
    abstract public function getRequiredGrant(): string;

    /**
     * Check that the user has the required grant. Sets IO error and returns false if not.
     *
     * @param string $grant The grant type to check.
     * @return bool True if the user has the grant, false otherwise.
     */
    protected function requireGrant(string $grant): bool
    {
        if (!$this->userData['grant'][$grant]) {
            $this->io->set(array(
                'type' => 'plugin grant',
                'access' => $grant
            ));
            $this->io->status(0);
            return false;
        }
        return true;
    }

    /**
     * Validate and parse a coordinate string into its components.
     *
     * @param string $coordString Raw coordinate string (e.g., "4:252:12").
     * @param int $exp Whether this is an expedition coordinate (position 16).
     * @return array{coords: string, galaxy: int, system: int, row: int}
     * @throws \InvalidArgumentException If coordinates are invalid.
     */
    protected function parseCoordinates(string $coordString, int $exp = 0): array
    {
        $coords = Check::coords($coordString, $exp);
        list($g, $s, $r) = explode(':', $coords);
        return [
            'coords' => $coords,
            'galaxy' => (int)$g,
            'system' => (int)$s,
            'row' => (int)$r,
        ];
    }

    /**
     * Resolve an integer/string planet type to the TYPE_PLANET/TYPE_MOON constant.
     *
     * @param int|string $type The type value from the extension data.
     * @return string TYPE_PLANET or TYPE_MOON constant.
     */
    protected function resolvePlanetType($type): string
    {
        return ((int)$type == 0 || (int)$type == TYPE_PLANET) ? TYPE_PLANET : TYPE_MOON;
    }

    /**
     * Get the database string representation of a planet type ('planet' or 'moon').
     *
     * @param string $planetTypeConstant TYPE_PLANET or TYPE_MOON.
     * @return string 'planet' or 'moon'.
     */
    protected function planetTypeToString(string $planetTypeConstant): string
    {
        return ($planetTypeConstant === TYPE_PLANET) ? 'planet' : 'moon';
    }

    /**
     * Build and execute an UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) query.
     *
     * @param string $table The table constant (e.g., TABLE_USER_BUILDING).
     * @param array $columns Ordered list of column names.
     * @param array $values Ordered list of values matching $columns.
     * @param array $updateColumns Column names to include in ON DUPLICATE KEY UPDATE using VALUES().
     * @return void
     */
    protected function executeUpsert(string $table, array $columns, array $values, array $updateColumns): void
    {
        $colStr = '`' . implode('`, `', $columns) . '`';
        $valStr = implode(', ', array_map(function ($val) {
            return is_int($val) || is_float($val) ? $val : "'" . $val . "'";
        }, $values));
        $updatePairs = array_map(function ($col) {
            return "`$col` = VALUES(`$col`)";
        }, $updateColumns);

        $query = "INSERT INTO $table ($colStr) VALUES ($valStr) ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);
        $this->db->sql_query($query);
    }

    /**
     * Register a callback with the CallbackHandler.
     *
     * @param string $type Callback type name.
     * @param array $params Parameters to pass to the callback.
     * @return void
     */
    protected function registerCallback(string $type, array $params): void
    {
        $this->callbackHandler->add($type, $params);
    }

    /**
     * Log an action via the xtense add_log function.
     *
     * @param string $type Log type (e.g., 'overview', 'buildings').
     * @param array $context Additional context (coords, planet_name, etc.). toolbar is added automatically.
     * @return void
     */
    protected function logAction(string $type, array $context = []): void
    {
        $context['toolbar'] = $this->toolbarInfo;
        add_log($type, $context);
    }

    /**
     * Set the IO response for a successful page update.
     *
     * @param string $page Page identifier (e.g., 'overview', 'buildings').
     * @param string $coords Coordinate string.
     * @return void
     */
    protected function setPageUpdatedResponse(string $page, string $coords): void
    {
        $this->io->set(array(
            'type' => 'home updated',
            'page' => $page,
            'planet' => $coords
        ));
    }
}
