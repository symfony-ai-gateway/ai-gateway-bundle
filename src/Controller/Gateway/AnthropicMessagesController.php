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
 * Anthropic-compatible gateway endpoint: POST /anthropic/v1/messages.
 */
final class AnthropicMessagesController extends AbstractGatewayController
{
    public function __construct(
        GatewayInterface $gateway,
        ?GatewayCatalog $catalog = null,
        ?RequestLogStore $requestLogStore = null,
        private readonly bool $authRequired = true,
    ) {
        parent::__construct($gateway, $catalog, $requestLogStore);
    }

    #[Route('/anthropic/v1/messages', name: 'ai_gateway_messages', methods: ['POST'])]
    public function messages(Request $request): Response|StreamedResponse
    {
        return $this->handleRequest($request, $this->extractApiKeyHeader($request), 'anthropic');
    }

    private function extractApiKeyHeader(Request $request): string
    {
        $token = trim((string) $request->headers->get('x-api-key', ''));

        if ('' === $token && $this->authRequired) {
            throw GatewayException::authenticationFailed('Missing x-api-key header.');
        }

        return $token;
    }
}
