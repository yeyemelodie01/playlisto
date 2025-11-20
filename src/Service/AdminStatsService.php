<?php

namespace App\Service;

use App\Repository\PlaylistRepository;
use App\Repository\UserRepository;
use DateTimeInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service to gather and provide statistics for the admin dashboard.
 *
 * @psalm-suppress PossiblyUnusedMethod
 */
final readonly class AdminStatsService
{
    /**
     * @param UserRepository     $userRepository
     * @param PlaylistRepository $playlistRepository
     * @param CacheInterface     $cache
     */
    public function __construct(private UserRepository $userRepository, private PlaylistRepository $playlistRepository, private CacheInterface $cache)
    {
    }

    /**
     * Retrieves various statistics for the admin dashboard.
     *
     * @param DateTimeInterface|null $from
     * @param DateTimeInterface|null $to
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDashboardStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $to = $to ?? new \DateTimeImmutable('now');
        $from = $from ?? $to->modify('-7 days');

        $key = \sprintf('dashboard_v1_%s_%s', $from->format('Ymd'), $to->format('Ymd'));

        return $this->cache->get($key, function (ItemInterface $item) use ($from, $to) {
            $item->expiresAfter(60);

            $countUsers = $this->userRepository->count([]);
            $countLastConnectedUsers = $this->userRepository->lastTenUserConnected();
            $countNewUsers = $this->userRepository->countNewUsersBetween($from, $to);
            $countPlaylists = $this->playlistRepository->count([]);
            $countPlaylistsByDate = $this->playlistRepository->countPlaylistFilteredByDate($from, $to);
            $countPlaylistsByMood = $this->playlistRepository->countByMoodBetween($from, $to);
            $countPlaylistByActivity = $this->playlistRepository->countByActivityBetween($from, $to);

            return [
                'totalUsers' => $countUsers,
                'lastConnectedUsers' => $countLastConnectedUsers,
                'newUsers' => $countNewUsers,
                'totalPlaylists' => $countPlaylists,
                'playlistsByDate' => $countPlaylistsByDate,
                'playlistsByMood' => $countPlaylistsByMood,
                'playlistsByActivity' => $countPlaylistByActivity,
            ];
        });
    }
}
