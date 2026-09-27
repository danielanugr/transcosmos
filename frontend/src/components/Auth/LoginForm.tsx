'use client';

import React, { useState } from 'react';
import { useAuth } from '@/lib/authContext';
import { useToast } from '../UI/Toast';
import { LogIn, KeyRound, Mail, UserCheck, Shield } from 'lucide-react';

export function LoginForm() {
  const { login } = useAuth();
  const { toast } = useToast();
  const [email, setEmail] = useState('alice@example.com');
  const [password, setPassword] = useState('password123');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password) {
      toast('Please enter both email and password.', 'error');
      return;
    }

    setLoading(true);
    try {
      await login(email, password);
      toast('Successfully authenticated.', 'success');
    } catch (err: any) {
      toast(err.message || 'Authentication failed. Please check credentials.', 'error');
    } finally {
      setLoading(false);
    }
  };

  const handleQuickFill = (demoEmail: string) => {
    setEmail(demoEmail);
    setPassword('password123');
  };

  return (
    <div className="w-full max-w-md mx-auto p-8 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl">
      <div className="text-center mb-8">
        <div className="inline-flex p-3 rounded-xl bg-slate-800 border border-slate-700 text-blue-400 mb-4">
          <Shield className="w-7 h-7" />
        </div>
        <h1 className="text-2xl font-bold text-slate-100 tracking-tight">Task Platform</h1>
        <p className="text-sm text-slate-400 mt-1">Sign in to manage tasks, collaborate, and upload assets</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-xs font-medium text-slate-300 mb-1.5" htmlFor="email-input">
            Email Address
          </label>
          <div className="relative">
            <Mail className="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              id="email-input"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full pl-10 pr-4 py-2.5 bg-slate-950/60 border border-slate-800 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition-all"
              placeholder="name@company.com"
            />
          </div>
        </div>

        <div>
          <label className="block text-xs font-medium text-slate-300 mb-1.5" htmlFor="password-input">
            Password
          </label>
          <div className="relative">
            <KeyRound className="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              id="password-input"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full pl-10 pr-4 py-2.5 bg-slate-950/60 border border-slate-800 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition-all"
              placeholder="••••••••"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-600/50 text-white rounded-lg text-sm font-semibold shadow-lg shadow-blue-600/20 transition-all active:scale-[0.99] cursor-pointer"
        >
          {loading ? (
            <div className="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin" />
          ) : (
            <>
              <LogIn className="w-4 h-4" />
              Sign In
            </>
          )}
        </button>
      </form>

      <div className="mt-8 pt-6 border-t border-slate-800/80">
        <p className="text-xs font-medium text-slate-400 mb-3 flex items-center gap-1.5">
          <UserCheck className="w-3.5 h-3.5 text-blue-400" />
          Quick Test Accounts:
        </p>
        <div className="grid grid-cols-2 gap-2 text-xs">
          <button
            type="button"
            onClick={() => handleQuickFill('alice@example.com')}
            className="p-2 text-left rounded-lg bg-slate-950/40 border border-slate-800 hover:border-slate-700 text-slate-300 transition-colors"
          >
            <div className="font-semibold text-slate-200">Admin (Alice)</div>
            <div className="text-[11px] text-slate-500 truncate">alice@example.com</div>
          </button>
          <button
            type="button"
            onClick={() => handleQuickFill('bob@example.com')}
            className="p-2 text-left rounded-lg bg-slate-950/40 border border-slate-800 hover:border-slate-700 text-slate-300 transition-colors"
          >
            <div className="font-semibold text-slate-200">Manager (Bob)</div>
            <div className="text-[11px] text-slate-500 truncate">bob@example.com</div>
          </button>
        </div>
      </div>
    </div>
  );
}
