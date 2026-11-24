<?php

use PHPUnit\Framework\Attributes\DataProvider;

class LifeformHandlerTest extends XtenseTestCase
{
    private array $planet = [
        'id' => '33717423',
        'name' => 'KEMPA III',
        'coords' => '4:246:7',
        'type' => '0',
    ];

    private function createSpeciesRecordingHandler(array &$observations): LifeformHandler
    {
        return new class(
            $observations,
            $this->db,
            $this->log,
            $this->io,
            $this->callbackHandler,
            $this->serverConfig,
            $this->userData,
            $this->xtenseDatabase,
            $this->toolbarInfo
        ) extends LifeformHandler {
            public function __construct(
                private array &$observations,
                ...$dependencies
            ) {
                parent::__construct(...$dependencies);
            }

            protected function storeLifeformSpecies(
                int $planetId,
                mixed $species,
                int $observedAt
            ): void {
                $this->observations[] = compact('planetId', 'species', 'observedAt');
            }
        };
    }

    public function testStoresRealLifeformBuildingIdsTransactionally(): void
    {
        $handler = $this->createHandler('LifeformHandler');
        $handler->handle([
            '_xtense_request_type' => 'lf_buildings',
            'planet' => $this->planet,
            'lfBuildings' => ['11101' => '12', '12101' => '8', '13101' => '4', '14101' => '2'],
        ]);

        $queries = $this->db->getQueries();
        $this->assertContains('TRANSACTION BEGIN', $queries);
        $this->assertContains('TRANSACTION COMMIT', $queries);
        $insert = $this->db->getQueriesContaining('INSERT INTO ogspy_game_lifeform_building')[0];
        $this->assertStringContainsString('(33717423, 11101, 12,', $insert);
        $this->assertStringContainsString('(33717423, 14101, 2,', $insert);

        $response = $this->getIoResponse();
        $this->assertSame('lf_buildings', $response['page']);
        $this->assertSame('4:246:7', $response['planet']);
    }

    public function testStoresAllEighteenMixedSpeciesResearchSlots(): void
    {
        $levels = [];
        foreach ([11201, 11202, 11203, 11204, 11205, 12201, 12202, 12203, 12204,
                  12205, 13201, 13202, 13203, 13204, 14201, 14202, 14203, 14204] as $index => $id) {
            $levels[(string)$id] = (string)($index + 1);
        }

        $handler = $this->createHandler('LifeformHandler');
        $handler->handle([
            '_xtense_request_type' => 'lf_research',
            'planet' => $this->planet,
            'lfResearch' => $levels,
        ]);

        $insert = $this->db->getQueriesContaining('INSERT INTO ogspy_game_lifeform_research')[0];
        $this->assertSame(18, substr_count($insert, '(33717423,'));
        $this->assertTrue($this->callbackHandler->hasCallForType('lf_research'));
    }

    public function testStoresSpeciesSentWithLifeformBuildings(): void
    {
        $observations = [];
        $handler = $this->createSpeciesRecordingHandler($observations);
        $handler->handle([
            '_xtense_request_type' => 'lf_buildings',
            'planet' => $this->planet,
            'lfBuildings' => ['12101' => '8'],
            'species' => 'ROCKTAL',
        ]);

        $this->assertCount(1, $observations);
        $this->assertSame(33717423, $observations[0]['planetId']);
        $this->assertSame('ROCKTAL', $observations[0]['species']);
        $this->assertGreaterThan(0, $observations[0]['observedAt']);
    }

    #[DataProvider('invalidLevelsProvider')]
    public function testRejectsInvalidOrDestructivePayloads(array $levels): void
    {
        $this->expectException(UnexpectedValueException::class);

        $handler = $this->createHandler('LifeformHandler');
        try {
            $handler->handle([
                '_xtense_request_type' => 'lf_buildings',
                'planet' => $this->planet,
                'lfBuildings' => $levels,
            ]);
        } finally {
            $this->assertEmpty($this->db->getQueriesContaining('DELETE FROM ogspy_game_lifeform_building'));
        }
    }

    public static function invalidLevelsProvider(): array
    {
        return [
            'empty snapshot' => [[]],
            'invalid technology ID' => [['not-an-id' => '4']],
            'negative level' => [['11101' => '-1']],
            'fractional level' => [['11101' => '1.5']],
            'level exceeds schema' => [['11101' => '65536']],
            'more than 18 entries' => [array_fill_keys(range(11001, 11019), '1')],
        ];
    }

    public function testRequiresEmpireGrant(): void
    {
        $handler = $this->createHandlerWithoutGrant('LifeformHandler', 'empire');
        $handler->handle([
            '_xtense_request_type' => 'lf_buildings',
            'planet' => $this->planet,
            'lfBuildings' => ['11101' => '1'],
        ]);

        $this->assertEmpty($this->db->getQueriesContaining('lifeform_building'));
        $response = $this->getIoResponse();
        $this->assertSame('plugin grant', $response['type']);
    }

    public function testRejectsAnUnsupportedLifeformCategory(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->createHandler('LifeformHandler')->handle([
            '_xtense_request_type' => 'lf_unknown',
        ]);
    }
}
