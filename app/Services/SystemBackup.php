<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class SystemBackup
{
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'sessions',
    ];

    public static function listBackups(): array
    {
        $dir = self::backupDirectory();
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.zip') ?: [];

        return collect($files)
            ->map(function (string $path) {
                return [
                    'filename' => basename($path),
                    'size' => filesize($path) ?: 0,
                    'created_at' => date(DATE_ATOM, filemtime($path) ?: time()),
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public static function createBackup(): array
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('PHP ext-zip is required to create backup archives.');
        }

        self::ensureBackupDirectory();

        $filename = 'strata-service-billing-support-platform-backup-'.now()->format('Y-m-d-His').'.zip';
        $path = self::backupDirectory().DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        try {
            $tables = self::databaseTables();
            $publicDisk = Storage::disk('public');
            $publicFiles = $publicDisk->allFiles();
            $installedLock = storage_path('installed.lock');

            $manifest = [
                'app' => [
                    'name' => config('app.name'),
                    'version' => self::appVersion(),
                    'url' => config('app.url'),
                ],
                'created_at' => now()->toIso8601String(),
                'database' => [
                    'driver' => config('database.default'),
                    'tables' => $tables,
                ],
                'files' => [
                    'public_disk_count' => count($publicFiles),
                    'includes_installed_lock' => is_file($installedLock),
                ],
            ];

            $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            foreach ($tables as $table) {
                $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();

                $zip->addFromString(
                    "database/{$table}.json",
                    json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }

            foreach ($publicFiles as $file) {
                $absolute = $publicDisk->path($file);
                if (is_file($absolute)) {
                    $zip->addFile($absolute, 'files/public/'.$file);
                }
            }

            if (is_file($installedLock)) {
                $zip->addFile($installedLock, 'files/storage/installed.lock');
            }
        } finally {
            $zip->close();
        }

        return [
            'filename' => $filename,
            'path' => $path,
        ];
    }

    public static function restoreFromUpload(UploadedFile $archive): void
    {
        if (! extension_loaded('zip')) {
            throw new RuntimeException('PHP ext-zip is required to restore backup archives.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archive->getRealPath()) !== true) {
            throw new RuntimeException('The uploaded backup archive could not be opened.');
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                throw new RuntimeException('The uploaded file is not a valid Strata backup archive.');
            }

            $manifest = json_decode($manifestRaw, true);
            $tables = $manifest['database']['tables'] ?? [];
            if (! is_array($tables) || $tables === []) {
                throw new RuntimeException('The backup archive does not include any database tables.');
            }

            self::restoreDatabase($zip, $tables);
            self::restorePublicDisk($zip);
            self::restoreInstalledLock($zip);

            Cache::forget('app_settings');

            foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'event:clear'] as $command) {
                try {
                    Artisan::call($command);
                } catch (\Throwable) {
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function restoreDatabase(ZipArchive $zip, array $tables): void
    {
        $currentTables = collect(self::databaseTables())->flip();

        DB::beginTransaction();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($tables as $table) {
                if (! is_string($table) || ! $currentTables->has($table)) {
                    continue;
                }

                $tableData = $zip->getFromName("database/{$table}.json");
                if ($tableData === false) {
                    continue;
                }

                $rows = json_decode($tableData, true);
                if (! is_array($rows)) {
                    throw new RuntimeException("Backup data for table {$table} is invalid.");
                }

                DB::table($table)->truncate();

                foreach (array_chunk($rows, 250) as $chunk) {
                    if ($chunk !== []) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
            }

            throw $e;
        }
    }

    private static function restorePublicDisk(ZipArchive $zip): void
    {
        $disk = Storage::disk('public');

        $existingFiles = $disk->allFiles();
        if ($existingFiles !== []) {
            $disk->delete($existingFiles);
        }

        $existingDirectories = $disk->allDirectories();
        if ($existingDirectories !== []) {
            usort($existingDirectories, fn ($a, $b) => substr_count($b, '/') <=> substr_count($a, '/'));
            foreach ($existingDirectories as $directory) {
                $disk->deleteDirectory($directory);
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! is_string($entry) || ! str_starts_with($entry, 'files/public/')) {
                continue;
            }

            $relativePath = substr($entry, strlen('files/public/'));
            if ($relativePath === '' || str_ends_with($entry, '/')) {
                continue;
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                throw new RuntimeException("Could not restore file {$relativePath} from the backup archive.");
            }

            $disk->put($relativePath, $contents);
        }
    }

    private static function restoreInstalledLock(ZipArchive $zip): void
    {
        $installedLock = $zip->getFromName('files/storage/installed.lock');
        if ($installedLock === false) {
            return;
        }

        file_put_contents(storage_path('installed.lock'), $installedLock);
    }

    private static function databaseTables(): array
    {
        $rows = DB::select('SHOW TABLES');

        return collect($rows)
            ->map(fn ($row) => array_values((array) $row)[0] ?? null)
            ->filter(fn ($table) => is_string($table) && ! in_array($table, self::EXCLUDED_TABLES, true))
            ->values()
            ->all();
    }

    private static function appVersion(): string
    {
        $lockPath = storage_path('installed.lock');
        if (is_file($lockPath)) {
            $lock = json_decode(file_get_contents($lockPath), true);
            if (! empty($lock['version'])) {
                return (string) $lock['version'];
            }
        }

        $composerPath = base_path('composer.json');
        if (is_file($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (! empty($composer['version'])) {
                return (string) $composer['version'];
            }
        }

        return 'unknown';
    }

    private static function ensureBackupDirectory(): void
    {
        if (! is_dir(self::backupDirectory())) {
            mkdir(self::backupDirectory(), 0755, true);
        }
    }

    private static function backupDirectory(): string
    {
        return storage_path('app/backups');
    }
}
