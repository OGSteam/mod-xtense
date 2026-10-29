<?php

class MessageSubtypeHandlerTest extends XtenseTestCase
{
    public function testAllyMessageRegistersCallback(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'ally_msg',
            'from' => 'Marshal Galileo',
            'tag' => 'SLD',
            'message' => 'Alliance message',
            'date' => 1776500000,
        ]);

        $this->assertTrue($this->callbackHandler->hasCallForType('ally_msg'));
        $this->assertSame('SLD', $this->callbackHandler->getCallsForType('ally_msg')[0]['params']['tag']);
    }

    public function testSpyReportPersistsAndRegistersCallback(): void
    {
        $data = $this->getDecodedData('messages_spy');
        $handler = $this->createHandler('MessageHandler');
        $handler->handle($data);

        $this->assertTrue($this->callbackHandler->hasCallForType('spy'));
        $this->assertNotEmpty($this->db->getQueriesContaining('INSERT INTO ogspy_game_spy'));
        $this->assertNotEmpty($this->db->getQueriesContaining('INSERT INTO ogspy_game_astro_object'));
        $this->assertSame('messages', $this->getIoResponse()['type']);
    }

    public function testEnemySpyRegistersCallbackAndPersistsReport(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'ennemy_spy',
            'from' => '4:246:12',
            'to' => '4:246:7',
            'proba' => '65',
            'date' => 1776500000,
        ]);

        $this->assertTrue($this->callbackHandler->hasCallForType('ennemy_spy'));
        $this->assertNotEmpty($this->db->getQueriesContaining('ogspy_parsedSpyEn'));
    }

    public function testRecyclerReportRegistersNormalizedCallback(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'rc_cdr',
            'nombre' => '5',
            'coords' => '4:246:12',
            'M_recovered' => '1200',
            'C_recovered' => '800',
            'M_total' => '2500',
            'C_total' => '1700',
            'date' => 1776500000,
        ]);

        $this->assertTrue($this->callbackHandler->hasCallForType('rc_cdr'));
        $params = $this->callbackHandler->getCallsForType('rc_cdr')[0]['params'];
        $this->assertSame(1200, $params['M_reco']);
        $this->assertNotEmpty($this->db->getQueriesContaining('ogspy_parsedRec'));
    }

    public function testExpeditionRegistersExpeditionCallback(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'expedition_shared',
            'coords' => '4:246:16',
            'content' => 'Resources found',
            'date' => 1776500000,
        ]);

        $this->assertTrue($this->callbackHandler->hasCallForType('expedition_shared'));
        $params = $this->callbackHandler->getCallsForType('expedition_shared')[0]['params'];
        $this->assertSame(['4', '246', '16'], $params['coords']);
    }
}
