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

    public function testHandleWritesAllFifteenPositionsToAstroObject(): void
    {
        $data    = $this->getDecodedData('system');
        $handler = $this->createHandler('SystemHandler');
        $handler->handle($data);

        // One INSERT per position (rows 1-15, including empty rows with planet_id=0)
        $queries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertGreaterThanOrEqual(15, count($queries),
            'Expected at least 15 UPSERTs into game_astro_object (one per row)');
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
}
