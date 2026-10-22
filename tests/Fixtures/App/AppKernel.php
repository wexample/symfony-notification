<?php

namespace Wexample\SymfonyNotification\Tests\Fixtures\App;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyMail\WexampleSymfonyMailBundle;
use Wexample\SymfonyNotification\WexampleSymfonyNotificationBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyMailBundle(),
            new WexampleSymfonyNotificationBundle(),
        ];
    }

    protected function configuresItsOwnSecurity(): bool
    {
        return true;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../../src/Controller', 'attribute');
        $routes->import(__DIR__ . '/Controller', 'attribute');
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__ . '/config/config.yaml'];
    }
}
