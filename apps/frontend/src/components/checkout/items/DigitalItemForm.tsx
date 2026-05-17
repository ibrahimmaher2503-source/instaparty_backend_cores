'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import type { ServiceDetail, AddBookingItemPayload } from '@/types/api';

const schema = z.object({
  quantity: z.number().min(1).max(99),
  recipient_email: z.string().email().optional().or(z.literal('')),
  recipient_phone_e164: z
    .string()
    .regex(/^\+\d{8,15}$/)
    .optional()
    .or(z.literal('')),
});

type Values = z.infer<typeof schema>;

export function DigitalItemForm({
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
    defaultValues: { quantity: 1 },
  });

  const submit = handleSubmit(async (v) => {
    const customization: Record<string, unknown> = {};
    if (v.recipient_email) customization.recipient_email = v.recipient_email;
    if (v.recipient_phone_e164) customization.recipient_phone_e164 = v.recipient_phone_e164;
    await onSubmit({
      service_id: service.public_id,
      quantity: v.quantity,
      customization_data: Object.keys(customization).length ? customization : undefined,
    });
  });

  return (
    <form onSubmit={submit} className="space-y-3">
      {service.digital?.delivery_method && (
        <p className="rounded-md bg-info/10 px-3 py-2 text-xs text-info">
          {ts('delivery_method')}: {service.digital.delivery_method}
        </p>
      )}
      {service.digital?.has_expiry && service.digital.expiry_days_after_purchase != null && (
        <p className="rounded-md bg-warning/10 px-3 py-2 text-xs text-warning">
          {ts('expiry_note')}: {service.digital.expiry_days_after_purchase}d
        </p>
      )}
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{ts('quantity')}</span>
        <input
          type="number"
          min={1}
          max={99}
          {...register('quantity', { valueAsNumber: true })}
          className="input"
        />
      </label>
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{t('recipient_email')}</span>
        <input type="email" {...register('recipient_email')} className="input" />
        {errors.recipient_email && <span className="text-xs text-danger">{errors.recipient_email.message}</span>}
      </label>
      <label className="block">
        <span className="mb-1 block text-xs font-medium">{t('recipient_phone')}</span>
        <input {...register('recipient_phone_e164')} className="input" placeholder="+201234567890" />
        {errors.recipient_phone_e164 && (
          <span className="text-xs text-danger">{errors.recipient_phone_e164.message}</span>
        )}
      </label>
      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('adding') : t('add_to_booking')}
      </button>
    </form>
  );
}
