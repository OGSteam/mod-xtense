<?php

/**
 * TDD tests for CombatReportHandler.
 *
 * These tests are skipped until CombatReportHandler is implemented.
 *
 * Fixtures:
 *   - tests/fixtures/rc.json          (trivial: 0 rounds, no ships, minimal loot)
 *   - tests/fixtures/rc_complex.json  (3 rounds, mixed fleet+defense, debris, moon)
 *
 * Expected behaviour mirrors xtense.php case 'rc' (lines ~1060–1460).
 *
 * Fixture rc_complex.json scenario:
 *   Attacker  Marshal Galileo  4:246:12  — CLE×100, CR×30, VB×5, PT×20
 *                                          armor 70%, weapon 80%, shield 60%
 *   Defender  Lord Kempatek    4:246:7   — LM×50, LLE×25, LLO×10, CLE×10, PT×5
 *                                          armor 90%, weapon 70%, shield 120%
 *   3 combat rounds; attacker wins.
 *   Loot      metal=200 000  crystal=150 000  deuterium=50 000
 *   Debris    metal=250 000  crystal=120 000
 *   Losses    attacker=2 500 000  defender=5 000 000
 *   Moon creation chance: 12 %
 *
 * Dependencies added to support these tests:
 *   - SpyDatabase::sql_insertid()               (returns auto-incrementing int)
 *   - XtenseTestCase TABLE_PARSEDRC/ROUND/*     (added to constant map)
 */
