'use client';

import { useState } from 'react';
import { useRouter, useParams, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import * as Tabs from '@radix-ui/react-tabs';
import { AxiosError } from 'axios';
import { authApi, writeToken } from '@/lib/auth';
import { useAuthStore } from '@/lib/store/auth';

const passwordSchema = z.object({
  login: z.string().min(3),
  password: z.string().min(1),
});
const otpSchema = z.object({
  phone_e164: z.string().regex(/^\+\d{8,15}$/, 'Phone must be in +E.164 format'),
});

export default function LoginPage() {
  const t = useTranslations('auth');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const search = useSearchParams();
  const locale = params?.locale ?? 'en';
  const next = search.get('next') ?? `/${locale}`;

  return (
    <div className="space-y-4">
      <h1 className="font-heading text-2xl font-bold">{t('login.title')}</h1>
      <p className="text-sm text-neutral-600">{t('login.subtitle')}</p>

      <Tabs.Root defaultValue="password" className="w-full">
        <Tabs.List className="mb-4 flex gap-2 border-b border-neutral-200">
          <Tabs.Trigger
            value="password"
            className="px-3 py-2 text-sm font-medium data-[state=active]:border-b-2 data-[state=active]:border-primary-600 data-[state=active]:text-primary-600"
          >
            {t('login.password_tab')}
          </Tabs.Trigger>
          <Tabs.Trigger
            value="otp"
            className="px-3 py-2 text-sm font-medium data-[state=active]:border-b-2 data-[state=active]:border-primary-600 data-[state=active]:text-primary-600"
          >
            {t('login.otp_tab')}
          </Tabs.Trigger>
        </Tabs.List>
        <Tabs.Content value="password">
          <PasswordForm next={next} locale={locale} />
        </Tabs.Content>
        <Tabs.Content value="otp">
          <OtpForm locale={locale} />
        </Tabs.Content>
      </Tabs.Root>

      <p className="text-center text-sm text-neutral-600">
        {t('login.no_account')}{' '}
        <Link href={`/${locale}/auth/register`} className="font-medium text-primary-600 hover:underline">
          {t('register.title')}
        </Link>
      </p>
      <p className="text-center text-xs text-neutral-500">
        <Link href={`/${locale}/auth/forgot`} className="hover:underline">
          {t('login.forgot')}
        </Link>
      </p>
    </div>
  );
}

function PasswordForm({ next, locale }: { next: string; locale: string }) {
  const t = useTranslations('auth');
  const router = useRouter();
  const setUser = useAuthStore((s) => s.setUser);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof passwordSchema>>({ resolver: zodResolver(passwordSchema) });

  const onSubmit = handleSubmit(async (values) => {
    setSubmitError(null);
    try {
      const res = await authApi.login({ ...values, device_name: 'web' });
      writeToken(res.token);
      setUser(res.user);
      router.push(next);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? ax.response?.data?.message ?? t('errors.login_failed'));
    }
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-neutral-800">{t('fields.login_identifier')}</span>
        <input {...register('login')} className="input" autoComplete="username" />
        {errors.login && <span className="mt-1 block text-xs text-danger">{errors.login.message}</span>}
      </label>
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-neutral-800">{t('fields.password')}</span>
        <input {...register('password')} className="input" type="password" autoComplete="current-password" />
        {errors.password && <span className="mt-1 block text-xs text-danger">{errors.password.message}</span>}
      </label>
      {submitError && <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>}
      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('login.submitting') : t('login.submit')}
      </button>
    </form>
  );
}

function OtpForm({ locale }: { locale: string }) {
  const t = useTranslations('auth');
  const router = useRouter();
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof otpSchema>>({ resolver: zodResolver(otpSchema) });

  const onSubmit = handleSubmit(async (values) => {
    setSubmitError(null);
    try {
      await authApi.sendOtp(values.phone_e164);
      router.push(`/${locale}/auth/verify?phone=${encodeURIComponent(values.phone_e164)}`);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]> }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? t('errors.otp_send_failed'));
    }
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-neutral-800">{t('fields.phone')}</span>
        <input {...register('phone_e164')} className="input" inputMode="tel" placeholder="+201234567890" />
        {errors.phone_e164 && <span className="mt-1 block text-xs text-danger">{errors.phone_e164.message}</span>}
      </label>
      {submitError && <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>}
      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('login.sending_otp') : t('login.send_otp')}
      </button>
    </form>
  );
}
