import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Göktürk Ulusal Birliği — 3D Tişört',
  description:
    'Göktürk Ulusal Birliği siyah oversize tişörtünün etkileşimli 360° ürün görünümü.',
  icons: {
    icon: '/ufaklogo.png',
  },
  openGraph: {
    title: 'Göktürk Ulusal Birliği — 3D Tişört',
    description:
      'Siyah oversize tişörtün etkileşimli 360° ürün görünümü.',
    type: 'website',
  },
  twitter: {
    card: 'summary',
    title: 'Göktürk Ulusal Birliği — 3D Tişört',
    description:
      'Siyah oversize tişörtün etkileşimli 360° ürün görünümü.',
  },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="tr">
      <body>{children}</body>
    </html>
  );
}
