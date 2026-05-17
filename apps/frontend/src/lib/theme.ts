import type { DesignTokens } from '@/types/api';

/**
 * Flatten the design-tokens JSON into a string of CSS custom-property declarations.
 * Output is injected inside <style>:root { ... }</style> in the root layout.
 */
export function tokensToCss(tokens: DesignTokens): string {
  const lines: string[] = [];

  for (const palette of ['primary', 'secondary', 'accent', 'neutral'] as const) {
    const ramp = tokens.colors[palette];
    for (const [shade, hex] of Object.entries(ramp)) {
      lines.push(`--color-${palette}-${shade}: ${hex};`);
    }
  }
  lines.push(`--color-success: ${tokens.colors.success};`);
  lines.push(`--color-warning: ${tokens.colors.warning};`);
  lines.push(`--color-danger: ${tokens.colors.danger};`);
  lines.push(`--color-info: ${tokens.colors.info};`);

  lines.push(`--font-base: "${tokens.typography.fontFamilyBase}";`);
  lines.push(`--font-heading: "${tokens.typography.fontFamilyHeading}";`);
  lines.push(`--font-arabic: "${tokens.typography.fontFamilyArabic}";`);

  for (const [k, v] of Object.entries(tokens.radius)) {
    lines.push(`--radius-${k}: ${v};`);
  }
  for (const [k, v] of Object.entries(tokens.shadow)) {
    lines.push(`--shadow-${k}: ${v};`);
  }

  return `:root{${lines.join('')}}`;
}

export function googleFontHref(tokens: DesignTokens): string | null {
  return tokens.typography.googleFontUrl ?? null;
}

export function isRtl(locale: string): boolean {
  return locale === 'ar';
}

export function fontFamilyForLocale(locale: string, tokens: DesignTokens): string {
  return isRtl(locale) ? tokens.typography.fontFamilyArabic : tokens.typography.fontFamilyBase;
}