class CombatReportHandlerTest extends XtenseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists('CombatReportHandler', false)) {
            $this->markTestSkipped('CombatReportHandler not yet implemented');
        }
    }

    // -------------------------------------------------------------------------
    // Fixture sanity (no handler required — exercised via getDecodedData)
    // -------------------------------------------------------------------------

    /**
     * @group fixture
     */
    public function testComplexFixtureHasThreeCombatRounds(): void
    {
        $data = $this->getDecodedData('rc_complex');
        $this->assertCount(3, $data['combatRounds'], 'rc_complex must contain exactly 3 combat rounds');
    }

    /**
     * @group fixture
     */
    public function testComplexFixtureHasDebrisField(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $debris  = $data['result']['debris']['resources'];
        $byType  = array_column($debris, 'amount', 'resource');

        $this->assertSame(250000, $byType['metal'],   'Debris metal should be 250 000');
        $this->assertSame(120000, $byType['crystal'],  'Debris crystal should be 120 000');
    }

    /**
     * @group fixture
     */
    public function testComplexFixtureHasMoonCreationChance(): void
    {
        $data = $this->getDecodedData('rc_complex');
        $this->assertSame(12, $data['result']['moonCreation']['chance']);
    }

    /**
     * @group fixture
     */
    public function testComplexFixtureAttackerHasFleetTechnologies(): void
    {
        $data = $this->getDecodedData('rc_complex');

        $attacker = null;
        foreach ($data['fleets'] as $fleet) {
            if ($fleet['side'] === 'attacker') {
                $attacker = $fleet;
                break;
            }
        }

        $this->assertNotNull($attacker);
        $techIds = array_column($attacker['combatTechnologies'], 'technologyId');
        // 202=PT 204=CLE 206=CR 207=VB
        foreach ([202, 204, 206, 207] as $id) {
            $this->assertContains($id, $techIds, "Attacker must have technologyId $id");
        }
    }

    /**
     * @group fixture
     */
    public function testComplexFixtureDefenderHasDefenseAndFleetTechnologies(): void
    {
        $data = $this->getDecodedData('rc_complex');

        $defender = null;
        foreach ($data['fleets'] as $fleet) {
            if ($fleet['side'] === 'defender') {
                $defender = $fleet;
                break;
            }
        }

        $this->assertNotNull($defender);
        $techIds = array_column($defender['combatTechnologies'], 'technologyId');
        // 202=PT 204=CLE 401=LM 402=LLE 403=LLO
        foreach ([202, 204, 401, 402, 403] as $id) {
            $this->assertContains($id, $techIds, "Defender must have technologyId $id");
        }
    }

    // -------------------------------------------------------------------------
    // Handler — main RC record (TABLE_PARSEDRC)
    // -------------------------------------------------------------------------

    public function testHandleInsertsMainRcRecord(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $queries = $this->db->getQueriesContaining('game_rc');
        $inserts = array_values(array_filter($queries, static fn(string $q) => stripos($q, 'INSERT') !== false && stripos($q, 'game_rc_round') === false));

        $this->assertNotEmpty($inserts, 'Expected INSERT INTO game_rc');
        $sql = $inserts[0];
        $this->assertStringContainsString('1776500000', $sql, 'Timestamp must appear in RC insert');
    }

    public function testHandleRecordsWinnerAttacker(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $inserts = $this->db->getQueriesContaining('game_rc');
        $sql     = $inserts[0];
        $this->assertStringContainsString("'A'", $sql, "Winner must be encoded as 'A'");
    }

    public function testHandleRecordsLootValues(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc')[0];
        $this->assertStringContainsString('200000', $sql, 'Loot metal missing');
        $this->assertStringContainsString('150000', $sql, 'Loot crystal missing');
        $this->assertStringContainsString('50000',  $sql, 'Loot deuterium missing');
    }

    public function testHandleRecordsDebrisValues(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc')[0];
        $this->assertStringContainsString('250000', $sql, 'Debris metal missing');
        $this->assertStringContainsString('120000', $sql, 'Debris crystal missing');
    }

    public function testHandleRecordsMoonCreationChance(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc')[0];
        $this->assertStringContainsString('12', $sql, 'Moon chance (12) missing from RC insert');
    }

    public function testHandleRecordsUnitLosses(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc')[0];
        $this->assertStringContainsString('2500000', $sql, 'Attacker losses missing');
        $this->assertStringContainsString('5000000', $sql, 'Defender losses missing');
    }

    // -------------------------------------------------------------------------
    // Handler — round records (TABLE_PARSEDRCROUND)
    // -------------------------------------------------------------------------

    public function testHandleInsertsThreeRoundRecords(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $rounds = $this->db->getQueriesContaining('game_rc_round');
        $inserts = array_values(array_filter($rounds, static fn(string $q) =>
            stripos($q, 'INSERT') !== false &&
            stripos($q, 'game_rc_round_attack')  === false &&
            stripos($q, 'game_rc_round_defense') === false
        ));

        $this->assertCount(3, $inserts, 'Expected exactly 3 round INSERTs');
    }

    public function testRoundRecordsContainStatistics(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $rounds = array_values(array_filter(
            $this->db->getQueriesContaining('game_rc_round'),
            static fn(string $q) =>
                stripos($q, 'INSERT') !== false &&
                stripos($q, 'game_rc_round_attack')  === false &&
                stripos($q, 'game_rc_round_defense') === false
        ));

        // Round 0: attacker hits=155, strength=8 500 000
        $this->assertStringContainsString('155', $rounds[0], 'Round 0 attacker hits expected');
        $this->assertStringContainsString('8500000', $rounds[0], 'Round 0 attacker strength expected');
    }

    // -------------------------------------------------------------------------
    // Handler — per-round fleet records
    // -------------------------------------------------------------------------

    public function testHandleInsertsAttackRoundRecordsPerRound(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $attacks = $this->db->getQueriesContaining('game_rc_round_attack');
        $this->assertCount(3, $attacks, 'Expected one attack-round INSERT per combat round');
    }

    public function testHandleInsertsDefenseRoundRecordsPerRound(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $defenses = $this->db->getQueriesContaining('game_rc_round_defense');
        $this->assertCount(3, $defenses, 'Expected one defense-round INSERT per combat round');
    }

    public function testAttackRoundRecordContainsCleColumn(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc_round_attack')[0];
        $this->assertStringContainsString('`CLE`', $sql, 'CLE column expected in attack round record');
        // Round 0 attacker: CLE remaining = 90
        $this->assertStringContainsString('90', $sql);
    }

    public function testDefenseRoundRecordContainsLmColumn(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $sql = $this->db->getQueriesContaining('game_rc_round_defense')[0];
        $this->assertStringContainsString('`LM`', $sql, 'LM column expected in defense round record');
        // Round 0 defender: LM remaining = 30
        $this->assertStringContainsString('30', $sql);
    }

    // -------------------------------------------------------------------------
    // Handler — IO response
    // -------------------------------------------------------------------------

    public function testHandleSetsRcIoResponse(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $response = $this->getIoResponse();
        $this->assertSame('rc', $response['type']);
    }

    // -------------------------------------------------------------------------
    // Handler — grant denial
    // -------------------------------------------------------------------------

    public function testHandleDeniedWithoutMessagesGrant(): void
    {
        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandlerWithoutGrant('CombatReportHandler', 'messages');
        $handler->handle($data);

        $this->assertEmpty(
            $this->db->getQueriesContaining('INSERT'),
            'No INSERTs should occur when messages grant is denied'
        );

        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame('messages',     $response['access']);
    }

    // -------------------------------------------------------------------------
    // Handler — duplicate RC (same timestamp) is skipped
    // -------------------------------------------------------------------------

    public function testHandleSkipsDuplicateCombatReport(): void
    {
        // Simulate the DB already having this RC by returning a non-null id
        // Override sql_fetch_row to simulate existing record
        $this->db = new class extends SpyDatabase {
            public function sql_fetch_row($result): array { return [42]; } // id_rc = 42
        };
        $GLOBALS['db'] = $this->db;

        $data    = $this->getDecodedData('rc_complex');
        $handler = $this->createHandler('CombatReportHandler');
        $handler->handle($data);

        $this->assertEmpty(
            $this->db->getQueriesContaining('INSERT'),
            'Duplicate RC must not produce any INSERT'
        );
    }
}
