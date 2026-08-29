import type { Metadata } from 'next';
import './globals.css';
import '../src/style.css';

export const metadata: Metadata = {
  metadataBase: new URL('https://gokturk-tisort-3d.sirloliletisim.chatgpt.site'),
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
    images: [
      {
        url: 'https://gokturk-tisort-3d.sirloliletisim.chatgpt.site/gub-tshirt-front.png',
        width: 933,
        height: 827,
        alt: 'Göktürk Ulusal Birliği siyah oversize tişört',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'GUB Merch — Resmî Mağaza',
    description:
      'Göktürk Ulusal Birliği sınırlı üretim ürünleri.',
    images: ['https://gokturk-tisort-3d.sirloliletisim.chatgpt.site/gub-tshirt-front.png'],
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
