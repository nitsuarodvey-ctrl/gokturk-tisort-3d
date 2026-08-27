import * as THREE from 'three';

export const DEBUG_DECALS = false;

/**
 * Positions and sizes are normalized to the fitted garment bounds.
 * position.x/z use half-width/depth units, position.y runs bottom (0) to top (1).
 * rotation.x/y aim the print center; rotation.z rolls without mirroring.
 * Sleeve depth is intentionally absent because its print uses the garment UVs.
 */
export const PRINT_CONFIG = {
  chest: {
    position: { x: -0.17, y: 0.64, z: 0.2 },
    rotation: { x: 0, y: 0, z: 0 },
    scale: { width: 0.105, depth: 0.12 },
  },
  sleeve: {
    projection: 'uv',
    position: { x: 0.93, y: 0.74, z: -0.03 },
    rotation: {
      x: 0,
      y: Math.PI * 0.4,
      z: Math.PI * 0.08,
    },
    scale: {
      width: 0.195,
    },
  },
};

export const VIEW_CONFIG = {
  normalizedHeight: 2.4,
  camera: {
    fov: 31,
    near: 0.05,
    far: 100,
  },
  material: {
    color: new THREE.Color(0x090a0c),
    roughness: 0.91,
    metalness: 0,
  },
};
