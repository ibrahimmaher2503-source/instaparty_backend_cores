import { describe, it, expect } from 'vitest';
import type { MenuItem } from '@/types/api';

function hrefFromItem(item: MenuItem, locale: string): string {
  switch (item.target_type) {
    case 'internal_path':
      return `/${locale}${item.target_value.startsWith('/') ? item.target_value : `/${item.target_value}`}`;
    case 'cms_page':
      return `/${locale}/p/${item.target_value}`;
    case 'category':
      return `/${locale}/c/${item.target_value}`;
    case 'occasion':
      return `/${locale}/o/${item.target_value}`;
    case 'external_url':
    default:
      return item.target_value;
  }
}

const make = (target_type: MenuItem['target_type'], target_value: string): MenuItem => ({
  public_id: 'x',
  label: 'x',
  target_type,
  target_value,
  icon: null,
  opens_in_new_tab: false,
  children: [],
});

describe('hrefFromItem (menu dispatch)', () => {
  it('prefixes internal_path with /{locale}', () => {
    expect(hrefFromItem(make('internal_path', '/search'), 'en')).toBe('/en/search');
    expect(hrefFromItem(make('internal_path', 'search'), 'ar')).toBe('/ar/search');
  });
  it('routes cms_page through /p/{slug}', () => {
    expect(hrefFromItem(make('cms_page', 'terms'), 'en')).toBe('/en/p/terms');
  });
  it('routes category through /c/{slug}', () => {
    expect(hrefFromItem(make('category', 'cakes'), 'ar')).toBe('/ar/c/cakes');
  });
  it('routes occasion through /o/{slug}', () => {
    expect(hrefFromItem(make('occasion', 'wedding'), 'en')).toBe('/en/o/wedding');
  });
  it('passes external_url through verbatim', () => {
    expect(hrefFromItem(make('external_url', 'https://x.com'), 'en')).toBe('https://x.com');
  });
});
