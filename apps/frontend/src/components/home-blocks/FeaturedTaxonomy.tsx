import Link from 'next/link';
import type { FeaturedListPayload } from '@/types/api';

export function FeaturedTaxonomy({
  payload,
  kind,
  locale,
}: {
  payload: FeaturedListPayload;
  kind: 'featured_occasions' | 'featured_categories';
  locale: string;
}) {
  const ids = payload.public_ids ?? [];
  const prefix = kind === 'featured_occasions' ? 'o' : 'c';

  return (
    <section className="mx-auto max-w-7xl px-4 my-12">
      <h2 className="font-heading text-2xl md:text-3xl font-bold mb-6">{payload.title}</h2>
      <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
        {ids.map((id) => (
          <Link
            key={id}
            href={`/${locale}/${prefix}/${id}`}
            className="aspect-square rounded-lg bg-gradient-to-br from-primary-100 to-accent-100 flex items-center justify-center text-center p-2 text-xs font-medium text-primary-900 hover:from-primary-200"
          >
            {id.slice(-6)}
          </Link>
        ))}
      </div>
    </section>
  );
}
