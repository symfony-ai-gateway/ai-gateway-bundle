<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\ModelAdminService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard CRUD controller for model aliases.
 */
final class ModelDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?ModelAdminService $modelAdmin = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/models', name: 'ai_gateway_dashboard_models', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'models.html.twig', ['models' => $this->modelAdmin?->list() ?? []]);
    }

    #[Route('/dashboard/models/new', name: 'ai_gateway_dashboard_model_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $providers = $this->modelAdmin?->providers() ?? [];
        if ($request->isMethod('POST')) {
            try {
                $this->modelAdmin?->save(
                    $this->postValue($request, 'alias'),
                    $this->postValue($request, 'provider_name'),
                    $this->postValue($request, 'model'),
                    (float) $this->postValue($request, 'pricing_input', '0'),
                    (float) $this->postValue($request, 'pricing_output', '0'),
                );
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'model_form.html.twig', [
                    'model' => $request->request->all(),
                    'providers' => $providers,
                    'action' => 'new',
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/models'));
        }

        return $this->renderDashboard($request, 'model_form.html.twig', ['model' => null, 'providers' => $providers, 'action' => 'new']);
    }

    #[Route('/dashboard/models/{alias}/edit', name: 'ai_gateway_dashboard_model_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $alias): Response
    {
        $model = $this->modelAdmin?->find($alias);
        $providers = $this->modelAdmin?->providers() ?? [];
        if (null === $model) {
            return $this->renderError($request, 'Model not found.', 404);
        }

        if ($request->isMethod('POST')) {
            try {
                $this->modelAdmin?->renameAndSave(
                    $alias,
                    $this->postValue($request, 'alias', $alias),
                    $this->postValue($request, 'provider_name'),
                    $this->postValue($request, 'model'),
                    (float) $this->postValue($request, 'pricing_input', '0'),
                    (float) $this->postValue($request, 'pricing_output', '0'),
                );
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'model_form.html.twig', [
                    'model' => ['alias' => $alias] + $request->request->all(),
                    'providers' => $providers,
                    'action' => 'edit',
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/models'));
        }

        return $this->renderDashboard($request, 'model_form.html.twig', ['model' => $model, 'providers' => $providers, 'action' => 'edit']);
    }

    #[Route('/dashboard/models/{alias}', name: 'ai_gateway_dashboard_model_detail', methods: ['GET'])]
    public function detail(Request $request, string $alias): Response
    {
        $data = $this->modelAdmin?->detail($alias);
        if (null === $data) {
            return $this->renderError($request, 'Model not found.', 404);
        }

        return $this->renderDashboard($request, 'models_detail.html.twig', $data);
    }

    #[Route('/dashboard/models/{alias}/delete', name: 'ai_gateway_dashboard_model_delete', methods: ['POST'])]
    public function delete(Request $request, string $alias): Response
    {
        try {
            $this->modelAdmin?->delete($alias);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/models'));
    }
}
