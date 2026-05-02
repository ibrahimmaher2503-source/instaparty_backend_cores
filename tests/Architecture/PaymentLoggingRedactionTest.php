<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('every writer that inserts into payment_attempts or gateway_webhook_logs also references RedactPciFields', function (): void {
    $offenders = [];

    $finder = (new Finder)
        ->files()
        ->in([
            base_path('app/Modules/Payments/Application/Actions'),
            base_path('app/Modules/Payments/Application/Listeners'),
            base_path('app/Modules/Payments/Infrastructure/Repositories'),
            base_path('app/Modules/Payments/Infrastructure/Gateways'),
        ])
        ->name('*.php');

    foreach ($finder as $file) {
        $contents = $file->getContents();

        $writesAttempts = preg_match('/PaymentAttempt::query\(\)->create|PaymentAttempt::create|DB::table\([\'"]payment_attempts[\'"]\)->insert|DB::table\([\'"]payment_attempts[\'"]\)->insertOrIgnore/', $contents);
        $writesLogs = preg_match('/GatewayWebhookLog::query\(\)->create|GatewayWebhookLog::create|DB::table\([\'"]gateway_webhook_logs[\'"]\)->insert/', $contents);

        if (! $writesAttempts && ! $writesLogs) {
            continue;
        }

        if (! str_contains($contents, 'RedactPciFields')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'These writer files insert into payment_attempts / gateway_webhook_logs without referencing RedactPciFields: '.implode(', ', $offenders));
});
