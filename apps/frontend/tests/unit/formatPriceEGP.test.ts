import { describe, it, expect } from 'vitest';
import { formatPriceEGP, cn } from '@/lib/utils';

describe('formatPriceEGP', () => {
  it('converts minor units to major EGP for en', () => {
    const out = formatPriceEGP(123400, 'en');
    expect(out).toMatch(/1,?234/);
    expect(out).toMatch(/EGP|E£|£/);
  });

  it('formats in Arabic locale', () => {
    const out = formatPriceEGP(5000, 'ar');
    expect(out.length).toBeGreaterThan(0);
  });

  it('omits fraction digits', () => {
    const out = formatPriceEGP(10050, 'en');
    expect(out).not.toMatch(/\.50/);
  });
});

describe('cn', () => {
  it('merges class lists', () => {
    expect(cn('a', 'b')).toBe('a b');
  });
  it('dedupes conflicting tailwind classes', () => {
    expect(cn('p-2', 'p-4')).toBe('p-4');
  });
  it('handles falsy values', () => {
    expect(cn('a', false, null, undefined, 'b')).toBe('a b');
  });
});
