import type { Metadata } from 'next';
import { ProductExperience } from '../../../src/components/ProductExperience';

export const metadata: Metadata = {
  title: 'Siyah Oversize T-Shirt — GUB Merch',
  description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
  openGraph: {
    title: 'Siyah Oversize T-Shirt — GUB Merch',
    description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
    type: 'website',
    images: [],
  },
  twitter: {
    card: 'summary',
    title: 'Siyah Oversize T-Shirt — GUB Merch',
    description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
    images: [],
  },
};

export default function TShirtProductPage() {
  return <ProductExperience />;
}
