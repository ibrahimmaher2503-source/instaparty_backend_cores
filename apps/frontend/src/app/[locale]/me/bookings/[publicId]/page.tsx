'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { bookingApi } from '@/lib/api';
import { formatPriceEGP } from '@/lib/utils';
import type { Booking } from '@/types/api';

export default function BookingDetailPage() {
  const t = useTranslations('me.booking_detail');
  const params = useParams<{ locale: string; publicId: string }>();
  const locale = params?.locale ?? 'en';
  const publicId = params!.publicId;

  const [booking, setBooking] = useState<Booking | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    bookingApi
      .show(locale, publicId)
      .then(setBooking)
      .finally(() => setLoading(false));
  }, [locale, publicId]);

  if (loading) return <p className="text-sm text-neutral-500">{t('loading')}</p>;
  if (!booking) return <p className="text-sm text-danger">{t('not_found')}</p>;

  const items = booking.vendors?.flatMap((v) => v.items.map((it) => ({ ...it, vendor: v.vendor_name }))) ?? [];
  const isCompleted = booking.lifecycle_status === 'completed' || booking.fulfillment_status === 'delivered';

  return (
    <div>
      <h1 className="font-heading text-2xl font-bold">{booking.reference_no}</h1>
      <p className="mt-1 text-sm text-neutral-600">
        {booking.event_starts_at ? new Date(booking.event_starts_at).toLocaleString(locale) : ''}
      </p>

      <div className="mt-4 flex flex-wrap gap-2">
        <Link href={`/${locale}/me/bookings/${publicId}/chat`} className="btn-secondary">
          {t('open_chat')}
        </Link>
        {isCompleted && (
          <Link href={`/${locale}/me/bookings/${publicId}/review`} className="btn-primary">
            {t('leave_review')}
          </Link>
        )}
      </div>

      <section className="mt-6 grid grid-cols-3 gap-2 rounded-lg border border-neutral-200 bg-white p-4 text-sm">
        <div>
          <p className="text-xs text-neutral-500">{t('lifecycle')}</p>
          <p className="font-medium">{booking.lifecycle_status}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">{t('payment')}</p>
          <p className="font-medium">{booking.payment_status}</p>
        </div>
        <div>
          <p className="text-xs text-neutral-500">{t('fulfillment')}</p>
          <p className="font-medium">{booking.fulfillment_status}</p>
        </div>
      </section>

      <section className="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h2 className="mb-2 text-sm font-semibold">{t('items')}</h2>
        <ul className="divide-y divide-neutral-100 text-sm">
          {items.map((item) => (
            <li key={item.public_id} className="flex justify-between py-2">
              <span>
                <span className="font-medium">{item.service_name}</span>
                <span className="ms-2 text-xs text-neutral-500">
                  ×{item.quantity} · {item.vendor} · {item.item_status}
                </span>
              </span>
              <span className="tabular-nums">{formatPriceEGP(item.total_minor, locale)}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-sm">
        <dl className="space-y-1">
          <div className="flex justify-between"><dt>{t('subtotal')}</dt><dd className="tabular-nums">{formatPriceEGP(booking.subtotal_minor, locale)}</dd></div>
          {booking.delivery_total_minor > 0 && <div className="flex justify-between"><dt>{t('delivery')}</dt><dd className="tabular-nums">{formatPriceEGP(booking.delivery_total_minor, locale)}</dd></div>}
          {booking.discount_total_minor > 0 && <div className="flex justify-between text-success"><dt>{t('discount')}</dt><dd className="tabular-nums">−{formatPriceEGP(booking.discount_total_minor, locale)}</dd></div>}
          <div className="flex justify-between border-t border-neutral-200 pt-2 font-semibold"><dt>{t('total')}</dt><dd className="tabular-nums">{formatPriceEGP(booking.total_minor, locale)}</dd></div>
        </dl>
      </section>
    </div>
  );
}
