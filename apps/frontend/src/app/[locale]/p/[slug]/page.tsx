import { notFound } from 'next/navigation';
import { cmsApi } from '@/lib/api';

export const revalidate = 120;

export async function generateMetadata({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  try {
    const page = await cmsApi.getPage(slug, locale);
    return { title: page.title, description: page.meta_description ?? undefined };
  } catch {
    return {};
  }
}

export default async function CmsPage({ params }: { params: Promise<{ locale: string; slug: string }> }) {
  const { locale, slug } = await params;
  let page;
  try {
    page = await cmsApi.getPage(slug, locale);
  } catch {
    notFound();
  }

  return (
    <article className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="font-heading text-4xl font-bold">{page.title}</h1>
      <div className="prose mt-6 max-w-none" dangerouslySetInnerHTML={{ __html: page.body }} />
      {page.blocks?.length ? (
        <div className="mt-8 space-y-6">
          {/* Lightweight server-side block render — kept simple for Phase 1. */}
          {page.blocks.map((b, i) => (
            <pre key={i} className="bg-neutral-100 text-xs p-3 rounded overflow-x-auto">
              {JSON.stringify(b, null, 2)}
            </pre>
          ))}
        </div>
      ) : null}
    </article>
  );
}
