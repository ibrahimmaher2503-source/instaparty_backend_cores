import Link from 'next/link';
import { discoveryApi } from '@/lib/api';

export const revalidate = 30;

export default async function SearchPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { locale } = await params;
  const sp = await searchParams;

  const q = new URLSearchParams();
  for (const [k, v] of Object.entries(sp)) {
    if (typeof v === 'string') q.set(k, v);
    else if (Array.isArray(v)) v.forEach((x) => q.append(k, x));
  }

  let result;
  try {
    result = await discoveryApi.searchServices(q, locale);
  } catch {
    result = { items: [], meta: { total: 0, per_page: 24, current_page: 1 } };
  }

  return (
    <section className="mx-auto max-w-7xl px-4 py-8">
      <h1 className="font-heading text-2xl md:text-3xl font-bold mb-6">Browse</h1>
      <form action="" className="mb-6 flex gap-2">
        <input
          name="q"
          type="search"
          defaultValue={(sp.q as string) ?? ''}
          placeholder="Search…"
          className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm"
        />
        <select name="product_type" defaultValue={(sp.product_type as string) ?? ''} className="rounded-md border border-neutral-300 px-3 py-2 text-sm">
          <option value="">All types</option>
          <option value="rental">Rental</option>
          <option value="sale">Sale</option>
          <option value="digital">Digital</option>
        </select>
        <button type="submit" className="rounded-md bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 text-sm">
          Search
        </button>
      </form>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {result.items.map((s) => (
          <Link
            key={s.public_id}
            href={`/${locale}/services/${s.public_id}`}
            className="block rounded-lg border border-neutral-200 bg-white hover:shadow-md transition overflow-hidden"
          >
            <div className="aspect-square bg-neutral-100">
              {s.cover_image_url ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={s.cover_image_url} alt={s.name} className="h-full w-full object-cover" />
              ) : null}
            </div>
            <div className="p-3">
              <div className="text-sm font-medium text-neutral-900 truncate">{s.name}</div>
              <div className="mt-0.5 text-xs text-neutral-500 truncate">{s.vendor?.business_name ?? '—'}</div>
              <div className="mt-2 flex items-center justify-between text-sm">
                <span className="font-semibold text-primary-700">
                  {new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', {
                    style: 'currency',
                    currency: s.currency,
                    maximumFractionDigits: 0,
                  }).format(s.price_from_minor / 100)}
                </span>
                <span className="inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] uppercase tracking-wide">
                  {s.product_type}
                </span>
              </div>
            </div>
          </Link>
        ))}
        {result.items.length === 0 ? (
          <p className="col-span-full text-neutral-500 text-sm">No services found.</p>
        ) : null}
      </div>
    </section>
  );
}
