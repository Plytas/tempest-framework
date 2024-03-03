<?php

declare(strict_types=1);

namespace Tempest\Http;

use Tempest\AppConfig;
use Tempest\Container\Container;
use Tempest\Container\Initializer;
use Tempest\Container\Singleton;

#[Singleton]
final readonly class RouteInitializer implements Initializer
{
    public function __construct(
        private Container $container,
        private AppConfig $appConfig,
        private RouteConfig $routeConfig,
    ) {
    }

    public function initialize(Container $container): Router
    {
        $router = new GenericRouter(
            container: $this->container,
            appConfig: $this->appConfig,
            routeConfig: $this->routeConfig,
        );

        return $router;
    }
}
