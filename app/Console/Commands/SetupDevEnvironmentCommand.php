<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupDevEnvironmentCommand extends Command
{
    protected $signature = 'app:setup-dev-env';

    protected $description = 'Verify and bootstrap local dev services (MinIO bucket, Meilisearch ping)';

    public function handle(): int
    {
        $this->info('Setting up local dev environment...');

        $this->setupMinioBucket();
        $this->pingMeilisearch();

        $this->newLine();
        $this->info('Dev environment setup complete.');

        return self::SUCCESS;
    }

    private function setupMinioBucket(): void
    {
        $bucket = config('filesystems.disks.s3.bucket');

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => config('filesystems.disks.s3.region', 'us-east-1'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', true),
                'credentials' => [
                    'key' => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);

            if ($client->doesBucketExist($bucket)) {
                $this->line("  <info>✓</info> MinIO bucket [{$bucket}] already exists");
            } else {
                $client->createBucket(['Bucket' => $bucket]);
                $this->line("  <info>✓</info> MinIO bucket [{$bucket}] created");
            }
        } catch (\Throwable $e) {
            $this->line("  <comment>⚠</comment> MinIO: {$e->getMessage()}");
        }
    }

    private function pingMeilisearch(): void
    {
        $host = config('scout.meilisearch.host', 'http://127.0.0.1:7700');

        try {
            $response = Http::timeout(3)->get("{$host}/health");

            if ($response->successful()) {
                $this->line("  <info>✓</info> Meilisearch is healthy at [{$host}]");
            } else {
                $this->line("  <comment>⚠</comment> Meilisearch returned HTTP {$response->status()} at [{$host}]");
            }
        } catch (\Throwable $e) {
            $this->line("  <comment>⚠</comment> Meilisearch: {$e->getMessage()}");
        }
    }
}
