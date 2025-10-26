<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Persistence\ObjectManager;

final class TestUserFixtures extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users
    ) {
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function getOrder(): int
    {
        return 0;
    }

    public function load(ObjectManager $manager): void
    {
        $email = 'user.functional@test.local';
        if ($this->users->findOneBy(['email' => $email])) {
            return;
        }

        $u = new User();
        $u->setEmail($email);
        $u->setUsername('functional-user');
        $u->setRoles(['ROLE_USER']);
        $u->setActive(true);
        $u->setPassword($this->hasher->hashPassword($u, 'User123'));
        $this->users->save($u, true);
    }
}
