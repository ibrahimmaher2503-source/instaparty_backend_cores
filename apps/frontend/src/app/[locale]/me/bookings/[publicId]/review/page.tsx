'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { Star } from 'lucide-react';
import { bookingApi, reviewApi } from '@/lib/api';
import type { Booking } from '@/types/api';

export default function ReviewPage() {
  const t = useTranslations('me.review');
  const params = useParams<{ locale: string; publicId: string }>();
  const router = useRouter();
  const locale = params?.locale ?? 'en';
  const publicId = params!.publicId;

  const [booking, setBooking] = useState<Booking | null>(null);
  const [itemRatings, setItemRatings] = useState<Record<string, number>>({});
  const [vendorRatings, setVendorRatings] = useState<Record<string, number>>({});
  const [comments, setComments] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    bookingApi.show(locale, publicId).then(setBooking).catch(() => null);
  }, [locale, publicId]);

  const submit = async () => {
    if (!booking) return;
    setSubmitting(true);
    setError(null);
    try {
      for (const vendor of booking.vendors ?? []) {
        if (vendorRatings[vendor.public_id]) {
          await reviewApi.submitVendor(locale, vendor.public_id, {
            rating: vendorRatings[vendor.public_id],
            comment: comments[`v:${vendor.public_id}`] ?? '',
          });
        }
        for (const item of vendor.items) {
          if (itemRatings[item.public_id]) {
            await reviewApi.submitService(locale, item.public_id, {
              rating: itemRatings[item.public_id],
              comment: comments[`i:${item.public_id}`] ?? '',
            });
          }
        }
      }
      router.push(`/${locale}/me/bookings/${publicId}`);
    } catch (err) {
      const ax = err as AxiosError<{ message?: string }>;
      setError(ax.response?.data?.message ?? t('error_generic'));
    } finally {
      setSubmitting(false);
    }
  };

  if (!booking) return <p className="text-sm text-neutral-500">{t('loading')}</p>;

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      {error && <p className="mb-3 rounded-md bg-danger/10 p-3 text-sm text-danger">{error}</p>}

      <div className="space-y-4">
        {(booking.vendors ?? []).map((vendor) => (
          <section key={vendor.public_id} className="rounded-lg border border-neutral-200 bg-white p-4">
            <h2 className="text-sm font-semibold">{vendor.vendor_name}</h2>
            <RatingRow
              label={t('vendor_rating')}
              value={vendorRatings[vendor.public_id] ?? 0}
              onChange={(v) => setVendorRatings((r) => ({ ...r, [vendor.public_id]: v }))}
            />
            <textarea
              rows={2}
              placeholder={t('vendor_comment_placeholder')}
              className="input mt-2"
              value={comments[`v:${vendor.public_id}`] ?? ''}
              onChange={(e) => setComments((c) => ({ ...c, [`v:${vendor.public_id}`]: e.target.value }))}
            />
            {vendor.items.map((item) => (
              <div key={item.public_id} className="mt-3 border-t border-neutral-100 pt-3">
                <p className="text-sm font-medium">{item.service_name}</p>
                <RatingRow
                  label={t('item_rating')}
                  value={itemRatings[item.public_id] ?? 0}
                  onChange={(v) => setItemRatings((r) => ({ ...r, [item.public_id]: v }))}
                />
                <textarea
                  rows={2}
                  placeholder={t('item_comment_placeholder')}
                  className="input mt-2"
                  value={comments[`i:${item.public_id}`] ?? ''}
                  onChange={(e) => setComments((c) => ({ ...c, [`i:${item.public_id}`]: e.target.value }))}
                />
              </div>
            ))}
          </section>
        ))}
      </div>

      <button type="button" onClick={submit} disabled={submitting} className="btn-primary mt-6">
        {submitting ? t('submitting') : t('submit')}
      </button>
    </div>
  );
}

function RatingRow({ label, value, onChange }: { label: string; value: number; onChange: (v: number) => void }) {
  return (
    <div className="mt-2 flex items-center gap-2">
      <span className="text-xs text-neutral-600">{label}</span>
      <div className="flex">
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            type="button"
            onClick={() => onChange(n)}
            aria-label={`${n}`}
            className="p-0.5"
          >
            <Star
              className={n <= value ? 'h-5 w-5 fill-warning text-warning' : 'h-5 w-5 text-neutral-300'}
            />
          </button>
        ))}
      </div>
    </div>
  );
}
