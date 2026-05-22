<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use AIGateway\Service\ChainAdminService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Dashboard CRUD controller for model chains.
 */
final class ChainDashboardController extends AbstractDashboardController
{
    public function __construct(Environment $twig, private readonly ?ChainAdminService $chainAdmin = null)
    {
        parent::__construct($twig);
    }

    #[Route('/dashboard/chains', name: 'ai_gateway_dashboard_chains', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, 'chains.html.twig', ['chains' => $this->chainAdmin?->list() ?? []]);
    }

    #[Route('/dashboard/chains/new', name: 'ai_gateway_dashboard_chain_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $alias = $this->postValue($request, 'alias');

            try {
                $this->chainAdmin?->create($alias);
            } catch (\Throwable $e) {
                return $this->renderDashboard($request, 'chain_form.html.twig', [
                    'chain' => null,
                    'action' => 'new',
                    'errors' => [$this->dashboardActionError($e)],
                ]);
            }

            return new RedirectResponse($this->dashboardUrl($request, '/dashboard/chains/' . $alias));
        }

        return $this->renderDashboard($request, 'chain_form.html.twig', ['chain' => null, 'action' => 'new']);
    }

    #[Route('/dashboard/chains/{alias}', name: 'ai_gateway_dashboard_chain_detail', methods: ['GET'])]
    public function detail(Request $request, string $alias): Response
    {
        $data = $this->chainAdmin?->detail($alias);
        if (null === $data) {
            return $this->renderError($request, 'Chain not found.', 404);
        }

        return $this->renderDashboard($request, 'chains_detail.html.twig', $data);
    }

    #[Route('/dashboard/chains/{alias}/steps/add', name: 'ai_gateway_dashboard_chain_step_add', methods: ['POST'])]
    public function addStep(Request $request, string $alias): Response
    {
        try {
            $this->chainAdmin?->addStep(
                $alias,
                $this->postValue($request, 'model_alias'),
                (int) $this->postValue($request, 'priority', '1'),
                (int) $this->postValue($request, 'weight', '100'),
            );
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/chains/' . $alias));
    }

    #[Route('/dashboard/chains/steps/{id}/remove', name: 'ai_gateway_dashboard_chain_step_remove', methods: ['POST'])]
    public function removeStep(Request $request, int $id): Response
    {
        try {
            $this->chainAdmin?->removeStep($id);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($request->headers->get('referer') ?: $this->dashboardUrl($request, '/dashboard/chains'));
    }

    #[Route('/dashboard/chains/{alias}/weights/save', name: 'ai_gateway_dashboard_chain_weights_save', methods: ['POST'])]
    public function saveWeights(Request $request, string $alias): Response
    {
        try {
            $this->chainAdmin?->saveWeights($alias, $request->request->all('weights'));
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/chains/' . $alias));
    }

    #[Route('/dashboard/chains/{alias}/delete', name: 'ai_gateway_dashboard_chain_delete', methods: ['POST'])]
    public function delete(Request $request, string $alias): Response
    {
        try {
            $this->chainAdmin?->delete($alias);
        } catch (\Throwable $e) {
            return $this->renderError($request, $this->dashboardActionError($e), 500);
        }

        return new RedirectResponse($this->dashboardUrl($request, '/dashboard/chains'));
    }
}
