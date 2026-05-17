'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Menu as MenuIcon, ShoppingBag, User } from 'lucide-react';
import type { Branding, Menu, MenuItem } from '@/types/api';
import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';
import { MobileDrawer } from './MobileDrawer';

type Props = { branding: Branding; menu: Menu; mobileDrawer: Menu; locale: string };

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

export function Header({ branding, menu, mobileDrawer, locale }: Props) {
  const t = useTranslations('nav');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const otherLocale = locale === 'ar' ? 'en' : 'ar';

  return (
    <header className="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
        <div className="flex items-center gap-3">
          <button
            type="button"
            className="md:hidden p-2 -ms-2 rounded hover:bg-neutral-100"
            aria-label="Open menu"
            onClick={() => setDrawerOpen(true)}
          >
            <MenuIcon className="h-5 w-5" />
          </button>
          <Link href={`/${locale}`} className="flex items-center gap-2">
            {branding.assets.logo_light ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={branding.assets.logo_light} alt={branding.site_name} className="h-8 w-auto" />
            ) : (
              <span className="font-heading text-lg font-bold text-primary-700">{branding.site_name}</span>
            )}
          </Link>
        </div>

        <nav className="hidden md:flex items-center gap-6">
          {menu.items.map((item) => (
            <Link
              key={item.public_id}
              href={hrefFromItem(item, locale)}
              target={item.opens_in_new_tab ? '_blank' : undefined}
              rel={item.opens_in_new_tab ? 'noreferrer' : undefined}
              className="text-sm font-medium text-neutral-700 hover:text-primary-700 transition"
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-1">
          <Link
            href={`/${otherLocale}`}
            className="hidden sm:inline-flex text-sm font-medium px-3 py-1.5 rounded-md hover:bg-neutral-100"
            aria-label={t('language')}
          >
            {t('language')}
          </Link>
          <Link href={`/${locale}/cart`} aria-label={t('cart')} className="p-2 rounded-md hover:bg-neutral-100">
            <ShoppingBag className="h-5 w-5" />
          </Link>
          <Link href={`/${locale}/auth/login`} aria-label={t('login')} className="p-2 rounded-md hover:bg-neutral-100">
            <User className="h-5 w-5" />
          </Link>
        </div>
      </div>

      <MobileDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        items={(mobileDrawer.items.length ? mobileDrawer : menu).items}
        locale={locale}
        hrefFromItem={hrefFromItem}
      />
    </header>
  );
}

// Workaround: cn keeps tailwind-merge tree-shake happy if we import but not use yet.
void cn;
