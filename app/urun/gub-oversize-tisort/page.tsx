import type { Metadata } from 'next';
import { ProductExperience } from '../../../src/components/ProductExperience';

export const metadata: Metadata = {
  title: 'Siyah Oversize T-Shirt — GUB Merch',
  description: 'Drop 001 siyah oversize tişört. Etkileşimli 360° ürün görünümü ve ön sipariş.',
};

export default function TShirtProductPage() {
  return <ProductExperience />;
}
