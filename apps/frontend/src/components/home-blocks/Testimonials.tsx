import type { TestimonialsPayload } from '@/types/api';

export function Testimonials({ payload }: { payload: TestimonialsPayload }) {
  return (
    <section className="bg-primary-50 py-12 my-12">
      <div className="mx-auto max-w-7xl px-4">
        <h2 className="font-heading text-2xl md:text-3xl font-bold mb-8 text-center">{payload.title}</h2>
        <div className="grid md:grid-cols-3 gap-6">
          {payload.items.map((item, i) => (
            <figure key={i} className="bg-white rounded-xl p-6 shadow-sm">
              <blockquote className="text-neutral-700">&ldquo;{item.quote}&rdquo;</blockquote>
              <figcaption className="mt-4 flex items-center gap-3">
                {item.avatar_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={item.avatar_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                ) : (
                  <div className="h-10 w-10 rounded-full bg-primary-200" />
                )}
                <span className="font-medium text-neutral-900">{item.author}</span>
              </figcaption>
            </figure>
          ))}
        </div>
      </div>
    </section>
  );
}
