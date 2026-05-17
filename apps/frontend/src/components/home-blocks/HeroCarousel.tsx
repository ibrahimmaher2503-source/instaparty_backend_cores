'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import type { HeroCarouselPayload } from '@/types/api';

export function HeroCarousel({ payload, locale }: { payload: HeroCarouselPayload; locale: string }) {
  const [idx, setIdx] = useState(0);
  const slides = payload.slides ?? [];

  useEffect(() => {
    if (slides.length <= 1) return;
    const id = setInterval(() => setIdx((i) => (i + 1) % slides.length), 6000);
    return () => clearInterval(id);
  }, [slides.length]);

  if (slides.length === 0) return null;
  const slide = slides[idx];

  return (
    <section className="relative h-[60vh] min-h-[420px] w-full overflow-hidden bg-neutral-900">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={slide.image_url} alt="" className="absolute inset-0 h-full w-full object-cover opacity-80" />
      <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
      <div className="relative mx-auto flex h-full max-w-7xl flex-col justify-end px-4 pb-12 text-white">
        <h1 className="font-heading text-4xl md:text-6xl font-bold max-w-3xl">{slide.headline}</h1>
        {slide.sub ? <p className="mt-3 max-w-2xl text-base md:text-lg text-white/90">{slide.sub}</p> : null}
        {slide.cta_url && slide.cta_label ? (
          <Link
            href={slide.cta_url.startsWith('http') ? slide.cta_url : `/${locale}${slide.cta_url}`}
            className="mt-6 inline-flex w-fit items-center justify-center rounded-md bg-primary-600 hover:bg-primary-700 px-5 py-3 text-sm font-semibold transition"
          >
            {slide.cta_label}
          </Link>
        ) : null}
      </div>
      {slides.length > 1 ? (
        <div className="absolute bottom-4 inline-flex gap-1.5 left-1/2 -translate-x-1/2">
          {slides.map((_, i) => (
            <button
              key={i}
              type="button"
              aria-label={`Slide ${i + 1}`}
              onClick={() => setIdx(i)}
              className={`h-1.5 w-6 rounded-full ${i === idx ? 'bg-white' : 'bg-white/40'}`}
            />
          ))}
        </div>
      ) : null}
    </section>
  );
}
