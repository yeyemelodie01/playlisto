<?php

namespace App\DataFixtures;

use App\Entity\Question;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class QuestionFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $questions = [
            'Quel est ton style musical préféré ?',
            'À quel moment de la journée écoutes-tu le plus de musique ?',
            'Préférez-vous écouter de la musique en travaillant ou en vous relaxant ?',
            'Quel est le dernier morceau que tu as adoré ?',
            'As-tu un artiste favori en ce moment ?',
            'Quelle humeur décrirait ta playlist du moment ?',
            'Quel genre musical te donne de l\'énergie ?',
            'Quel morceau te calme quand tu es stressé ?',
            'Avec quel style musical commences-tu ta journée ?',
            'Quelle chanson te rappelle un bon souvenir ?',
            'Tu préfères les playlists créées automatiquement ou manuellement ?',
            'Combien de temps par jour écoutes-tu de la musique ?',
            'Quel instrument aimerais-tu apprendre ?',
            'Ta musique préférée pour faire du sport ?',
            'Quel est ton guilty pleasure musical ?',
            'Écoutes-tu des musiques différentes selon les saisons ?',
            'Quel genre musical détestes-tu ?',
            'Es-tu du genre à écouter une chanson en boucle ?',
            'Utilises-tu souvent Shazam ou d\'autres applis de reconnaissance ?',
            'As-tu une chanson fétiche pour les moments tristes ?',
        ];

        foreach ($questions as $label) {
            $question = new Question();
            $question->setLabel($label);
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
