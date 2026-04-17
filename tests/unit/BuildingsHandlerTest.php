<?php

/**
 * TDD tests for BuildingsHandler (Phase 2).
 *
 * These tests are skipped until BuildingsHandler is implemented.
 * Fixtures:
 *   - tests/fixtures/buildings.json          (resource buildings: M, C, D, …)
 *   - tests/fixtures/buildings_facilities.json (facilities: UdR, Lab, …)
 * Expected behaviour from xtense.php case 'buildings' (lines 353–413).
 */
class BuildingsHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('BuildingsHandler', false)) {
            $this->markTestSkipped('BuildingsHandler not yet implemented (Phase 2)');
        }
    }

    // -------------------------------------------------------------------------
    // Resource buildings (buildings.json)
    // -------------------------------------------------------------------------

    public function testHandleInsertsResourceBuildingsIntoAstroObject(): void
    {
        $data    = $this->getDecodedData('buildings');
        $handler = $this->createHandler('BuildingsHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertCount(1, $queries, 'Expected exactly one UPSERT into game_astro_object');

        $sql = $queries[0];
        $this->assertStringContainsString('INSERT INTO',           $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        // Building codes from fixture: M=7, C=5, D=5, CES=9, …
        $this->assertStringContainsString('`M`',  $sql);
        $this->assertStringContainsString('`C`',  $sql);
        $this->assertStringContainsString('`D`',  $sql);
        // Values cast to int
        $this->assertStringContainsString('7', $sql); // M = 7
    }

    public function testHandleSetsCorrectIoResponseForBuildings(): void
    {
        $data    = $this->getDecodedData('buildings');
        $handler = $this->createHandler('BuildingsHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('home updated', $response['type']);
        $this->assertSame('buildings',    $response['page']);
        $this->assertSame('4:246:12',     $response['planet']);
    }

    public function testHandleRegistersBuildingsCallback(): void
    {
        $data    = $this->getDecodedData('buildings');
        $handler = $this->createHandler('BuildingsHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('buildings'));
        $calls  = $this->callbackHandler->getCallsForType('buildings');
        $params = $calls[0]['params'];
        $this->assertArrayHasKey('coords',    $params);
        $this->assertArrayHasKey('buildings', $params);
    }

    // -------------------------------------------------------------------------
    // Facilities buildings (buildings_facilities.json)
    // -------------------------------------------------------------------------

    public function testHandleInsertsFacilitiesIntoAstroObject(): void
    {
        $data    = $this->getDecodedData('buildings_facilities');
        $handler = $this->createHandler('BuildingsHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertCount(1, $queries);

        $sql = $queries[0];
        $this->assertStringContainsString('`UdR`', $sql); // Shipyard
        $this->assertStringContainsString('`Lab`', $sql); // Lab
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutEmpireGrant(): void
    {
        $data    = $this->getDecodedData('buildings');
        $handler = $this->createHandlerWithoutGrant('BuildingsHandler', 'empire');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_astro_object'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }
}
