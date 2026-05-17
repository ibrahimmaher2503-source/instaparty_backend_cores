import clsx, { type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(...inputs));
}

export function formatPriceEGP(minor: number, locale: string, currency = 'EGP'): string {
  const major = minor / 100;
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-EG', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(major);
}
