import type { Metadata } from 'next';
import './globals.css';
import { AuthProvider } from '@/lib/authContext';
import { ToastProvider } from '@/components/UI/Toast';

export const metadata: Metadata = {
  title: 'Task Management Platform',
  description: 'Full-stack task management platform with real-time updates and secure file handling',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" className="dark" suppressHydrationWarning>
      <body
        className="min-h-screen bg-slate-950 text-slate-100 antialiased font-sans"
        suppressHydrationWarning
      >
        <AuthProvider>
          <ToastProvider>
            {children}
          </ToastProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
