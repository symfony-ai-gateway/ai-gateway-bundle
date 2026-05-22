<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\TeamAdminService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard controller for teams and their budgets/rate rules.
 */
final class TeamDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?TeamAdminService $teamAdmin = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/teams', name: 'ai_gateway_dashboard_teams', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'teams.html.twig', $this->teamAdmin?->list() ?? []);
    }

    #[Route('/dashboard/teams/new', name: 'ai_gateway_dashboard_team_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $this->teamAdmin?->create($this->postValue($request, 'name'), $this->extractRules($request));
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'team_form.html.twig', ($this->teamAdmin?->formData(null, 'new') ?? []) + [
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/teams'));
        }

        return $this->renderDashboard($request, 'team_form.html.twig', $this->teamAdmin?->formData(null, 'new') ?? []);
    }

    #[Route('/dashboard/teams/{id}', name: 'ai_gateway_dashboard_team_detail', methods: ['GET'])]
    public function detail(Request $request, int $id): Response
    {
        $data = $this->teamAdmin?->detail($id);
        if (null === $data) {
            return $this->renderError($request, 'Team not found.', 404);
        }

        return $this->renderDashboard($request, 'teams_detail.html.twig', $data);
    }

    #[Route('/dashboard/teams/{id}/edit', name: 'ai_gateway_dashboard_team_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $team = $this->teamAdmin?->find($id);
        if (null === $team) {
            return $this->renderError($request, 'Team not found.', 404);
        }

        if ($request->isMethod('POST')) {
            try {
                $this->teamAdmin?->update($team, $this->postValue($request, 'name', $team->getName()), $this->extractRules($request));
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'team_form.html.twig', ($this->teamAdmin?->formData($team, 'edit') ?? []) + [
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/teams/' . $id));
        }

        return $this->renderDashboard($request, 'team_form.html.twig', $this->teamAdmin?->formData($team, 'edit') ?? []);
    }

    private function extractRules(Request $request): array
    {
        return [
            'budget_per_day' => '' !== $this->postValue($request, 'budget_per_day') ? (float) $this->postValue($request, 'budget_per_day') : null,
            'budget_per_month' => '' !== $this->postValue($request, 'budget_per_month') ? (float) $this->postValue($request, 'budget_per_month') : null,
            'rate_limit_per_minute' => '' !== $this->postValue($request, 'rate_limit') ? (int) $this->postValue($request, 'rate_limit') : null,
            'rate_limit_per_day' => '' !== $this->postValue($request, 'rate_limit_per_day') ? (int) $this->postValue($request, 'rate_limit_per_day') : null,
            'allowed_models' => $this->parseCsv($this->postValue($request, 'models')),
        ];
    }

    private function parseCsv(string $value): ?array
    {
        if ('' === trim($value)) {
            return null;
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $item): bool => '' !== $item));
    }
}
