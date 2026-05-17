import Link from 'next/link';
import type { VendorSpotlightPayload } from '@/types/api';

export function VendorSpotlight({ payload, locale }: { payload: VendorSpotlightPayload; locale: string }) {
  return (
    <section className="mx-auto max-w-7xl px-4 my-12">
      <h2 className="font-heading text-2xl md:text-3xl font-bold mb-6">{payload.title}</h2>
      <Link
        href={`/${locale}/vendors/${payload.vendor_public_id}`}
        className="block rounded-xl bg-gradient-to-r from-secondary-100 to-primary-100 p-8 hover:from-secondary-200 hover:to-primary-200 transition"
      >
        <div className="font-heading text-lg font-semibold text-primary-900">{payload.vendor_public_id}</div>
        <div className="mt-1 text-sm text-primary-800">View vendor profile →</div>
      </Link>
    </section>
  );
}
