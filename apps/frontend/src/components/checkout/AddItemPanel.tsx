'use client';

import type { ServiceDetail, AddBookingItemPayload } from '@/types/api';
import { RentalItemForm } from './items/RentalItemForm';
import { SaleItemForm } from './items/SaleItemForm';
import { DigitalItemForm } from './items/DigitalItemForm';

export function AddItemPanel({
  service,
  onSubmit,
}: {
  service: ServiceDetail;
  onSubmit: (payload: AddBookingItemPayload) => Promise<void>;
}) {
  switch (service.product_type) {
    case 'rental':
      return <RentalItemForm service={service} onSubmit={onSubmit} />;
    case 'sale':
      return <SaleItemForm service={service} onSubmit={onSubmit} />;
    case 'digital':
      return <DigitalItemForm service={service} onSubmit={onSubmit} />;
  }
}
