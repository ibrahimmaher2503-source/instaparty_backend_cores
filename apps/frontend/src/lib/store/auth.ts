'use client';

import { create } from 'zustand';
import type { AuthStatus, User } from '@/types/auth';

type AuthState = {
  user: User | null;
  status: AuthStatus;
  setUser: (user: User | null) => void;
  setStatus: (status: AuthStatus) => void;
  reset: () => void;
};

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  status: 'unknown',
  setUser: (user) => set({ user, status: user ? 'authenticated' : 'guest' }),
  setStatus: (status) => set({ status }),
  reset: () => set({ user: null, status: 'guest' }),
}));
