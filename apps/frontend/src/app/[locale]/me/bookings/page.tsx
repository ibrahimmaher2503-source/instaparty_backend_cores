'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { bookingApi } from '@/lib/api';
import { formatPriceEGP } from '@/lib/utils';
import type { Booking } from '@/types/api';

const LIFECYCLE_TABS = ['all', 'submitted', 'awaiting_payment', 'confirmed', 'completed', 'cancelled'] as const;

export default function MyBookingsPage() {
  const t = useTranslations('me.bookings');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const [tab, setTab] = useState<(typeof LIFECYCLE_TABS)[number]>('all');
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    bookingApi
      .list(locale, tab === 'all' ? undefined : tab)
      .then((b) => {
        setBookings(b);
        setError(null);
      })
      .catch(() => setError(t('error_load')))
      .finally(() => setLoading(false));
  }, [locale, tab, t]);

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>

      <div className="mb-4 flex flex-wrap gap-2">
        {LIFECYCLE_TABS.map((status) => (
          <button
            key={status}
            type="button"
            onClick={() => setTab(status)}
            className={
              tab === status
                ? 'rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white'
                : 'rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-xs text-neutral-700 hover:bg-neutral-50'
            }
          >
            {t(`status.${status}`)}
          </button>
        ))}
      </div>

      {loading ? (
        <p className="text-sm text-neutral-500">{t('loading')}</p>
      ) : error ? (
        <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{error}</p>
      ) : bookings.length === 0 ? (
        <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
          {t('empty')}
        </p>
      ) : (
        <ul className="space-y-3">
          {bookings.map((b) => (
            <li key={b.public_id}>
              <Link
                href={`/${locale}/me/bookings/${b.public_id}`}
                className="block rounded-lg border border-neutral-200 bg-white p-4 hover:border-primary-300"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium">
                    {b.reference_no}{' '}
                    <span className="ms-2 text-xs font-normal text-neutral-500">
                      {b.event_starts_at ? new Date(b.event_starts_at).toLocaleString(locale) : ''}
                    </span>
                  </p>
                  <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700">
                    {t(`status.${b.lifecycle_status}`)}
                  </span>
                </div>
                <p className="mt-1 text-sm text-neutral-600">
                  {t('total')}: <strong className="tabular-nums">{formatPriceEGP(b.total_minor, locale)}</strong>
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
