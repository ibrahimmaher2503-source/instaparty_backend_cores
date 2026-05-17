import { notFound } from 'next/navigation';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages } from 'next-intl/server';
import { themeApi } from '@/lib/api';
import { tokensToCss, googleFontHref, isRtl } from '@/lib/theme';
import { locales, type Locale } from '@/i18n';
import { Header } from '@/components/layout/Header';
import { Footer } from '@/components/layout/Footer';
import '../globals.css';

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) return {};

  try {
    const branding = await themeApi.getBranding(locale);
    return {
      title: { default: branding.site_name, template: `%s — ${branding.site_name}` },
      description: branding.tagline ?? undefined,
      icons: branding.assets.favicon ? [{ url: branding.assets.favicon }] : undefined,
      openGraph: branding.assets.og_image ? { images: [branding.assets.og_image] } : undefined,
    };
  } catch {
    return { title: 'InstaParty' };
  }
}

export default async function LocaleLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) notFound();

  const [tokens, branding, headerMenu, footerPrimary, footerSecondary, mobileDrawer] = await Promise.all([
    themeApi.getTokens(locale),
    themeApi.getBranding(locale),
    themeApi.getMenu('header', locale).catch(() => ({ slot: 'header', name: 'Header', items: [] })),
    themeApi.getMenu('footer_primary', locale).catch(() => ({ slot: 'footer_primary', name: 'Footer', items: [] })),
    themeApi.getMenu('footer_secondary', locale).catch(() => ({ slot: 'footer_secondary', name: 'Footer 2', items: [] })),
    themeApi.getMenu('mobile_drawer', locale).catch(() => ({ slot: 'mobile_drawer', name: 'Mobile', items: [] })),
  ]);

  const messages = await getMessages();
  const css = tokensToCss(tokens.tokens);
  const fontHref = googleFontHref(tokens.tokens);
  const dir = isRtl(locale) ? 'rtl' : 'ltr';

  return (
    <html lang={locale} dir={dir}>
      <head>
        {fontHref ? <link rel="stylesheet" href={fontHref} /> : null}
        <style dangerouslySetInnerHTML={{ __html: css }} />
      </head>
      <body>
        <NextIntlClientProvider messages={messages} locale={locale}>
          <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:bg-primary-600 focus:text-white focus:px-3 focus:py-2 focus:rounded">
            {(messages as Record<string, Record<string, string>>).common?.skip_to_content ?? 'Skip'}
          </a>
          <Header branding={branding} menu={headerMenu} mobileDrawer={mobileDrawer} locale={locale} />
          <main id="main" className="min-h-[60vh]">
            {children}
          </main>
          <Footer branding={branding} primary={footerPrimary} secondary={footerSecondary} />
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
