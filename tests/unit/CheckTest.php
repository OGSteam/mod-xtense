<?php

/**
 * Unit tests for the Check static validation class.
 * All tests run immediately (no handler stubs needed).
 */
class CheckTest extends XtenseTestCase
{
    // -------------------------------------------------------------------------
    // Check::coords()
    // -------------------------------------------------------------------------

    public function testCoordsValidFormatReturnsString(): void
    {
        $result = Check::coords('4:246:12');
        $this->assertSame('4:246:12', $result);
    }

    public function testCoordsInvalidFormatThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Check::coords('abc');
    }

    public function testCoordsEmptyStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Check::coords('');
    }

    public function testCoordsGalaxyOutOfBoundsReturnsNull(): void
    {
        // num_of_galaxies = 9; galaxy 10 is out of bounds → method returns null implicitly
        $result = Check::coords('10:246:12');
        $this->assertNull($result);
    }

    public function testCoordsRowAbove15ReturnsNull(): void
    {
        // Non-expedition row > 15 is invalid
        $result = Check::coords('4:246:16');
        $this->assertNull($result);
    }

    public function testCoordsExpeditionSlot16IsValid(): void
    {
        // With $exp = 1, row 16 is allowed
        $result = Check::coords('4:246:16', 1);
        $this->assertSame('4:246:16', $result);
    }

    public function testCoordsExpeditionRequiresExactlyRow16(): void
    {
        // With $exp = 1, row != 16 is invalid
        $result = Check::coords('4:246:12', 1);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Check::player_status()
    // -------------------------------------------------------------------------

    public function testPlayerStatusValidPatternsReturnOne(): void
    {
        $this->assertSame(1, Check::player_status('vf'));
        $this->assertSame(1, Check::player_status('I'));
        $this->assertSame(1, Check::player_status(''));   // empty string is valid
    }

    public function testPlayerStatusInvalidPatternReturnsZero(): void
    {
        $this->assertSame(0, Check::player_status('xyz'));
    }

    // -------------------------------------------------------------------------
    // Check::player_status_forbidden()
    // -------------------------------------------------------------------------

    public function testPlayerStatusForbiddenMatchesPhPattern(): void
    {
        $this->assertSame(1, Check::player_status_forbidden('ph'));
        $this->assertSame(1, Check::player_status_forbidden(''));   // empty also matches ^[ph]*$
    }

    public function testPlayerStatusForbiddenDoesNotMatchNormalStatus(): void
    {
        $this->assertSame(0, Check::player_status_forbidden('vf'));
        $this->assertSame(0, Check::player_status_forbidden('I'));
    }

    // -------------------------------------------------------------------------
    // Check::universe()
    // -------------------------------------------------------------------------

    public function testUniverseExtractsUrlFromFullUri(): void
    {
        $result = Check::universe('https://s277-fr.ogame.gameforge.com/');
        $this->assertSame('https://s277-fr.ogame.gameforge.com', $result);
    }

    public function testUniverseReturnsFalseForNonMatchingInput(): void
    {
        $this->assertFalse(Check::universe('not-a-universe-url'));
        $this->assertFalse(Check::universe(''));
    }
}
