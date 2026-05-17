'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import type { ServiceDetail, AddBookingItemPayload } from '@/types/api';

const schema = z.object({
  quantity: z.number().min(1).max(99),
  effective_starts_at: z.string().min(1),
  effective_ends_at: z.string().min(1),
});

type Values = z.infer<typeof schema>;

export function RentalItemForm({
  service,
  onSubmit,
}: {
  service: ServiceDetail;
  onSubmit: (payload: AddBookingItemPayload) => Promise<void>;
}) {
  const t = useTranslations('checkout.items');
  const ts = useTranslations('service');
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { quantity: 1, effective_starts_at: '', effective_ends_at: '' },
  });

  const submit = handleSubmit(async (v) => {
    await onSubmit({
      service_id: service.public_id,
      quantity: v.quantity,
      effective_starts_at: new Date(v.effective_starts_at).toISOString(),
      effective_ends_at: new Date(v.effective_ends_at).toISOString(),
    });
  });

  return (
    <form onSubmit={submit} className="space-y-3">
      {service.rental?.security_deposit_minor != null && service.rental.security_deposit_minor > 0 && (
        <p className="rounded-md bg-info/10 px-3 py-2 text-xs text-info">
          {ts('security_deposit')}: {(service.rental.security_deposit_minor / 100).toFixed(2)} {service.currency}
        </p>
      )}
      <div className="grid grid-cols-2 gap-3">
        <label className="block">
          <span className="mb-1 block text-xs font-medium">{t('starts_at')}</span>
          <input type="datetime-local" {...register('effective_starts_at')} className="input" />
          {errors.effective_starts_at && <span className="text-xs text-danger">{errors.effective_starts_at.message}</span>}
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium">{t('ends_at')}</span>
          <input type="datetime-local" {...register('effective_ends_at')} className="input" />
          {errors.effective_ends_at && <span className="text-xs text-danger">{errors.effective_ends_at.message}</span>}
        </label>
      </div>
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{ts('quantity')}</span>
        <input type="number" min={1} max={99} {...register('quantity', { valueAsNumber: true })} className="input" />
      </label>
      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('adding') : t('add_to_booking')}
      </button>
    </form>
  );
}
