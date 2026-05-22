<?php

declare(strict_types=1);

namespace AIGateway\Bundle\EventSubscriber;

use AIGateway\Exception\GatewayException;

use function is_int;

use ReflectionClass;

use function sprintf;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts uncaught PHP exceptions into JSON error responses.
 */
final class JsonExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /** Convert uncaught exceptions into JSON error responses. */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/v1/') && !str_starts_with($path, '/api/')) {
            return;
        }

        if ($exception instanceof GatewayException) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => [
                        'type' => 'gateway_error',
                        'message' => $exception->getMessage(),
                    ],
                ],
                is_int($exception->getCode()) && $exception->getCode() >= 400
                    ? $exception->getCode()
                    : 500,
            ));

            return;
        }

        if ($exception instanceof \JsonException) {
            $event->setResponse(new JsonResponse(
                [
                    'error' => [
                        'type' => 'invalid_request',
                        'message' => 'Invalid JSON request body.',
                    ],
                ],
                400,
            ));

            return;
        }

        $event->setResponse(new JsonResponse(
            [
                'error' => [
                    'type' => 'internal_error',
                    'message' => sprintf('Unexpected %s', (new ReflectionClass($exception))->getShortName()),
                ],
            ],
            500,
        ));
    }
}
