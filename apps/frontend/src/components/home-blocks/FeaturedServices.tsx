import Link from 'next/link';
import type { FeaturedListPayload } from '@/types/api';

export function FeaturedServices({ payload, locale }: { payload: FeaturedListPayload; locale: string }) {
  const ids = payload.service_public_ids ?? [];
  return (
    <section className="mx-auto max-w-7xl px-4 my-12">
      <h2 className="font-heading text-2xl md:text-3xl font-bold mb-6">{payload.title}</h2>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {ids.map((id) => (
          <Link
            key={id}
            href={`/${locale}/services/${id}`}
            className="block rounded-lg border border-neutral-200 bg-white hover:shadow-md transition overflow-hidden"
          >
            <div className="aspect-square bg-neutral-100" />
            <div className="p-3">
              <div className="text-sm font-medium text-neutral-900 truncate">{id}</div>
              <div className="mt-1 text-xs text-neutral-500">View details</div>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
