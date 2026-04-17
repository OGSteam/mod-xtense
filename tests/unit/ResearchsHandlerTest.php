<?php

/**
 * TDD tests for ResearchsHandler (Phase 2).
 *
 * These tests are skipped until ResearchsHandler is implemented.
 * Fixture: tests/fixtures/researchs.json
 * Expected behaviour from xtense.php case 'researchs' (lines 559–610).
 *
 * Key behavioural difference vs buildings handlers:
 *   - Data is keyed by player_id (not planet_id) → INSERT INTO game_player_technology
 *   - 16 research columns from $database['labo']
 */
class ResearchsHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('ResearchsHandler', false)) {
            $this->markTestSkipped('ResearchsHandler not yet implemented (Phase 2)');
        }
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function testHandleInsertsTechnologyTableKeyedByPlayerId(): void
    {
        $data    = $this->getDecodedData('researchs');
        $handler = $this->createHandler('ResearchsHandler');
        $handler->handle($data);

        // Target table is game_player_technology, NOT game_astro_object
        $this->assertEmpty(
            $this->db->getQueriesContaining('game_astro_object'),
            'ResearchsHandler must not write to game_astro_object'
        );

        $queries = $this->db->getQueriesContaining('game_player_technology');
        $this->assertNotEmpty($queries, 'Expected INSERT INTO game_player_technology');

        $sql = $queries[0];
        $this->assertStringContainsString('INSERT INTO',             $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        // userData['player_id'] = 42 (from XtenseTestCase)
        $this->assertStringContainsString('42', $sql);
    }

    public function testHandleIncludesAllSixteenResearchColumns(): void
    {
        $data    = $this->getDecodedData('researchs');
        $handler = $this->createHandler('ResearchsHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_player_technology')[0];

        // All 16 labo codes from $database['labo'] that appear in the fixture
        $expectedCodes = ['Esp', 'Ordi', 'Armes', 'Bouclier', 'Protection',
                          'NRJ', 'Hyp', 'RC', 'RI', 'PH',
                          'Laser', 'Ions', 'Plasma', 'RRI', 'Graviton', 'Astrophysique'];

        foreach ($expectedCodes as $code) {
            $this->assertStringContainsString("`$code`", $sql, "Column '$code' missing from query");
        }
    }

    public function testHandleSetsCorrectIoResponse(): void
    {
        $data    = $this->getDecodedData('researchs');
        $handler = $this->createHandler('ResearchsHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('home updated', $response['type']);
        $this->assertSame('labo',         $response['page']);
    }

    public function testHandleRegistersResearchCallback(): void
    {
        $data    = $this->getDecodedData('researchs');
        $handler = $this->createHandler('ResearchsHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('research'));
        $calls  = $this->callbackHandler->getCallsForType('research');
        $this->assertArrayHasKey('research', $calls[0]['params']);
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutEmpireGrant(): void
    {
        $data    = $this->getDecodedData('researchs');
        $handler = $this->createHandlerWithoutGrant('ResearchsHandler', 'empire');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_player_technology'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }
}
