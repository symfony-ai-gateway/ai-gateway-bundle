<?php

declare(strict_types=1);

namespace AIGateway\Bundle\DependencyInjection;

use AIGateway\Auth\ApiKeyAuthenticator;
use AIGateway\Auth\AuthEnforcer;
use AIGateway\Auth\KeyRuleScopeValidator;
use AIGateway\Auth\Store\KeyStoreInterface;
use AIGateway\Auth\Store\OrmKeyStore;
use AIGateway\Auth\Store\SlidingWindowKeyRateLimiter;
use AIGateway\Bundle\EventSubscriber\DashboardAuthSubscriber;
use AIGateway\Bundle\EventSubscriber\JsonExceptionSubscriber;
use AIGateway\Bundle\Routing\AIGatewayRouteLoader;
use AIGateway\Catalog\GatewayCatalog;
use AIGateway\Core\Gateway;
use AIGateway\Core\GatewayInterface;
use AIGateway\Logging\RequestLogStore;
use AIGateway\Provider\ProviderAdapterFactory;
use AIGateway\Routing\ChainWeightNormalizer;
use AIGateway\Routing\ModelRegistry;
use AIGateway\Service\ChainAdminService;
use AIGateway\Service\DashboardOverviewService;
use AIGateway\Service\KeyAdminService;
use AIGateway\Service\ModelAdminService;
use AIGateway\Service\ProviderAdminService;
use AIGateway\Service\RequestExplorerService;
use AIGateway\Service\TeamAdminService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

use function dirname;
use function is_string;

/**
 * Bundle extension: registers gateway services, controllers, and event subscribers.
 */
final class AIGatewayExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'ai_gateway';
    }

    /** Configure framework extensions needed by the gateway. */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'paths' => [dirname(__DIR__) . '/Resources/views' => 'AIGateway'],
        ]);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'AIGateway' => [
                        'type' => 'attribute',
                        'dir' => dirname(__DIR__, 2) . '/Entity',
                        'prefix' => 'AIGateway\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    /** Register bundle services and controllers. */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->register(ModelRegistry::class, ModelRegistry::class)
            ->setArgument('$config', []);

        $container->register(GatewayCatalog::class, GatewayCatalog::class)
            ->setArgument('$em', new Reference('doctrine.orm.default_entity_manager'));
        $container->register(ProviderAdapterFactory::class, ProviderAdapterFactory::class)
            ->setArgument('$httpClient', new Reference('http_client'));
        $container->register(RequestLogStore::class, RequestLogStore::class)
            ->setArgument('$em', new Reference('doctrine.orm.default_entity_manager'));
        $container->register(KeyRuleScopeValidator::class, KeyRuleScopeValidator::class);
        $container->register(ChainWeightNormalizer::class, ChainWeightNormalizer::class);
        $container->autowire(ProviderAdminService::class, ProviderAdminService::class);
        $container->autowire(ModelAdminService::class, ModelAdminService::class);
        $container->autowire(ChainAdminService::class, ChainAdminService::class);
        $container->autowire(DashboardOverviewService::class, DashboardOverviewService::class);
        $container->autowire(KeyAdminService::class, KeyAdminService::class);
        $container->autowire(TeamAdminService::class, TeamAdminService::class);
        $container->autowire(RequestExplorerService::class, RequestExplorerService::class);

        $container->register(SlidingWindowKeyRateLimiter::class, SlidingWindowKeyRateLimiter::class);
        $container->register(OrmKeyStore::class, OrmKeyStore::class)
            ->setArgument('$em', new Reference('doctrine.orm.default_entity_manager'));
        if (!$container->hasAlias(KeyStoreInterface::class) && !$container->hasDefinition(KeyStoreInterface::class)) {
            $container->setAlias(KeyStoreInterface::class, OrmKeyStore::class);
        }

        $container->register(ApiKeyAuthenticator::class, ApiKeyAuthenticator::class)
            ->setArgument('$keyStore', new Reference(KeyStoreInterface::class));
        $container->register(AuthEnforcer::class, AuthEnforcer::class)
            ->setArguments([
                '$keyStore' => new Reference(KeyStoreInterface::class),
                '$rateLimiter' => new Reference(SlidingWindowKeyRateLimiter::class),
            ]);

        foreach (AIGatewayRouteLoader::GATEWAY_CONTROLLERS as $controllerClass) {
            $container->autowire($controllerClass, $controllerClass)
                ->addTag('controller.service_arguments');
        }

        foreach (AIGatewayRouteLoader::DASHBOARD_CONTROLLERS as $controllerClass) {
            $container->autowire($controllerClass, $controllerClass)
                ->setArgument('$twig', new Reference('twig'))
                ->addTag('controller.service_arguments');
        }

        $container->autowire(Gateway::class, Gateway::class)
            ->setArguments([
                '$modelRegistry' => new Reference(ModelRegistry::class),
                '$providers' => [],
                '$authEnforcer' => new Reference(AuthEnforcer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$authenticator' => new Reference(ApiKeyAuthenticator::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$catalog' => new Reference(GatewayCatalog::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$providerAdapterFactory' => new Reference(ProviderAdapterFactory::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$requestLogStore' => new Reference(RequestLogStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
        if (!$container->hasAlias(GatewayInterface::class) && !$container->hasDefinition(GatewayInterface::class)) {
            $container->setAlias(GatewayInterface::class, Gateway::class);
        }

        $routesConfig = $mergedConfig['routes'] ?? [];
        $container->register(AIGatewayRouteLoader::class, AIGatewayRouteLoader::class)
            ->setArguments([
                '$prefix' => $routesConfig['prefix'] ?? '',
                '$enabled' => $routesConfig['enabled'] ?? true,
            ])
            ->addTag('routing.loader');

        $container->register(JsonExceptionSubscriber::class, JsonExceptionSubscriber::class)
            ->addTag('kernel.event_subscriber');

        $dashboardConfig = $mergedConfig['dashboard'] ?? [];
        $dashboardToken = isset($dashboardConfig['token']) && is_string($dashboardConfig['token']) ? $dashboardConfig['token'] : null;
        $tokenRequired = (bool) ($dashboardConfig['token_required'] ?? false)
            || (bool) ($dashboardConfig['tokenRequired'] ?? false);
        if ($tokenRequired && null !== $dashboardToken && '' !== $dashboardToken) {
            $container->register(DashboardAuthSubscriber::class, DashboardAuthSubscriber::class)
                ->setArguments([
                    '$dashboardToken' => $dashboardToken,
                    '$routePrefix' => $routesConfig['prefix'] ?? '',
                ])
                ->addTag('kernel.event_subscriber');
        }
    }
}
