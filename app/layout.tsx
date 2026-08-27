import type { Metadata } from 'next';
import './globals.css';
import '../src/style.css';

export const metadata: Metadata = {
  title: 'GUB Merch — Siyah Oversize T-Shirt',
  description:
    'GUB Merch Drop 001 siyah oversize tişört ön sipariş sayfası ve etkileşimli 360° ürün görünümü.',
  icons: {
    icon: '/ufaklogo.png',
  },
  openGraph: {
    title: 'GUB Merch — Siyah Oversize T-Shirt',
    description:
      'Drop 001 siyah oversize tişört. Ön sipariş ve etkileşimli 360° ürün görünümü.',
    type: 'website',
  },
  twitter: {
    card: 'summary',
    title: 'GUB Merch — Siyah Oversize T-Shirt',
    description:
      'Drop 001 siyah oversize tişört ön siparişi.',
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
