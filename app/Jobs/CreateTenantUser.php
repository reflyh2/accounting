<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;
use App\Models\CentralUser;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class CreateTenantUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $centralUser;

    public function __construct(Tenant $tenant, CentralUser $centralUser)
    {
        $this->tenant = $tenant;
        $this->centralUser = $centralUser;
    }

    public function handle()
    {
        // Check if the tenant's database is ready
        if (!$this->tenant->database()->manager()->databaseExists($this->tenant->database()->getName())) {
            // If not ready, retry the job after a delay
            $this->release(30); // Retry after 30 seconds
            return;
        }

        $this->tenant->run(function () {
            // Check if the users table exists
            if (!Schema::hasTable('users')) {
                // If the table doesn't exist, retry the job
                $this->release(30);
                return;
            }

            \App\Models\User::create([
                'global_id' => $this->centralUser->global_id,
                'name' => $this->centralUser->name,
                'email' => $this->centralUser->email,
                'password' => $this->centralUser->password,
            ]);
        });
    }
}