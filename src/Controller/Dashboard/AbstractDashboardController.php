<?php

declare(strict_types=1);

namespace AIGateway\Controller\Dashboard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Shared view helpers for dashboard controllers.
 *
 * Each dashboard controller stays focused on HTTP actions while this base class
 * centralizes token-aware rendering and small request helpers.
 */
abstract class AbstractDashboardController
{
    public function __construct(
        protected readonly Environment $twig,
    ) {}

    /** Render one dashboard template with the dashboard token propagated. */
    protected function renderDashboard(Request $request, string $template, array $params = []): Response
    {
        return new Response($this->twig->render('@AIGateway/dashboard/' . $template, $this->dashboardParams($request, $params)));
    }

    /** Render a standard dashboard error page. */
    protected function renderError(Request $request, string $message, int $status): Response
    {
        return new Response($this->twig->render('@AIGateway/dashboard/error.html.twig', $this->dashboardParams($request, ['message' => $message])), $status);
    }

    /** Inject the dashboard token into template params. */
    protected function dashboardParams(Request $request, array $params): array
    {
        return $params + ['dashboard_token' => $this->dashboardToken($request)];
    }

    /** Preserve the dashboard token when redirecting between dashboard pages. */
    protected function dashboardUrl(Request $request, string $path): string
    {
        $token = $this->dashboardToken($request);
        if ('' === $token) {
            return $path;
        }

        return $path . (str_contains($path, '?') ? '&' : '?') . 'token=' . urlencode($token);
    }

    /** Read one POST value as a string. */
    protected function postValue(Request $request, string $key, string $default = ''): string
    {
        $value = $request->request->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /** Read one query value as a string. */
    protected function queryValue(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    protected function dashboardActionError(\Throwable $exception): string
    {
        return sprintf('Action failed: %s', $exception->getMessage());
    }

    private function dashboardToken(Request $request): string
    {
        $token = $request->query->get('token') ?? $request->request->get('token', '');

        return is_string($token) ? $token : '';
    }
}
