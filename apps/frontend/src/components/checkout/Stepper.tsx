'use client';

import { useTranslations } from 'next-intl';
import { cn } from '@/lib/utils';

export type CheckoutStep = 'event' | 'items' | 'summary' | 'negotiation' | 'pay';

const ORDER: CheckoutStep[] = ['event', 'items', 'summary', 'negotiation', 'pay'];

export function Stepper({ current }: { current: CheckoutStep }) {
  const t = useTranslations('checkout');
  const currentIndex = ORDER.indexOf(current);

  return (
    <ol className="mb-8 flex w-full items-center gap-2 text-xs font-medium">
      {ORDER.map((step, idx) => {
        const reached = idx <= currentIndex;
        const isActive = idx === currentIndex;
        return (
          <li key={step} className="flex flex-1 items-center gap-2">
            <span
              className={cn(
                'flex h-7 w-7 items-center justify-center rounded-full border text-xs',
                reached ? 'border-primary-600 bg-primary-600 text-white' : 'border-neutral-300 text-neutral-500',
                isActive && 'ring-2 ring-primary-200',
              )}
            >
              {idx + 1}
            </span>
            <span className={cn('hidden sm:inline', reached ? 'text-neutral-900' : 'text-neutral-500')}>
              {t(`steps.${step}`)}
            </span>
            {idx < ORDER.length - 1 && (
              <span className={cn('h-px flex-1', idx < currentIndex ? 'bg-primary-600' : 'bg-neutral-200')} />
            )}
          </li>
        );
      })}
    </ol>
  );
}
