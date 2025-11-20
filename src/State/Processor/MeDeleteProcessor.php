<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\PlaylistRepository;
use App\Repository\SurveySubmissionRepository;
use App\Repository\TrackRepository;
use App\Repository\UserRepository;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class MeDeleteProcessor implements ProcessorInterface
{
    /**
     * @param Security                   $security
     * @param UserRepository             $userRepository
     * @param TrackRepository            $trackRepository
     * @param PlaylistRepository         $playlistRepository
     * @param SurveySubmissionRepository $surveySubmissionRepository
     */
    public function __construct(private Security $security, private UserRepository $userRepository, private TrackRepository $trackRepository, private PlaylistRepository $playlistRepository, private SurveySubmissionRepository $surveySubmissionRepository)
    {
    }

    /**
     * @param mixed     $data
     * @param Operation $operation
     * @param array     $uriVariables
     * @param array     $context
     *
     * @return mixed
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new RuntimeException('User not authenticated');
        }

        try {
            $playlists = $user->getPlaylists();
            $surveySubmissions = $user->getSurveySubmissions();
            foreach ($playlists as $playlist) {
                $tracks = $playlist->getTracks();
                foreach ($tracks as $track) {
                    $this->trackRepository->remove($track);
                }
                $this->playlistRepository->remove($playlist);
            }

            foreach ($surveySubmissions as $submission) {
                $this->surveySubmissionRepository->remove($submission);
            }

            $this->userRepository->remove($user, true);

            return null;
        } catch (\Exception $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }
}
