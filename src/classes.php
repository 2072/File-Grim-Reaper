<?php
declare(strict_types=1);

/* File Grim Reaper v1.6 - It will reap your files!
 * (c) 2011-2025 John Wellesz
 *
 *  This file is part of File Grim Reaper.
 *
 *  Project home:
 *      https://github.com/2072/File-Grim-Reaper
 *
 *  Bug reports/Suggestions:
 *      https://github.com/2072/File-Grim-Reaper/issues
 *
 *   File Grim Reaper is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   File Grim Reaper is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with File Grim Reaper. If not, see <http://www.gnu.org/licenses/>.
 */

final class Name
{
    private function __construct(private string $name) {
    }

    /** @var array<string,self> */
    private static array $pool = [];

    public static function getPoolSize(): Int {
        return count(self::$pool);
    }

    public static function get(string $name): self
    {
        return self::$pool[$name] ??= new self($name);
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public static function _resetPool(): void
    {
        self::$pool = [];
    }

    /* ------------------------------------------------------------------ */
    /*  SERIALIZATION: store only the string, rebuild via factory         */
    /* ------------------------------------------------------------------ */
    public function __serialize(): array
    {
        return [(string)$this];
    }

    public function __unserialize(array $data): void
    {
        $name = $data[0];
        // Just delegate to the factory — it handles caching!
        $instance = self::get($name);
        $this->name = $name;

        self::$pool[$name] = $this;
    }
}

final class Path
{
    /** @param Name[] $segments */
    private function __construct(private array $segments) {}

    /** @var array<int, self>  crc32(path) → Path */
    private static array $crcCache = [];

    /** @var array<string, self>  full path string → Path (collision fallback) */
    private static array $fullCache = [];

    public static function getCacheSizes(): array {
        return [count(self::$crcCache), count(self::$fullCache)];
    }


    private static string $dirSep = DIRECTORY_SEPARATOR;

    public static function setDirSep(string $sep): void {
        self::$dirSep = $sep;
    }



    /**
     * @param string $path
     * @param int|null $forceCrc  FOR TESTING ONLY: force CRC value
     */
    public static function fromString(string $path, ?int $forceCrc = null): self
    {
        if ($path !== "" && $path[0] === '/' || self::$dirSep == "/") {
            $path = '/' . trim($path, '/');
            if ($path !== '/') {
                $path = rtrim($path, '/');
            }
        }

        // 1. Full cache (exact match)
        if (isset(self::$fullCache[$path])) {
            return self::$fullCache[$path];
        }

        // 2. Compute CRC
        $crc = $forceCrc ?? (crc32($path) & 0x7fffffff);
        $candidate = self::$crcCache[$crc] ?? null;

        // 3. Fast match?
        if ($candidate !== null && (string)$candidate === $path) {
            return $candidate;
        }

        // 4. Create new
        $parts    = $path === '/' ? [] : explode(self::$dirSep, trim($path, self::$dirSep));
        $segments = array_map([Name::class, 'get'], $parts);
        $new      = new self($segments);

        // 5. Collision? Move both to full cache
        if ($candidate !== null) {
            $oldPath = (string)$candidate;
            self::$fullCache[$oldPath] = $candidate;
            self::$fullCache[$path]    = $new;
            unset(self::$crcCache[$crc]);
        } else {
            self::$crcCache[$crc] = $new;
        }

        return $new;
    }

    public function __toString(): string
    {
        return ((self::$dirSep == "/") ? "/" : "") . implode(self::$dirSep, $this->segments);
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getDepth(): int {
        return count($this->segments);
    }

    public static function _reset(): void
    {
        self::$crcCache = [];
        self::$fullCache = [];
    }


    /* ------------------------------------------------------------------ */
    /*  SERIALIZATION: store only the string, rebuild via factory         */
    /* ------------------------------------------------------------------ */
    public function __serialize(): array
    {
        return [(string)$this];
    }

    public function __unserialize(array $data): void
    {
        $path = $data[0];
        // test if we already have a crc
        $crc = (crc32($path) & 0x7fffffff);
        $candidate = self::$crcCache[$crc] ?? null;

        if ($candidate === null || (string)$candidate === $path) {
            $instance = self::fromString($path);
            $this->segments = $instance->segments;
            self::$crcCache[$crc] = $this; // replace the cache with the unserialized.

        } else { // collision
            // put the colliding in the full cache
            self::$fullCache[(string)$candidate] = $candidate;
            // then create the instance
            $instance = self::fromString($path);
            $this->segments = $instance->segments;
            self::$fullCache[$path] = $this; // replace the cache with the unserialized.
            unset(self::$crcCache[$crc]); // redundant
        }


    }
}
