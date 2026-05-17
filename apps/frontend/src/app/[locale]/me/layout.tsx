import { type ReactNode } from 'react';
import { MeSidebar } from '@/components/me/Sidebar';

export default async function MeLayout({
  children,
  params,
}: {
  children: ReactNode;
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  return (
    <section className="mx-auto max-w-6xl px-4 py-8">
      <div className="grid gap-6 md:grid-cols-[220px_1fr]">
        <MeSidebar locale={locale} />
        <div className="min-w-0">{children}</div>
      </div>
    </section>
  );
}
