<?php

namespace App\DataFixtures;

use App\Entity\Playlist;
use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\MoodType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use App\Repository\PlaylistRepository;

final class PlaylistFixtures extends Fixture implements OrderedFixtureInterface
{
    public function __construct(private readonly PlaylistRepository $playlistRepository)
    {
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return 2;
    }

    /**
     * @param ObjectManager $manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        $titles = [
            'Chill Vibes', 'Workout Beats', 'Morning Boost', 'Late Night Drive',
            'Focus Zone', 'Feel Good Hits', 'Rainy Day', 'Summer Party',
            'Study Time', 'Emotional Ride', 'Electro Flow', 'Soft Acoustic',
            'Hip-Hop Chill', 'Jazz Classics', 'Lo-fi Coding', 'Epic Soundtrack',
            'Romantic Evenings', 'Piano Peace', 'Rock Vibes', 'Sunday Blues'
        ];

        $descriptions = [
            'Une playlist pour se détendre après une longue journée.',
            'Des sons motivants pour booster ton entraînement.',
            'De l’énergie pour bien commencer ta journée.',
            'Pour accompagner tes trajets de nuit.',
            'Une ambiance calme pour se concentrer.',
            'Des musiques pour garder le smile.',
            'Une ambiance cozy pour les jours de pluie.',
            'À écouter sous le soleil entre amis.',
            'Idéal pour réviser efficacement.',
            'Pour traverser toutes tes émotions.',
            'Une ambiance électro relax.',
            'Des guitares douces pour les soirées tranquilles.',
            'Hip-hop tranquille pour se poser.',
            'Les classiques du jazz.',
            'Parfait pour coder concentré.',
            'Idéal pour les grandes scènes imaginaires.',
            'Pour les moments romantiques.',
            'Sérénité au piano.',
            'Du rock pour faire vibrer tes écouteurs.',
            'Un dimanche tout en musique.'
        ];

        $moods = MoodType::cases();
        $activities = ActivityType::cases();

        $users = $manager->getRepository(User::class)->findAll();
        $userCount = count($users);

        if ($userCount === 0) {
            // No users to attach playlists to; skip
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            $playlist = new Playlist();
            $playlist->setTitle($titles[$i]);
            $playlist->setDescription($descriptions[$i]);
            $playlist->setMood($moods[array_rand($moods)]);
            $playlist->setActivity($activities[array_rand($activities)]);
            $playlist->setUser($users[$i % $userCount]);
            $daysAgo = random_int(0, 60);
            $createdAt = (new \DateTime('now'))->modify('-' . $daysAgo . ' days');
            if (method_exists($playlist, 'setCreatedAt')) {
                $playlist->setCreatedAt($createdAt);
            }
            if (method_exists($playlist, 'setUpdatedAt')) {
                $playlist->setUpdatedAt($createdAt);
            }

            $this->playlistRepository->save($playlist, true);
        }
    }
}
