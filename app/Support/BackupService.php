<?php

namespace App\Support;

use RuntimeException;

class BackupService
{
    public function __construct(private ?string $databasePath = null, private ?string $directory = null)
    {
        $this->databasePath ??= (string) config('database.connections.sqlite.database');
        $this->directory ??= storage_path('app/backups');
    }

    public function supported(): bool
    {
        return config('database.default') === 'sqlite' && $this->databasePath !== ':memory:' && is_file($this->databasePath);
    }

    /** @return list<array{name: string, size: int, created: string}> */
    public function all(): array
    {
        $files = glob($this->directory.'/backup-*.sqlite') ?: [];
        rsort($files);

        return array_map(fn ($f) => ['name' => basename($f), 'size' => (int) filesize($f), 'created' => date('Y-m-d H:i:s', (int) filemtime($f))], $files);
    }

    public function create(): string
    {
        if (! $this->supported()) {
            throw new RuntimeException('Backups need a SQLite database file.');
        }
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $name = 'backup-'.date('Ymd-His').'-'.random_int(100, 999).'.sqlite';
        if (! copy($this->databasePath, $this->directory.'/'.$name)) {
            throw new RuntimeException('The backup could not be written.');
        }

        return $name;
    }

    public function path(string $name): string
    {
        if (! preg_match('/^backup-\d{8}-\d{6}-\d{3}\.sqlite$/', $name) || ! is_file($this->directory.'/'.$name)) {
            throw new RuntimeException('That backup does not exist.');
        }

        return $this->directory.'/'.$name;
    }

    public function delete(string $name): void
    {
        unlink($this->path($name));
    }

    /** Replace the live database with a backup, taking a safety copy first. */
    public function restore(string $name): string
    {
        $source = $this->path($name);
        $safety = $this->create();

        if (! copy($source, $this->databasePath)) {
            throw new RuntimeException('The backup could not be restored.');
        }

        return $safety;
    }
}
