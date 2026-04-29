<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

function flattenLang(string $path): array
{
    return Arr::dot(require $path);
}

function assertParity(string $namespace, string $enPath, string $arPath): void
{
    expect(file_exists($enPath))->toBeTrue("Missing EN file for {$namespace}: {$enPath}");
    expect(file_exists($arPath))->toBeTrue("Missing AR file for {$namespace}: {$arPath}");

    $en = flattenLang($enPath);
    $ar = flattenLang($arPath);

    $missingInAr = array_diff_key($en, $ar);
    $missingInEn = array_diff_key($ar, $en);

    expect($missingInAr)->toBe([], "Keys in EN but missing in AR for {$namespace}: ".implode(', ', array_keys($missingInAr)));
    expect($missingInEn)->toBe([], "Keys in AR but missing in EN for {$namespace}: ".implode(', ', array_keys($missingInEn)));
}

it('has parity for admin translations', function (): void {
    assertParity(
        'admin',
        base_path('lang/en/admin.php'),
        base_path('lang/ar/admin.php'),
    );
})->group('i18n');

it('has parity for identity translations', function (): void {
    assertParity(
        'identity',
        base_path('app/Modules/Identity/Resources/lang/en/identity.php'),
        base_path('app/Modules/Identity/Resources/lang/ar/identity.php'),
    );
})->group('i18n');

it('has parity for geography translations', function (): void {
    assertParity(
        'geography',
        base_path('app/Modules/Geography/Resources/lang/en/geography.php'),
        base_path('app/Modules/Geography/Resources/lang/ar/geography.php'),
    );
})->group('i18n');

it('has parity for filament-shield vendor translations', function (): void {
    assertParity(
        'filament-shield',
        base_path('lang/vendor/filament-shield/en/filament-shield.php'),
        base_path('lang/vendor/filament-shield/ar/filament-shield.php'),
    );
})->group('i18n');
