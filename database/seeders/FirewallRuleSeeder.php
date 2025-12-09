<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FirewallRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        // Default firewall rules from setup-1-ubuntu.sh provision script
        $rules = [
            // SSH Access
            [
                'name' => 'SSH',
                'action' => 'allow',
                'direction' => 'in',
                'port' => '22',
                'protocol' => 'tcp',
                'from_ip' => 'any',
                'to_ip' => null,
                'is_active' => true,
                'is_system' => true, // System rule - cannot be deleted
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // HTTP Access (Nginx)
            [
                'name' => 'Nginx HTTP',
                'action' => 'allow',
                'direction' => 'in',
                'port' => '80',
                'protocol' => 'tcp',
                'from_ip' => 'any',
                'to_ip' => null,
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            
            // HTTPS Access (Nginx)
            [
                'name' => 'Nginx HTTPS',
                'action' => 'allow',
                'direction' => 'in',
                'port' => '443',
                'protocol' => 'tcp',
                'from_ip' => 'any',
                'to_ip' => null,
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        // Insert rules only if table is empty
        if (DB::table('firewall_rules')->count() === 0) {
            DB::table('firewall_rules')->insert($rules);
            $this->command->info('✓ Default firewall rules seeded (SSH, HTTP, HTTPS)');
        } else {
            $this->command->info('ℹ Firewall rules already exist, skipping...');
        }
    }
}
