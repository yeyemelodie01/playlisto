<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Loads default user accounts into the database.
 *
 * This fixture is responsible for creating and inserting predefined user accounts
 * into the database, including their roles and other attributes.
 */
final class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['email' => 'alice@example.com', 'username' => 'alice', 'spotifyId' => 1001],
            ['email' => 'bob@example.com', 'username' => 'bob', 'spotifyId' => 1002],
            ['email' => 'charlie@example.com', 'username' => 'charlie', 'spotifyId' => 1003],
            ['email' => 'diana@example.com', 'username' => 'diana', 'spotifyId' => 1004],
            ['email' => 'ethan@example.com', 'username' => 'ethan', 'spotifyId' => 1005],
            ['email' => 'fiona@example.com', 'username' => 'fiona', 'spotifyId' => 1006],
            ['email' => 'george@example.com', 'username' => 'george', 'spotifyId' => 1007],
            ['email' => 'hannah@example.com', 'username' => 'hannah', 'spotifyId' => 1008],
            ['email' => 'isaac@example.com', 'username' => 'isaac', 'spotifyId' => 1009],
            ['email' => 'julia@example.com', 'username' => 'julia', 'spotifyId' => 1010],
        ];

        foreach ($users as $data) {
            if (null === $this->userRepository->findOneBy(['email' => $data['email']])) {
                $user = new User();
                $user->setUsername($data['username']);
                $user->setEmail($data['email']);
                $user->setSpotifyId($data['spotifyId']);
                $user->setRoles(['ROLE_USER']);
                $user->setActive(true);
                $user->setCreatedAt(new \DateTime());
                $user->setUpdatedAt(new \DateTime());
                $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));

                $manager->persist($user);
            }
        }

        $manager->flush();
    }
}
