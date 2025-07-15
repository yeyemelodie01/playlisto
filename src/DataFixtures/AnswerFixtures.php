<?php

namespace App\DataFixtures;

use App\Entity\Answer;
use App\Entity\Question;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AnswerFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $questions = $manager->getRepository(Question::class)->findAll();
        $answersByQuestion = [
            'Quelle est votre humeur du moment ?' => ['Heureux(se)', 'Triste', 'Énergique', 'Fatigué(e)', 'Stressant', 'Posé(e)'],
            'Quel type d’activité faites-vous actuellement ?' => ['Travail', 'Sport', 'Repos', 'Cuisine', 'Lecture', 'Transport'],
            'Quel genre de musique préférez-vous en ce moment ?' => ['Pop', 'Rock', 'Jazz', 'Classique', 'Electro', 'Rap'],
            'Avec qui écoutez-vous cette playlist ?' => ['Seul(e)', 'En couple', 'Entre amis', 'En famille'],
            'À quel moment de la journée écoutez-vous de la musique ?' => ['Matin', 'Après-midi', 'Soirée', 'Nuit', 'Tout au long de la journée'],
        ];

        foreach ($questions as $question) {
            $label = $question->getLabel();
            $propositions = $answersByQuestion[$label] ?? [];

            foreach ($propositions as $text) {
                $answer = new Answer();
                $answer->setLabel($text);
                $answer->setQuestion($question);
                $manager->persist($answer);
            }
        }

        $manager->flush();
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return 4;
    }
}
