'use client';

import { useEffect, useRef, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { bookingApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { Stepper } from '@/components/checkout/Stepper';

export default function CheckoutSubmitPage() {
  const t = useTranslations('checkout.submit');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const bookingPublicId = useBookingDraft((s) => s.bookingPublicId);
  const setBooking = useBookingDraft((s) => s.setBooking);
  const [error, setError] = useState<string | null>(null);
  const ranRef = useRef(false);

  useEffect(() => {
    if (!bookingPublicId) {
      router.replace(`/${locale}/checkout/event`);
      return;
    }
    if (ranRef.current) return;
    ranRef.current = true;

    const key =
      typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    bookingApi
      .submit(locale, bookingPublicId, key)
      .then((booking) => {
        setBooking(booking);
        router.push(`/${locale}/checkout/negotiation/${booking.public_id}`);
      })
      .catch((err) => {
        const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
        setError(first ?? ax.response?.data?.message ?? t('error_generic'));
      });
  }, [bookingPublicId, locale, router, setBooking, t]);

  return (
    <section className="mx-auto max-w-2xl px-4 py-12 text-center">
      <Stepper current="summary" />
      {error ? (
        <>
          <h1 className="font-heading text-xl font-bold text-danger">{t('failed_title')}</h1>
          <p className="mt-2 text-sm text-danger">{error}</p>
          <button
            type="button"
            onClick={() => {
              ranRef.current = false;
              setError(null);
              router.refresh();
            }}
            className="btn-primary mt-4"
          >
            {t('retry')}
          </button>
        </>
      ) : (
        <>
          <div className="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-primary-200 border-t-primary-600" />
          <h1 className="mt-4 font-heading text-xl font-bold">{t('submitting_title')}</h1>
          <p className="mt-2 text-sm text-neutral-600">{t('submitting_body')}</p>
        </>
      )}
    </section>
  );
}
