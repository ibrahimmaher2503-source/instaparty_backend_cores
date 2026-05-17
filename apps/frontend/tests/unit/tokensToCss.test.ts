import { describe, it, expect } from 'vitest';
import { tokensToCss, isRtl, fontFamilyForLocale } from '@/lib/theme';
import type { DesignTokens } from '@/types/api';

const ramp = (base: string): Record<string, string> =>
  Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((n) => [String(n), `${base}${n}`]),
  );

const tokens: DesignTokens = {
  version: 1,
  colors: {
    primary: ramp('#p'),
    secondary: ramp('#s'),
    accent: ramp('#a'),
    neutral: ramp('#n'),
    success: '#0a0',
    warning: '#fc0',
    danger: '#f00',
    info: '#00f',
  },
  typography: {
    fontFamilyBase: 'Inter',
    fontFamilyHeading: 'Cairo',
    fontFamilyArabic: 'Tajawal',
    googleFontUrl: 'https://fonts.googleapis.com/x',
    scale: { base: '1rem' },
    weight: { regular: 400 },
    lineHeight: { normal: '1.5' },
  },
  spacing: { scale: [0, 4, 8] },
  radius: { sm: '0.25rem', md: '0.5rem' },
  shadow: { sm: '0 1px 2px rgba(0,0,0,.05)' },
  mode: { supportsDarkMode: false, defaultMode: 'light' },
};

describe('tokensToCss', () => {
  it('emits a single :root rule', () => {
    const css = tokensToCss(tokens);
    expect(css.startsWith(':root{')).toBe(true);
    expect(css.endsWith('}')).toBe(true);
  });

  it('flattens every color shade into a var', () => {
    const css = tokensToCss(tokens);
    expect(css).toContain('--color-primary-500: #p500;');
    expect(css).toContain('--color-secondary-50: #s50;');
    expect(css).toContain('--color-neutral-900: #n900;');
  });

  it('exposes typography family vars', () => {
    const css = tokensToCss(tokens);
    expect(css).toContain('--font-base: "Inter";');
    expect(css).toContain('--font-heading: "Cairo";');
    expect(css).toContain('--font-arabic: "Tajawal";');
  });

  it('exposes radius and shadow vars', () => {
    const css = tokensToCss(tokens);
    expect(css).toContain('--radius-sm: 0.25rem;');
    expect(css).toContain('--shadow-sm: 0 1px 2px rgba(0,0,0,.05);');
  });

  it('exposes semantic colors', () => {
    const css = tokensToCss(tokens);
    expect(css).toContain('--color-success: #0a0;');
    expect(css).toContain('--color-danger: #f00;');
  });
});

describe('isRtl', () => {
  it('returns true only for ar', () => {
    expect(isRtl('ar')).toBe(true);
    expect(isRtl('en')).toBe(false);
    expect(isRtl('fr')).toBe(false);
  });
});

describe('fontFamilyForLocale', () => {
  it('picks the Arabic family in RTL', () => {
    expect(fontFamilyForLocale('ar', tokens)).toBe('Tajawal');
  });
  it('picks the base family otherwise', () => {
    expect(fontFamilyForLocale('en', tokens)).toBe('Inter');
  });
});
