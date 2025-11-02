<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Include your classes (adjust path as needed)
require_once __DIR__ . '/../src/classes.php';


class NameAndPathTest extends TestCase
{
    protected function setUp(): void
    {
        Name::_resetPool();
        Path::_reset();
        Path::setDirSep("/");
    }

    public function testSamePathReturnsSameInstance(): void
    {
        $p1 = Path::fromString('/var/www/html/index.php');
        $p2 = Path::fromString('/var/www/html/index.php');

        $this->assertSame($p1, $p2);
        $this->assertSame('/var/www/html/index.php', (string)$p1);


        Path::setDirSep("\\");

        $p1 = Path::fromString('L:\VariousDev\FileGrimReaper\index.php');
        $p2 = Path::fromString('L:\VariousDev\FileGrimReaper\index.php');

        $this->assertSame($p1, $p2);
        $this->assertSame('L:\VariousDev\FileGrimReaper\index.php', (string)$p1);
    }


    public function testDifferentPathsReturnDifferentInstances(): void
    {
        $p1 = Path::fromString('/a/b/c');
        $p2 = Path::fromString('/a/b/d');

        $this->assertNotSame($p1, $p2);
    }

    public function testCacheHitAvoidsSegmentCreation(): void
    {
        Name::_resetPool();
        Path::_reset();

        // First call: creates segments
        $p1 = Path::fromString('/a/b/c');

        // Spy on Name::get calls
        $calls = 0;
        $reflection = new ReflectionClass(Name::class);
        $pool = $reflection->getProperty('pool');
        $pool->setAccessible(true);
        $original = $pool->getValue();

        // Second call: should NOT call Name::get
        $p2 = Path::fromString('/a/b/c');

        $this->assertSame($p1, $p2);
        $this->assertSame($original, $pool->getValue()); // no new Name objects
    }


    public function testNameReturnsSameInstanceForSameString(): void
    {
        $a = Name::get('config');
        $b = Name::get('config');
        $this->assertSame($a, $b);
    }

    public function testNameReturnsDifferentInstancesForDifferentStrings(): void
    {
        $a = Name::get('users');
        $b = Name::get('admins');
        $this->assertNotSame($a, $b);
    }

    public function testPathUsesInternedNames(): void
    {
        Path::setDirSep("/");
        $p1 = Path::fromString('/a/b/c');
        $p2 = Path::fromString('/a/d/c');

        $this->assertEquals($p2->getDepth(), 3);

        $s1 = $p1->getSegments();
        $s2 = $p2->getSegments();

        $this->assertSame($s1[0], $s2[0]); // 'a'
        $this->assertSame($s1[2], $s2[2]); // 'c'
        $this->assertNotSame($s1[1], $s2[1]); // 'b' vs 'd'
    }

    public function testPathCanBeUsedAsSplObjectStorageKey(): void
    {
        $storage = new SplObjectStorage();


        $path = Path::fromString('/etc/config/app.conf');
        $file = Name::get('app.conf');

        $storage[$path] ??= new SplObjectStorage();
        $storage[$path][$file] = [0 => 123];

        $this->assertArrayHasKey($file, $storage[$path]);
        $this->assertEquals(123, $storage[$path][$file][0]);
    }

    public function testMemoryUsageIsLow(): void
    {
        $start = memory_get_peak_usage();
        $storage = new SplObjectStorage();

        for ($i = 0; $i < 100_000; $i++) {
            $path = Path::fromString("/var/data/$i/file.txt");
            $file = Name::get('file.txt');
            $storage[$path] ??= new SplObjectStorage();
            $storage[$path][$file] = [0 => 1];
        }
        $this->assertEquals(count($storage), 100_000);

        // test SplObjectStorage behaves as expected
        foreach($storage as $path) {
            $this->assertStringStartsWith("/var", (String)$path);
            $this->assertTrue(isset($storage[$path]));

            $dirItems = $storage[$path];
            foreach($dirItems as $fileName) {
                $times = $dirItems[$fileName];

                $this->assertSame("file.txt", (string)$fileName);
                $this->assertEquals($storage[$path][$fileName][0], 1);
                $this->assertEquals($times[0], 1);
                $this->assertTrue(isset($storage[$path][$fileName]));
                unset($storage[$path][$fileName]);
                $this->assertFalse(isset($storage[$path][$fileName]));

            }

        }

        $usedMb = (memory_get_peak_usage() - $start) / 1024 / 1024;
        $this->assertLessThan(200, $usedMb, "Used: {$usedMb} MB");
    }

    public function testNamePoolIsPopulatedCorrectly(): void
    {
        Name::get('tmp');
        Name::get('log');
        Name::get('tmp');           // duplicate

        $ref   = new ReflectionClass(Name::class);
        $prop  = $ref->getProperty('pool');
        $prop->setAccessible(true);
        $pool = $prop->getValue();

        $this->assertCount(2, $pool);
        $this->assertArrayHasKey('tmp', $pool);
        $this->assertArrayHasKey('log', $pool);
    }

    public function testPathHandlesDifferentPaths(): void
    {
        $a = Path::fromString('/home/user/docs');
        $b = Path::fromString('/home/user/downloads');

        $this->assertNotSame($a, $b);
        $this->assertSame('/home/user/docs', (string)$a);
        $this->assertSame('/home/user/downloads', (string)$b);
    }

    public function testPathNormalizesSlashesAndEmptyParts(): void
    {
        $cases = [
            '/var/www//'      => '/var/www',
            '///tmp//'        => '/tmp',
            '/usr/local/bin/' => '/usr/local/bin',
            'relative/path'   => '/relative/path',
            ''                => '/',
            '/'               => '/',
        ];

        foreach ($cases as $input => $expected) {
            $p = Path::fromString($input);
            $this->assertSame($expected, (string)$p, "Failed on '$input'");
        }
    }

}


