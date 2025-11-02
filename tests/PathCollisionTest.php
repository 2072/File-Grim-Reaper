<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Include your classes (adjust path as needed)
require_once __DIR__ . '/../src/classes.php';



class PathCollisionTest extends TestCase
{
    private ReflectionProperty $crcCache;
    private ReflectionProperty $fullCache;

    protected function setUp(): void
    {
        Name::_resetPool();
        Path::_reset();

        $ref = new ReflectionClass(Path::class);
        $this->crcCache  = $ref->getProperty('crcCache');
        $this->fullCache = $ref->getProperty('fullCache');
        $this->crcCache->setAccessible(true);
        $this->fullCache->setAccessible(true);
    }

    public function testCollisionMovesBothToFullCache(): void
    {
        $pathA = '/collision/path/a';
        $pathB = '/collision/path/b';
        $forcedCrc = 0x12345678;

        // 1. First path — uses forced CRC
        $pA = Path::fromString($pathA, $forcedCrc);
        $this->assertSame($pathA, (string)$pA);

        $crcMap = $this->crcCache->getValue();
        $this->assertArrayHasKey($forcedCrc, $crcMap);
        $this->assertSame($pA, $crcMap[$forcedCrc]);

        $fullMap = $this->fullCache->getValue();
        $this->assertCount(0, $fullMap);

        // 2. Second path — same forced CRC → collision!
        $pB = Path::fromString($pathB, $forcedCrc);
        $this->assertSame($pathB, (string)$pB);
        $this->assertNotSame($pA, $pB);

        // CRC cache must be cleaned
        $crcMap = $this->crcCache->getValue();
        $this->assertArrayNotHasKey($forcedCrc, $crcMap);

        // Both in full cache
        $fullMap = $this->fullCache->getValue();
        $this->assertCount(2, $fullMap);
        $this->assertArrayHasKey($pathA, $fullMap);
        $this->assertArrayHasKey($pathB, $fullMap);
        $this->assertSame($pA, $fullMap[$pathA]);
        $this->assertSame($pB, $fullMap[$pathB]);

        // 3. Future calls hit full cache
        $pA2 = Path::fromString($pathA); // no forceCrc
        $this->assertSame($pA, $pA2);
    }
}
