'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Trash2 } from 'lucide-react';
import { wishlistApi, type WishlistItemRow } from '@/lib/api';
import { formatPriceEGP } from '@/lib/utils';

export default function WishlistPage() {
  const t = useTranslations('me.wishlist');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const [items, setItems] = useState<WishlistItemRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    wishlistApi.list(locale).then(setItems).finally(() => setLoading(false));
  }, [locale]);

  const remove = async (servicePublicId: string) => {
    await wishlistApi.remove(locale, servicePublicId);
    setItems((prev) => prev.filter((i) => i.service.public_id !== servicePublicId));
  };

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      {loading ? (
        <p className="text-sm text-neutral-500">{t('loading')}</p>
      ) : items.length === 0 ? (
        <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
          {t('empty')}
        </p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {items.map((row) => (
            <li
              key={row.public_id}
              className="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white p-3"
            >
              {row.service.cover_image_url && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={row.service.cover_image_url} alt="" className="h-16 w-16 rounded object-cover" />
              )}
              <div className="min-w-0 flex-1">
                <Link
                  href={`/${locale}/services/${row.service.public_id}`}
                  className="block truncate text-sm font-medium hover:text-primary-600"
                >
                  {row.service.name}
                </Link>
                <p className="text-xs text-neutral-500">{row.service.product_type}</p>
                <p className="text-xs font-medium tabular-nums">
                  {formatPriceEGP(row.service.price_from_minor, locale)}
                </p>
              </div>
              <button
                type="button"
                onClick={() => remove(row.service.public_id)}
                className="p-2 text-neutral-500 hover:text-danger"
                aria-label={t('remove')}
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
