<?php

namespace App\Enum;

enum SpotifyGenre : string
{
    case ACOUSTIC = 'acoustic';
    case AFROBEATS = 'afrobeat';
    case ALT_ROCK = 'alt-rock';
    case ALTERNATIVE = 'alternative';
    case AMBIENT = 'ambient';
    case BLACK_METAL = 'black-metal';
    case BLUEGRASS = 'bluegrass';
    case BLUES = 'blues';
    case BOSSA_NOVA = 'bossa-nova';
    case BREAKBEAT = 'breakbeat';
    case BRITPOP = 'britpop';
    case CHICAGO_HOUSE = 'chicago-house';
    case CHILDREN = 'children';
    case CHILL = 'chill';
    case CLASSICAL = 'classical';
    case CLUB = 'club';
    case COUNTRY = 'country';
    case DANCE = 'dance';
    case DANCEHALL = 'dancehall';
    case DEEP_HOUSE = 'deep-house';
    case DISCO = 'disco';
    case DRUM_AND_BASS = 'drum-and-bass';
    case DUB = 'dub';
    case DUBSTEP = 'dubstep';
    case EDM = 'edm';
    case ELECTRO = 'electro';
    case ELECTRONIC = 'electronic';
    case EMO = 'emo';
    case FOLK = 'folk';
    case FORRO = 'forro';
    case FUNK = 'funk';
    case GARAGE = 'garage';
    case GOSPEL = 'gospel';
    case GOTH = 'goth';
    case GRINDCORE = 'grindcore';
    case GROOVE = 'groove';
    case GRUNGE = 'grunge';
    case GUITAR = 'guitar';
    case HARD_ROCK = 'hard-rock';
    case HARDCORE = 'hardcore';
    case HARDSTYLE = 'hardstyle';
    case HEAVY_METAL = 'heavy-metal';
    case HIP_HOP = 'hip-hop';
    case HOUSE = 'house';
    case IDM = 'idm';
    case INDIE = 'indie';
    case INDIE_POP = 'indie-pop';
    case INDUSTRIAL = 'industrial';
    case JAZZ = 'jazz';
    case KPOP = 'k-pop';
    case LATIN = 'latin';
    case LOFI = 'lo-fi';
    case METAL = 'metal';
    case MINIMAL_TECHNO = 'minimal-techno';
    case MPB = 'mpb';
    case NEW_AGE = 'new-age';
    case OPERA = 'opera';
    case PAGODE = 'pagode';
    case PARTY = 'party';
    case PIANO = 'piano';
    case POP = 'pop';
    case PROGRESSIVE_HOUSE = 'progressive-house';
    case PUNK = 'punk';
    case RNB = 'r-n-b';
    case REGGAE = 'reggae';
    case REGGAETON = 'reggaeton';
    case ROCK = 'rock';
    case ROCK_N_ROLL = 'rock-n-roll';
    case ROMANCE = 'romance';
    case SALSA = 'salsa';
    case SAMBA = 'samba';
    case SERTANEJO = 'sertanejo';
    case SKA = 'ska';
    case SOUL = 'soul';
    case SYNTHPOP = 'synthpop';
    case TANGO = 'tango';
    case TECHNO = 'techno';
    case TRANCE = 'trance';
    case TRAP = 'trap';
    case TRIP_HOP = 'trip-hop';
    case WORLD_MUSIC = 'world-music';

    /**
     * @param string $genre
     *
     * @return bool
     */
    public static function isValid(string $genre): bool
    {
        return \in_array($genre, array_column(self::cases(), 'value'), true);
    }

    /**
     * @param array $genres
     *
     * @return array
     */
    public static function normalize(array $genres): array
    {
        $valid = [];
        foreach ($genres as $g) {
            if (self::isValid($g)) {
                $valid[] = $g;
            }
        }

        return \array_slice(array_unique($valid), 0, 5);
    }
}
