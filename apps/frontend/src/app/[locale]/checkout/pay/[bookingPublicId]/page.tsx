'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { bookingApi, paymentApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { Stepper } from '@/components/checkout/Stepper';
import { formatPriceEGP } from '@/lib/utils';
import type { Booking } from '@/types/api';

export default function PayPage() {
  const t = useTranslations('checkout.pay');
  const router = useRouter();
  const params = useParams<{ locale: string; bookingPublicId: string }>();
  const locale = params?.locale ?? 'en';
  const bookingPublicId = params!.bookingPublicId;
  const ensureKey = useBookingDraft((s) => s.ensurePaymentIdempotencyKey);

  const [booking, setBooking] = useState<Booking | null>(null);
  const [redirectUrl, setRedirectUrl] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [initiating, setInitiating] = useState(false);

  useEffect(() => {
    bookingApi.show(locale, bookingPublicId).then(setBooking).catch(() => null);
  }, [locale, bookingPublicId]);

  const initiate = async () => {
    setInitiating(true);
    setError(null);
    try {
      const key = ensureKey();
      const result = await paymentApi.initiate(locale, bookingPublicId, 'paymob_card', key);
      setRedirectUrl(result.redirect_url);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setError(first ?? ax.response?.data?.message ?? t('error_generic'));
    } finally {
      setInitiating(false);
    }
  };

  useEffect(() => {
    if (!redirectUrl) return;
    const handler = (event: MessageEvent) => {
      const data = event.data;
      if (typeof data === 'object' && data !== null && 'paymob_status' in data && data.paymob_status === 'success') {
        router.push(`/${locale}/checkout/confirmed/${bookingPublicId}`);
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, [redirectUrl, locale, bookingPublicId, router]);

  return (
    <section className="mx-auto max-w-3xl px-4 py-8">
      <Stepper current="pay" />
      <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>

      {booking && (
        <p className="mt-2 text-sm text-neutral-600">
          {t('amount_due')}:{' '}
          <strong className="tabular-nums">{formatPriceEGP(booking.total_minor, locale)}</strong>
        </p>
      )}

      {error && <p className="mt-3 rounded-md bg-danger/10 p-3 text-sm text-danger">{error}</p>}

      {redirectUrl ? (
        <div className="mt-6 overflow-hidden rounded-lg border border-neutral-200 bg-white">
          <iframe src={redirectUrl} title="Paymob" className="h-[680px] w-full" />
        </div>
      ) : (
        <button type="button" onClick={initiate} disabled={initiating} className="btn-primary mt-6 w-full">
          {initiating ? t('initiating') : t('proceed')}
        </button>
      )}

      <p className="mt-4 text-xs text-neutral-500">{t('iframe_note')}</p>
    </section>
  );
}
