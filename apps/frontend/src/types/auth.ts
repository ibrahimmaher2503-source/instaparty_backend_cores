export type User = {
  public_id: string;
  name: string;
  phone_e164: string;
  email: string | null;
  phone_verified_at: string | null;
  preferred_locale: 'en' | 'ar';
  avatar_url: string | null;
};

export type AuthStatus = 'unknown' | 'authenticated' | 'guest';
