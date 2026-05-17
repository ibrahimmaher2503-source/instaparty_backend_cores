'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { bookingApi, loyaltyApi, type LoyaltyBalanceRow } from '@/lib/api';

export default function LoyaltyPage() {
  const t = useTranslations('me.loyalty');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const [balances, setBalances] = useState<LoyaltyBalanceRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const bookings = await bookingApi.list(locale);
        const seen = new Set<string>();
        const vendorIds: string[] = [];
        for (const b of bookings) {
          for (const v of b.vendors ?? []) {
            if (!seen.has(v.vendor_public_id)) {
              seen.add(v.vendor_public_id);
              vendorIds.push(v.vendor_public_id);
            }
          }
        }
        const results = await Promise.allSettled(vendorIds.map((id) => loyaltyApi.balance(locale, id)));
        setBalances(
          results
            .filter((r): r is PromiseFulfilledResult<LoyaltyBalanceRow> => r.status === 'fulfilled')
            .map((r) => r.value),
        );
      } finally {
        setLoading(false);
      }
    })();
  }, [locale]);

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      {loading ? (
        <p className="text-sm text-neutral-500">{t('loading')}</p>
      ) : balances.length === 0 ? (
        <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
          {t('empty')}
        </p>
      ) : (
        <ul className="space-y-2">
          {balances.map((b) => (
            <li
              key={b.vendor_public_id}
              className="flex items-center justify-between rounded-lg border border-neutral-200 bg-white p-4"
            >
              <div>
                <p className="font-medium">{b.vendor_name}</p>
                <p className="text-xs text-neutral-500">
                  {t('lifetime')}: {b.points_earned_lifetime}
                </p>
              </div>
              <p className="text-xl font-semibold tabular-nums text-primary-700">{b.points_balance}</p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
