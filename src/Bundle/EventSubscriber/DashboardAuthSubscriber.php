<?php

declare(strict_types=1);

namespace AIGateway\Bundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protects dashboard routes behind a token check.
 */
final class DashboardAuthSubscriber implements EventSubscriberInterface
{
    private readonly string $dashboardToken;

    public function __construct(
        ?string $dashboardToken,
        private readonly string $routePrefix = '',
    ) {
        $this->dashboardToken = $dashboardToken ?? '';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 33],
        ];
    }

    /** Check dashboard token before processing dashboard routes. */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $dashboardBase = ('' !== $this->routePrefix ? $this->routePrefix : '').'/dashboard';

        if (!str_starts_with($path, $dashboardBase)) {
            return;
        }

        if ('' === $this->dashboardToken) {
            return;
        }

        if ($request->isMethod('POST') && 'dashboard_login' === $request->request->get('_action')) {
            $submitted = (string) $request->request->get('token', '');
            if ($submitted === $this->dashboardToken) {
                $redirectUrl = $request->getSchemeAndHttpHost().$path.'?token='.urlencode($this->dashboardToken);
                $event->setResponse(new RedirectResponse($redirectUrl));

                return;
            }
        }

        $submitted = (string) ($request->query->get('token') ?? $request->request->get('token', ''));

        if ($submitted === $this->dashboardToken) {
            $request->attributes->set('dashboard_token_valid', true);
            $request->attributes->set('dashboard_token', $this->dashboardToken);

            return;
        }

        $event->setResponse(new Response($this->renderLoginForm($dashboardBase), 403));
        $event->stopPropagation();
    }

    private function renderLoginForm(string $dashboardBase): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>AIGateway Dashboard — Login</title>
                <style>
                    *{margin:0;padding:0;box-sizing:border-box}
                    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f1117;color:#e4e6f0;display:flex;align-items:center;justify-content:center;min-height:100vh}
                    .login-card{background:#1a1d27;border:1px solid #2e3348;border-radius:12px;padding:40px;width:100%;max-width:400px}
                    .login-card h1{font-size:20px;margin-bottom:8px;color:#818cf8}
                    .login-card p{font-size:14px;color:#8b90a8;margin-bottom:24px}
                    .login-card input{width:100%;padding:12px 16px;background:#242838;border:1px solid #2e3348;border-radius:8px;color:#e4e6f0;font-size:14px;font-family:monospace;outline:none}
                    .login-card input:focus{border-color:#6366f1}
                    .login-card button{width:100%;margin-top:16px;padding:12px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
                    .login-card button:hover{background:#818cf8}
                    .error{color:#ef4444;font-size:13px;margin-top:12px;text-align:center}
                </style>
            </head>
            <body>
                <div class="login-card">
                    <h1>AIGateway Dashboard</h1>
                    <p>Enter your dashboard token to continue.</p>
                    <form method="POST">
                        <input type="hidden" name="_action" value="dashboard_login">
                        <input type="password" name="token" placeholder="Dashboard token" autofocus autocomplete="off">
                        <button type="submit">Sign in</button>
                    </form>
                </div>
            </body>
            </html>
            HTML;
    }
}
