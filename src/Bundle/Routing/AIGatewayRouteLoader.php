<?php

declare(strict_types=1);

namespace AIGateway\Bundle\Routing;

use AIGateway\Controller\Gateway\AnthropicMessagesController;
use AIGateway\Controller\Dashboard\DashboardHomeController;
use AIGateway\Controller\Dashboard\ProviderDashboardController;
use AIGateway\Controller\Dashboard\ModelDashboardController;
use AIGateway\Controller\Dashboard\ChainDashboardController;
use AIGateway\Controller\Dashboard\KeyDashboardController;
use AIGateway\Controller\Dashboard\TeamDashboardController;
use AIGateway\Controller\Dashboard\RequestDashboardController;
use AIGateway\Controller\Gateway\InfoController;
use AIGateway\Controller\Gateway\OpenAIChatController;
use LogicException;

use function sprintf;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads routes from gateway and dashboard controllers.
 */
final class AIGatewayRouteLoader extends Loader
{
    public const GATEWAY_CONTROLLERS = [
        OpenAIChatController::class,
        AnthropicMessagesController::class,
        InfoController::class,
    ];

    public const DASHBOARD_CONTROLLERS = [
        DashboardHomeController::class,
        ProviderDashboardController::class,
        ModelDashboardController::class,
        ChainDashboardController::class,
        KeyDashboardController::class,
        TeamDashboardController::class,
        RequestDashboardController::class,
    ];

    private bool $loaded = false;

    public function __construct(
        private readonly string $prefix = '',
        private readonly bool $enabled = true,
    ) {
        parent::__construct();
    }

    /** Load routes from all registered controllers. */
    public function load(mixed $resource, string|null $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new LogicException('AIGatewayRouteLoader has already been loaded.');
        }
        $this->loaded = true;

        if (!$this->enabled) {
            return new RouteCollection();
        }

        $collection = new RouteCollection();

        foreach ([...self::GATEWAY_CONTROLLERS, ...self::DASHBOARD_CONTROLLERS] as $controllerClass) {
            $collection->addCollection($this->importAttributes($controllerClass));
        }

        if ('' !== $this->prefix) {
            $collection->addPrefix($this->prefix);
        }

        return $collection;
    }

    /** Check whether this loader supports the given resource. */
    public function supports(mixed $resource, string|null $type = null): bool
    {
        return 'ai_gateway' === $type;
    }

    private function importAttributes(string $controllerClass): RouteCollection
    {
        $resolver = $this->resolver;
        if (null === $resolver) {
            throw new LogicException('A route resolver must be set before importing attributes.');
        }

        $loader = $resolver->resolve($controllerClass, 'attribute');
        if (false === $loader) {
            throw new LogicException(sprintf('Cannot resolve attribute loader for "%s".', $controllerClass));
        }

        return $loader->load($controllerClass, 'attribute');
    }
}
