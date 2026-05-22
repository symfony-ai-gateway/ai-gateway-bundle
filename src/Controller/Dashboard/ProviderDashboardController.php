<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\ProviderAdminService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard CRUD controller for provider definitions.
 */
final class ProviderDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?ProviderAdminService $providerAdmin = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/providers', name: 'ai_gateway_dashboard_providers', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'providers.html.twig', ['providers' => $this->providerAdmin?->list() ?? []]);
    }

    #[Route('/dashboard/providers/new', name: 'ai_gateway_dashboard_provider_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $this->providerAdmin?->save(
                    $this->postValue($request, 'name'),
                    $this->postValue($request, 'format', 'openai'),
                    $this->postValue($request, 'api_key', ''),
                    $this->postValue($request, 'base_url') ?: null,
                    $this->postValue($request, 'completions_path', '/chat/completions'),
                );
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'provider_form.html.twig', [
                    'provider' => $request->request->all(),
                    'action' => 'new',
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/providers'));
        }

        return $this->renderDashboard($request, 'provider_form.html.twig', ['provider' => null, 'action' => 'new']);
    }

    #[Route('/dashboard/providers/{name}/edit', name: 'ai_gateway_dashboard_provider_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $name): Response
    {
        $provider = $this->providerAdmin?->find($name);
        if (null === $provider) {
            return $this->renderError($request, 'Provider not found.', 404);
        }

        if ($request->isMethod('POST')) {
            try {
                $this->providerAdmin?->save(
                    $name,
                    $this->postValue($request, 'format', 'openai'),
                    $this->postValue($request, 'api_key', ''),
                    $this->postValue($request, 'base_url') ?: null,
                    $this->postValue($request, 'completions_path', '/chat/completions'),
                );
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'provider_form.html.twig', [
                    'provider' => ['name' => $name] + $request->request->all(),
                    'action' => 'edit',
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/providers'));
        }

        return $this->renderDashboard($request, 'provider_form.html.twig', ['provider' => $provider, 'action' => 'edit']);
    }

    #[Route('/dashboard/providers/{name}', name: 'ai_gateway_dashboard_provider_detail', methods: ['GET'])]
    public function detail(Request $request, string $name): Response
    {
        $data = $this->providerAdmin?->detail($name);
        if (null === $data) {
            return $this->renderError($request, 'Provider not found.', 404);
        }

        return $this->renderDashboard($request, 'providers_detail.html.twig', $data);
    }

    #[Route('/dashboard/providers/{name}/delete', name: 'ai_gateway_dashboard_provider_delete', methods: ['POST'])]
    public function delete(Request $request, string $name): Response
    {
        try {
            $this->providerAdmin?->delete($name);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/providers'));
    }
}
