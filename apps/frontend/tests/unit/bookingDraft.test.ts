import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useBookingDraft } from '@/lib/store/booking-draft';

beforeEach(() => {
  useBookingDraft.getState().clear();
});

describe('useBookingDraft store', () => {
  it('starts empty', () => {
    const s = useBookingDraft.getState();
    expect(s.bookingPublicId).toBeNull();
    expect(s.eventBasics).toBeNull();
    expect(s.booking).toBeNull();
    expect(s.holdExpiresAt).toBeNull();
  });

  it('setBooking syncs publicId from the booking', () => {
    useBookingDraft.getState().setBooking({
      public_id: 'BK_123',
      reference_no: 'INP-001',
      lifecycle_status: 'draft',
      payment_status: 'unpaid',
      fulfillment_status: 'not_started',
      event_starts_at: null,
      event_ends_at: null,
      guest_count: null,
      subtotal_minor: 0,
      delivery_total_minor: 0,
      discount_total_minor: 0,
      total_minor: 0,
      currency: 'EGP',
      requires_customer_approval: false,
    });
    expect(useBookingDraft.getState().bookingPublicId).toBe('BK_123');
  });

  it('ensurePaymentIdempotencyKey caches a single key per draft', () => {
    const k1 = useBookingDraft.getState().ensurePaymentIdempotencyKey();
    const k2 = useBookingDraft.getState().ensurePaymentIdempotencyKey();
    expect(k1).toBe(k2);
    expect(typeof k1).toBe('string');
    expect(k1.length).toBeGreaterThan(0);
  });

  it('clear() wipes the draft including idempotency key', () => {
    useBookingDraft.getState().ensurePaymentIdempotencyKey();
    useBookingDraft.getState().setHoldExpiresAt('2030-01-01T00:00:00Z');
    useBookingDraft.getState().clear();
    const s = useBookingDraft.getState();
    expect(s.holdExpiresAt).toBeNull();
    expect(s.paymentIdempotencyKey).toBeNull();
  });
});
