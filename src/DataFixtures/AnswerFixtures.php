<?php

namespace App\DataFixtures;

use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class AnswerFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $users = $manager->getRepository(User::class)->findAll();

        $questions = $manager->getRepository(Question::class)->findAll();


        $answersByQuestion = [
            'Quel est ton style musical préféré ?' => ['Pop', 'Rock', 'Jazz', 'Classique', 'Électro', 'Rap', 'Indie', 'Lo-fi'],
            'À quel moment de la journée écoutes-tu le plus de musique ?' => ['Matin', 'Après-midi', 'Soirée', 'Nuit', 'Toute la journée'],
            'Préférez-vous écouter de la musique en travaillant ou en vous relaxant ?' => ['En travaillant', 'En me relaxant', 'Les deux', 'Ça dépend'],
            'Quel est le dernier morceau que tu as adoré ?' => ['Titre inconnu', 'Je ne m’en souviens plus', 'C’était un classique', 'Un nouveau titre tendance'],
            'As-tu un artiste favori en ce moment ?' => ['Oui', 'Non', 'J’en découvre encore', 'Je préfère les playlists variées'],
            'Quelle humeur décrirait ta playlist du moment ?' => ['Heureuse', 'Triste', 'Énergique', 'Calme', 'Nostalgique', 'Motivée'],
            'Quel genre musical te donne de l\'énergie ?' => ['Électro', 'Rock', 'Rap', 'Pop', 'Metal', 'Afrobeat'],
            'Quel morceau te calme quand tu es stressé ?' => ['Un morceau de piano', 'Une chanson douce', 'Une musique nature', 'Aucune idée', 'Silence'],
            'Avec quel style musical commences-tu ta journée ?' => ['Pop', 'Jazz', 'Classique', 'Ambiant', 'Motivant'],
            'Quelle chanson te rappelle un bon souvenir ?' => ['Une chanson d’enfance', 'Un hit d’été', 'Une chanson romantique', 'Un classique intemporel'],
            'Tu préfères les playlists créées automatiquement ou manuellement ?' => ['Automatiquement', 'Manuellement', 'Un mix des deux'],
            'Combien de temps par jour écoutes-tu de la musique ?' => ['Moins de 30 min', '1-2 heures', '3-5 heures', 'Toute la journée'],
            'Quel instrument aimerais-tu apprendre ?' => ['Guitare', 'Piano', 'Batterie', 'Violon', 'Aucun', 'Je sais déjà jouer'],
            'Ta musique préférée pour faire du sport ?' => ['Rap', 'Electro', 'Rock', 'Afrobeat', 'Dancehall'],
            'Quel est ton guilty pleasure musical ?' => ['Années 80', 'Boys bands', 'K-pop', 'Chansons Disney', 'Chansons romantiques'],
            'Écoutes-tu des musiques différentes selon les saisons ?' => ['Oui', 'Non', 'Je ne sais pas'],
            'Quel genre musical détestes-tu ?' => ['Aucun', 'Metal', 'Techno', 'Classique', 'Rap', 'Ça dépend du moment'],
            'Es-tu du genre à écouter une chanson en boucle ?' => ['Oui', 'Non', 'Parfois'],
            'Utilises-tu souvent Shazam ou d\'autres applis de reconnaissance ?' => ['Souvent', 'Parfois', 'Rarement', 'Jamais'],
            'As-tu une chanson fétiche pour les moments tristes ?' => ['Oui', 'Non', 'Ça change selon les moments'],
        ];

        foreach ($questions as $question) {
            $label = $question->getLabel();
            $propositions = [];

            foreach ($answersByQuestion as $key => $values) {
                if (stripos($label, $key) !== false) {
                    $propositions = $values;
                    break;
                }
            }

            foreach ($propositions as $text) {
                $answer = new Answer();
                $answer->setLabel($text);
                $answer->setQuestion($question);
                if (!empty($users)) {
                    $randomUser = $users[array_rand($users)];
                    $answer->setUser($randomUser);
                }
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
