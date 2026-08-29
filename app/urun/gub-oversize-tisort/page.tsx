import type { Metadata } from 'next';
import { ProductExperience } from '../../../src/components/ProductExperience';

export const metadata: Metadata = {
  title: 'Siyah Oversize T-Shirt — GUB Merch',
  description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
  openGraph: {
    title: 'Siyah Oversize T-Shirt — GUB Merch',
    description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
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
    title: 'Siyah Oversize T-Shirt — GUB Merch',
    description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
    images: ['https://gokturk-tisort-3d.sirloliletisim.chatgpt.site/gub-tshirt-front.png'],
  },
};

export default function TShirtProductPage() {
  return <ProductExperience />;
}
