<?php

declare(strict_types=1);

use App\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testNormalizeHandlesLegacyAndSemverInputs(): void
    {
        $this->assertNull(Version::normalize(null));
        $this->assertSame('0.7', Version::normalize('7'));
        $this->assertSame('0.9', Version::normalize('0.9'));
        $this->assertSame('1.2.0', Version::normalize('v1.2'));
        $this->assertSame('1.2.3', Version::normalize('001.02.003.4'));
        $this->assertSame('beta-1', Version::normalize('beta-1'));
    }

    public function testCompareReturnsExpectedOrdering(): void
    {
        $this->assertSame(-1, Version::compare('1.2.3', '1.2.4'));
        $this->assertSame(1, Version::compare('2.0.0', '1.9.9'));
        $this->assertSame(0, Version::compare('v1.2', '1.2.0'));
    }

    public function testIsLegacyDetectsZeroMajorTwoPartVersions(): void
    {
        $this->assertTrue(Version::isLegacy('9'));
        $this->assertTrue(Version::isLegacy('0.12'));
        $this->assertFalse(Version::isLegacy('1.0.0'));
        $this->assertFalse(Version::isLegacy(null));
    }

    public function testIsEmptyCoversNullZeroAndBlankValues(): void
    {
        $this->assertTrue(Version::isEmpty(null));
        $this->assertTrue(Version::isEmpty(0));
        $this->assertTrue(Version::isEmpty('0'));
        $this->assertTrue(Version::isEmpty('   '));
        $this->assertFalse(Version::isEmpty('1.0'));
    }

    public function testToSemverNormalizesAndDefaults(): void
    {
        $this->assertSame('1.2.0', Version::toSemver('1.2'));
        $this->assertSame('0.7.0', Version::toSemver('7'));
        $this->assertSame(Version::DEFAULT, Version::toSemver(null));
    }

    public function testBumpIncrementsRequestedPart(): void
    {
        $this->assertSame('2.0.0', Version::bump('1.2.3', 'major'));
        $this->assertSame('1.3.0', Version::bump('1.2.3', 'minor'));
        $this->assertSame('1.2.4', Version::bump('1.2.3', 'patch'));
        $this->assertSame('1.2.4', Version::bump('1.2.3', 'unknown'));
    }

    public function testNextReturnsDefaultForEmptyAndLegacyOtherwiseBumps(): void
    {
        $this->assertSame(Version::DEFAULT, Version::next(null));
        $this->assertSame(Version::DEFAULT, Version::next('9'));
        $this->assertSame('1.2.4', Version::next('1.2.3'));
        $this->assertSame('1.3.0', Version::next('1.2.3', 'minor'));
    }
}
