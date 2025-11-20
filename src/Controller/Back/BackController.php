<?php

namespace App\Controller\Back;

use App\Service\AdminStatsService;
use DateTimeImmutable;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * BackController serves as the main entry point for the back office.
 *
 * It provides a dashboard view for administrators.
 *
 * @psalm-suppress UnusedClass
 */
final class BackController extends AbstractController
{
    protected const TEMPLATE_DIR = 'back';

    /**
     * @param AdminStatsService $adminStatsService
     */
    public function __construct(private readonly AdminStatsService $adminStatsService)
    {
    }

    /**
     * Displays the main dashboard of the back office.
     *
     * @param Request $request
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    #[Route(name: 'index')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $range = $request->query->get('range', '7d');
        $fromParam = $request->query->get('from');
        $toParam   = $request->query->get('to');

        $from = null;
        $to   = null;

        if ($range === '30d') {
            $to = new DateTimeImmutable('now');
            $from = $to->modify('-30 days');
        } elseif ($range === 'custom' && $fromParam && $toParam) {
            try {
                $from = (new DateTimeImmutable($fromParam))->setTime(0, 0, 0);
                $to   = (new DateTimeImmutable($toParam))->setTime(23, 59, 59);
                if ($from > $to) {
                    $this->addFlash('error', 'Invalid date range.');
                    $from = $to = null;
                }
            } catch (Exception) {
                $this->addFlash('error', 'Dates invalid.');
            }
        }

        $data = $this->adminStatsService->getDashboardStats($from, $to);

        return $this->render(self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.html.twig', [
            'stats' => $data,
            'range' => $range,
            'from'  => $from?->format('Y-m-d'),
            'to'    => $to?->format('Y-m-d'),
        ]);
    }
}
