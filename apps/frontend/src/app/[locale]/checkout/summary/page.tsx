'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { bookingApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { Stepper } from '@/components/checkout/Stepper';
import { HoldCountdown } from '@/components/checkout/HoldCountdown';
import { formatPriceEGP } from '@/lib/utils';
import type { Booking } from '@/types/api';

export default function CheckoutSummaryPage() {
  const t = useTranslations('checkout.summary');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const bookingPublicId = useBookingDraft((s) => s.bookingPublicId);
  const [booking, setBooking] = useState<Booking | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!bookingPublicId) {
      router.replace(`/${locale}/checkout/event`);
      return;
    }
    bookingApi
      .show(locale, bookingPublicId)
      .then(setBooking)
      .finally(() => setLoading(false));
  }, [bookingPublicId, locale, router]);

  if (loading || !booking) {
    return <p className="mx-auto max-w-3xl px-4 py-8 text-neutral-500">{t('loading')}</p>;
  }

  const items = booking.vendors?.flatMap((v) => v.items.map((it) => ({ ...it, vendor: v.vendor_name }))) ?? [];

  return (
    <section className="mx-auto max-w-3xl px-4 py-8">
      <Stepper current="summary" />
      <HoldCountdown />
      <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>

      <section className="mt-6 rounded-lg border border-neutral-200 bg-white p-4">
        <h2 className="text-sm font-semibold">{t('event_details')}</h2>
        <dl className="mt-2 grid grid-cols-2 gap-2 text-sm">
          <div>
            <dt className="text-xs text-neutral-500">{t('starts_at')}</dt>
            <dd>{booking.event_starts_at ? new Date(booking.event_starts_at).toLocaleString(locale) : '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500">{t('ends_at')}</dt>
            <dd>{booking.event_ends_at ? new Date(booking.event_ends_at).toLocaleString(locale) : '—'}</dd>
          </div>
          {booking.address && (
            <div className="col-span-2">
              <dt className="text-xs text-neutral-500">{t('address')}</dt>
              <dd>
                {booking.address.address_line}, {booking.address.recipient_name} ({booking.address.recipient_phone_e164})
              </dd>
            </div>
          )}
        </dl>
      </section>

      <section className="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h2 className="text-sm font-semibold">{t('items')}</h2>
        <ul className="mt-2 divide-y divide-neutral-100 text-sm">
          {items.map((item) => (
            <li key={item.public_id} className="flex justify-between py-2">
              <span>
                <span className="font-medium">{item.service_name}</span>
                <span className="ms-2 text-xs text-neutral-500">×{item.quantity} · {item.vendor}</span>
              </span>
              <span className="tabular-nums">{formatPriceEGP(item.total_minor, locale)}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
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
      </section>

      <div className="mt-6 flex justify-end gap-2">
        <Link href={`/${locale}/checkout/items`} className="btn-secondary">
          {t('back')}
        </Link>
        <Link href={`/${locale}/checkout/submit`} className="btn-primary">
          {t('submit')}
        </Link>
      </div>
    </section>
  );
}
