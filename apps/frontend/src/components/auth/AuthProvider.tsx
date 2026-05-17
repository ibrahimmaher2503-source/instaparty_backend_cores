'use client';

import { useEffect, type ReactNode } from 'react';
import { authApi, readToken } from '@/lib/auth';
import { useAuthStore } from '@/lib/store/auth';

export function AuthProvider({ children }: { children: ReactNode }) {
  const setUser = useAuthStore((s) => s.setUser);
  const setStatus = useAuthStore((s) => s.setStatus);

  useEffect(() => {
    const token = readToken();
    if (!token) {
      setStatus('guest');
      return;
    }
    authApi
      .me()
      .then(setUser)
      .catch(() => setStatus('guest'));
  }, [setUser, setStatus]);

  return <>{children}</>;
}

export function useAuth() {
  return useAuthStore((s) => ({ user: s.user, status: s.status }));
}
