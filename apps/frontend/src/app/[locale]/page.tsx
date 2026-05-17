import Link from 'next/link';
import { themeApi } from '@/lib/api';
import { HomeBlockRenderer } from '@/components/home-blocks/HomeBlockRenderer';
import { getTranslations } from 'next-intl/server';

export const revalidate = 60;

export default async function HomePage({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  const t = await getTranslations('home');

  const blocks = await themeApi.getHomepage(locale).catch(() => []);

  if (blocks.length === 0) {
    // Empty-state fallback so an unconfigured admin still gets a sensible homepage.
    return (
      <section className="mx-auto max-w-3xl px-4 py-24 text-center">
        <h1 className="font-heading text-4xl md:text-5xl font-bold">{t('hero_default_headline')}</h1>
        <p className="mt-4 text-neutral-700">{t('hero_default_sub')}</p>
        <Link
          href={`/${locale}/search`}
          className="mt-8 inline-flex rounded-md bg-primary-600 hover:bg-primary-700 text-white px-5 py-3 text-sm font-semibold"
        >
          {t('browse_services')}
        </Link>
      </section>
    );
  }

  return (
    <>
      {blocks.map((block) => (
        <HomeBlockRenderer key={block.public_id} block={block} locale={locale} />
      ))}
    </>
  );
}
