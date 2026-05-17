import Link from 'next/link';
import { CheckCircle2 } from 'lucide-react';
import { getTranslations } from 'next-intl/server';
import { themeApi } from '@/lib/api';

export default async function ConfirmedPage({
  params,
}: {
  params: Promise<{ locale: string; bookingPublicId: string }>;
}) {
  const { locale, bookingPublicId } = await params;
  const t = await getTranslations({ locale, namespace: 'checkout.confirmed' });
  const branding = await themeApi.getBranding(locale).catch(() => null);

  const shareUrl = `${process.env.NEXT_PUBLIC_APP_URL ?? ''}/${locale}/checkout/confirmed/${bookingPublicId}`;
  const whatsapp = `https://wa.me/?text=${encodeURIComponent(`${branding?.site_name ?? 'InstaParty'}: ${shareUrl}`)}`;

  return (
    <section className="mx-auto max-w-2xl px-4 py-16 text-center">
      <CheckCircle2 className="mx-auto h-16 w-16 text-success" />
      <h1 className="mt-4 font-heading text-3xl font-bold">{t('title')}</h1>
      <p className="mt-2 text-sm text-neutral-600">{t('subtitle')}</p>
      <p className="mt-4 text-xs text-neutral-500">{t('reference')}: <code>{bookingPublicId}</code></p>

      <div className="mt-8 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <Link href={`/${locale}/me/bookings/${bookingPublicId}`} className="btn-primary">
          {t('view_booking')}
        </Link>
        <a
          href={whatsapp}
          target="_blank"
          rel="noreferrer"
          className="btn-secondary"
        >
          {t('share_whatsapp')}
        </a>
        <Link href={`/${locale}`} className="btn-secondary">
          {t('back_home')}
        </Link>
      </div>
    </section>
  );
}
