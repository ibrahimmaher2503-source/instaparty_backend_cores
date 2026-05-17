import createMiddleware from 'next-intl/middleware';
import { NextResponse, type NextRequest } from 'next/server';
import { locales, defaultLocale } from './i18n';

const intlMiddleware = createMiddleware({
  locales,
  defaultLocale,
  localePrefix: 'always',
});

const PROTECTED_PREFIXES = ['/me', '/checkout'];
const TOKEN_COOKIE = 'instaparty_token';

export default function middleware(req: NextRequest) {
  const { pathname, search } = req.nextUrl;

  const localeStripped = pathname.replace(/^\/(en|ar)/, '') || '/';
  const needsAuth = PROTECTED_PREFIXES.some((p) => localeStripped === p || localeStripped.startsWith(`${p}/`));

  if (needsAuth) {
    const hasToken = req.cookies.has(TOKEN_COOKIE);
    if (!hasToken) {
      const locale = pathname.match(/^\/(en|ar)/)?.[1] ?? defaultLocale;
      const url = req.nextUrl.clone();
      url.pathname = `/${locale}/auth/login`;
      url.searchParams.set('next', `${pathname}${search}`);
      return NextResponse.redirect(url);
    }
  }

  return intlMiddleware(req);
}

export const config = {
  matcher: ['/((?!api|_next|_vercel|.*\\..*).*)'],
};
