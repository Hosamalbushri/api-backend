<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $app->loadEnvironmentFrom($this->testingEnvironmentFile($app));
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Always load a testing env file so PHPUnit does not read a missing (or production) `.env`.
     */
    private function testingEnvironmentFile(Application $app): string
    {
        $path = $app->environmentPath().'/.env.testing';
        if (is_file($path)) {
            return '.env.testing';
        }

        $dir = $app->storagePath('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fallback = $dir.'/.env';
        if (! is_file($fallback)) {
            file_put_contents($fallback, <<<'ENV'
APP_ENV=testing
APP_KEY=base64:2fl+Ktvkfl+Fuz4Qp/Ej30Tes4O1gVlO/9j4C6AKEiQ=
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
DB_URL=
CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
LOG_CHANNEL=null
SEED_ON_BOOT=false
ALLOW_DEV_TOKEN=true
DEV_API_TOKEN=test-token
AUTH_RATE_LIMIT_PER_MINUTE=0
JWT_SECRET=test-jwt-secret-change-me-please-use-long-random
JWT_ISSUER=nexabiz-experimental
CORS_ORIGINS=*
SEED_ADMIN_PASSWORD=ChangeMeAdmin!123
ENV);
        }

        $app->useEnvironmentPath($dir);

        return '.env';
    }
}
