import Link from 'next/link';
import { getTranslations } from 'next-intl/server';

export default async function ForgotPage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'auth' });

  return (
    <div className="space-y-4">
      <h1 className="font-heading text-2xl font-bold">{t('forgot.title')}</h1>
      <p className="text-sm text-neutral-600">{t('forgot.coming_soon')}</p>
      <Link href={`/${locale}/auth/login`} className="text-sm text-primary-600 hover:underline">
        {t('forgot.back_to_login')}
      </Link>
    </div>
  );
}
