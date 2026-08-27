'use client';

import { useEffect, useRef } from 'react';

const sizes = [
  { size: 'S', chest: 54, length: 71 },
  { size: 'M', chest: 56, length: 72 },
  { size: 'L', chest: 58, length: 73 },
  { size: 'XL', chest: 60, length: 74 },
];

type Props = {
  open: boolean;
  onClose: () => void;
};

export function SizeGuideModal({ open, onClose }: Props) {
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    closeRef.current?.focus();
    const handleKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKey);
    return () => {
      document.removeEventListener('keydown', handleKey);
      previous?.focus();
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="modal-layer" role="presentation" onMouseDown={onClose}>
      <section
        className="size-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="size-guide-title"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">ÖLÇÜ REHBERİ</p>
            <h2 id="size-guide-title">Beden Tablosu</h2>
          </div>
          <button ref={closeRef} className="text-button close-button" onClick={onClose}>
            Kapat <span aria-hidden="true">×</span>
          </button>
        </div>

        <div className="size-table" role="table" aria-label="Tişört beden ölçüleri">
          <div className="size-table-row size-table-head" role="row">
            <span role="columnheader">Beden</span>
            <span role="columnheader">Göğüs</span>
            <span role="columnheader">Uzunluk</span>
          </div>
          {sizes.map((item) => (
            <div className="size-table-row" role="row" key={item.size}>
              <strong role="cell">{item.size}</strong>
              <span role="cell">{item.chest} cm</span>
              <span role="cell">{item.length} cm</span>
            </div>
          ))}
        </div>

        <p className="measurement-note">
          Ölçüler düz zeminde, koltuk altından koltuk altına alınmıştır. ±1–2 cm
          üretim farkı olabilir.
        </p>
      </section>
    </div>
  );
}
