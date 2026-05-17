'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { profileApi } from '@/lib/api';
import { useAuthStore } from '@/lib/store/auth';
import type { User } from '@/types/auth';

const schema = z.object({
  name: z.string().min(2).max(255),
  email: z.string().email().optional().or(z.literal('')),
  preferred_locale: z.enum(['en', 'ar']),
});

type Values = z.infer<typeof schema>;

export default function ProfilePage() {
  const t = useTranslations('me.profile');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const setUser = useAuthStore((s) => s.setUser);

  const [savedAt, setSavedAt] = useState<number | null>(null);

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<Values>({
    resolver: zodResolver(schema),
  });

  useEffect(() => {
    profileApi.get(locale).then((data) => {
      const user = (data as Record<string, unknown>) as User & Record<string, unknown>;
      reset({
        name: (user.name as string) ?? '',
        email: (user.email as string) ?? '',
        preferred_locale: (user.preferred_locale as 'en' | 'ar') ?? 'en',
      });
    });
  }, [locale, reset]);

  const onSubmit = handleSubmit(async (v) => {
    const result = await profileApi.update(locale, {
      name: v.name,
      email: v.email || null,
      preferred_locale: v.preferred_locale,
    });
    setUser(result as unknown as User);
    setSavedAt(Date.now());
  });

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      <form onSubmit={onSubmit} className="max-w-md space-y-3 rounded-lg border border-neutral-200 bg-white p-4">
        <label className="block">
          <span className="mb-1 block text-xs font-medium">{t('name')}</span>
          <input {...register('name')} className="input" />
          {errors.name && <span className="text-xs text-danger">{errors.name.message}</span>}
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium">{t('email')}</span>
          <input type="email" {...register('email')} className="input" />
        </label>
        <label className="block">
          <span className="mb-1 block text-xs font-medium">{t('preferred_locale')}</span>
          <select {...register('preferred_locale')} className="input">
            <option value="en">English</option>
            <option value="ar">العربية</option>
          </select>
        </label>
        <button type="submit" disabled={isSubmitting} className="btn-primary">
          {isSubmitting ? t('saving') : t('save')}
        </button>
        {savedAt && <p className="text-xs text-success">{t('saved')}</p>}
      </form>
    </div>
  );
}
