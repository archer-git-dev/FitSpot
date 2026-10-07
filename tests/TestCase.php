<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'pgsql'
            || $app['config']->get('database.connections.pgsql.database') !== 'fitspot_test'
            || $app['config']->get('database.connections.pgsql.url')) {
            throw new \RuntimeException('Database tests require the dedicated PostgreSQL fitspot_test database.');
        }

        return $app;
    }
}
