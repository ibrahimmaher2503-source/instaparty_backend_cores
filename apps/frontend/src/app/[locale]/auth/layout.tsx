import { type ReactNode } from 'react';
import Link from 'next/link';
import { getTranslations } from 'next-intl/server';

export default async function AuthLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'common' });

  return (
    <div className="mx-auto flex min-h-[calc(100vh-12rem)] w-full max-w-md flex-col items-center justify-center px-4 py-12">
      <Link href={`/${locale}`} className="mb-6 font-heading text-2xl font-bold text-primary-600">
        {t('site_name')}
      </Link>
      <div className="w-full rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        {children}
      </div>
    </div>
  );
}
