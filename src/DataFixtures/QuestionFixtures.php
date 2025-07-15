<?php

namespace App\DataFixtures;

use App\Entity\Question;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class QuestionFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $labels = [
            'Quelle est votre humeur du moment ?',
            'Quel type d’activité faites-vous actuellement ?',
            'Quel genre de musique préférez-vous en ce moment ?',
            'Avec qui écoutez-vous cette playlist ?',
            'À quel moment de la journée écoutez-vous de la musique ?',
        ];

        $users = $manager->getRepository(User::class)->findAll();
        foreach ($labels as $label) {
            $question = new Question();
            $question->setLabel($label);
            $question->setUser($users[array_rand($users)]);
            $manager->persist($question);
        }

        $manager->flush();
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return 3;
    }
}
