<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Models\Domain;

class MigrateExistingDataToTenant extends Command
{
    protected $signature = 'tenant:migrate-existing-data';
    protected $description = 'Migre les donnees du schema public vers le premier tenant (tenant_1)';

    private array $tables = [
        'users', 'clients', 'orders', 'order_levels', 'order_images',
        'order_status_logs', 'transactions', 'ingredients',
        'inventory_movements', 'recipes', 'recipe_ingredients',
        'suppliers', 'delivery_partners', 'settings', 'whatsapp_templates',
        'experiences', 'passkeys', 'notifications',
        'permissions', 'roles', 'model_has_roles', 'model_has_permissions',
        'role_has_permissions',
    ];

    public function handle(): int
    {
        $this->info('=== Migration des donnees existantes vers tenant_1 ===');

        $companyName = Setting::getValue('company_name', 'Patisserie');

        Domain::query()->delete();
        Tenant::query()->delete();

        $tenant = Tenant::create([
            'name' => $companyName,
            'slug' => 'app',
            'schema_name' => 'tenant_1',
            'status' => 'active',
        ]);
        $tenant->setInternal('db_name', 'tenant_1');
        $tenant->save();

        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'app.' . config('app.domain', 'pastrysaas.com'),
            'is_primary' => true,
        ]);

        $this->info("Tenant cree : {$tenant->name} (tenant_1)");

        DB::statement("CREATE SCHEMA IF NOT EXISTS tenant_1");
        $this->info("Schema cree : tenant_1");

        $this->setupTenantConnection($tenant);

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
            '--realpath' => true,
            '--database' => 'tenant',
        ]);
        $this->info("Migrations executees dans tenant_1");

        DB::purge('tenant');
        DB::reconnect('pgsql');

        DB::statement('SET session_replication_role = replica;');

        $teamIdTables = ['roles', 'model_has_roles', 'model_has_permissions'];
        foreach ($this->tables as $table) {
            if (!$this->tableExistsInPublic($table)) continue;

            $columns = $this->getCommonColumns($table);
            if (empty($columns)) continue;

            $count = DB::selectOne("SELECT COUNT(*) AS cnt FROM public.{$table}")->cnt;
            if ($count == 0) {
                $this->line("  - {$table} : 0 ligne");
                continue;
            }

            // Detect type mismatch on team_id: bigint in public, uuid in tenant
            $hasTeamIdMismatch = in_array($table, $teamIdTables, true)
                && in_array('team_id', $columns, true);

            if ($hasTeamIdMismatch) {
                $otherCols = array_values(array_filter($columns, fn($c) => $c !== 'team_id'));
                $colList = implode(', ', array_map(fn($c) => "\"{$c}\"", $otherCols));
                $selectList = implode(', ', array_map(fn($c) => "\"{$c}\"", $otherCols));
                DB::statement("INSERT INTO tenant_1.{$table} ({$colList}, \"team_id\") SELECT {$selectList}, '{$tenant->id}'::uuid FROM public.{$table}");
            } else {
                $colList = implode(', ', array_map(fn($c) => "\"{$c}\"", $columns));
                DB::statement("INSERT INTO tenant_1.{$table} ({$colList}) SELECT {$colList} FROM public.{$table}");
            }
            $this->line("  v {$table} : {$count} lignes copiees");
        }

        $this->updateSequences();
        $this->migrateStorageFiles($tenant);

        DB::statement('SET session_replication_role = default;');

        $this->verifyMigration();
        $this->info('=== Migration terminee avec succes ===');

        return Command::SUCCESS;
    }

    private function setupTenantConnection(Tenant $tenant): void
    {
        $template = config('tenancy.database.template_tenant_connection')
            ?? config('tenancy.database.central_connection');
        $templateConfig = config("database.connections.{$template}");
        $tenantConfig = $templateConfig;
        $tenantConfig['search_path'] = $tenant->getInternal('db_name');
        config(["database.connections.tenant" => $tenantConfig]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function getCommonColumns(string $table): array
    {
        $publicCols = DB::select(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?",
            [$table]
        );
        $tenantCols = DB::select(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'tenant_1' AND table_name = ?",
            [$table]
        );
        $publicNames = array_map(fn($c) => $c->column_name, $publicCols);
        $tenantNames = array_map(fn($c) => $c->column_name, $tenantCols);
        return array_values(array_intersect($publicNames, $tenantNames));
    }

    private function updateSequences(): void
    {
        foreach ($this->tables as $table) {
            if (!$this->tableExistsInPublic($table)) continue;
            $type = DB::selectOne(
                "SELECT data_type FROM information_schema.columns WHERE table_schema = 'tenant_1' AND table_name = ? AND column_name = 'id'",
                [$table]
            );
            if (!$type || !in_array($type->data_type, ['integer', 'bigint', 'smallint'], true)) continue;
            $max = DB::selectOne("SELECT MAX(id) AS max_id FROM tenant_1.{$table}");
            if ($max && $max->max_id > 0) {
                try {
                    DB::statement("SELECT setval('tenant_1.{$table}_id_seq', {$max->max_id})");
                    $this->line("  -> Sequence {$table}_id_seq -> {$max->max_id}");
                } catch (\Exception $e) {
                    // no sequence
                }
            }
        }
    }

    private function tableExistsInPublic(string $table): bool
    {
        return (bool) DB::selectOne(
            "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?)",
            [$table]
        )->exists;
    }

    private function migrateStorageFiles(Tenant $tenant): void
    {
        $this->info('  -> Migration des fichiers...');

        $originalDisks = config('tenancy.filesystem.disks');
        config(['tenancy.filesystem.disks' => []]);

        foreach (['avatars', 'covers', 'orders'] as $dir) {
            if (Storage::disk('public')->exists($dir)) {
                $files = Storage::disk('public')->allFiles($dir);
                foreach ($files as $file) {
                    Storage::disk('public')->move($file, "{$tenant->schema_name}/{$file}");
                }
                $this->line("  v {$dir} : " . count($files) . " fichiers deplaces");
            }
        }

        config(['tenancy.filesystem.disks' => $originalDisks]);
    }

    private function verifyMigration(): void
    {
        $this->info('  -> Verification :');
        $this->line('  ' . str_pad('Table', 25) . 'public    tenant_1');

        foreach ($this->tables as $table) {
            if (!$this->tableExistsInPublic($table)) continue;
            $public = DB::selectOne("SELECT COUNT(*) AS cnt FROM public.{$table}")->cnt;
            $tenantCount = DB::selectOne("SELECT COUNT(*) AS cnt FROM tenant_1.{$table}")->cnt;
            $status = $public === $tenantCount ? 'v' : '!';
            $this->line("  {$status} " . str_pad($table, 22) . str_pad((string)$public, 10) . $tenantCount);
        }
    }
}
