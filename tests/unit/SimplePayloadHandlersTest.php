<?php

class SimplePayloadHandlersTest extends XtenseTestCase
{
    public function testDefenseHandlerStoresDefenseAndReturnsPageUpdate(): void
    {
        $handler = $this->createHandler('DefenseHandler');
        $handler->handle([
            'planet' => ['coords' => '4:246:12', 'name' => 'Home', 'id' => 33717002, 'type' => 0],
            'defense' => ['LM' => 15, 'PB' => 1],
        ]);

        $queries = $this->db->getQueriesContaining('game_player_defense');
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('`LM`', $queries[0]);
        $this->assertSame('defense', $this->getIoResponse()['page']);
        $this->assertTrue($this->callbackHandler->hasCallForType('defense'));
    }

    public function testFleetHandlerStoresFleetAndProductionValues(): void
    {
        $handler = $this->createHandler('FleetHandler');
        $handler->handle([
            'planet' => ['coords' => '4:246:12', 'name' => 'Home', 'id' => 33717002, 'type' => 0],
            'fleet' => ['PT' => 5, 'SAT' => 2],
        ]);

        $this->assertNotEmpty($this->db->getQueriesContaining('game_player_fleet'));
        $productionQueries = $this->db->getQueriesContaining('game_astro_object');
        $this->assertNotEmpty($productionQueries);
        $this->assertStringContainsString('`SAT`', end($productionQueries));
        $this->assertSame('fleet', $this->getIoResponse()['page']);
        $this->assertTrue($this->callbackHandler->hasCallForType('fleet'));
    }

    public function testAllyListHandlerBuildsCallbackAndResponse(): void
    {
        $handler = $this->createHandler('AllyListHandler');
        $handler->handle([
            'tag' => 'SLD',
            'allyList' => [[
                'player' => 'Marshal Galileo',
                'points' => '12345',
                'rank' => '42',
                'coords' => '4:246:12',
            ]],
        ]);

        $calls = $this->callbackHandler->getCallsForType('ally_list');
        $this->assertCount(1, $calls);
        $this->assertSame('SLD', $calls[0]['params']['tag']);
        $this->assertSame(['4', '246', '12'], $calls[0]['params']['list'][0]['coords']);
        $this->assertSame('ally_list', $this->getIoResponse()['type']);
    }
}
