<?php

class MessageHandlerTest extends XtenseTestCase
{
    public function testPersonalMessageRegistersExpectedCallback(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'msg',
            'coords' => '4:246:12',
            'from' => 'Marshal Galileo',
            'subject' => 'Fleet movement',
            'message' => 'Fleet arrives soon',
            'date' => 1776500000,
        ]);

        $this->assertTrue($this->callbackHandler->hasCallForType('msg'));
        $params = $this->callbackHandler->getCallsForType('msg')[0]['params'];
        $this->assertSame(['4', '246', '12'], $params['coords']);
        $this->assertSame('Fleet arrives soon', $params['message']);
        $this->assertSame('messages', $this->getIoResponse()['type']);
    }

    public function testReturnAsSpyChangesResponseType(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $handler->handle([
            'type' => 'msg',
            'coords' => '4:246:12',
            'from' => 'Marshal Galileo',
            'subject' => 'Fleet movement',
            'message' => 'Fleet arrives soon',
            '_xtense_return_as' => 'spy',
        ]);

        $this->assertSame('spy', $this->getIoResponse()['type']);
    }

    public function testUnknownMessageTypeThrows(): void
    {
        $handler = $this->createHandler('MessageHandler');
        $this->expectException(UnexpectedValueException::class);
        $handler->handle(['type' => 'unknown']);
    }
}
