import { getTranslations } from 'next-intl/server';

export default async function ChatPage({
  params,
}: {
  params: Promise<{ locale: string; publicId: string }>;
}) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'me.chat' });

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      <div className="rounded-lg border border-neutral-200 bg-white p-6">
        <p className="text-sm text-neutral-600">{t('coming_soon')}</p>
        <p className="mt-2 text-xs text-neutral-500">{t('firestore_note')}</p>
      </div>
    </div>
  );
}
