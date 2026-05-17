'use client';

import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import type { Booking, CreateBookingDraftPayload } from '@/types/api';

type EventBasics = CreateBookingDraftPayload | null;

type BookingDraftState = {
  bookingPublicId: string | null;
  eventBasics: EventBasics;
  booking: Booking | null;
  holdExpiresAt: string | null;
  paymentIdempotencyKey: string | null;
  setBooking: (booking: Booking | null) => void;
  setEventBasics: (basics: EventBasics) => void;
  setHoldExpiresAt: (expiresAt: string | null) => void;
  ensurePaymentIdempotencyKey: () => string;
  clear: () => void;
};

const STORAGE_KEY = 'instaparty.booking_draft.v1';

export const useBookingDraft = create<BookingDraftState>()(
  persist(
    (set, get) => ({
      bookingPublicId: null,
      eventBasics: null,
      booking: null,
      holdExpiresAt: null,
      paymentIdempotencyKey: null,
      setBooking: (booking) =>
        set({
          booking,
          bookingPublicId: booking?.public_id ?? null,
          holdExpiresAt: booking?.hold_expires_at ?? get().holdExpiresAt,
        }),
      setEventBasics: (eventBasics) => set({ eventBasics }),
      setHoldExpiresAt: (holdExpiresAt) => set({ holdExpiresAt }),
      ensurePaymentIdempotencyKey: () => {
        const existing = get().paymentIdempotencyKey;
        if (existing) return existing;
        const key = typeof crypto !== 'undefined' && 'randomUUID' in crypto
          ? crypto.randomUUID()
          : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        set({ paymentIdempotencyKey: key });
        return key;
      },
      clear: () =>
        set({
          bookingPublicId: null,
          eventBasics: null,
          booking: null,
          holdExpiresAt: null,
          paymentIdempotencyKey: null,
        }),
    }),
    {
      name: STORAGE_KEY,
      storage: createJSONStorage(() => (typeof window === 'undefined' ? sessionStorage : localStorage)),
      partialize: (state) => ({
        bookingPublicId: state.bookingPublicId,
        eventBasics: state.eventBasics,
        holdExpiresAt: state.holdExpiresAt,
        paymentIdempotencyKey: state.paymentIdempotencyKey,
      }),
    },
  ),
);
