'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import type { ServiceDetail, AddBookingItemPayload } from '@/types/api';

const schema = z.object({
  quantity: z.number().min(1).max(999),
  note: z.string().max(500).optional(),
});

type Values = z.infer<typeof schema>;

export function SaleItemForm({
  service,
  onSubmit,
}: {
  service: ServiceDetail;
  onSubmit: (payload: AddBookingItemPayload) => Promise<void>;
}) {
  const t = useTranslations('checkout.items');
  const ts = useTranslations('service');
  const maxQty = service.sale?.stock_quantity ?? 999;
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { quantity: 1 },
  });

  const submit = handleSubmit(async (v) => {
    await onSubmit({
      service_id: service.public_id,
      quantity: v.quantity,
      customization_data: v.note ? { note: v.note } : undefined,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-3">
      {service.sale?.lead_time_hours != null && (
        <p className="rounded-md bg-info/10 px-3 py-2 text-xs text-info">
          {ts('lead_time')}: {service.sale.lead_time_hours}h
        </p>
      )}
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{ts('quantity')}</span>
        <input
          type="number"
          min={1}
          max={maxQty}
          {...register('quantity', { valueAsNumber: true })}
          className="input"
        />
        {errors.quantity && <span className="text-xs text-danger">{errors.quantity.message}</span>}
      </label>
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{t('note_optional')}</span>
        <textarea {...register('note')} rows={3} className="input" />
      </label>
      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('adding') : t('add_to_booking')}
      </button>
    </form>
  );
}
