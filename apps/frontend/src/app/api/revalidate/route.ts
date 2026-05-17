import { revalidatePath, revalidateTag } from 'next/cache';
import { NextResponse } from 'next/server';

export const dynamic = 'force-dynamic';

export async function POST(req: Request) {
  const secret = req.headers.get('X-Revalidate-Secret');
  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ ok: false, error: 'unauthorized' }, { status: 401 });
  }

  const body = (await req.json().catch(() => ({}))) as {
    reason?: string;
    paths?: string[];
    tags?: string[];
  };

  for (const path of body.paths ?? []) {
    revalidatePath(path, 'layout');
  }
  for (const tag of body.tags ?? ['theme', 'cms', 'menus']) {
    revalidateTag(tag);
  }

  return NextResponse.json({ ok: true, revalidated_at: new Date().toISOString(), reason: body.reason ?? null });
}
