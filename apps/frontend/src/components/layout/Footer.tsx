import Link from 'next/link';
import type { Branding, Menu, MenuItem } from '@/types/api';

function hrefFromItem(item: MenuItem, locale: string): string {
  switch (item.target_type) {
    case 'internal_path':
      return `/${locale}${item.target_value.startsWith('/') ? item.target_value : `/${item.target_value}`}`;
    case 'cms_page':
      return `/${locale}/p/${item.target_value}`;
    case 'category':
      return `/${locale}/c/${item.target_value}`;
    case 'occasion':
      return `/${locale}/o/${item.target_value}`;
    default:
      return item.target_value;
  }
}

export function Footer({ branding, primary, secondary }: { branding: Branding; primary: Menu; secondary: Menu }) {
  const year = new Date().getFullYear();

  return (
    <footer className="border-t border-neutral-200 bg-white mt-16">
      <div className="mx-auto max-w-7xl px-4 py-12 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div>
          <div className="font-heading font-bold text-primary-700 mb-2">{branding.site_name}</div>
          {branding.tagline ? <p className="text-neutral-500">{branding.tagline}</p> : null}
        </div>

        {[primary, secondary].map((menu) =>
          menu.items.length ? (
            <div key={menu.slot}>
              <div className="font-medium text-neutral-900 mb-3">{menu.name}</div>
              <ul className="space-y-2 text-neutral-600">
                {menu.items.map((item) => (
                  <li key={item.public_id}>
                    <Link href={hrefFromItem(item, menu.slot.includes('ar') ? 'ar' : 'en')} className="hover:text-primary-700 transition">
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ) : null,
        )}

        <div>
          <div className="font-medium text-neutral-900 mb-3">Contact</div>
          <ul className="space-y-2 text-neutral-600">
            {branding.support_email ? <li><a href={`mailto:${branding.support_email}`}>{branding.support_email}</a></li> : null}
            {branding.support_phone ? <li><a href={`tel:${branding.support_phone}`}>{branding.support_phone}</a></li> : null}
            {branding.whatsapp_number ? <li><a href={`https://wa.me/${branding.whatsapp_number.replace(/\D/g, '')}`} target="_blank" rel="noreferrer">WhatsApp</a></li> : null}
          </ul>
          <div className="mt-4 flex gap-3 text-neutral-500">
            {branding.social.instagram ? <a href={branding.social.instagram} target="_blank" rel="noreferrer">IG</a> : null}
            {branding.social.facebook ? <a href={branding.social.facebook} target="_blank" rel="noreferrer">FB</a> : null}
            {branding.social.tiktok ? <a href={branding.social.tiktok} target="_blank" rel="noreferrer">TT</a> : null}
            {branding.social.x ? <a href={branding.social.x} target="_blank" rel="noreferrer">X</a> : null}
            {branding.social.youtube ? <a href={branding.social.youtube} target="_blank" rel="noreferrer">YT</a> : null}
          </div>
        </div>
      </div>
      <div className="border-t border-neutral-100 py-4 text-center text-xs text-neutral-500">© {year} {branding.site_name}</div>
    </footer>
  );
}
