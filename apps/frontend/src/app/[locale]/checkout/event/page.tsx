'use client';

import { useEffect, useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { bookingApi, catalogApi, geographyApi } from '@/lib/api';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { Stepper } from '@/components/checkout/Stepper';
import type { City, Governorate, Occasion } from '@/types/api';

const schema = z
  .object({
    occasion_id: z.string().min(1),
    event_starts_at: z.string().min(1),
    event_ends_at: z.string().min(1),
    guest_count: z.coerce.number().int().min(1).optional(),
    governorate_id: z.string().min(1),
    city_id: z.string().min(1),
    address_line: z.string().min(3).max(255),
    building: z.string().max(80).optional(),
    floor: z.string().max(20).optional(),
    apartment: z.string().max(20).optional(),
    landmark: z.string().max(255).optional(),
    recipient_name: z.string().min(2).max(120),
    recipient_phone_e164: z.string().regex(/^\+\d{8,15}$/),
  })
  .refine((d) => new Date(d.event_ends_at) > new Date(d.event_starts_at), {
    path: ['event_ends_at'],
    message: 'End must be after start',
  });

type Values = z.infer<typeof schema>;

export default function CheckoutEventPage() {
  const t = useTranslations('checkout.event');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const setBooking = useBookingDraft((s) => s.setBooking);
  const setEventBasics = useBookingDraft((s) => s.setEventBasics);
  const bookingPublicId = useBookingDraft((s) => s.bookingPublicId);
  const [occasions, setOccasions] = useState<Occasion[]>([]);
  const [governorates, setGovernorates] = useState<Governorate[]>([]);
  const [cities, setCities] = useState<City[]>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    watch,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema) });

  const govId = watch('governorate_id');

  useEffect(() => {
    catalogApi.occasions(locale).then(setOccasions).catch(() => setOccasions([]));
    geographyApi.governorates(locale).then(setGovernorates).catch(() => setGovernorates([]));
  }, [locale]);

  useEffect(() => {
    if (!govId) {
      setCities([]);
      return;
    }
    geographyApi.cities(locale, govId).then(setCities).catch(() => setCities([]));
  }, [locale, govId]);

  const onSubmit = handleSubmit(async (v) => {
    setSubmitError(null);
    const payload = {
      occasion_id: v.occasion_id,
      event_starts_at: new Date(v.event_starts_at).toISOString(),
      event_ends_at: new Date(v.event_ends_at).toISOString(),
      guest_count: v.guest_count,
      address: {
        city_id: v.city_id,
        address_line: v.address_line,
        building: v.building || undefined,
        floor: v.floor || undefined,
        apartment: v.apartment || undefined,
        landmark: v.landmark || undefined,
        recipient_name: v.recipient_name,
        recipient_phone_e164: v.recipient_phone_e164,
      },
    };

    try {
      setEventBasics(payload);
      if (bookingPublicId) {
        router.push(`/${locale}/checkout/items`);
        return;
      }
      const booking = await bookingApi.create(locale, payload);
      setBooking(booking);
      if (!useBookingDraft.getState().holdExpiresAt) {
        useBookingDraft.getState().setHoldExpiresAt(new Date(Date.now() + 15 * 60 * 1000).toISOString());
      }
      router.push(`/${locale}/checkout/items`);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? ax.response?.data?.message ?? t('error_generic'));
    }
  });

  return (
    <section className="mx-auto max-w-3xl px-4 py-8">
      <Stepper current="event" />
      <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>
      <p className="mt-1 text-sm text-neutral-600">{t('subtitle')}</p>

      <form onSubmit={onSubmit} className="mt-6 space-y-6">
        <section className="space-y-3 rounded-lg border border-neutral-200 bg-white p-4">
          <h2 className="text-sm font-semibold">{t('section_event')}</h2>
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{t('occasion')}</span>
            <select {...register('occasion_id')} className="input">
              <option value="">{t('select_occasion')}</option>
              {occasions.map((o) => (
                <option key={o.public_id} value={o.public_id}>
                  {o.name}
                </option>
              ))}
            </select>
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('starts_at')}</span>
              <input type="datetime-local" {...register('event_starts_at')} className="input" />
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('ends_at')}</span>
              <input type="datetime-local" {...register('event_ends_at')} className="input" />
              {errors.event_ends_at && <span className="text-xs text-danger">{errors.event_ends_at.message}</span>}
            </label>
          </div>
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{t('guest_count')}</span>
            <input type="number" min={1} {...register('guest_count')} className="input" />
          </label>
        </section>

        <section className="space-y-3 rounded-lg border border-neutral-200 bg-white p-4">
          <h2 className="text-sm font-semibold">{t('section_address')}</h2>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('governorate')}</span>
              <select {...register('governorate_id')} className="input">
                <option value="">{t('select_governorate')}</option>
                {governorates.map((g) => (
                  <option key={g.public_id} value={g.public_id}>
                    {g.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('city')}</span>
              <select {...register('city_id')} className="input" disabled={!govId}>
                <option value="">{t('select_city')}</option>
                {cities.map((c) => (
                  <option key={c.public_id} value={c.public_id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{t('address_line')}</span>
            <input {...register('address_line')} className="input" />
          </label>
          <div className="grid grid-cols-3 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('building')}</span>
              <input {...register('building')} className="input" />
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('floor')}</span>
              <input {...register('floor')} className="input" />
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('apartment')}</span>
              <input {...register('apartment')} className="input" />
            </label>
          </div>
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{t('landmark')}</span>
            <input {...register('landmark')} className="input" />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('recipient_name')}</span>
              <input {...register('recipient_name')} className="input" />
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{t('recipient_phone')}</span>
              <input {...register('recipient_phone_e164')} className="input" placeholder="+201234567890" />
              {errors.recipient_phone_e164 && (
                <span className="text-xs text-danger">{errors.recipient_phone_e164.message}</span>
              )}
            </label>
          </div>
        </section>

        {submitError && <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>}

        <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
          {isSubmitting ? t('submitting') : t('submit')}
        </button>
      </form>
    </section>
  );
}
