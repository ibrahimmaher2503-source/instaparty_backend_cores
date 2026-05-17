<?php

declare(strict_types=1);

// Unit directory already has TestCase applied in tests/Pest.php

describe('vendor-onboarding translations', function () {
    it('has identical key sets in en and ar vendor-onboarding files', function () {
        $en = require app_path('Modules/Identity/Resources/lang/en/vendor-onboarding.php');
        $ar = require app_path('Modules/Identity/Resources/lang/ar/vendor-onboarding.php');

        $flattenKeys = function (array $array, string $prefix = '') use (&$flattenKeys): array {
            $keys = [];
            foreach ($array as $key => $value) {
                $fullKey = $prefix ? $prefix . '.' . $key : (string) $key;
                if (is_array($value)) {
                    $keys = array_merge($keys, $flattenKeys($value, $fullKey));
                } else {
                    $keys[] = $fullKey;
                }
            }
            return $keys;
        };

        $enKeys = $flattenKeys($en);
        $arKeys = $flattenKeys($ar);

        sort($enKeys);
        sort($arKeys);

        expect($arKeys)->toBe($enKeys);
    });

    it('has no empty string values in either locale file', function () {
        $en = require app_path('Modules/Identity/Resources/lang/en/vendor-onboarding.php');
        $ar = require app_path('Modules/Identity/Resources/lang/ar/vendor-onboarding.php');

        $flattenValues = function (array $array): array {
            $values = [];
            array_walk_recursive($array, function ($value) use (&$values) {
                if ($value !== null) {
                    $values[] = $value;
                }
            });
            return $values;
        };

        $enValues = $flattenValues($en);
        $arValues = $flattenValues($ar);

        foreach ($enValues as $value) {
            expect($value)->not->toBe('');
        }

        foreach ($arValues as $value) {
            expect($value)->not->toBe('');
        }
    });
});
