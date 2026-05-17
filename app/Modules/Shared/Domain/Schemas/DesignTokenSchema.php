<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Schemas;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DesignTokenSchema
{
    public const CURRENT_VERSION = 1;

    private const HEX_COLOR = ['regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

    private const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'];

    public static function validate(array $tokens): array
    {
        $rules = [
            'version' => ['required', 'integer', 'in:'.self::CURRENT_VERSION],

            'colors' => ['required', 'array'],
            'colors.primary' => ['required', 'array'],
            'colors.secondary' => ['required', 'array'],
            'colors.accent' => ['required', 'array'],
            'colors.neutral' => ['required', 'array'],
            'colors.success' => array_merge(['required', 'string'], self::HEX_COLOR),
            'colors.warning' => array_merge(['required', 'string'], self::HEX_COLOR),
            'colors.danger' => array_merge(['required', 'string'], self::HEX_COLOR),
            'colors.info' => array_merge(['required', 'string'], self::HEX_COLOR),

            'typography' => ['required', 'array'],
            'typography.fontFamilyBase' => ['required', 'string', 'max:100'],
            'typography.fontFamilyHeading' => ['required', 'string', 'max:100'],
            'typography.fontFamilyArabic' => ['required', 'string', 'max:100'],
            'typography.googleFontUrl' => ['nullable', 'string', 'url', 'max:1024'],
            'typography.scale' => ['required', 'array'],
            'typography.weight' => ['required', 'array'],
            'typography.lineHeight' => ['required', 'array'],

            'spacing' => ['required', 'array'],
            'spacing.scale' => ['required', 'array', 'min:1'],
            'spacing.scale.*' => ['integer', 'min:0'],

            'radius' => ['required', 'array'],
            'radius.*' => ['required', 'string', 'max:32'],

            'shadow' => ['required', 'array'],
            'shadow.*' => ['required', 'string', 'max:255'],

            'mode' => ['required', 'array'],
            'mode.supportsDarkMode' => ['required', 'boolean'],
            'mode.defaultMode' => ['required', 'string', 'in:light,dark'],
        ];

        foreach (['primary', 'secondary', 'accent', 'neutral'] as $palette) {
            foreach (self::SHADES as $shade) {
                $rules["colors.{$palette}.{$shade}"] = array_merge(['required', 'string'], self::HEX_COLOR);
            }
        }

        $validator = Validator::make($tokens, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    public static function default(): array
    {
        return [
            'version' => self::CURRENT_VERSION,
            'colors' => [
                'primary' => self::shadeRamp('#fdf2f8', '#831843'),
                'secondary' => self::shadeRamp('#eef2ff', '#312e81'),
                'accent' => self::shadeRamp('#fefce8', '#713f12'),
                'neutral' => self::shadeRamp('#fafafa', '#171717'),
                'success' => '#16a34a',
                'warning' => '#f59e0b',
                'danger' => '#dc2626',
                'info' => '#0ea5e9',
            ],
            'typography' => [
                'fontFamilyBase' => 'Inter',
                'fontFamilyHeading' => 'Inter',
                'fontFamilyArabic' => 'Tajawal',
                'googleFontUrl' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap',
                'scale' => [
                    'xs' => '0.75rem', 'sm' => '0.875rem', 'base' => '1rem',
                    'lg' => '1.125rem', 'xl' => '1.25rem', '2xl' => '1.5rem',
                    '3xl' => '1.875rem', '4xl' => '2.25rem',
                ],
                'weight' => ['regular' => 400, 'medium' => 500, 'semibold' => 600, 'bold' => 700],
                'lineHeight' => ['tight' => '1.25', 'normal' => '1.5', 'relaxed' => '1.75'],
            ],
            'spacing' => ['scale' => [0, 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96]],
            'radius' => [
                'sm' => '0.25rem', 'md' => '0.5rem', 'lg' => '0.75rem',
                'xl' => '1rem', 'full' => '9999px',
            ],
            'shadow' => [
                'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
                'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            ],
            'mode' => ['supportsDarkMode' => false, 'defaultMode' => 'light'],
        ];
    }

    private static function shadeRamp(string $light, string $dark): array
    {
        return [
            '50' => $light,
            '100' => $light,
            '200' => $light,
            '300' => $light,
            '400' => $dark,
            '500' => $dark,
            '600' => $dark,
            '700' => $dark,
            '800' => $dark,
            '900' => $dark,
        ];
    }
}
