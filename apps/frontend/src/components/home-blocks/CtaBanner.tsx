import Link from 'next/link';
import type { CtaBannerPayload } from '@/types/api';

export function CtaBanner({ payload }: { payload: CtaBannerPayload }) {
  return (
    <section className="mx-auto max-w-7xl px-4 my-12">
      <div className="rounded-xl overflow-hidden bg-primary-700 text-white p-8 md:p-12 grid md:grid-cols-2 gap-8 items-center">
        <div>
          <h2 className="font-heading text-3xl md:text-4xl font-bold">{payload.headline}</h2>
          <Link href={payload.cta_url} className="mt-6 inline-flex rounded-md bg-white text-primary-700 px-5 py-3 text-sm font-semibold hover:bg-neutral-50">
            {payload.cta_label}
          </Link>
        </div>
        {payload.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={payload.image_url} alt="" className="h-48 md:h-64 w-full object-cover rounded-lg" />
        ) : null}
      </div>
    </section>
  );
}
