'use client';

import { useTranslations } from 'next-intl';
import type { ServiceDetail } from '@/types/api';

export function RentalVariant({ service, locale }: { service: ServiceDetail; locale: string }) {
  const t = useTranslations('service');
  const rental = service.rental;

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
          <span className="text-sm text-neutral-500">{t('per_day')}</span>
        </div>

        {rental ? (
          <ul className="mt-4 space-y-2 text-sm">
            <li>
              <span className="text-neutral-500">{t('setup_time')}: </span>
              <span className="font-medium">{rental.setup_time_minutes} min</span>
            </li>
            {rental.security_deposit_minor !== null ? (
              <li>
                <span className="text-neutral-500">{t('security_deposit')}: </span>
                <span className="font-medium">
                  {new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', {
                    style: 'currency',
                    currency: service.currency,
                    maximumFractionDigits: 0,
                  }).format(rental.security_deposit_minor / 100)}
                </span>
              </li>
            ) : null}
            {rental.requires_outdoor_space ? <li className="text-warning">⚠ {t('delivery_required')}</li> : null}
          </ul>
        ) : null}

        <label className="block mt-6 text-sm">
          <span className="font-medium">Date range</span>
          <input type="date" className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" />
        </label>

        <button className="mt-6 w-full rounded-md bg-primary-600 hover:bg-primary-700 text-white py-3 text-sm font-semibold">
          {t('book_now')}
        </button>
      </div>
    </div>
  );
}
