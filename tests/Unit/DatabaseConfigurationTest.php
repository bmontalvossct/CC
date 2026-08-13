<?php

namespace Tests\Unit;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Env;
use PDO;
use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_neon_pooler_url_enables_laravel_pooled_postgres_mode(): void
    {
        $environment = Env::getRepository();
        $originalDatabaseUrl = $environment->get('DATABASE_URL');
        $originalDirectUrl = $environment->get('DB_DATABASE_URL_UNPOOLED');
        $originalPooled = $environment->get('DB_POOLED');

        try {
            $environment->set('DATABASE_URL', 'postgresql://user:password@ep-example-pooler.test/neondb?sslmode=require');
            $environment->set('DB_DATABASE_URL_UNPOOLED', 'postgresql://user:password@ep-example.test/neondb?sslmode=require');
            $environment->clear('DB_POOLED');

            $database = require config_path('database.php');
            $postgres = $database['connections']['pgsql'];
            $connection = (new ConnectionFactory($this->app))->make($postgres, 'pgsql');

            $this->assertTrue($postgres['pooled']);
            $this->assertSame('ep-example.test', $postgres['direct']['host']);
            $this->assertSame('neondb', $postgres['direct']['database']);
            $this->assertTrue($connection->getConfig('options')[PDO::ATTR_EMULATE_PREPARES]);
        } finally {
            $this->restoreEnvironmentValue('DATABASE_URL', $originalDatabaseUrl);
            $this->restoreEnvironmentValue('DB_DATABASE_URL_UNPOOLED', $originalDirectUrl);
            $this->restoreEnvironmentValue('DB_POOLED', $originalPooled);
        }
    }

    private function restoreEnvironmentValue(string $key, mixed $value): void
    {
        $environment = Env::getRepository();

        if ($value === null) {
            $environment->clear($key);

            return;
        }

        $environment->set($key, $value);
    }
}
