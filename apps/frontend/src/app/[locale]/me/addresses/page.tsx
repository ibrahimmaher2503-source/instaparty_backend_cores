'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Trash2 } from 'lucide-react';
import { addressApi, geographyApi, type CustomerAddressRow } from '@/lib/api';
import type { City, Governorate } from '@/types/api';

const schema = z.object({
  label: z.string().min(1).max(40),
  governorate_id: z.string().min(1),
  city_id: z.string().min(1),
  address_line: z.string().min(3).max(255),
  building: z.string().max(80).optional(),
  floor: z.string().max(20).optional(),
  apartment: z.string().max(20).optional(),
  landmark: z.string().max(255).optional(),
  recipient_name: z.string().min(2).max(120),
  recipient_phone_e164: z.string().regex(/^\+\d{8,15}$/),
});

type Values = z.infer<typeof schema>;

export default function AddressesPage() {
  const t = useTranslations('me.addresses');
  const tc = useTranslations('checkout.event');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';

  const [addresses, setAddresses] = useState<CustomerAddressRow[]>([]);
  const [governorates, setGovernorates] = useState<Governorate[]>([]);
  const [cities, setCities] = useState<City[]>([]);
  const [showForm, setShowForm] = useState(false);

  const {
    register,
    watch,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema) });
  const govId = watch('governorate_id');

  useEffect(() => {
    addressApi.list(locale).then(setAddresses);
    geographyApi.governorates(locale).then(setGovernorates);
  }, [locale]);

  useEffect(() => {
    if (!govId) {
      setCities([]);
      return;
    }
    geographyApi.cities(locale, govId).then(setCities);
  }, [locale, govId]);

  const onSubmit = handleSubmit(async (v) => {
    await addressApi.create(locale, {
      label: v.label,
      city_id: v.city_id,
      address_line: v.address_line,
      building: v.building || undefined,
      floor: v.floor || undefined,
      apartment: v.apartment || undefined,
      landmark: v.landmark || undefined,
      recipient_name: v.recipient_name,
      recipient_phone_e164: v.recipient_phone_e164,
    });
    const refreshed = await addressApi.list(locale);
    setAddresses(refreshed);
    reset();
    setShowForm(false);
  });

  const remove = async (publicId: string) => {
    await addressApi.remove(locale, publicId);
    setAddresses((prev) => prev.filter((a) => a.public_id !== publicId));
  };

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="font-heading text-2xl font-bold">{t('title')}</h1>
        <button type="button" onClick={() => setShowForm((v) => !v)} className="btn-secondary">
          {showForm ? t('cancel') : t('add')}
        </button>
      </div>

      {showForm && (
        <form onSubmit={onSubmit} className="mb-6 space-y-3 rounded-lg border border-neutral-200 bg-white p-4">
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{t('label')}</span>
            <input {...register('label')} className="input" placeholder={t('label_placeholder')} />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{tc('governorate')}</span>
              <select {...register('governorate_id')} className="input">
                <option value="">{tc('select_governorate')}</option>
                {governorates.map((g) => (
                  <option key={g.public_id} value={g.public_id}>
                    {g.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{tc('city')}</span>
              <select {...register('city_id')} className="input" disabled={!govId}>
                <option value="">{tc('select_city')}</option>
                {cities.map((c) => (
                  <option key={c.public_id} value={c.public_id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <label className="block">
            <span className="mb-1 block text-xs font-medium">{tc('address_line')}</span>
            <input {...register('address_line')} className="input" />
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{tc('recipient_name')}</span>
              <input {...register('recipient_name')} className="input" />
            </label>
            <label className="block">
              <span className="mb-1 block text-xs font-medium">{tc('recipient_phone')}</span>
              <input {...register('recipient_phone_e164')} className="input" placeholder="+201234567890" />
              {errors.recipient_phone_e164 && (
                <span className="text-xs text-danger">{errors.recipient_phone_e164.message}</span>
              )}
            </label>
          </div>
          <button type="submit" disabled={isSubmitting} className="btn-primary">
            {isSubmitting ? t('saving') : t('save')}
          </button>
        </form>
      )}

      {addresses.length === 0 ? (
        <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
          {t('empty')}
        </p>
      ) : (
        <ul className="space-y-2">
          {addresses.map((a) => (
            <li
              key={a.public_id}
              className="flex items-start justify-between gap-3 rounded-lg border border-neutral-200 bg-white p-4"
            >
              <div className="min-w-0 flex-1">
                <p className="font-medium">
                  {a.label}
                  {a.is_default && (
                    <span className="ms-2 rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-700">
                      {t('default')}
                    </span>
                  )}
                </p>
                <p className="text-sm text-neutral-600">{a.address_line}</p>
                <p className="text-xs text-neutral-500">
                  {a.recipient_name} · {a.recipient_phone_e164}
                </p>
              </div>
              <button
                type="button"
                onClick={() => remove(a.public_id)}
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
