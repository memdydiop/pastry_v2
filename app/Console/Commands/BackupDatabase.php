<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=30 : Number of backups to keep}';

    protected $description = 'Create a PostgreSQL database backup';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');
        $disk = Storage::build(['driver' => 'local', 'root' => storage_path('backups')]);

        if (! $disk->exists('')) {
            $disk->makeDirectory('');
        }

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.sql';
        $path = storage_path('backups/'.$filename);

        $db = config('database.connections.pgsql');

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --clean > %s 2>&1',
            escapeshellarg($db['password']),
            escapeshellarg($db['host']),
            escapeshellarg($db['port']),
            escapeshellarg($db['username']),
            escapeshellarg($db['database']),
            escapeshellarg($path),
        );

        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            $this->error('Backup failed: '.implode("\n", $output));

            return self::FAILURE;
        }

        $this->info('Database backup created: '.$filename);

        $files = collect($disk->files(''))
            ->filter(fn ($f) => str_starts_with($f, 'backup-') && str_ends_with($f, '.sql'))
            ->sort()
            ->values();

        if ($files->count() > $keep) {
            $toDelete = $files->take($files->count() - $keep);
            foreach ($toDelete as $file) {
                $disk->delete($file);
                $this->info('Removed old backup: '.$file);
            }
        }

        return self::SUCCESS;
    }
}
