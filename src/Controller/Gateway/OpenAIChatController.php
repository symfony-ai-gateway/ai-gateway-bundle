<?php

declare(strict_types=1);

namespace AIGateway\Controller\Gateway;

use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Core\GatewayInterface;
use AIGateway\Exception\GatewayException;
use AIGateway\Logging\RequestLogStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use function trim;

/**
 * OpenAI-compatible gateway endpoint.
 */
final class OpenAIChatController extends AbstractGatewayController
{
    public function __construct(
        GatewayInterface $gateway,
        ?GatewayCatalog $catalog = null,
        ?RequestLogStore $requestLogStore = null,
        private readonly bool $authRequired = true,
    ) {
        parent::__construct($gateway, $catalog, $requestLogStore);
    }

    #[Route('/openai/v1/chat/completions', name: 'ai_gateway_chat', methods: ['POST'])]
    public function chat(Request $request): Response|StreamedResponse
    {
        return $this->handleRequest($request, $this->extractBearerToken($request), 'openai');
    }

    private function extractBearerToken(Request $request): string
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        $token = trim((string) preg_replace('/^Bearer\s+/i', '', $authorization));

        if ('' === $token && $this->authRequired) {
            throw GatewayException::authenticationFailed('Missing Authorization: Bearer header.');
        }

        return $token;
    }
}
