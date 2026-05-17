'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { LogIn, User } from 'lucide-react';
import { useTranslations } from 'next-intl';
import * as Popover from '@radix-ui/react-popover';
import { useAuth } from '@/components/auth/AuthProvider';
import { useAuthStore } from '@/lib/store/auth';
import { authApi, clearToken } from '@/lib/auth';

export function AccountMenu({ locale }: { locale: string }) {
  const t = useTranslations('nav');
  const { user, status } = useAuth();
  const router = useRouter();
  const reset = useAuthStore((s) => s.reset);

  if (status !== 'authenticated' || !user) {
    return (
      <Link
        href={`/${locale}/auth/login`}
        aria-label={t('login')}
        className="p-2 rounded-md hover:bg-neutral-100"
      >
        <LogIn className="h-5 w-5" />
      </Link>
    );
  }

  const onLogout = async () => {
    try {
      await authApi.logout();
    } catch {
      // proceed to clear local state even if logout call fails
    }
    clearToken();
    reset();
    router.push(`/${locale}`);
    router.refresh();
  };

  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <button
          type="button"
          aria-label={user.name}
          className="p-2 rounded-md hover:bg-neutral-100"
        >
          <User className="h-5 w-5" />
        </button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          className="z-50 mt-2 w-56 rounded-md border border-neutral-200 bg-white p-1 shadow-lg"
          align="end"
        >
          <div className="px-3 py-2 text-sm">
            <p className="font-medium text-neutral-900">{user.name}</p>
            <p className="text-xs text-neutral-500">{user.phone_e164}</p>
          </div>
          <hr className="my-1 border-neutral-200" />
          <Link href={`/${locale}/me/profile`} className="block rounded px-3 py-2 text-sm hover:bg-neutral-100">
            {t('profile')}
          </Link>
          <Link href={`/${locale}/me/bookings`} className="block rounded px-3 py-2 text-sm hover:bg-neutral-100">
            {t('my_bookings')}
          </Link>
          <Link href={`/${locale}/me/wishlist`} className="block rounded px-3 py-2 text-sm hover:bg-neutral-100">
            {t('wishlist')}
          </Link>
          <hr className="my-1 border-neutral-200" />
          <button
            type="button"
            onClick={onLogout}
            className="block w-full rounded px-3 py-2 text-start text-sm text-danger hover:bg-danger/10"
          >
            {t('logout')}
          </button>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}
