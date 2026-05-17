'use client';

import { useState } from 'react';
import { useRouter, useParams, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import { authApi } from '@/lib/auth';
import { useAuthStore } from '@/lib/store/auth';

const schema = z.object({
  code: z.string().regex(/^\d{6}$/, 'Must be 6 digits'),
});

export default function VerifyPage() {
  const t = useTranslations('auth');
  const router = useRouter();
  const params = useParams<{ locale: string }>();
  const search = useSearchParams();
  const locale = params?.locale ?? 'en';
  const phone = search.get('phone') ?? '';
  const setUser = useAuthStore((s) => s.setUser);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [resendState, setResendState] = useState<'idle' | 'sending' | 'sent'>('idle');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) });

  const onSubmit = handleSubmit(async (values) => {
    setSubmitError(null);
    try {
      const user = await authApi.verifyPhone(phone, values.code);
      setUser(user);
      router.push(`/${locale}/auth/login?verified=1`);
    } catch (err) {
      const ax = err as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
      const first = Object.values(ax.response?.data?.errors ?? {})[0]?.[0];
      setSubmitError(first ?? ax.response?.data?.message ?? t('errors.verify_failed'));
    }
  });

  const onResend = async () => {
    setResendState('sending');
    try {
      await authApi.sendOtp(phone);
      setResendState('sent');
    } catch {
      setResendState('idle');
    }
  };

  if (!phone) {
    return <p className="text-sm text-danger">{t('verify.missing_phone')}</p>;
  }

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <h1 className="font-heading text-2xl font-bold">{t('verify.title')}</h1>
      <p className="text-sm text-neutral-600">{t('verify.subtitle', { phone })}</p>

      <label className="block">
        <span className="mb-1 block text-sm font-medium text-neutral-800">{t('fields.otp_code')}</span>
        <input
          {...register('code')}
          className="input text-center text-2xl tracking-[0.5em]"
          inputMode="numeric"
          maxLength={6}
          autoComplete="one-time-code"
        />
        {errors.code && <span className="mt-1 block text-xs text-danger">{errors.code.message}</span>}
      </label>

      {submitError && <p className="rounded-md bg-danger/10 p-3 text-sm text-danger">{submitError}</p>}

      <button type="submit" disabled={isSubmitting} className="btn-primary w-full">
        {isSubmitting ? t('verify.submitting') : t('verify.submit')}
      </button>

      <button
        type="button"
        onClick={onResend}
        disabled={resendState !== 'idle'}
        className="w-full text-sm text-primary-600 hover:underline disabled:text-neutral-400"
      >
        {resendState === 'sent' ? t('verify.resent') : resendState === 'sending' ? t('verify.resending') : t('verify.resend')}
      </button>
    </form>
  );
}
