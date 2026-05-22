<?php

declare(strict_types=1);

namespace AIGateway\Catalog;

use AIGateway\Entity\GatewayModel;
use AIGateway\Entity\GatewayProvider;
use AIGateway\Entity\ModelChain;
use AIGateway\Entity\ModelChainStep;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persistence-facing catalog for providers, models, and chains.
 *
 * This class isolates Doctrine from the gateway runtime and returns simple
 * arrays that are easy to consume from controllers and routing services.
 */
final class GatewayCatalog
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return list<array{name:string,format:string,api_key:string,base_url:string,completions_path:string}> */
    public function listProviders(): array
    {
        try {
            $providers = $this->em->getRepository(GatewayProvider::class)->findBy([], ['name' => 'ASC']);
        } catch (\Throwable) {
            return [];
        }

        return array_map(self::providerToArray(...), $providers);
    }

    /** @return array{name:string,format:string,api_key:string,base_url:string,completions_path:string}|null */
    public function getProvider(string $name): ?array
    {
        try {
            $provider = $this->em->find(GatewayProvider::class, $name);
        } catch (\Throwable) {
            return null;
        }

        return null !== $provider ? self::providerToArray($provider) : null;
    }

    /**
     * Create or update a provider definition.
     */
    public function saveProvider(string $name, string $format, string $apiKey, ?string $baseUrl, string $completionsPath = '/chat/completions'): void
    {
        $provider = $this->em->find(GatewayProvider::class, $name);

        if (null !== $provider) {
            $provider->setFormat($format);
            $provider->setApiKey($apiKey);
            $provider->setBaseUrl($baseUrl ?? '');
            $provider->setCompletionsPath($completionsPath);
        } else {
            $provider = new GatewayProvider();
            $provider->setName($name);
            $provider->setFormat($format);
            $provider->setApiKey($apiKey);
            $provider->setBaseUrl($baseUrl ?? '');
            $provider->setCompletionsPath($completionsPath);
            $this->em->persist($provider);
        }
        $this->em->flush();
    }

    /**
     * Delete a provider and its attached model aliases.
     */
    public function deleteProvider(string $name): void
    {
        $provider = $this->em->find(GatewayProvider::class, $name);
        if (null === $provider) {
            return;
        }
        foreach ($provider->getModels() as $model) {
            $this->em->remove($model);
        }
        $this->em->remove($provider);
        $this->em->flush();
    }

    /** @return list<array{alias:string,provider_name:string,format:string,model:string,pricing_input:float,pricing_output:float}> */
    public function listModels(): array
    {
        try {
            $models = $this->em->getRepository(GatewayModel::class)->findBy([], ['alias' => 'ASC']);
        } catch (\Throwable) {
            return [];
        }

        return array_map(self::modelToArray(...), $models);
    }

    /** @return array{alias:string,provider_name:string,format:string,model:string,pricing_input:float,pricing_output:float}|null */
    public function getModel(string $alias): ?array
    {
        try {
            $model = $this->em->getRepository(GatewayModel::class)->findOneBy(['alias' => $alias]);
        } catch (\Throwable) {
            return null;
        }

        return null !== $model ? self::modelToArray($model) : null;
    }

    /**
     * Create or update a model alias.
     */
    public function saveModel(string $alias, string $providerName, string $model, float $pricingInput = 0.0, float $pricingOutput = 0.0): void
    {
        $entity = $this->em->getRepository(GatewayModel::class)->findOneBy(['alias' => $alias]);
        $provider = $this->em->find(GatewayProvider::class, $providerName);

        if (null !== $entity) {
            if (null !== $provider) {
                $entity->setProvider($provider);
            }
            $entity->setModel($model);
            $entity->setPricingInput($pricingInput);
            $entity->setPricingOutput($pricingOutput);
        } else {
            $entity = new GatewayModel();
            $entity->setAlias($alias);
            if (null !== $provider) {
                $entity->setProvider($provider);
            }
            $entity->setModel($model);
            $entity->setPricingInput($pricingInput);
            $entity->setPricingOutput($pricingOutput);
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    /**
     * Delete a model alias.
     */
    public function deleteModel(string $alias): void
    {
        $model = $this->em->getRepository(GatewayModel::class)->findOneBy(['alias' => $alias]);
        if (null !== $model) {
            $this->em->remove($model);
            $this->em->flush();
        }
    }

    /** @return list<array{alias:string,created_at:mixed}> */
    public function listChains(): array
    {
        try {
            $chains = $this->em->getRepository(ModelChain::class)->findBy([], ['alias' => 'ASC']);
        } catch (\Throwable) {
            return [];
        }

        return array_map(self::chainToArray(...), $chains);
    }

    /** @return array{alias:string,created_at:mixed}|null */
    public function getChain(string $alias): ?array
    {
        try {
            $chain = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $alias]);
        } catch (\Throwable) {
            return null;
        }

        return null !== $chain ? self::chainToArray($chain) : null;
    }

    /**
     * Create a chain if it does not already exist.
     */
    public function saveChain(string $alias): void
    {
        $existing = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $alias]);
        if (null !== $existing) {
            return;
        }
        $chain = new ModelChain();
        $chain->setAlias($alias);
        $this->em->persist($chain);
        $this->em->flush();
    }

    /**
     * Delete a chain and all of its steps.
     */
    public function deleteChain(string $alias): void
    {
        $chain = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $alias]);
        if (null === $chain) {
            return;
        }
        foreach ($chain->getSteps() as $step) {
            $this->em->remove($step);
        }
        $this->em->remove($chain);
        $this->em->flush();
    }

    /** @return list<array{id:int,chain_alias:string,model_alias:string,priority:int,weight:int}> */
    public function getChainSteps(string $chainAlias): array
    {
        try {
            $chain = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $chainAlias]);
        } catch (\Throwable) {
            return [];
        }

        if (null === $chain) {
            return [];
        }
        return array_map(self::stepToArray(...), $chain->getSteps()->toArray());
    }

    /**
     * Append one step to a chain.
     */
    public function addChainStep(string $chainAlias, string $modelAlias, int $priority, int $weight): int
    {
        $chain = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $chainAlias]);
        $model = $this->em->getRepository(GatewayModel::class)->findOneBy(['alias' => $modelAlias]);

        $step = new ModelChainStep();
        $step->setChain($chain);
        $step->setModel($model);
        $step->setPriority($priority);
        $step->setWeight($weight);

        $this->em->persist($step);
        $this->em->flush();

        return $step->getId();
    }

    /**
     * Remove one chain step.
     */
    public function removeChainStep(int $id): void
    {
        $step = $this->em->find(ModelChainStep::class, $id);
        if (null !== $step) {
            $this->em->remove($step);
            $this->em->flush();
        }
    }

    /**
     * Persist edited weights for several chain steps.
     */
    public function updateChainWeights(string $chainAlias, array $weightsByStepId): void
    {
        foreach ($weightsByStepId as $id => $weight) {
            $step = $this->em->find(ModelChainStep::class, $id);
            if (null !== $step) {
                $step->setWeight($weight);
            }
        }
        $this->em->flush();
    }

    /**
     * Return the minimal chain representation required by the router.
     *
     * @return list<array{id:int,model_alias:string,priority:int,weight:int}>
     */
    public function resolveChainSteps(string $chainAlias): array
    {
        try {
            $chain = $this->em->getRepository(ModelChain::class)->findOneBy(['alias' => $chainAlias]);
        } catch (\Throwable) {
            return [];
        }

        if (null === $chain) {
            return [];
        }

        return array_map(static fn(ModelChainStep $s): array => [
            'id' => $s->getId(),
            'model_alias' => $s->getModel()->getAlias(),
            'priority' => $s->getPriority(),
            'weight' => $s->getWeight(),
        ], $chain->getSteps()->toArray());
    }
    private static function providerToArray(GatewayProvider $p): array
    {
        return [
            'name' => $p->getName(),
            'format' => $p->getFormat(),
            'api_key' => $p->getApiKey(),
            'base_url' => $p->getBaseUrl(),
            'completions_path' => $p->getCompletionsPath(),
        ];
    }
    private static function modelToArray(GatewayModel $m): array
    {
        return [
            'alias' => $m->getAlias(),
            'provider_name' => $m->getProvider()->getName(),
            'format' => $m->getProvider()->getFormat(),
            'model' => $m->getModel(),
            'pricing_input' => $m->getPricingInput(),
            'pricing_output' => $m->getPricingOutput(),
        ];
    }
    private static function chainToArray(ModelChain $c): array
    {
        return [
            'alias' => $c->getAlias(),
            'created_at' => $c->getCreatedAt(),
        ];
    }
    private static function stepToArray(ModelChainStep $s): array
    {
        return [
            'id' => $s->getId(),
            'chain_alias' => $s->getChain()->getAlias(),
            'model_alias' => $s->getModel()->getAlias(),
            'priority' => $s->getPriority(),
            'weight' => $s->getWeight(),
        ];
    }
}
