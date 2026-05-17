import { RentalVariant } from './RentalVariant';
import { SaleVariant } from './SaleVariant';
import { DigitalVariant } from './DigitalVariant';
import type { ServiceDetail } from '@/types/api';

/**
 * Per-product-type dispatcher.
 * Per `.claude/rules/product-types.md`, this MUST be a discriminated-union switch,
 * not `if/else` on a string.
 */
export function ServiceDetailPanel({ service, locale }: { service: ServiceDetail; locale: string }) {
  switch (service.product_type) {
    case 'rental':
      return <RentalVariant service={service} locale={locale} />;
    case 'sale':
      return <SaleVariant service={service} locale={locale} />;
    case 'digital':
      return <DigitalVariant service={service} locale={locale} />;
  }
}
