import { apiClient } from './api';
import type { Envelope } from '@/types/api';
import type { User } from '@/types/auth';

const TOKEN_COOKIE = 'instaparty_token';
const TOKEN_TTL_DAYS = 30;

export function readToken(): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${TOKEN_COOKIE}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export function writeToken(token: string): void {
  if (typeof document === 'undefined') return;
  const expires = new Date(Date.now() + TOKEN_TTL_DAYS * 24 * 60 * 60 * 1000).toUTCString();
  const secure = window.location.protocol === 'https:' ? '; Secure' : '';
  document.cookie = `${TOKEN_COOKIE}=${encodeURIComponent(token)}; Path=/; Expires=${expires}; SameSite=Lax${secure}`;
}

export function clearToken(): void {
  if (typeof document === 'undefined') return;
  document.cookie = `${TOKEN_COOKIE}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax`;
}

export type RegisterPayload = {
  name: string;
  phone_e164: string;
  email?: string;
  password: string;
  password_confirmation: string;
  preferred_locale?: 'en' | 'ar';
};

export type LoginPayload = {
  login: string;
  password: string;
  device_name?: string;
};

export type AuthLoginResponse = { user: User; token: string };

async function unwrap<T>(promise: Promise<{ data: Envelope<T> }>): Promise<T> {
  const res = await promise;
  return res.data.data;
}

export const authApi = {
  register: (payload: RegisterPayload) =>
    unwrap<User>(apiClient.post('/register/customer', payload)),

  sendOtp: (phone_e164: string) =>
    unwrap<{ sent: boolean }>(apiClient.post('/phone/otp/send', { phone_e164 })),

  verifyPhone: (phone_e164: string, code: string) =>
    unwrap<User>(apiClient.post('/phone/verify', { phone_e164, code })),

  login: (payload: LoginPayload) =>
    unwrap<AuthLoginResponse>(apiClient.post('/login', payload)),

  logout: () => unwrap<null>(apiClient.post('/logout')),

  me: () => unwrap<User>(apiClient.get('/customer/profile')),
};
