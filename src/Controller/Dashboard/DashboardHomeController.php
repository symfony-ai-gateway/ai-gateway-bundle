<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\DashboardOverviewService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard landing page controller.
 */
final class DashboardHomeController extends AbstractDashboardController
{
    public function __construct(
        Environment $twig,
        private readonly ?DashboardOverviewService $overview = null,
    ) {
        parent::__construct($twig);
    }

    /**
     * Show the global gateway overview.
     */
    #[Route('/dashboard', name: 'ai_gateway_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'index.html.twig', $this->overview?->overview() ?? []);
    }
}
