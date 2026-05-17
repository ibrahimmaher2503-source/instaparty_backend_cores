// Wire types matching the backend ApiResponse envelope: { data, meta, errors }

export type Envelope<T> = { data: T; meta: Record<string, unknown> | null; errors: unknown };

export type DesignTokens = {
  version: number;
  colors: {
    primary: Record<string, string>;
    secondary: Record<string, string>;
    accent: Record<string, string>;
    neutral: Record<string, string>;
    success: string;
    warning: string;
    danger: string;
    info: string;
  };
  typography: {
    fontFamilyBase: string;
    fontFamilyHeading: string;
    fontFamilyArabic: string;
    googleFontUrl?: string | null;
    scale: Record<string, string>;
    weight: Record<string, number>;
    lineHeight: Record<string, string>;
  };
  spacing: { scale: number[] };
  radius: Record<string, string>;
  shadow: Record<string, string>;
  mode: { supportsDarkMode: boolean; defaultMode: 'light' | 'dark' };
};

export type ThemeTokens = {
  public_id: string | null;
  name: string;
  tokens: DesignTokens;
  updated_at: string | null;
};

export type Branding = {
  public_id: string;
  site_name: string;
  tagline: string | null;
  address_line: string | null;
  support_email: string | null;
  support_phone: string | null;
  whatsapp_number: string | null;
  social: {
    instagram?: string;
    facebook?: string;
    tiktok?: string;
    x?: string;
    youtube?: string;
  };
  assets: {
    logo_light: string | null;
    logo_dark: string | null;
    favicon: string | null;
    og_image: string | null;
    app_store_badge: string | null;
    play_store_badge: string | null;
  };
  updated_at: string | null;
};

export type MenuItem = {
  public_id: string;
  label: string;
  target_type: 'internal_path' | 'external_url' | 'cms_page' | 'category' | 'occasion';
  target_value: string;
  icon: string | null;
  opens_in_new_tab: boolean;
  children: MenuItem[];
};

export type Menu = { slot: string; name: string; items: MenuItem[] };

export type HomepageBlock =
  | { public_id: string; block_type: 'hero_carousel'; name: string; position: number; payload: HeroCarouselPayload }
  | { public_id: string; block_type: 'featured_services'; name: string; position: number; payload: FeaturedListPayload }
  | { public_id: string; block_type: 'featured_occasions' | 'featured_categories'; name: string; position: number; payload: FeaturedListPayload }
  | { public_id: string; block_type: 'cta_banner'; name: string; position: number; payload: CtaBannerPayload }
  | { public_id: string; block_type: 'vendor_spotlight'; name: string; position: number; payload: VendorSpotlightPayload }
  | { public_id: string; block_type: 'text_image_split'; name: string; position: number; payload: TextImageSplitPayload }
  | { public_id: string; block_type: 'testimonials'; name: string; position: number; payload: TestimonialsPayload }
  | { public_id: string; block_type: 'loyalty_promo'; name: string; position: number; payload: LoyaltyPromoPayload };

export type HeroCarouselPayload = {
  slides: Array<{
    image_url: string;
    headline: string;
    sub?: string | null;
    cta_label?: string | null;
    cta_url?: string | null;
  }>;
};

export type FeaturedListPayload = {
  title: string;
  service_public_ids?: string[];
  public_ids?: string[];
};

export type CtaBannerPayload = {
  headline: string;
  cta_label: string;
  cta_url: string;
  image_url?: string | null;
};

export type VendorSpotlightPayload = {
  title: string;
  vendor_public_id: string;
};

export type TextImageSplitPayload = {
  headline: string;
  body: string;
  image_url: string;
  image_side: 'left' | 'right';
};

export type TestimonialsPayload = {
  title: string;
  items: Array<{ quote: string; author: string; avatar_url?: string | null }>;
};

export type LoyaltyPromoPayload = {
  headline: string;
  body?: string | null;
  cta_url?: string | null;
};

export type ProductType = 'rental' | 'sale' | 'digital';

