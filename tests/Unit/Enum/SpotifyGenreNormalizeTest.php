<?php

namespace App\Tests\Unit\Enum;

use App\Enum\SpotifyGenre;
use PHPUnit\Framework\TestCase;

final class SpotifyGenreNormalizeTest extends TestCase
{
    public function testNormalizeFiltersUnknownAndLimitsToFive(): void
    {
        $input = [
            'chill', 'lo-fi', 'indie-pop', 'pop', 'rock', 'unknown-foo', 'POP', 'Lo-Fi'
        ];

        $lower = array_map(static fn($g) => mb_strtolower($g), $input);

        $normalized = SpotifyGenre::normalize($lower);

        self::assertContains('chill', $normalized);
        self::assertContains('lo-fi', $normalized);
        self::assertContains('indie-pop', $normalized);
        self::assertContains('pop', $normalized);
        self::assertContains('rock', $normalized);
        self::assertCount(5, $normalized, 'Should limit to 5 genres');
        self::assertNotContains('unknown-foo', $normalized);
    }
}