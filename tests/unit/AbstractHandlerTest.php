<?php

/**
 * Concrete stub of AbstractHandler used exclusively for testing its
 * protected helper methods. Public "call*" wrappers delegate to the
 * protected methods so assertions can be made without reflection.
 */
class ConcreteTestHandler extends AbstractHandler
{
    public function handle(array $data): void {}
    public function getType(): string         { return 'test'; }
    public function getRequiredGrant(): string { return 'empire'; }

    public function callRequireGrant(string $grant): bool
    {
        return $this->requireGrant($grant);
    }

    public function callParseCoordinates(string $coords, int $exp = 0): array
    {
        return $this->parseCoordinates($coords, $exp);
    }

    public function callResolvePlanetType($type): string
    {
        return $this->resolvePlanetType($type);
    }

    public function callPlanetTypeToString(string $const): string
    {
        return $this->planetTypeToString($const);
    }

    public function callExecuteUpsert(string $table, array $cols, array $vals, array $updateCols): void
    {
        $this->executeUpsert($table, $cols, $vals, $updateCols);
    }

    public function callRegisterCallback(string $type, array $params): void
    {
        $this->registerCallback($type, $params);
    }

    public function callLogAction(string $type, array $context = []): void
    {
        $this->logAction($type, $context);
    }

    public function callSetPageUpdatedResponse(string $page, string $coords): void
    {
        $this->setPageUpdatedResponse($page, $coords);
    }
}

/**
 * Unit tests for AbstractHandler shared helper methods.
 * All tests run immediately (no handler stubs needed).
 */
class AbstractHandlerTest extends XtenseTestCase
{
    private ConcreteTestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new ConcreteTestHandler(
            $this->db,
            $this->log,
            $this->io,
            $this->callbackHandler,
            $this->serverConfig,
            $this->userData,
            $this->xtenseDatabase,
            $this->toolbarInfo
        );
    }

    // -------------------------------------------------------------------------
    // requireGrant()
    // -------------------------------------------------------------------------

    public function testRequireGrantReturnsTrueWhenGrantPresent(): void
    {
        $result = $this->handler->callRequireGrant('empire');
        $this->assertTrue($result);
    }

    public function testRequireGrantReturnsFalseWhenGrantMissing(): void
    {
        $userData               = $this->userData;
        $userData['grant']['empire'] = false;
        $handler = new ConcreteTestHandler(
            $this->db, $this->log, $this->io, $this->callbackHandler,
            $this->serverConfig, $userData, $this->xtenseDatabase, $this->toolbarInfo
        );

        $this->assertFalse($handler->callRequireGrant('empire'));
    }

    public function testRequireGrantSetsIoErrorWhenDenied(): void
    {
        $userData                    = $this->userData;
        $userData['grant']['empire'] = false;
        $handler = new ConcreteTestHandler(
            $this->db, $this->log, $this->io, $this->callbackHandler,
            $this->serverConfig, $userData, $this->xtenseDatabase, $this->toolbarInfo
        );

        $handler->callRequireGrant('empire');
        $response = $this->getIoResponse();

        $this->assertSame('plugin grant', $response['type']);
        $this->assertSame('empire',       $response['access']);
        $this->assertSame(0,              $response['status']);
    }

    // -------------------------------------------------------------------------
    // parseCoordinates()
    // -------------------------------------------------------------------------

    public function testParseCoordinatesReturnsStructuredArray(): void
    {
        $result = $this->handler->callParseCoordinates('4:246:12');

        $this->assertSame('4:246:12', $result['coords']);
        $this->assertSame(4,          $result['galaxy']);
        $this->assertSame(246,        $result['system']);
        $this->assertSame(12,         $result['row']);
    }

    public function testParseCoordinatesInvalidFormatThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->handler->callParseCoordinates('not:a:valid:coord');
    }

    // -------------------------------------------------------------------------
    // resolvePlanetType()
    // -------------------------------------------------------------------------

    public function testResolvePlanetTypeZeroReturnsPlanet(): void
    {
        $this->assertSame(TYPE_PLANET, $this->handler->callResolvePlanetType(0));
        $this->assertSame(TYPE_PLANET, $this->handler->callResolvePlanetType('0'));
    }

    public function testResolvePlanetTypeOneReturnsMoon(): void
    {
        $this->assertSame(TYPE_MOON, $this->handler->callResolvePlanetType(1));
    }

    // -------------------------------------------------------------------------
    // planetTypeToString()
    // -------------------------------------------------------------------------

    public function testPlanetTypeToStringReturnsPlanet(): void
    {
        $this->assertSame('planet', $this->handler->callPlanetTypeToString(TYPE_PLANET));
    }

    public function testPlanetTypeToStringReturnsMoon(): void
    {
        $this->assertSame('moon', $this->handler->callPlanetTypeToString(TYPE_MOON));
    }

    // -------------------------------------------------------------------------
    // executeUpsert()
    // -------------------------------------------------------------------------

    public function testExecuteUpsertBuildsInsertOnDuplicateKeyQuery(): void
    {
        $this->handler->callExecuteUpsert(
            'ogspy_test_table',
            ['id', 'name'],
            [1,    'test-value'],
            ['name']
        );

        $queries = $this->db->getQueries();
        $this->assertCount(1, $queries);

        $sql = $queries[0];
        $this->assertStringContainsString('INSERT INTO ogspy_test_table', $sql);
        $this->assertStringContainsString('`id`, `name`',                  $sql);
        $this->assertStringContainsString("'test-value'",                   $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE',        $sql);
        $this->assertStringContainsString('`name` = VALUES(`name`)',        $sql);
    }

    public function testExecuteUpsertQuotesStringsButNotIntegers(): void
    {
        $this->handler->callExecuteUpsert(
            'ogspy_test_table',
            ['id', 'val'],
            [42, 99],
            ['val']
        );

        $sql = $this->db->getLastQuery();
        // Integer 42 should appear without quotes (it is the first value after opening paren)
        $this->assertStringContainsString('(42,',  $sql);
        // Integer 99 should appear without quotes
        $this->assertStringContainsString(', 99)', $sql);
        // Neither should be wrapped in quotes
        $this->assertStringNotContainsString("'42'", $sql);
    }

    // -------------------------------------------------------------------------
    // registerCallback()
    // -------------------------------------------------------------------------

    public function testRegisterCallbackDelegatesToCallbackHandler(): void
    {
        $this->handler->callRegisterCallback('buildings', ['coords' => [4, 246, 12]]);

        $this->assertTrue($this->callbackHandler->hasCallForType('buildings'));
        $calls = $this->callbackHandler->getCallsForType('buildings');
        $this->assertSame([4, 246, 12], $calls[0]['params']['coords']);
    }

    // -------------------------------------------------------------------------
    // logAction()
    // -------------------------------------------------------------------------

    public function testLogActionDoesNotThrow(): void
    {
        $this->handler->callLogAction(
            'buildings',
            ['coords' => '4:246:12', 'planet_name' => 'test planet']
        );
        $this->addToAssertionCount(1); // explicit: reaching here without exception is the assertion
    }

    // -------------------------------------------------------------------------
    // setPageUpdatedResponse()
    // -------------------------------------------------------------------------

    public function testSetPageUpdatedResponseSetsCorrectIoFields(): void
    {
        $this->handler->callSetPageUpdatedResponse('buildings', '4:246:12');
        $response = $this->getIoResponse();

        $this->assertSame('home updated', $response['type']);
        $this->assertSame('buildings',    $response['page']);
        $this->assertSame('4:246:12',     $response['planet']);
    }
}
