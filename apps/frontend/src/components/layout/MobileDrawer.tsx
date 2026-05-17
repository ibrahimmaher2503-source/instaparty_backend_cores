'use client';

import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import Link from 'next/link';
import type { MenuItem } from '@/types/api';
import { useTranslations } from 'next-intl';

type Props = {
  open: boolean;
  onClose: () => void;
  items: MenuItem[];
  locale: string;
  hrefFromItem: (item: MenuItem, locale: string) => string;
};

export function MobileDrawer({ open, onClose, items, locale, hrefFromItem }: Props) {
  const t = useTranslations('common');

  return (
    <Dialog.Root open={open} onOpenChange={(o) => (o ? null : onClose())}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 bg-black/40 z-50" />
        <Dialog.Content className="fixed start-0 top-0 h-full w-72 bg-white z-50 p-4 shadow-xl flex flex-col">
          <div className="flex items-center justify-between mb-4">
            <Dialog.Title className="font-heading text-base">{t('menu')}</Dialog.Title>
            <Dialog.Close asChild>
              <button aria-label={t('close')} className="p-2 rounded hover:bg-neutral-100">
                <X className="h-4 w-4" />
              </button>
            </Dialog.Close>
          </div>
          <nav className="flex flex-col gap-1">
            {items.map((item) => (
              <Link
                key={item.public_id}
                href={hrefFromItem(item, locale)}
                onClick={onClose}
                className="px-3 py-2 rounded hover:bg-neutral-100 text-sm font-medium"
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
