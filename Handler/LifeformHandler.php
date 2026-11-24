<?php

use Ogsteam\Ogspy\Model\Player_Lifeform_Model;

if (!defined('IN_SPYOGAME')) {
    die('Hacking Attempt!');
}

/**
 * Stores the life-form levels sent by Xtense WE 3.4.1.
 *
 * Real OGame technology IDs are preserved as-is. A successful payload is a
 * complete snapshot for one category and planet, so existing rows are replaced
 * inside a transaction.
 */
class LifeformHandler extends AbstractHandler
{
    private const MAX_LEVELS_PER_CATEGORY = 18;

    public function handle(array $data): void
    {
        if (!$this->requireGrant($this->getRequiredGrant())) {
            return;
        }

        $requestType = $data['_xtense_request_type'] ?? null;
        [$payloadKey, $table] = match ($requestType) {
            'lf_buildings' => ['lfBuildings', TABLE_GAME_LIFEFORM_BUILDING],
            'lf_research' => ['lfResearch', TABLE_GAME_LIFEFORM_RESEARCH],
            default => throw new UnexpectedValueException('Unsupported life-form data category'),
        };

        $planet = $data['planet'] ?? null;
        if (!is_array($planet)) {
            throw new UnexpectedValueException('Life-form data is missing planet information');
        }

        $planetId = filter_var($planet['id'] ?? null, FILTER_VALIDATE_INT);
        if ($planetId === false || $planetId <= 0) {
            throw new UnexpectedValueException('Life-form data contains an invalid planet ID');
        }
        $coordinates = $this->parseCoordinates((string)($planet['coords'] ?? ''))['coords'];

        $levels = $this->validateLevels($data[$payloadKey] ?? null);
        $this->assertOwnedPlanet($planetId);

        $timestamp = time();
        $this->db->sql_transaction('begin');
        try {
            $this->db->sql_query("DELETE FROM {$table} WHERE `astro_object_id` = {$planetId}");

            $rows = [];
            foreach ($levels as $technologyId => $level) {
                $rows[] = "({$planetId}, {$technologyId}, {$level}, {$timestamp})";
            }
            $this->db->sql_query(
                "INSERT INTO {$table} (`astro_object_id`, `technology_id`, `level`, `last_update`) VALUES "
                . implode(', ', $rows)
            );
            if (array_key_exists('species', $data)) {
                $this->storeLifeformSpecies($planetId, $data['species'], $timestamp);
            }
            $this->db->sql_transaction('commit');
        } catch (Throwable $exception) {
            $this->db->sql_transaction('rollback');
            throw $exception;
        }

        $this->setPageUpdatedResponse($requestType, $coordinates);
        $this->registerCallback($requestType, [
            'coords' => explode(':', $coordinates),
            'planet_id' => $planetId,
            $payloadKey => $levels,
        ]);
        $this->logAction($requestType, ['coords' => $coordinates, 'planet_id' => $planetId]);
    }

    public function getType(): string
    {
        return 'lifeforms';
    }

    public function getRequiredGrant(): string
    {
        return 'empire';
    }

    protected function storeLifeformSpecies(int $planetId, mixed $species, int $observedAt): void
    {
        (new Player_Lifeform_Model())->storeResourceObservation(
            $planetId,
            ['species' => $species],
            $observedAt
        );
    }

    /**
     * @return array<int, int>
     */
    private function validateLevels($levels): array
    {
        if (!is_array($levels) || $levels === []) {
            throw new UnexpectedValueException('Life-form levels must be a non-empty object');
        }
        if (count($levels) > self::MAX_LEVELS_PER_CATEGORY) {
            throw new UnexpectedValueException('Life-form payload contains too many levels');
        }

        $validated = [];
        foreach ($levels as $technologyId => $level) {
            $technologyId = (string)$technologyId;
            $level = (string)$level;
            if (!preg_match('/^[1-9]\d*$/', $technologyId)) {
                throw new UnexpectedValueException('Life-form payload contains an invalid technology ID');
            }
            if (!preg_match('/^\d+$/', $level) || (int)$level > 65535) {
                throw new UnexpectedValueException('Life-form payload contains an invalid level');
            }

            $validated[(int)$technologyId] = (int)$level;
        }

        return $validated;
    }

    private function assertOwnedPlanet(int $planetId): void
    {
        $playerId = (int)($this->userData['player_id'] ?? 0);
        if ($playerId <= 0) {
            throw new UnexpectedValueException('The OGSpy user is not linked to an OGame player');
        }

        $result = $this->db->sql_query(
            "SELECT `id` FROM " . TABLE_USER_BUILDING
            . " WHERE `id` = {$planetId} AND `player_id` = {$playerId}"
        );
        $row = $this->db->sql_fetch_row($result);
        if ((int)($row[0] ?? 0) !== $planetId) {
            throw new UnexpectedValueException('Life-form planet is not registered for this player');
        }
    }
}
