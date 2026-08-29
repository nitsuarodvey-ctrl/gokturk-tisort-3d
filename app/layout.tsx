import type { Metadata } from 'next';
import './globals.css';
import '../src/style.css';

export const metadata: Metadata = {
  title: 'GUB Merch — Resmî Mağaza',
  description:
    'Göktürk Ulusal Birliği resmî ürün mağazası. Sınırlı üretim giyim ve aksesuar koleksiyonları.',
  icons: {
    icon: '/ufaklogo.png',
  },
  openGraph: {
    title: 'GUB Merch — Resmî Mağaza',
    description:
      'Göktürk Ulusal Birliği sınırlı üretim giyim ve aksesuar koleksiyonları.',
    type: 'website',
  },
  twitter: {
    card: 'summary',
    title: 'GUB Merch — Resmî Mağaza',
    description:
      'Göktürk Ulusal Birliği sınırlı üretim ürünleri.',
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
