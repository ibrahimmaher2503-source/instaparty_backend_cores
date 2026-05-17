'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Trash2 } from 'lucide-react';
import { bookingApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { useAuth } from '@/components/auth/AuthProvider';
import { formatPriceEGP } from '@/lib/utils';
import { HoldCountdown } from '@/components/checkout/HoldCountdown';
import type { Booking } from '@/types/api';

export default function CartPage() {
  const t = useTranslations('cart');
  const params = useParams<{ locale: string }>();
  const search = useSearchParams();
  const locale = params?.locale ?? 'en';
  const router = useRouter();
  const { status } = useAuth();
  const bookingPublicId = useBookingDraft((s) => s.bookingPublicId);
  const setBooking = useBookingDraft((s) => s.setBooking);
  const clear = useBookingDraft((s) => s.clear);
  const [booking, setLocal] = useState<Booking | null>(null);
  const [loading, setLoading] = useState(true);
  const [removing, setRemoving] = useState<string | null>(null);
  const expired = search.get('expired') === '1';

  useEffect(() => {
    if (!bookingPublicId) {
      setLoading(false);
      return;
    }
    bookingApi
      .show(locale, bookingPublicId)
      .then((b) => {
        setLocal(b);
        setBooking(b);
      })
      .catch(() => {
        clear();
        setLocal(null);
      })
      .finally(() => setLoading(false));
  }, [bookingPublicId, locale, setBooking, clear]);

  const removeItem = async (itemId: string) => {
    if (!booking) return;
    setRemoving(itemId);
    try {
      await bookingApi.removeItem(locale, booking.public_id, itemId);
      const refreshed = await bookingApi.show(locale, booking.public_id);
      setLocal(refreshed);
      setBooking(refreshed);
    } finally {
      setRemoving(null);
    }
  };

  const items = booking?.vendors?.flatMap((v) => v.items.map((it) => ({ ...it, vendor_name: v.vendor_name }))) ?? [];

  return (
    <section className="mx-auto max-w-4xl px-4 py-8">
      <h1 className="font-heading text-3xl font-bold">{t('title')}</h1>

      {expired && (
        <p className="mt-4 rounded-md border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning">
          {t('expired_notice')}
        </p>
      )}

      {loading ? (
        <p className="mt-8 text-neutral-500">{t('loading')}</p>
      ) : !booking ? (
        <div className="mt-8 rounded-lg border border-neutral-200 bg-white p-8 text-center">
          <p className="text-neutral-600">{t('empty')}</p>
          <Link
            href={status === 'authenticated' ? `/${locale}/checkout/event` : `/${locale}/auth/login?next=/${locale}/checkout/event`}
            className="btn-primary mt-4 inline-flex"
          >
            {t('start_planning')}
          </Link>
        </div>
      ) : (
        <>
          <HoldCountdown />
          <div className="mt-4 space-y-3">
            {items.length === 0 ? (
              <div className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-neutral-600">
                {t('no_items')}
                <div className="mt-4">
                  <Link href={`/${locale}/checkout/items`} className="btn-primary inline-flex">
                    {t('add_items')}
                  </Link>
                </div>
              </div>
            ) : (
              items.map((item) => (
                <div
                  key={item.public_id}
                  className="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-white p-4"
                >
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-neutral-900">{item.service_name}</p>
                    <p className="text-xs text-neutral-500">
                      {item.vendor_name} · {item.product_type} · ×{item.quantity}
                    </p>
                  </div>
                  <p className="font-medium tabular-nums">
                    {formatPriceEGP(item.total_minor, locale)}
                  </p>
                  <button
                    type="button"
                    onClick={() => removeItem(item.public_id)}
                    disabled={removing === item.public_id}
                    className="p-2 text-neutral-500 hover:text-danger disabled:opacity-50"
                    aria-label={t('remove')}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))
            )}
          </div>

          {items.length > 0 && (
            <div className="mt-6 rounded-lg border border-neutral-200 bg-white p-4">
              <dl className="space-y-1 text-sm">
                <div className="flex justify-between">
                  <dt>{t('subtotal')}</dt>
                  <dd className="tabular-nums">{formatPriceEGP(booking.subtotal_minor, locale)}</dd>
                </div>
                {booking.delivery_total_minor > 0 && (
                  <div className="flex justify-between">
                    <dt>{t('delivery')}</dt>
                    <dd className="tabular-nums">{formatPriceEGP(booking.delivery_total_minor, locale)}</dd>
                  </div>
                )}
                {booking.discount_total_minor > 0 && (
                  <div className="flex justify-between text-success">
                    <dt>{t('discount')}</dt>
                    <dd className="tabular-nums">−{formatPriceEGP(booking.discount_total_minor, locale)}</dd>
                  </div>
                )}
                <div className="flex justify-between border-t border-neutral-200 pt-2 text-base font-semibold">
                  <dt>{t('total')}</dt>
                  <dd className="tabular-nums">{formatPriceEGP(booking.total_minor, locale)}</dd>
                </div>
              </dl>
              <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <Link href={`/${locale}/checkout/items`} className="btn-secondary">
                  {t('add_more')}
                </Link>
                <Link href={`/${locale}/checkout/summary`} className="btn-primary">
                  {t('continue')}
                </Link>
              </div>
            </div>
          )}
        </>
      )}
    </section>
  );
}
