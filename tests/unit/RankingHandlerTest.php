<?php

/**
 * TDD tests for RankingHandler (Phase 3).
 *
 * These tests are skipped until RankingHandler is implemented.
 * Fixtures:
 *   - tests/fixtures/ranking_player_fleet_built.json  (type2=fleet, type3=5)
 *   - tests/fixtures/ranking_player_points.json       (type2=points)
 *   - tests/fixtures/ranking_player_economy.json      (type2=economy)
 *
 * Expected behaviour from xtense.php case 'ranking' (lines 836–1011).
 */
class RankingHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('RankingHandler', false)) {
            $this->markTestSkipped('RankingHandler not yet implemented (Phase 3)');
        }
    }

    // -------------------------------------------------------------------------
    // Table routing — correct table is selected based on type2/type3
    // -------------------------------------------------------------------------

    public function testPlayerFleetBuiltRoutesToMilitaryBuiltTable(): void
    {
        // type2=fleet, type3=5 → TABLE_RANK_PLAYER_MILITARY_BUILT
        $data    = $this->getDecodedData('ranking_player_fleet_built');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_rank_player_military_built');
        $this->assertNotEmpty($queries,
            'Expected REPLACE INTO game_rank_player_military_built for type3=5');
    }

    public function testPlayerPointsRoutesToPointsTable(): void
    {
        // type2=points → TABLE_RANK_PLAYER_POINTS
        $data    = $this->getDecodedData('ranking_player_points');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_rank_player_points');
        $this->assertNotEmpty($queries,
            'Expected REPLACE INTO game_rank_player_points for type2=points');
    }

    public function testPlayerEconomyRoutesToEconomicsTable(): void
    {
        // type2=economy → TABLE_RANK_PLAYER_ECO
        $data    = $this->getDecodedData('ranking_player_economy');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_rank_player_economics');
        $this->assertNotEmpty($queries,
            'Expected REPLACE INTO game_rank_player_economics for type2=economy');
    }

    // -------------------------------------------------------------------------
    // Query structure
    // -------------------------------------------------------------------------

    public function testRankingQueryUsesReplaceInto(): void
    {
        $data    = $this->getDecodedData('ranking_player_points');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('REPLACE INTO');
        $this->assertNotEmpty($queries, 'Ranking handler must use REPLACE INTO');
    }

    public function testRankingInsertsGamePlayerForEachEntry(): void
    {
        $data    = $this->getDecodedData('ranking_player_fleet_built');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        // Every ranked player should get a game_player UPSERT
        $playerQueries = $this->db->getQueriesContaining('game_player');
        $this->assertNotEmpty($playerQueries,
            'Expected game_player UPSERTs for ranked players');
    }

    public function testRankingInsertsGameAllyForEntriesWithAlliance(): void
    {
        $data    = $this->getDecodedData('ranking_player_fleet_built');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        // At least some entries have non-zero ally_id (e.g. ASTRO, FAR, SLD …)
        $allyQueries = $this->db->getQueriesContaining('game_ally');
        $this->assertNotEmpty($allyQueries,
            'Expected game_ally UPSERTs for players with an alliance');
    }

    // -------------------------------------------------------------------------
    // fleet/military table — special nb_spacecraft column
    // -------------------------------------------------------------------------

    public function testFleetMilitaryTableQueryIncludesNbSpacecraft(): void
    {
        // When the target table is TABLE_RANK_PLAYER_MILITARY the nb_spacecraft
        // column must be present in the REPLACE INTO query.
        // For fleet_built (type3=5) it must NOT be present.
        $data    = $this->getDecodedData('ranking_player_fleet_built');
        $handler = $this->createHandler('RankingHandler');
        $handler->handle($data);

        $builtQueries = $this->db->getQueriesContaining('game_rank_player_military_built');
        // fleet_built uses the regular columns (no nb_spacecraft)
        $this->assertStringNotContainsString('nb_spacecraft', $builtQueries[0],
            'game_rank_player_military_built should not include nb_spacecraft');
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testRankingThrowsOnInvalidOffset(): void
    {
        $data             = $this->getDecodedData('ranking_player_points');
        $data['offset']   = '50'; // valid offsets: 1, 101, 201, … → (offset-1) % 100 == 0
        $handler          = $this->createHandler('RankingHandler');

        $this->expectException(\UnexpectedValueException::class);
        $handler->handle($data);
    }

    public function testRankingThrowsOnUnknownType2(): void
    {
        $data           = $this->getDecodedData('ranking_player_points');
        $data['type2']  = 'nonexistent';
        $handler        = $this->createHandler('RankingHandler');

        $this->expectException(\UnexpectedValueException::class);
        $handler->handle($data);
    }

    // -------------------------------------------------------------------------
    // Grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutRankingGrant(): void
    {
        $data    = $this->getDecodedData('ranking_player_points');
        $handler = $this->createHandlerWithoutGrant('RankingHandler', 'ranking');
        $handler->handle($data);

        $this->assertEmpty($this->db->getQueriesContaining('game_rank_player_points'));

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame(0,              $response['status']);
    }
}
