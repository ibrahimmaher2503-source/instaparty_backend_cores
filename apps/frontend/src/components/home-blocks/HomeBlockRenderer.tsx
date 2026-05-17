import type { HomepageBlock } from '@/types/api';
import { HeroCarousel } from './HeroCarousel';
import { CtaBanner } from './CtaBanner';
import { FeaturedServices } from './FeaturedServices';
import { TextImageSplit } from './TextImageSplit';
import { Testimonials } from './Testimonials';
import { LoyaltyPromo } from './LoyaltyPromo';
import { FeaturedTaxonomy } from './FeaturedTaxonomy';
import { VendorSpotlight } from './VendorSpotlight';

export function HomeBlockRenderer({ block, locale }: { block: HomepageBlock; locale: string }) {
  // Discriminated-union dispatch — no if/else on a string.
  switch (block.block_type) {
    case 'hero_carousel':
      return <HeroCarousel payload={block.payload} locale={locale} />;
    case 'featured_services':
      return <FeaturedServices payload={block.payload} locale={locale} />;
    case 'featured_occasions':
    case 'featured_categories':
      return <FeaturedTaxonomy payload={block.payload} kind={block.block_type} locale={locale} />;
    case 'vendor_spotlight':
      return <VendorSpotlight payload={block.payload} locale={locale} />;
    case 'cta_banner':
      return <CtaBanner payload={block.payload} />;
    case 'text_image_split':
      return <TextImageSplit payload={block.payload} />;
    case 'testimonials':
      return <Testimonials payload={block.payload} />;
    case 'loyalty_promo':
      return <LoyaltyPromo payload={block.payload} />;
  }
}
