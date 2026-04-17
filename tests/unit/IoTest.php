<?php

/**
 * Unit tests for the Io response-builder class.
 * All tests run immediately (no handler stubs needed).
 */
class IoTest extends XtenseTestCase
{
    // -------------------------------------------------------------------------
    // set() / del()
    // -------------------------------------------------------------------------

    public function testSetSingleValue(): void
    {
        $this->io->set('type', 'hello');
        $output = $this->getIoResponse();
        $this->assertSame('hello', $output['type']);
    }

    public function testSetArray(): void
    {
        $this->io->set(['type' => 'hello', 'page' => 'overview']);
        $output = $this->getIoResponse();
        $this->assertSame('hello',    $output['type']);
        $this->assertSame('overview', $output['page']);
    }

    public function testDel(): void
    {
        $this->io->set('type', 'hello');
        $this->io->del('type');
        $output = $this->getIoResponse();
        $this->assertArrayNotHasKey('type', $output);
    }

    // -------------------------------------------------------------------------
    // status() / send()
    // -------------------------------------------------------------------------

    public function testStatusSetsStatusField(): void
    {
        $this->io->status(0);
        $output = $this->getIoResponse();
        $this->assertSame(0, $output['status']);
    }

    public function testSendOutputsValidJson(): void
    {
        $this->io->set('type', 'test_type');
        $this->io->status(4);

        ob_start();
        $this->io->send();
        $raw = ob_get_clean();

        $decoded = json_decode($raw, true);
        $this->assertNotNull($decoded, 'send() must output valid JSON');
        $this->assertSame('test_type', $decoded['type']);
        $this->assertSame(4,           $decoded['status']);
    }

    public function testSendWithStatusArgOverridesStoredStatus(): void
    {
        $this->io->status(0);

        ob_start();
        $this->io->send(4);
        $output = json_decode(ob_get_clean(), true);

        $this->assertSame(4, $output['status']);
    }

    // -------------------------------------------------------------------------
    // append_call()
    // -------------------------------------------------------------------------

    public function testAppendCallAddsToSuccessList(): void
    {
        $call = ['id' => 'mod-test', 'title' => 'Test Mod'];
        $this->io->append_call($call, Io::SUCCESS);
        $output = $this->getIoResponse();
        $this->assertContains('Test Mod', $output['calls']['success']);
    }

    public function testAppendCallDuplicateIdIsIgnored(): void
    {
        $call = ['id' => 'mod-test', 'title' => 'Test Mod'];
        $this->io->append_call($call, Io::SUCCESS);
        $this->io->append_call($call, Io::SUCCESS); // same id → must not be added again
        $output = $this->getIoResponse();
        $this->assertCount(1, $output['calls']['success']);
    }

    // -------------------------------------------------------------------------
    // append_call_error()
    // -------------------------------------------------------------------------

    public function testAppendCallErrorAddsToErrorList(): void
    {
        $call = ['id' => 'mod-err', 'title' => 'Error Mod', 'root' => 'testmod'];
        $this->io->append_call_error($call, 'Something failed');
        $output = $this->getIoResponse();
        $this->assertContains('Error Mod', $output['calls']['error']);
    }

    public function testAppendCallErrorDoesNotAddToSuccessList(): void
    {
        $call = ['id' => 'mod-err', 'title' => 'Error Mod', 'root' => 'testmod'];
        $this->io->append_call_error($call, 'Something failed');
        $output = $this->getIoResponse();
        $this->assertEmpty($output['calls']['success']);
    }
}
