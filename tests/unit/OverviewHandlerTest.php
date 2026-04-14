<?php

/**
 * TDD tests for OverviewHandler (Phase 2).
 *
 * These tests are skipped until OverviewHandler is implemented.
 * Fixture: tests/fixtures/overview.json
 * Expected behaviour from xtense.php case 'overview' (lines 139–351).
 */
class OverviewHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('OverviewHandler', false)) {
            $this->markTestSkipped('OverviewHandler not yet implemented (Phase 2)');
        }
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testHandleInsertsGamePlayerRecord(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        // player_id 107058 from fixture
        $queries = $this->db->getQueriesContaining('game_player');
        $this->assertNotEmpty($queries, 'Expected at least one INSERT INTO game_player');
        $this->assertStringContainsString('107058', $queries[0]);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $queries[0]);
    }

    public function testHandleUpdatesUserPlayerIdLink(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        // UPDATE ogspy_user SET player_id = 107058 WHERE id = 1
        $queries = $this->db->getQueriesContaining('UPDATE ogspy_user');
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('player_id', $queries[0]);
        $this->assertStringContainsString('107058',    $queries[0]);
    }

    public function testHandleInsertsUniverseSpeedConfigs(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        // 4 speed configs: speed_uni, speed_fleet_peaceful, speed_fleet_war, speed_fleet_holding
        $queries = $this->db->getQueriesContaining('ogspy_config');
        $configTypes = ['speed_uni', 'speed_fleet_peaceful', 'speed_fleet_war', 'speed_fleet_holding'];
        foreach ($configTypes as $configKey) {
            $found = $this->db->getQueriesContaining($configKey);
            $this->assertNotEmpty($found, "Expected INSERT for config key '$configKey'");
        }
    }

    public function testHandleInsertsAstroObject(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertNotEmpty($queries, 'Expected INSERT INTO game_astro_object');

        $sql = $queries[0];
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        // coords: galaxy=4, system=246, row=12
        $this->assertStringContainsString('4',   $sql);
        $this->assertStringContainsString('246', $sql);
        $this->assertStringContainsString('12',  $sql);
        // planet_id 33717002
        $this->assertStringContainsString('33717002', $sql);
    }

    public function testHandleSetsCorrectIoResponse(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('home updated', $response['type']);
        $this->assertSame('overview',     $response['page']);
        $this->assertSame('4:246:12',     $response['planet']);
    }

    public function testHandleRegistersOverviewCallback(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandler('OverviewHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('overview'));
        $calls = $this->callbackHandler->getCallsForType('overview');
        $params = $calls[0]['params'];
        $this->assertArrayHasKey('coords',        $params);
        $this->assertArrayHasKey('planet_type',   $params);
        $this->assertArrayHasKey('ressources',    $params);
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutEmpireGrant(): void
    {
        $data    = $this->getDecodedData('overview');
        $handler = $this->createHandlerWithoutGrant('OverviewHandler', 'empire');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_astro_object'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }
}
