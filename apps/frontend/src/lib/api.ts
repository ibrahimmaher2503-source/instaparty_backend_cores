import axios, { type AxiosRequestConfig } from 'axios';
import type {
  AddBookingItemPayload,
  Booking,
  BookingItem,
  BookingModification,
  Branding,
  Category,
  City,
  CmsPagePayload,
  CreateBookingDraftPayload,
  Envelope,
  FeatureFlag,
  Governorate,
  HomepageBlock,
  Menu,
  Occasion,
  Payment,
  PaymentInitiation,
  ServiceDetail,
  ServiceSummary,
  ThemeTokens,
} from '@/types/api';

const SERVER_BASE = process.env.API_BASE_URL ?? 'http://localhost:8000/api/v1';
const CLIENT_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000/api/v1';
const TOKEN_COOKIE = 'instaparty_token';

export const apiClient = axios.create({
  baseURL: typeof window === 'undefined' ? SERVER_BASE : CLIENT_BASE,
  withCredentials: true,
  headers: { Accept: 'application/json' },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
});

apiClient.interceptors.request.use((config) => {
  if (typeof document !== 'undefined') {
    const match = document.cookie.match(new RegExp(`(?:^|; )${TOKEN_COOKIE}=([^;]+)`));
    if (match) {
      config.headers.set('Authorization', `Bearer ${decodeURIComponent(match[1])}`);
    }
  }
  return config;
});

async function serverFetch<T>(path: string, locale: string, revalidate = 60): Promise<T> {
  const res = await fetch(`${SERVER_BASE}${path}`, {
    headers: { 'Accept-Language': locale, Accept: 'application/json' },
    next: { revalidate, tags: ['theme', 'cms', 'menus'] },
  });
  if (!res.ok) throw new Error(`API ${path} failed: ${res.status}`);
  const json = (await res.json()) as Envelope<T>;
  return json.data;
}

export const themeApi = {
  getTokens: (locale: string) => serverFetch<ThemeTokens>('/theme/tokens', locale, 300),
  getBranding: (locale: string) => serverFetch<Branding>('/theme/branding', locale, 300),
  getMenu: (slot: string, locale: string) => serverFetch<Menu>(`/theme/menus?slot=${encodeURIComponent(slot)}`, locale, 300),
  getHomepage: (locale: string) => serverFetch<HomepageBlock[]>('/cms/homepage', locale, 60),
  getFeatureFlags: (locale: string) => serverFetch<FeatureFlag[]>('/feature-flags/public', locale, 60),
};

export const cmsApi = {
  getPage: (slug: string, locale: string) => serverFetch<CmsPagePayload>(`/cms/pages/${encodeURIComponent(slug)}`, locale, 120),
};

export const discoveryApi = {
  searchServices: (params: URLSearchParams, locale: string) =>
    serverFetch<{ items: ServiceSummary[]; meta: { total: number; per_page: number; current_page: number } }>(
      `/discovery/services?${params.toString()}`,
      locale,
      30,
    ),
  getServiceDetail: (publicId: string, locale: string) =>
    serverFetch<ServiceDetail>(`/catalog/services/${encodeURIComponent(publicId)}`, locale, 60),
};

export async function clientApi<T>(path: string, locale: string, init?: AxiosRequestConfig): Promise<T> {
  const res = await apiClient.request<Envelope<T>>({
    url: path,
    headers: { 'Accept-Language': locale, ...(init?.headers ?? {}) },
    ...init,
  });
  return res.data.data;
}

export const geographyApi = {
  governorates: (locale: string) => clientApi<Governorate[]>('/customer/governorates', locale),
  cities: (locale: string, governoratePublicId?: string) =>
    clientApi<City[]>(
      `/customer/cities${governoratePublicId ? `?governorate=${encodeURIComponent(governoratePublicId)}` : ''}`,
      locale,
    ),
};

export const catalogApi = {
  occasions: (locale: string) => clientApi<Occasion[]>('/customer/occasions', locale),
  categories: (locale: string) => clientApi<Category[]>('/customer/categories', locale),
};

export const bookingApi = {
  create: (locale: string, payload: CreateBookingDraftPayload) =>
    clientApi<Booking>('/customer/bookings', locale, { method: 'POST', data: payload }),
  show: (locale: string, publicId: string) =>
    clientApi<Booking>(`/customer/bookings/${publicId}`, locale),
  addItem: (locale: string, bookingPublicId: string, payload: AddBookingItemPayload) =>
    clientApi<BookingItem>(`/customer/bookings/${bookingPublicId}/items`, locale, {
      method: 'POST',
      data: payload,
    }),
  removeItem: (locale: string, bookingPublicId: string, itemPublicId: string) =>
    clientApi<{ removed: boolean }>(
      `/customer/bookings/${bookingPublicId}/items/${itemPublicId}`,
      locale,
      { method: 'DELETE' },
    ),
  submit: (locale: string, bookingPublicId: string, idempotencyKey: string) =>
    clientApi<Booking>(`/customer/bookings/${bookingPublicId}/submit`, locale, {
      method: 'POST',
      headers: { 'Idempotency-Key': idempotencyKey },
    }),
  listModifications: (locale: string, bookingPublicId: string) =>
    clientApi<BookingModification[]>(`/customer/bookings/${bookingPublicId}/modifications`, locale),
  decideModification: (
    locale: string,
    bookingPublicId: string,
    modificationPublicId: string,
    decision: 'accept' | 'reject',
    idempotencyKey: string,
  ) =>
    clientApi<Booking>(
      `/customer/bookings/${bookingPublicId}/modifications/${modificationPublicId}/decide`,
      locale,
      {
        method: 'POST',
        data: { decision },
        headers: { 'Idempotency-Key': idempotencyKey },
      },
    ),
};

export const paymentApi = {
  initiate: (locale: string, bookingPublicId: string, method: string, idempotencyKey: string) =>
    clientApi<PaymentInitiation>(`/customer/bookings/${bookingPublicId}/payments`, locale, {
      method: 'POST',
      data: { method },
      headers: { 'Idempotency-Key': idempotencyKey },
    }),
  show: (locale: string, paymentPublicId: string) =>
    clientApi<Payment>(`/customer/payments/${paymentPublicId}`, locale),
};
