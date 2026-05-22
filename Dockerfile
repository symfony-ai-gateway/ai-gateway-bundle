FROM dunglas/frankenphp:1-php8.4-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache curl
RUN install-php-extensions pdo_sqlite

ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV DEFAULT_URI=http://localhost
ENV DATABASE_URL=sqlite:////runtime/data/auth.db

WORKDIR /tmp

COPY . /bundle

RUN composer create-project symfony/skeleton:^8.0 /runtime --no-interaction --no-progress

WORKDIR /runtime

RUN composer require symfony/framework-bundle:^8.0 symfony/twig-bundle:^8.0 symfony/console:^8.0 symfony/runtime:^8.0 symfony/dotenv:^8.0 doctrine/dbal:^4.4 doctrine/doctrine-bundle:^3.2 doctrine/orm:^3.6 --no-interaction --no-progress --no-scripts \
    && composer config repositories.ai-gateway path /bundle \
    && composer require ai-gateway/ai-gateway-bundle:@dev --no-interaction --no-progress --no-scripts

RUN php -r '$f="config/bundles.php"; $c=file_get_contents($f); foreach (["Symfony\\Bundle\\TwigBundle\\TwigBundle::class", "Doctrine\\Bundle\\DoctrineBundle\\DoctrineBundle::class", "AIGateway\\Bundle\\AIGatewayBundle::class"] as $b) { if (!str_contains($c, $b)) { $c=str_replace("];", "    ".$b." => [\"all\" => true],\n];", $c); } } file_put_contents($f, $c);' \
    && mkdir -p config/packages config/routes data \
    && printf "doctrine:\n    dbal:\n        url: '%%env(resolve:DATABASE_URL)%%'\n    orm:\n        auto_mapping: true\n        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware\n" > config/packages/doctrine.yaml \
    && printf "ai_gateway:\n    dashboard:\n        token_required: true\n        token: '%%env(default::DASHBOARD_TOKEN)%%'\n    routes:\n        enabled: true\n        prefix: ''\n" > config/packages/ai_gateway.yaml \
    && printf "ai_gateway:\n    resource: .\n    type: ai_gateway\n" > config/routes/ai_gateway.yaml

RUN APP_ENV=prod APP_DEBUG=0 DEFAULT_URI=http://localhost DATABASE_URL=sqlite:///tmp/build-cache.db composer dump-autoload --no-dev --optimize \
    && APP_ENV=prod APP_DEBUG=0 DEFAULT_URI=http://localhost DATABASE_URL=sqlite:///tmp/build-cache.db php bin/console cache:clear \
    && chmod +x bin/console

WORKDIR /runtime

COPY docker-entrypoint.sh /docker-entrypoint.sh
COPY Caddyfile /etc/caddy/Caddyfile
RUN chmod +x /docker-entrypoint.sh && mkdir -p /runtime/data && chown -R www-data:www-data /runtime

VOLUME /runtime/data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD curl -f http://localhost/v1/health || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
