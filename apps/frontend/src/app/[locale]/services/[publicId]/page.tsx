import { notFound } from 'next/navigation';
import { discoveryApi } from '@/lib/api';
import { ServiceDetailPanel } from '@/components/service-detail/ServiceDetailPanel';

export const revalidate = 60;

export default async function ServicePage({
  params,
}: {
  params: Promise<{ locale: string; publicId: string }>;
}) {
  const { locale, publicId } = await params;
  let service;
  try {
    service = await discoveryApi.getServiceDetail(publicId, locale);
  } catch {
    notFound();
  }

  return (
    <article className="mx-auto max-w-7xl px-4 py-8 grid lg:grid-cols-3 gap-8">
      <div className="lg:col-span-2">
        <div className="aspect-[4/3] rounded-xl bg-neutral-200 overflow-hidden">
          {service.cover_image_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={service.cover_image_url} alt={service.name} className="h-full w-full object-cover" />
          ) : null}
        </div>

        <h1 className="mt-6 font-heading text-3xl md:text-4xl font-bold">{service.name}</h1>
        <div className="mt-2 flex items-center gap-3 text-sm text-neutral-600">
          {service.vendor ? <span>{service.vendor.business_name}</span> : null}
          {service.category ? <span>· {service.category.name}</span> : null}
          <span className="inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-xs uppercase tracking-wide">
            {service.product_type}
          </span>
        </div>

        {service.description ? (
          <div className="prose mt-6 max-w-none text-neutral-700 whitespace-pre-line">{service.description}</div>
        ) : null}

        {service.gallery?.length ? (
          <div className="mt-8 grid grid-cols-2 md:grid-cols-3 gap-3">
            {service.gallery.map((url, i) => (
              // eslint-disable-next-line @next/next/no-img-element
              <img key={i} src={url} alt="" className="aspect-square w-full object-cover rounded-lg" />
            ))}
          </div>
        ) : null}
      </div>

      <aside className="lg:sticky lg:top-20 self-start">
        <ServiceDetailPanel service={service} locale={locale} />
      </aside>
    </article>
  );
}
