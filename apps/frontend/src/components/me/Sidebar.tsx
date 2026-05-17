'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useTranslations } from 'next-intl';
import {
  CalendarCheck,
  Heart,
  MapPin,
  UserCircle,
  Sparkles,
  Bell,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const ITEMS = [
  { href: '/me/bookings', key: 'bookings', icon: CalendarCheck },
  { href: '/me/wishlist', key: 'wishlist', icon: Heart },
  { href: '/me/addresses', key: 'addresses', icon: MapPin },
  { href: '/me/profile', key: 'profile', icon: UserCircle },
  { href: '/me/loyalty', key: 'loyalty', icon: Sparkles },
  { href: '/me/notifications', key: 'notifications', icon: Bell },
];

export function MeSidebar({ locale }: { locale: string }) {
  const t = useTranslations('me.nav');
  const pathname = usePathname() ?? '';

  return (
    <nav className="rounded-lg border border-neutral-200 bg-white p-2 text-sm">
      <ul className="space-y-1">
        {ITEMS.map((item) => {
          const Icon = item.icon;
          const href = `/${locale}${item.href}`;
          const isActive = pathname === href || pathname.startsWith(`${href}/`);
          return (
            <li key={item.key}>
              <Link
                href={href}
                className={cn(
                  'flex items-center gap-2 rounded-md px-3 py-2',
                  isActive ? 'bg-primary-50 text-primary-700' : 'text-neutral-700 hover:bg-neutral-50',
                )}
              >
                <Icon className="h-4 w-4" />
                {t(item.key)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
