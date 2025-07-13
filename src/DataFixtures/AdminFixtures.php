<?php

namespace App\DataFixtures;

use App\Entity\Administrator;
use App\Repository\AdministratorRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Loads default administrator accounts into the database.
 */
class AdminFixtures extends Fixture
{
    /**
     * AdminFixtures constructor.
     *
     * @param UserPasswordHasherInterface $passwordHasher
     * @param AdministratorRepository     $administratorRepository
     */
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher, private readonly AdministratorRepository $administratorRepository)
    {
    }

    /**
     * Load administrator fixtures into the database.
     *
     * This method creates predefined administrator accounts and inserts them
     * into the database with hashed passwords.
     *
     * @param ObjectManager $manager the Doctrine entity manager
     */
    public function load(ObjectManager $manager): void
    {
        $admins = [
            [
                'firstName' => 'Mélodie',
                'lastName' => 'YEYE',
                'email' => 'yeyemelodie@outlook.fr',
                'password' => 'Admin123',
                'roles' => ['ROLE_ADMIN'],
                'superAdministrator' => true,
            ],
        ];

        foreach ($admins as $adminData) {
            if (null === $this->administratorRepository->findOneBy(['email' => $adminData['email']])) {
                $admin = new Administrator();
                $admin->setFirstName($adminData['firstName']);
                $admin->setLastName($adminData['lastName']);
                $admin->setEmail($adminData['email']);
                $admin->setRoles($adminData['roles']);
                $admin->setSuperAdministrator($adminData['superAdministrator']);

                $hashedPassword = $this->passwordHasher->hashPassword($admin, $adminData['password']);
                $admin->setPassword($hashedPassword);

                $manager->persist($admin);
            }
        }

        $manager->flush();
    }
}
