import Link from 'next/link';
import type { LoyaltyPromoPayload } from '@/types/api';

export function LoyaltyPromo({ payload }: { payload: LoyaltyPromoPayload }) {
  return (
    <section className="mx-auto max-w-3xl px-4 my-12 text-center">
      <h2 className="font-heading text-2xl md:text-3xl font-bold">{payload.headline}</h2>
      {payload.body ? <p className="mt-3 text-neutral-700">{payload.body}</p> : null}
      {payload.cta_url ? (
        <Link href={payload.cta_url} className="mt-6 inline-flex rounded-md bg-accent-500 hover:bg-accent-600 text-white px-5 py-3 text-sm font-semibold">
          Learn more
        </Link>
      ) : null}
    </section>
  );
}
