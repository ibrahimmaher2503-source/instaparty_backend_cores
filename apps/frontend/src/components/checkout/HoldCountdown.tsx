'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useRouter, useParams } from 'next/navigation';
import { Clock } from 'lucide-react';
import { useBookingDraft } from '@/lib/store/booking-draft';
import { cn } from '@/lib/utils';

export function HoldCountdown() {
  const t = useTranslations('checkout');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const holdExpiresAt = useBookingDraft((s) => s.holdExpiresAt);
  const clear = useBookingDraft((s) => s.clear);
  const [remaining, setRemaining] = useState<number | null>(null);

  useEffect(() => {
    if (!holdExpiresAt) {
      setRemaining(null);
      return;
    }
    const target = new Date(holdExpiresAt).getTime();
    const tick = () => {
      const diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
      setRemaining(diff);
      if (diff === 0) {
        clear();
        router.push(`/${locale}/cart?expired=1`);
      }
    };
    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, [holdExpiresAt, clear, locale, router]);

  if (remaining === null) return null;

  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;
  const warning = remaining <= 120;

  return (
    <div
      className={cn(
        'mb-4 flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
        warning
          ? 'border-warning/40 bg-warning/10 text-warning'
          : 'border-neutral-200 bg-neutral-50 text-neutral-700',
      )}
      role="status"
      aria-live="polite"
    >
      <Clock className="h-4 w-4" />
      <span>
        {t('hold_countdown.label')}{' '}
        <strong className="font-mono">
          {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
        </strong>
      </span>
    </div>
  );
}
