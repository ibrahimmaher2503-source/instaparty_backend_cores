'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { notificationApi, type NotificationPreferenceRow } from '@/lib/api';

export default function NotificationsPage() {
  const t = useTranslations('me.notifications');
  const params = useParams<{ locale: string }>();
  const locale = params?.locale ?? 'en';
  const [prefs, setPrefs] = useState<NotificationPreferenceRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState<string | null>(null);

  useEffect(() => {
    notificationApi.list(locale).then(setPrefs).finally(() => setLoading(false));
  }, [locale]);

  const toggle = async (pref: NotificationPreferenceRow) => {
    if (pref.is_system) return;
    const key = `${pref.channel}:${pref.event_category}`;
    setUpdating(key);
    try {
      const updated = await notificationApi.update(locale, pref.channel, pref.event_category, !pref.is_enabled);
      setPrefs((rows) =>
        rows.map((r) =>
          r.channel === pref.channel && r.event_category === pref.event_category ? updated : r,
        ),
      );
    } finally {
      setUpdating(null);
    }
  };

  const channels = Array.from(new Set(prefs.map((p) => p.channel)));

  return (
    <div>
      <h1 className="mb-4 font-heading text-2xl font-bold">{t('title')}</h1>
      {loading ? (
        <p className="text-sm text-neutral-500">{t('loading')}</p>
      ) : prefs.length === 0 ? (
        <p className="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-600">
          {t('empty')}
        </p>
      ) : (
        <div className="space-y-6">
          {channels.map((channel) => (
            <section key={channel} className="rounded-lg border border-neutral-200 bg-white p-4">
              <h2 className="mb-2 text-sm font-semibold capitalize">{channel}</h2>
              <ul className="divide-y divide-neutral-100 text-sm">
                {prefs
                  .filter((p) => p.channel === channel)
                  .map((p) => {
                    const key = `${p.channel}:${p.event_category}`;
                    return (
                      <li key={key} className="flex items-center justify-between py-2">
                        <span>
                          <span className="capitalize">{p.event_category.replace(/_/g, ' ')}</span>
                          {p.is_system && (
                            <span className="ms-2 text-xs text-neutral-500">{t('system')}</span>
                          )}
                        </span>
                        <button
                          type="button"
                          disabled={p.is_system || updating === key}
                          onClick={() => toggle(p)}
                          className={
                            p.is_enabled
                              ? 'rounded-full bg-primary-600 px-3 py-1 text-xs font-medium text-white'
                              : 'rounded-full bg-neutral-200 px-3 py-1 text-xs font-medium text-neutral-700'
                          }
                        >
                          {p.is_enabled ? t('on') : t('off')}
                        </button>
                      </li>
                    );
                  })}
              </ul>
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
