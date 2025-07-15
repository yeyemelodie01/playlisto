<?php

namespace App\DataFixtures;

use App\Entity\Playlist;
use App\Entity\Track;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Loads default tracks into the database.
 *
 * This fixture is responsible for creating and inserting predefined music tracks
 * into the database, including their metadata and associations with playlists.
 */
final class TracksFixtures extends Fixture implements OrderedFixtureInterface
{
    /**
     * @return int
     */
    public function getOrder(): int
    {
        return 3;
    }
    public function load(ObjectManager $manager): void
    {
        $genres = ['Pop', 'Rock', 'Jazz', 'Hip-Hop', 'Electro', 'Classique', 'Funk', 'Reggae'];
        $albums = ['Greatest Hits', 'Summer Vibes', 'Chill Sessions', 'Night Drive', 'Workout Pump', 'Jazz Lounge'];
        $artists = ['Artist A', 'Band B', 'DJ C', 'Singer D', 'Trio E', 'Composer F'];

        $playlists = $manager->getRepository(Playlist::class)->findAll();

        for ($i = 1; $i <= 40; $i++) {
            $track = new Track();
            $track->setTitle("Titre {$i}");
            $track->setArtist($artists[array_rand($artists)]);
            $track->setAlbum($albums[array_rand($albums)]);
            $track->setGenre($genres[array_rand($genres)]);
            $track->setDuration(rand(60, 180)); // durée entre 1 et 3 minutes
            $track->setSpotifyId(1000 + $i);
            $track->setCoverUrl("https://example.com/cover{$i}.jpg");

            $randomPlaylists = (array)array_rand($playlists, rand(1, min(3, count($playlists))));
            foreach ((array) $randomPlaylists as $key) {
                $track->addPlaylist($playlists[$key]);
            }

            $manager->persist($track);
        }

        $manager->flush();
    }
}
