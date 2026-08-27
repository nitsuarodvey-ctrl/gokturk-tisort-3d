import * as THREE from 'three';

export const DEBUG_DECALS = false;

export const PRINT_CONFIG = {
  chest: {
    position: { x: -0.17, y: 0.64, z: 0.2 },
    rotation: { x: 0, y: 0, z: 0 },
    scale: { width: 0.105, depth: 0.12 },
  },
  sleeve: {
    projection: 'uv',
    position: { x: 0.93, y: 0.755, z: -0.03 },
    rotation: {
      x: 0,
      y: Math.PI * 0.4,
    },
    uvRotation: THREE.MathUtils.degToRad(2),
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
