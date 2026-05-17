'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import { authApi } from '@/lib/auth';
import { AxiosError } from 'axios';

const schema = z
  .object({
    name: z.string().min(2).max(255),
    phone_e164: z.string().regex(/^\+\d{8,15}$/, 'Phone must be in +E.164 format'),
    email: z.string().email().optional().or(z.literal('')),
    password: z.string().min(8),
    password_confirmation: z.string().min(8),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match',
  });

type FormValues = z.infer<typeof schema>;

export default function RegisterPage() {
  const t = useTranslations('auth');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const locale = (params?.locale as 'en' | 'ar') ?? 'en';
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = handleSubmit(async (values) => {
    setSubmitError(null);
    try {
      await authApi.register({
        name: values.name,
        phone_e164: values.phone_e164,
        email: values.email || undefined,
        password: values.password,
        password_confirmation: values.password_confirmation,
        preferred_locale: locale,
      });
      await authApi.sendOtp(values.phone_e164);
      router.push(`/${locale}/auth/verify?phone=${encodeURIComponent(values.phone_e164)}`);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]> }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? t('errors.register_failed'));
    }
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h1 className="font-heading text-2xl font-bold">{t('register.title')}</h1>
      <p className="text-sm text-neutral-600">{t('register.subtitle')}</p>

      <Field label={t('fields.name')} error={errors.name?.message}>
        <input {...register('name')} className="input" autoComplete="name" />
      </Field>

      <Field label={t('fields.phone')} error={errors.phone_e164?.message} hint={t('fields.phone_hint')}>
        <input {...register('phone_e164')} className="input" inputMode="tel" placeholder="+201234567890" />
      </Field>

      <Field label={t('fields.email_optional')} error={errors.email?.message}>
        <input {...register('email')} className="input" type="email" autoComplete="email" />
      </Field>

      <Field label={t('fields.password')} error={errors.password?.message}>
        <input {...register('password')} className="input" type="password" autoComplete="new-password" />
      </Field>

      <Field label={t('fields.password_confirmation')} error={errors.password_confirmation?.message}>
        <input
          {...register('password_confirmation')}
          className="input"
          type="password"
          autoComplete="new-password"
        />
      </Field>

      {submitError && <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>}

      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('register.submitting') : t('register.submit')}
      </button>

      <p className="text-center text-sm text-neutral-600">
        {t('register.have_account')}{' '}
        <Link href={`/${locale}/auth/login`} className="font-medium text-primary-600 hover:underline">
          {t('login.title')}
        </Link>
      </p>
    </form>
  );
}

function Field({
  label,
  error,
  hint,
  children,
}: {
  label: string;
  error?: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-neutral-800">{label}</span>
      {children}
      {hint && !error && <span className="mt-1 block text-xs text-neutral-500">{hint}</span>}
      {error && <span className="mt-1 block text-xs text-danger">{error}</span>}
    </label>
  );
}
