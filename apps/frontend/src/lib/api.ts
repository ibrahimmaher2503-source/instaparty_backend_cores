import axios, { type AxiosRequestConfig } from 'axios';
import type {
  Branding,
  CmsPagePayload,
  Envelope,
  FeatureFlag,
  HomepageBlock,
  Menu,
  ServiceDetail,
  ServiceSummary,
  ThemeTokens,
} from '@/types/api';

const SERVER_BASE = process.env.API_BASE_URL ?? 'http://localhost:8000/api/v1';
const CLIENT_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000/api/v1';

export const apiClient = axios.create({
  baseURL: typeof window === 'undefined' ? SERVER_BASE : CLIENT_BASE,
  withCredentials: true,
  headers: { Accept: 'application/json' },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
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
