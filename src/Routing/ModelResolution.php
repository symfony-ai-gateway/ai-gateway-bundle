<?php

declare(strict_types=1);

namespace AIGateway\Routing;

/**
 * Resolved routing target for one gateway model alias.
 */
final readonly class ModelResolution
{
    /**
     * @param string $alias Public alias exposed by the gateway.
     * @param string $provider Provider name to call.
     * @param string $providerFormat Provider request format (`openai` or `anthropic`).
     * @param string $model Actual provider model name.
     * @param ModelPricing $pricing Pricing data used for cost logging.
     */
    public function __construct(
        public string $alias,
        public string $provider,
        public string $providerFormat,
        public string $model,
        public ModelPricing $pricing,
    ) {}
}
