'use client';

import { useTranslations } from 'next-intl';
import type { ServiceDetail } from '@/types/api';

export function DigitalVariant({ service, locale }: { service: ServiceDetail; locale: string }) {
  const t = useTranslations('service');
  const digital = service.digital;

  return (
    <div className="space-y-4">
      <div className="rounded-lg border border-neutral-200 p-4 bg-white">
        <div className="flex flex-wrap items-baseline gap-2">
          <div className="text-2xl font-bold text-primary-700">
            {new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', {
              style: 'currency',
              currency: service.currency,
              maximumFractionDigits: 0,
            }).format(service.price_from_minor / 100)}
          </div>
        </div>

        {digital ? (
          <ul className="mt-4 space-y-2 text-sm">
            <li>
              <span className="text-neutral-500">{t('delivery_method')}: </span>
              <span className="font-medium">{digital.delivery_method}</span>
            </li>
            {digital.has_expiry && digital.expiry_days_after_purchase !== null ? (
              <li>
                <span className="text-neutral-500">{t('expiry_note')}: </span>
                <span className="font-medium">{digital.expiry_days_after_purchase} days</span>
              </li>
            ) : null}
            {digital.is_refundable_after_delivery ? <li className="text-info">Refundable after delivery</li> : null}
          </ul>
        ) : null}

        <div className="mt-6 space-y-3">
          <label className="block text-sm">
            <span className="font-medium">Recipient email</span>
            <input type="email" className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" />
          </label>
          <label className="block text-sm">
            <span className="font-medium">Recipient phone</span>
            <input type="tel" className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" />
          </label>
        </div>

        <button className="mt-6 w-full rounded-md bg-primary-600 hover:bg-primary-700 text-white py-3 text-sm font-semibold">
          {t('book_now')}
        </button>
      </div>
    </div>
  );
}
