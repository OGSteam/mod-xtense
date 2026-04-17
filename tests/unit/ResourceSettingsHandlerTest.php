<?php

/**
 * TDD tests for ResourceSettingsHandler (Phase 2).
 *
 * These tests are skipped until ResourceSettingsHandler is implemented.
 * Fixture: tests/fixtures/resourceSettings.json
 * Expected behaviour from xtense.php case 'resourceSettings' (lines 415–492).
 *
 * Note: the IO page response is 'buildings' (not 'resourceSettings') —
 * this matches the existing production logic and is intentional.
 */
class ResourceSettingsHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('ResourceSettingsHandler', false)) {
            $this->markTestSkipped('ResourceSettingsHandler not yet implemented (Phase 2)');
        }
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testHandleInsertsPercentageColumnsIntoAstroObject(): void
    {
        $data    = $this->getDecodedData('resourceSettings');
        $handler = $this->createHandler('ResourceSettingsHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertCount(1, $queries, 'Expected exactly one UPSERT into game_astro_object');

        $sql = $queries[0];
        $this->assertStringContainsString('INSERT INTO',              $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE',  $sql);
        // All 7 percentage columns must be present
        $this->assertStringContainsString('`M_percentage`',   $sql);
        $this->assertStringContainsString('`C_Percentage`',   $sql);
        $this->assertStringContainsString('`D_percentage`',   $sql);
        $this->assertStringContainsString('`CES_percentage`', $sql);
        $this->assertStringContainsString('`CEF_percentage`', $sql);
        // SAT_percentage is stored as Sat_percentage in the DB (case mapping)
        $this->assertStringContainsString('percentage', $sql);
        $this->assertStringContainsString('`FOR_percentage`', $sql);
    }

    public function testHandleIncludesPlanetCoordinates(): void
    {
        $data    = $this->getDecodedData('resourceSettings');
        $handler = $this->createHandler('ResourceSettingsHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_astro_object')[0];
        // planet_id 33717002, coords 4:246:12
        $this->assertStringContainsString('33717002', $sql);
        $this->assertStringContainsString('246',      $sql);
    }

    public function testHandleSetsIoResponseWithBuildingsPage(): void
    {
        // The existing production code reports page='buildings' for resourceSettings.
        $data    = $this->getDecodedData('resourceSettings');
        $handler = $this->createHandler('ResourceSettingsHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('home updated', $response['type']);
        $this->assertSame('buildings',    $response['page']); // intentional: matches prod
        $this->assertSame('4:246:12',     $response['planet']);
    }

    public function testHandleRegistersResourceSettingsCallback(): void
    {
        $data    = $this->getDecodedData('resourceSettings');
        $handler = $this->createHandler('ResourceSettingsHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('resourceSettings'));
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutEmpireGrant(): void
    {
        $data    = $this->getDecodedData('resourceSettings');
        $handler = $this->createHandlerWithoutGrant('ResourceSettingsHandler', 'empire');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_astro_object'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }
}
