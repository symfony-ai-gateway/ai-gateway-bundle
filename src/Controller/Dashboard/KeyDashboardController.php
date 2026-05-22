<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\KeyAdminService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard controller for gateway API keys.
 */
final class KeyDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?KeyAdminService $keyAdmin = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/keys', name: 'ai_gateway_dashboard_keys', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'keys.html.twig', $this->keyAdmin?->list() ?? []);
    }

    #[Route('/dashboard/keys/new', name: 'ai_gateway_dashboard_key_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $teamId = '' !== $this->postValue($request, 'team_id') ? (int) $this->postValue($request, 'team_id') : null;
            $overrides = $this->extractOverrides($request);
            $errors = $this->keyAdmin?->validateOverrides($teamId, $overrides) ?? [];
            if ([] !== $errors) {
                return $this->renderDashboard($request, 'key_form.html.twig', ($this->keyAdmin?->createForm() ?? []) + [
                    'errors' => $errors,
                    'submitted' => $request->request->all(),
                    'action' => 'new',
                ]);
            }

            try {
                $rawKey = $this->keyAdmin?->create($this->postValue($request, 'name'), $teamId, $overrides);
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'key_form.html.twig', ($this->keyAdmin?->createForm() ?? []) + [
                    'errors' => [$this->dashboardActionError($e)],
                    'submitted' => $request->request->all(),
                    'action' => 'new',
                ]);
            }

            return $this->renderDashboard($request, 'key_created.html.twig', ['raw_key' => $rawKey]);
        }

        return $this->renderDashboard($request, 'key_form.html.twig', ($this->keyAdmin?->createForm() ?? []) + ['action' => 'new']);
    }

    #[Route('/dashboard/keys/{id}/edit', name: 'ai_gateway_dashboard_key_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        if ($request->isMethod('POST')) {
            $overrides = $this->extractOverrides($request);
            $teamId = '' !== $this->postValue($request, 'team_id') ? (int) $this->postValue($request, 'team_id') : null;
            $errors = $this->keyAdmin?->validateOverrides($teamId, $overrides) ?? [];

            if ([] !== $errors) {
                return $this->renderDashboard($request, 'key_form.html.twig', ($this->keyAdmin?->editForm($id) ?? []) + [
                    'errors' => $errors,
                    'submitted' => $request->request->all(),
                ]);
            }

            try {
                $this->keyAdmin?->update($id, $this->postValue($request, 'name'), $overrides);
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'key_form.html.twig', ($this->keyAdmin?->editForm($id) ?? []) + [
                    'errors' => [$this->dashboardActionError($e)],
                    'submitted' => $request->request->all(),
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/keys/' . $id));
        }

        $formData = $this->keyAdmin?->editForm($id);
        if (null === ($formData['key'] ?? null)) {
            return $this->renderError($request, 'Key not found.', 404);
        }

        return $this->renderDashboard($request, 'key_form.html.twig', $formData);
    }

    #[Route('/dashboard/keys/{id}', name: 'ai_gateway_dashboard_key_detail', methods: ['GET'])]
    public function detail(Request $request, int $id): Response
    {
        $data = $this->keyAdmin?->detail($id);
        if (null === $data) {
            return $this->renderError($request, 'Key not found.', 404);
        }

        return $this->renderDashboard($request, 'keys_detail.html.twig', $data);
    }

    #[Route('/dashboard/keys/{id}/regenerate', name: 'ai_gateway_dashboard_key_regenerate', methods: ['POST'])]
    public function regenerate(Request $request, int $id): Response
    {
        try {
            $rawKey = $this->keyAdmin?->regenerate($id);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        if (null === $rawKey) {
            return $this->renderError($request, 'Key not found.', 404);
        }

        return $this->renderDashboard($request, 'key_created.html.twig', ['raw_key' => $rawKey]);
    }

    #[Route('/dashboard/keys/{id}/revoke', name: 'ai_gateway_dashboard_key_revoke', methods: ['POST'])]
    public function revoke(Request $request, int $id): Response
    {
        try {
            $this->keyAdmin?->revoke($id);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/keys'));
    }

    private function extractOverrides(Request $request): array
    {
        return array_filter([
            'allowed_models' => $this->parseCsv($this->postValue($request, 'models')),
            'budget_per_day' => '' !== $this->postValue($request, 'budget_per_day') ? (float) $this->postValue($request, 'budget_per_day') : null,
            'budget_per_month' => '' !== $this->postValue($request, 'budget_per_month') ? (float) $this->postValue($request, 'budget_per_month') : null,
            'rate_limit_per_minute' => '' !== $this->postValue($request, 'rate_limit') ? (int) $this->postValue($request, 'rate_limit') : null,
            'rate_limit_per_day' => '' !== $this->postValue($request, 'rate_limit_per_day') ? (int) $this->postValue($request, 'rate_limit_per_day') : null,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }

    private function parseCsv(string $value): ?array
    {
        if ('' === trim($value)) {
            return null;
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $item): bool => '' !== $item));
    }
}
