'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import type { ServiceDetail } from '@/types/api';

export function SaleVariant({ service, locale }: { service: ServiceDetail; locale: string }) {
  const t = useTranslations('service');
  const sale = service.sale;
  const [qty, setQty] = useState(1);

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
          <span className="text-sm text-neutral-500">{t('per_unit')}</span>
        </div>

        {sale ? (
          <ul className="mt-4 space-y-2 text-sm">
            <li>
              <span className="text-neutral-500">{t('lead_time')}: </span>
              <span className="font-medium">{sale.lead_time_hours}h</span>
            </li>
            {sale.is_perishable ? <li className="text-warning">⚠ Perishable</li> : null}
            {sale.is_made_to_order ? <li className="text-info">Made to order</li> : null}
            {sale.stock_quantity !== null ? (
              <li>
                <span className="text-neutral-500">Stock: </span>
                <span className="font-medium">{sale.stock_quantity}</span>
              </li>
            ) : null}
          </ul>
        ) : null}

        <label className="block mt-6 text-sm">
          <span className="font-medium">{t('quantity')}</span>
          <input
            type="number"
            min={1}
            max={sale?.stock_quantity ?? 99}
            value={qty}
            onChange={(e) => setQty(Math.max(1, Number(e.target.value)))}
            className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
          />
        </label>

        <button className="mt-6 w-full rounded-md bg-primary-600 hover:bg-primary-700 text-white py-3 text-sm font-semibold">
          {t('add_to_cart')}
        </button>
      </div>
    </div>
  );
}
