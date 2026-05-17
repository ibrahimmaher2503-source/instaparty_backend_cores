import type { TextImageSplitPayload } from '@/types/api';

export function TextImageSplit({ payload }: { payload: TextImageSplitPayload }) {
  const imageFirst = payload.image_side === 'left';

  return (
    <section className="mx-auto max-w-7xl px-4 my-12 grid md:grid-cols-2 gap-8 items-center">
      {imageFirst ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={payload.image_url} alt="" className="rounded-xl h-72 md:h-96 w-full object-cover" />
      ) : null}
      <div>
        <h2 className="font-heading text-3xl font-bold">{payload.headline}</h2>
        <p className="mt-4 text-neutral-700 whitespace-pre-line">{payload.body}</p>
      </div>
      {!imageFirst ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={payload.image_url} alt="" className="rounded-xl h-72 md:h-96 w-full object-cover" />
      ) : null}
    </section>
  );
}
