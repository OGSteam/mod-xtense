<?php

/**
 * TDD tests for SystemHandler (Phase 3).
 *
 * These tests are skipped until SystemHandler is implemented.
 * Fixture: tests/fixtures/system.json  (galaxy 4, system 246, 9 occupied positions)
 * Expected behaviour from xtense.php case 'system' (lines 689–834).
 *
 * Fixture row summary (0-indexed, loop processes 1–15):
 *   rows 1-5  → null  (empty positions)
 *   row  6    → Czar Oberon    (ally_id=500003, moon=0)
 *   row  7    → Lord Kempatek  (ally_id=500003, moon=0)
 *   row  8    → Admiral Keyes  (ally_id=0,      moon=1, moon_id=33641931)
 *   row  9    → nain galactique(ally_id=0,      moon=0)
 *   rows 10-11→ null
 *   row  12   → Marshal Galileo(ally_id=0,      moon=0, player_id="107058")
 *   row  13   → null
 *   row  14   → Czar Oberon    (ally_id=500003, moon=0, second planet)
 *   row  15   → Admiral Keyes  (ally_id=0,      moon=1, moon_id=33693888)
 */
class SystemHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('SystemHandler', false)) {
            $this->markTestSkipped('SystemHandler not yet implemented (Phase 3)');
        }
    }

    // -------------------------------------------------------------------------
    // Core system writes
    // -------------------------------------------------------------------------

    public function testHandleWritesOnlyObservedAstroObjects(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('INSERT INTO ogspy_game_astro_object');
        $this->assertCount(9, $queries, 'Expected seven planets and two moons');
        $this->assertStringNotContainsString('VALUES (-1,', implode("\n", $queries));
        $this->assertStringNotContainsString('VALUES (0,', implode("\n", $queries));
    }

    public function testHandleDeletesStalePlanetsAndMoonsFromObservedSystem(): void
    {
        $data = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('DELETE FROM ogspy_game_astro_object');
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('`galaxy` = 4 AND `system` = 246', $queries[0]);
        $this->assertStringContainsString("`type` IN ('planet', 'moon')", $queries[0]);
        $this->assertStringContainsString('33717002', $queries[0]);
        $this->assertStringContainsString('33641931', $queries[0]);
        $this->assertStringContainsString('33693888', $queries[0]);
    }

    public function testHandleDeletesAllAstroObjectsWhenSystemIsEmpty(): void
    {
        $data = $this->getDecodedData('system');
        $data['rows'] = [];

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('DELETE FROM ogspy_game_astro_object')[0];
        $this->assertStringNotContainsString('NOT IN', $query);
        $this->assertEmpty($this->db->getQueriesContaining('INSERT INTO ogspy_game_astro_object'));
    }

    public function testHandleUpdatesCoordinatesWhenObjectIdAlreadyExists(): void
    {
        $data = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('33717002')[1];
        $this->assertStringContainsString('`type` = \'planet\'', $query);
        $this->assertStringContainsString('`galaxy` = 4', $query);
        $this->assertStringContainsString('`system` = 246', $query);
        $this->assertStringContainsString('`row` = 12', $query);
    }

    public function testHandleWritesMoonRowsForPositionsWithMoon(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        // Rows 8 (moon_id=33641931) and 15 (moon_id=33693888) have moons
        $moonQueries = $this->db->getQueriesContaining('33641931');
        $this->assertNotEmpty($moonQueries, 'Expected moon INSERT for row 8 (moon_id=33641931)');

        $moonQueries2 = $this->db->getQueriesContaining('33693888');
        $this->assertNotEmpty($moonQueries2, 'Expected moon INSERT for row 15 (moon_id=33693888)');
    }

    public function testHandleWritesKnownPlanetIdToAstroObject(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        // planet_id 33717002 from row 12 (Marshal Galileo)
        $queries = $this->db->getQueriesContaining('33717002');
        $this->assertNotEmpty($queries, 'Expected planet_id 33717002 in an astro_object UPSERT');
    }

    // -------------------------------------------------------------------------
    // Debris snapshot
    // -------------------------------------------------------------------------

    public function testHandleStoresACompleteDebrisSnapshot(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_debris_field');
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('(`galaxy`, `system`, `position`', $queries[0]);
        $this->assertStringContainsString('(4, 246, 7, 1000, 0, 0,', $queries[0]);
        $this->assertStringContainsString('(4, 246, 16, 0, 0, NULL,', $queries[0]);
        $this->assertSame(16, substr_count($queries[0], '(4, 246,'));
    }

    public function testHandleAcceptsCrystalAndLegacyCristalKeys(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        $data['rows'][3]['debris'] = ['metal' => 10, 'crystal' => 20, 'deuterium' => 30];
        $data['rows'][4]['debris'] = ['metal' => 40, 'cristal' => 50, 'deuterium' => 60];

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('game_debris_field')[0];
        $this->assertStringContainsString('(4, 246, 3, 10, 20, 30,', $query);
        $this->assertStringContainsString('(4, 246, 4, 40, 50, 60,', $query);
    }

    public function testHandleKeepsUnavailableDeuteriumAsNull(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        $data['rows'][8]['debris'] = ['metal' => 100, 'crystal' => 200];

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('game_debris_field')[0];
        $this->assertStringContainsString('(4, 246, 8, 100, 200, NULL,', $query);
    }

    public function testHandleIgnoresInvalidDebrisPositionsAndAmounts(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        $data['rows'][0] = ['debris' => ['metal' => 999, 'crystal' => 999, 'deuterium' => 999]];
        $data['rows'][17] = ['debris' => ['metal' => 999, 'crystal' => 999, 'deuterium' => 999]];
        $data['rows'][5]['debris'] = ['metal' => -1, 'crystal' => 'invalid', 'deuterium' => -3];

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('game_debris_field')[0];
        $this->assertStringNotContainsString(', 999, 999, 999,', $query);
        $this->assertStringContainsString('(4, 246, 5, 0, 0, 0,', $query);
        $this->assertSame(16, substr_count($query, '(4, 246,'));
    }

    public function testHandleClearsMissingPositionsForAnEmptyObservedSnapshot(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        foreach (range(1, 16) as $position) {
            $data['rows'][$position]['debris'] = ['metal' => 0, 'cristal' => 0];
        }

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $query = $this->db->getQueriesContaining('game_debris_field')[0];
        $this->assertStringContainsString('(4, 246, 1, 0, 0, NULL,', $query);
        $this->assertStringContainsString('(4, 246, 16, 0, 0, NULL,', $query);
        $this->assertSame(16, substr_count($query, '(4, 246,'));
    }

    public function testHandleDoesNotClearDebrisWhenObservationIsMissing(): void
    {
        $data = $this->getDecodedData('system');

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_debris_field'));
    }

    // -------------------------------------------------------------------------
    // Game-player / ally upserts
    // -------------------------------------------------------------------------

    public function testHandleInsertsGamePlayerForOccupiedPositions(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        // player_id 107056 (Czar Oberon) appears in rows 6 and 14
        $queries = $this->db->getQueriesContaining('game_player');
        $this->assertNotEmpty($queries, 'Expected at least one INSERT into game_player');

        $czarQueries = $this->db->getQueriesContaining('107056');
        $this->assertNotEmpty($czarQueries, 'Expected game_player UPSERT for player 107056');
    }

    public function testHandleDoesNotInsertBlankPlayerFromEmptyRows(): void
    {
        $data = $this->withCompleteDebrisObservation($this->getDecodedData('system'));
        $data['rows'][1]['player_id'] = '';

        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('INSERT INTO ogspy_game_player');
        $this->assertStringNotContainsString('VALUES (0,', implode("\n", $queries));
    }

    public function testHandleInsertsGameAllyForPositionsWithAlliance(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        // ally_id 500003 (SLD) appears in rows 6, 7, 14
        $allyQueries = $this->db->getQueriesContaining('game_ally');
        $this->assertNotEmpty($allyQueries, 'Expected at least one INSERT into game_ally');

        $sldQueries = $this->db->getQueriesContaining('500003');
        $this->assertNotEmpty($sldQueries, 'Expected game_ally UPSERT for ally_id 500003');
    }

    // -------------------------------------------------------------------------
    // Statistics and counter update
    // -------------------------------------------------------------------------

    public function testHandleUpdatesPlanetImportsCounter(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('planet_imports');
        $this->assertNotEmpty($queries, 'Expected UPDATE for planet_imports counter');
    }

    public function testHandleUpdatesSystemTransactionally(): void
    {
        $data = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $queries = $this->db->getQueries();
        $this->assertSame('TRANSACTION BEGIN', $queries[0]);
        $this->assertContains('TRANSACTION COMMIT', $queries);
    }

    // -------------------------------------------------------------------------
    // IO response and callback
    // -------------------------------------------------------------------------

    public function testHandleSetsCorrectIoResponse(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('system', $response['type']);
        $this->assertSame('4',      (string)$response['galaxy']);
        $this->assertSame('246',    (string)$response['system']);
    }

    public function testHandleRegistersSystemCallback(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('system'));
        $calls  = $this->callbackHandler->getCallsForType('system');
        $params = $calls[0]['params'];
        $this->assertArrayHasKey('galaxy', $params);
        $this->assertArrayHasKey('system', $params);
        $this->assertArrayHasKey('data',   $params);
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutSystemGrant(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandlerWithoutGrant('SystemHandler', 'system');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_astro_object'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }

    public function testHandleRejectsGalaxyOutOfBounds(): void
    {
        $data             = $this->getDecodedData('system');
        $data['galaxy']   = '99'; // > num_of_galaxies (9)
        $handler          = $this->createHandler('SystemHandler');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_astro_object'));

        $response = $this->getIoResponse();
        $this->assertSame(0, $response['status']);
    }

    private function withCompleteDebrisObservation(array $data): array
    {
        foreach (range(1, 16) as $position) {
            if (!isset($data['rows'][$position]) || !is_array($data['rows'][$position])) {
                $data['rows'][$position] = [
                    'planet_id' => '',
                    'planet_name' => '',
                    'player_name' => '',
                    'status' => '',
                    'ally_tag' => '',
                    'moon' => 0,
                    'activity' => '',
                ];
            }

            if (!isset($data['rows'][$position]['debris'])) {
                $data['rows'][$position]['debris'] = ['metal' => 0, 'cristal' => 0];
            }
        }

        return $data;
    }
}
