<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (app()->environment('testing')) {
            \Illuminate\Support\Facades\Schema::dropIfExists('sessions');
            \Illuminate\Support\Facades\Schema::dropIfExists('password_reset_tokens');
            \Illuminate\Support\Facades\Schema::dropIfExists('users');
            \Illuminate\Support\Facades\Schema::dropIfExists('cache_locks');
            \Illuminate\Support\Facades\Schema::dropIfExists('cache');
            \Illuminate\Support\Facades\Schema::dropIfExists('job_batches');
            \Illuminate\Support\Facades\Schema::dropIfExists('failed_jobs');
            \Illuminate\Support\Facades\Schema::dropIfExists('jobs');

            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
            ]);
        }
    }
}