export type ServiceSummary = {
  public_id: string;
  name: string;
  short_description: string | null;
  product_type: ProductType;
  category: { public_id: string; name: string } | null;
  vendor: { public_id: string; business_name: string } | null;
  cover_image_url: string | null;
  price_from_minor: number;
  currency: string;
  rating_avg: number | null;
  rating_count: number;
};

export type ServiceDetail = ServiceSummary & {
  description: string | null;
  gallery: string[];
  rental?: {
    requires_electricity: boolean;
    requires_outdoor_space: boolean;
    default_rental_duration_hours: number;
    setup_time_minutes: number;
    security_deposit_minor: number | null;
  };
  sale?: {
    is_perishable: boolean;
    is_made_to_order: boolean;
    lead_time_hours: number;
    stock_quantity: number | null;
  };
  digital?: {
    delivery_method: string;
    has_expiry: boolean;
    expiry_days_after_purchase: number | null;
    is_refundable_after_delivery: boolean;
  };
};

export type CmsPagePayload = {
  slug: string;
  title: string;
  body: string;
  meta_description: string | null;
  blocks: Array<Record<string, unknown>> | null;
  published_at: string | null;
};

export type FeatureFlag = { key: string; is_enabled: boolean; rollout_pct: number };

export type Governorate = { public_id: string; name: string; code: string | null };
export type City = {
  public_id: string;
  governorate_public_id: string | null;
  name: string;
  latitude: number | null;
  longitude: number | null;
};
export type Occasion = { public_id: string; name: string; slug: string };
export type Category = { public_id: string; name: string; slug: string };

export type BookingAddress = {
  city_id: string;
  address_line: string;
  building: string | null;
  floor: string | null;
  apartment: string | null;
  landmark: string | null;
  recipient_name: string;
  recipient_phone_e164: string;
};

export type BookingItem = {
  public_id: string;
  service_public_id: string;
  service_name: string;
  product_type: ProductType;
  quantity: number;
  unit_price_minor: number;
  total_minor: number;
  currency: string;
  effective_starts_at: string | null;
  effective_ends_at: string | null;
  item_status: string;
  customization_data: Record<string, unknown> | null;
};

export type BookingVendor = {
  public_id: string;
  vendor_public_id: string;
  vendor_name: string;
  sub_status: string;
  response_deadline: string | null;
  items: BookingItem[];
};

export type Booking = {
  public_id: string;
  reference_no: string;
  lifecycle_status: string;
  payment_status: string;
  fulfillment_status: string;
  event_starts_at: string | null;
  event_ends_at: string | null;
  guest_count: number | null;
  subtotal_minor: number;
  delivery_total_minor: number;
  discount_total_minor: number;
  total_minor: number;
  currency: string;
  requires_customer_approval: boolean;
  vendors?: BookingVendor[];
  address?: BookingAddress;
  hold_expires_at?: string | null;
};

export type BookingModification = {
  public_id: string;
  booking_vendor_public_id: string;
  status: string;
  reason: string | null;
  vendor_explanation: string | null;
  proposed_diff: Record<string, unknown>;
  decided_at: string | null;
  created_at: string;
};

export type PaymentInitiation = {
  payment_public_id: string;
  redirect_url: string;
  status: string;
};

export type Payment = {
  public_id: string;
  booking_public_id: string;
  gateway: string;
  status: string;
  amount_minor: number;
  amount_currency: string;
  paid_at: string | null;
};

export type CreateBookingDraftPayload = {
  occasion_id: string;
  event_starts_at: string;
  event_ends_at: string;
  guest_count?: number;
  theme?: { en?: string; ar?: string };
  celebrant_name?: string;
  celebrant_dob?: string;
  celebrant_gender?: 'male' | 'female' | 'other';
  address: {
    city_id: string;
    address_line: string;
    building?: string;
    floor?: string;
    apartment?: string;
    landmark?: string;
    recipient_name: string;
    recipient_phone_e164: string;
    latitude?: number;
    longitude?: number;
  };
};

export type AddBookingItemPayload = {
  service_id: string;
  quantity: number;
  effective_starts_at?: string;
  effective_ends_at?: string;
  customization_data?: Record<string, unknown>;
};
