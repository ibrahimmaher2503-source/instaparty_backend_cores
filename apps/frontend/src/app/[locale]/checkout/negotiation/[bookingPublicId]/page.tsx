'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { bookingApi } from '@/lib/api';
import { Stepper } from '@/components/checkout/Stepper';
import type { Booking, BookingModification } from '@/types/api';

export default function NegotiationPage() {
  const t = useTranslations('checkout.negotiation');
  const router = useRouter();
  const params = useParams<{ locale: string; bookingPublicId: string }>();
  const locale = params?.locale ?? 'en';
  const bookingPublicId = params!.bookingPublicId;

  const [booking, setBooking] = useState<Booking | null>(null);
  const [mods, setMods] = useState<BookingModification[]>([]);
  const [deciding, setDeciding] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const refresh = async () => {
    try {
      const [b, m] = await Promise.all([
        bookingApi.show(locale, bookingPublicId),
        bookingApi.listModifications(locale, bookingPublicId),
      ]);
      setBooking(b);
      setMods(m);
    } catch (err) {
      const ax = err as AxiosError<{ message?: string }>;
      setError(ax.response?.data?.message ?? t('error_generic'));
    }
  };

  useEffect(() => {
    void refresh();
    const id = window.setInterval(refresh, 30000);
    return () => window.clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bookingPublicId, locale]);

  useEffect(() => {
    if (booking?.lifecycle_status === 'awaiting_payment' || booking?.payment_status === 'authorized') {
      router.push(`/${locale}/checkout/pay/${bookingPublicId}`);
    }
  }, [booking, locale, bookingPublicId, router]);

  const decide = async (modPublicId: string, decision: 'accept' | 'reject') => {
    setDeciding(modPublicId);
    const key =
      typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    try {
      await bookingApi.decideModification(locale, bookingPublicId, modPublicId, decision, key);
      await refresh();
    } finally {
      setDeciding(null);
    }
  };

  return (
    <section className="mx-auto max-w-3xl px-4 py-8">
      <Stepper current="negotiation" />
      <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>
      <p className="mt-1 text-sm text-neutral-600">{t('subtitle')}</p>

      {error && <p className="mt-3 rounded-md bg-danger/10 p-3 text-sm text-danger">{error}</p>}

      <div className="mt-6 space-y-3">
        {mods.length === 0 ? (
          <p className="rounded-md border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
            {t('waiting')}
          </p>
        ) : (
          mods.map((mod) => (
            <article key={mod.public_id} className="rounded-lg border border-neutral-200 bg-white p-4">
              <header className="flex items-center justify-between">
                <span className="text-xs font-medium uppercase tracking-wide text-neutral-500">
                  {mod.status}
                </span>
                <time className="text-xs text-neutral-500">
                  {new Date(mod.created_at).toLocaleString(locale)}
                </time>
              </header>
              {mod.vendor_explanation && (
                <p className="mt-2 text-sm">{mod.vendor_explanation}</p>
              )}
              {mod.reason && <p className="mt-1 text-xs text-neutral-500">{mod.reason}</p>}
              {Object.keys(mod.proposed_diff ?? {}).length > 0 && (
                <pre className="mt-2 overflow-x-auto rounded bg-neutral-50 p-2 text-xs">
                  {JSON.stringify(mod.proposed_diff, null, 2)}
                </pre>
              )}
              {mod.status === 'pending_customer' && (
                <div className="mt-3 flex gap-2">
                  <button
                    type="button"
                    onClick={() => decide(mod.public_id, 'accept')}
                    disabled={deciding === mod.public_id}
                    className="btn-primary"
                  >
                    {t('accept')}
                  </button>
                  <button
                    type="button"
                    onClick={() => decide(mod.public_id, 'reject')}
                    disabled={deciding === mod.public_id}
                    className="btn-secondary"
                  >
                    {t('reject')}
                  </button>
                </div>
              )}
            </article>
          ))
        )}
      </div>

      {booking?.lifecycle_status === 'awaiting_payment' && (
        <div className="mt-6 flex justify-end">
          <a href={`/${locale}/checkout/pay/${bookingPublicId}`} className="btn-primary">
            {t('proceed_to_pay')}
          </a>
        </div>
      )}
    </section>
  );
}
