<?php

namespace App\Console\Commands;

use App\Support\RolePermissionSynchronizer;
use Illuminate\Console\Command;

class RepairRolePermissions extends Command
{
    protected $signature = 'permissions:repair';
    protected $description = 'Create the permission catalog and remove stale role permission links';

    public function handle(RolePermissionSynchronizer $synchronizer): int
    {
        $synchronizer->synchronize();
        $this->info('Role permissions repaired.');

        return self::SUCCESS;
    }
}
