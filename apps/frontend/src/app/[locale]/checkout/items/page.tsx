'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { bookingApi, clientApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { Stepper } from '@/components/checkout/Stepper';
import { HoldCountdown } from '@/components/checkout/HoldCountdown';
import { AddItemPanel } from '@/components/checkout/AddItemPanel';
import { formatPriceEGP } from '@/lib/utils';
import type { AddBookingItemPayload, ServiceDetail, ServiceSummary } from '@/types/api';

export default function CheckoutItemsPage() {
  const t = useTranslations('checkout.items');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const router = useRouter();
  const bookingPublicId = useBookingDraft((s) => s.bookingPublicId);
  const setBooking = useBookingDraft((s) => s.setBooking);
  const [services, setServices] = useState<ServiceSummary[]>([]);
  const [picked, setPicked] = useState<ServiceDetail | null>(null);
  const [loadingPicked, setLoadingPicked] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [query, setQuery] = useState('');

  useEffect(() => {
    if (!bookingPublicId) {
      router.replace(`/${locale}/checkout/event`);
    }
  }, [bookingPublicId, locale, router]);

  useEffect(() => {
    const params = new URLSearchParams();
    if (query) params.set('q', query);
    params.set('per_page', '12');
    clientApi<{ items: ServiceSummary[] }>(`/customer/services?${params.toString()}`, locale)
      .then((res) => setServices(res.items ?? []))
      .catch(() => setServices([]));
  }, [locale, query]);

  const pick = async (publicId: string) => {
    setLoadingPicked(true);
    try {
      const detail = await clientApi<ServiceDetail>(`/catalog/services/${publicId}`, locale);
      setPicked(detail);
    } finally {
      setLoadingPicked(false);
    }
  };

  const addItem = async (payload: AddBookingItemPayload) => {
    if (!bookingPublicId) return;
    setSubmitError(null);
    try {
      await bookingApi.addItem(locale, bookingPublicId, payload);
      const refreshed = await bookingApi.show(locale, bookingPublicId);
      setBooking(refreshed);
      setPicked(null);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? ax.response?.data?.message ?? t('error_generic'));
    }
  };

  return (
    <section className="mx-auto max-w-5xl px-4 py-8">
      <Stepper current="items" />
      <HoldCountdown />
      <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>
      <p className="mt-1 text-sm text-neutral-600">{t('subtitle')}</p>

      {submitError && (
        <p className="mt-3 rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>
      )}

      <div className="mt-6 grid gap-6 md:grid-cols-[1fr_360px]">
        <div>
          <input
            type="search"
            placeholder={t('search_placeholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="input mb-4"
          />
          <div className="grid gap-3 sm:grid-cols-2">
            {services.map((s) => (
              <button
                key={s.public_id}
                type="button"
                onClick={() => pick(s.public_id)}
                className={`flex gap-3 rounded-lg border p-3 text-start hover:border-primary-400 ${
                  picked?.public_id === s.public_id ? 'border-primary-500 bg-primary-50' : 'border-neutral-200 bg-white'
                }`}
              >
                {s.cover_image_url && (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={s.cover_image_url} alt="" className="h-16 w-16 rounded object-cover" />
                )}
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{s.name}</p>
                  <p className="truncate text-xs text-neutral-500">{s.product_type}</p>
                  <p className="text-xs font-medium tabular-nums">
                    {formatPriceEGP(s.price_from_minor, locale)}
                  </p>
                </div>
              </button>
            ))}
            {services.length === 0 && (
              <p className="col-span-full rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-600">
                {t('no_results')}
              </p>
            )}
          </div>
        </div>

        <aside className="rounded-lg border border-neutral-200 bg-white p-4">
          {loadingPicked ? (
            <p className="text-sm text-neutral-500">{t('loading_service')}</p>
          ) : picked ? (
            <>
              <p className="mb-2 font-medium">{picked.name}</p>
              <AddItemPanel service={picked} onSubmit={addItem} />
            </>
          ) : (
            <p className="text-sm text-neutral-500">{t('pick_service')}</p>
          )}
        </aside>
      </div>

      <div className="mt-6 flex justify-end gap-2">
        <Link href={`/${locale}/cart`} className="btn-secondary">
          {t('back_to_cart')}
        </Link>
        <Link href={`/${locale}/checkout/summary`} className="btn-primary">
          {t('continue')}
        </Link>
      </div>
    </section>
  );
}
