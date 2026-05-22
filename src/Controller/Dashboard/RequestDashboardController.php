<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\RequestExplorerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard controller for request exploration and analytics pages.
 */
final class RequestDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?RequestExplorerService $requestExplorer = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/requests', name: 'ai_gateway_dashboard_requests', methods: ['GET'])]
    public function requests(Request $request): Response
    {
        $filters = array_filter([
            'provider' => $this->queryValue($request, 'provider'),
            'model' => $this->queryValue($request, 'model'),
            'keyId' => '' !== $this->queryValue($request, 'key_id') ? (int) $this->queryValue($request, 'key_id') : null,
            'teamId' => '' !== $this->queryValue($request, 'team_id') ? (int) $this->queryValue($request, 'team_id') : null,
            'statusFamily' => $this->queryValue($request, 'status'),
            'fromDate' => '' !== $this->queryValue($request, 'from_date') ? $this->queryValue($request, 'from_date') . ' 00:00:00' : null,
            'toDate' => '' !== $this->queryValue($request, 'to_date') ? $this->queryValue($request, 'to_date') . ' 23:59:59' : null,
        ], static fn ($value): bool => null !== $value && '' !== $value);

        $rawFilters = [
            'provider' => $this->queryValue($request, 'provider'),
            'model' => $this->queryValue($request, 'model'),
            'key_id' => $this->queryValue($request, 'key_id'),
            'team_id' => $this->queryValue($request, 'team_id'),
            'status' => $this->queryValue($request, 'status'),
            'from_date' => $this->queryValue($request, 'from_date'),
            'to_date' => $this->queryValue($request, 'to_date'),
        ];

        return $this->renderDashboard($request, 'requests.html.twig', $this->requestExplorer?->requests($filters, $rawFilters) ?? []);
    }

    #[Route('/dashboard/analytics', name: 'ai_gateway_dashboard_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        return $this->renderDashboard($request, 'analytics.html.twig', $this->requestExplorer?->analytics() ?? []);
    }
}
