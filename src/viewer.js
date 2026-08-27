import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { DecalGeometry } from 'three/addons/geometries/DecalGeometry.js';
import { DEBUG_DECALS, PRINT_CONFIG, VIEW_CONFIG } from './config.js';

const easeInOutCubic = (t) =>
  t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

export class TShirtViewer {
  constructor(container, callbacks = {}) {
    this.container = container;
    this.callbacks = callbacks;
    this.lastFrameAt = null;
    this.disposables = [];
    this.garmentMeshes = [];
    this.printMeshes = [];
    this.decalDebug = [];
    this.cameraTween = null;
    this.frameId = null;
    this.isRunning = false;
    this.userHasInteracted = false;
    this.settleFrames = 0;
    this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x0c0d10);

    this.camera = new THREE.PerspectiveCamera(
      VIEW_CONFIG.camera.fov,
      Math.max(container.clientWidth, 1) / Math.max(container.clientHeight, 1),
      VIEW_CONFIG.camera.near,
      VIEW_CONFIG.camera.far,
    );

    this.renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
    });
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.04;
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFShadowMap;
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setSize(container.clientWidth, container.clientHeight, false);
    this.renderer.domElement.setAttribute(
      'aria-label',
      'Etkileşimli siyah tişört ürün görünümü',
    );
    this.renderer.domElement.setAttribute('role', 'img');
    container.prepend(this.renderer.domElement);

    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.dampingFactor = 0.075;
    this.controls.enablePan = false;
    this.controls.rotateSpeed = 0.68;
    this.controls.zoomSpeed = 0.72;
    this.controls.autoRotate = !this.reducedMotion;
    this.controls.autoRotateSpeed = 0.38;
    this.controls.addEventListener('start', () => this.handleInteractionStart());
    this.controls.addEventListener('change', () => this.render());
    this.controls.addEventListener('end', () => {
      this.settleFrames = 42;
      this.startLoop();
    });

    this.addStudioLighting();

    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(container);
    this.loadModel();
  }

  addStudioLighting() {
    const hemi = new THREE.HemisphereLight(0xbac5d7, 0x15100d, 1.1);
    this.scene.add(hemi);

    const key = new THREE.DirectionalLight(0xfff4e8, 4.6);
    key.position.set(-3.4, 4.8, 4.6);
    key.castShadow = true;
    key.shadow.mapSize.set(2048, 2048);
    key.shadow.camera.near = 0.1;
    key.shadow.camera.far = 14;
    key.shadow.camera.left = -2.4;
    key.shadow.camera.right = 2.4;
    key.shadow.camera.top = 2.8;
    key.shadow.camera.bottom = -2.8;
    key.shadow.bias = -0.00025;
    this.scene.add(key);

    const fill = new THREE.DirectionalLight(0x9bb2d4, 1.65);
    fill.position.set(4.2, 1.3, 3.2);
    this.scene.add(fill);

    const rim = new THREE.DirectionalLight(0xdde7ff, 3.3);
    rim.position.set(1.7, 2.7, -4.8);
    this.scene.add(rim);
  }

  async loadModel() {
    const loader = new GLTFLoader();

    try {
      const gltf = await loader.loadAsync('/basic_t-shirt.glb', (event) => {
        if (event.total) {
          this.callbacks.onProgress?.(event.loaded / event.total);
        }
      });

      this.model = gltf.scene;
      this.inspectModel(gltf.scene);
      this.prepareGarment(gltf.scene);
      this.scene.add(gltf.scene);
      gltf.scene.updateMatrixWorld(true);

      this.bounds = new THREE.Box3().setFromObject(gltf.scene);
      this.size = this.bounds.getSize(new THREE.Vector3());
      this.center = this.bounds.getCenter(new THREE.Vector3());
      await this.addPrints();
      this.addGround();
      this.configureCamera();
      this.addDebugHelpers();

      this.callbacks.onReady?.();
      this.startLoop();
    } catch (error) {
      console.error('[T-shirt viewer] Model load failed:', error);
      this.callbacks.onError?.(error);
    }
  }

  inspectModel(model) {
    const meshes = [];
    model.traverse((child) => {
      if (!child.isMesh) return;
      child.geometry.computeBoundingBox();
      const localSize = child.geometry.boundingBox.getSize(new THREE.Vector3());
      meshes.push({
        name: child.name || '(unnamed mesh)',
        vertices: child.geometry.attributes.position?.count ?? 0,
        dimensions: localSize.toArray(),
        material: child.material?.name || '(unnamed material)',
      });
    });

    const rawBounds = new THREE.Box3().setFromObject(model);
    const rawSize = rawBounds.getSize(new THREE.Vector3());
    const rawCenter = rawBounds.getCenter(new THREE.Vector3());

    console.groupCollapsed('[T-shirt viewer] GLB inspection');
    console.table(meshes);
    console.log('Raw bounding box:', rawBounds.min.toArray(), rawBounds.max.toArray());
    console.log('Raw size:', rawSize.toArray());
    console.log('Raw center:', rawCenter.toArray());
    console.log(
      'Detected orientation: the GLB root matrix converts source Z-up to Y-up; front is viewer +Z.',
    );
    console.groupEnd();
  }

  prepareGarment(model) {
    // The supplied Sketchfab GLB already contains the Z-up to Y-up conversion
    // in its root node matrix. Preserve that authored transform.
    model.updateMatrixWorld(true);

    let bounds = new THREE.Box3().setFromObject(model);
    const initialSize = bounds.getSize(new THREE.Vector3());
    const scale = VIEW_CONFIG.normalizedHeight / initialSize.y;
    model.scale.setScalar(scale);
    model.updateMatrixWorld(true);

    bounds = new THREE.Box3().setFromObject(model);
    const center = bounds.getCenter(new THREE.Vector3());
    model.position.set(-center.x, -center.y, -center.z);
    model.updateMatrixWorld(true);

    let largestMesh = null;
    let largestVertices = -1;

    model.traverse((child) => {
      if (!child.isMesh) return;
      const source = Array.isArray(child.material) ? child.material[0] : child.material;
      const vertexCount = child.geometry.attributes.position?.count ?? 0;
      if (vertexCount > largestVertices) {
        largestVertices = vertexCount;
        largestMesh = child;
      }

      const cotton = new THREE.MeshPhysicalMaterial({
        color: VIEW_CONFIG.material.color,
        map: source?.map ?? null,
        normalMap: source?.normalMap ?? null,
        normalScale: new THREE.Vector2(0.7, 0.7),
        roughness: VIEW_CONFIG.material.roughness,
        metalness: VIEW_CONFIG.material.metalness,
        sheen: 0.16,
        sheenColor: new THREE.Color(0x222328),
        sheenRoughness: 0.88,
        side: THREE.DoubleSide,
      });

      if (cotton.map) cotton.map.colorSpace = THREE.SRGBColorSpace;
      child.material = cotton;
      source?.dispose?.();
      child.castShadow = true;
      child.receiveShadow = true;
      this.garmentMeshes.push(child);
      this.disposables.push(cotton);
    });

    this.mainGarmentMesh = largestMesh;
    console.info('[T-shirt viewer] Main garment mesh:', largestMesh?.name);
  }

  addGround() {
    const material = new THREE.ShadowMaterial({
      color: 0x000000,
      opacity: 0.34,
      transparent: true,
      depthWrite: false,
    });
    const geometry = new THREE.PlaneGeometry(12, 12);
    const ground = new THREE.Mesh(geometry, material);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = this.bounds.min.y - 0.025;
    ground.receiveShadow = true;
    this.scene.add(ground);
    this.disposables.push(geometry, material);
  }

  async addPrints() {
    if (!this.mainGarmentMesh) {
      throw new Error('No garment mesh was available for decal projection.');
    }

    const textureLoader = new THREE.TextureLoader();
    const [chestTexture, sleeveTexture] = await Promise.all([
      textureLoader.loadAsync('/ufaklogo.png'),
      textureLoader.loadAsync('/büyüklogo.png'),
    ]);

    const maxAnisotropy = this.renderer.capabilities.getMaxAnisotropy();
    [chestTexture, sleeveTexture].forEach((texture) => {
      texture.colorSpace = THREE.SRGBColorSpace;
      texture.anisotropy = Math.min(8, maxAnisotropy);
      texture.generateMipmaps = true;
      texture.needsUpdate = true;
      this.disposables.push(texture);
    });

    this.createPrint('chest', chestTexture, PRINT_CONFIG.chest);
    this.createSleeveUvPrint(sleeveTexture, PRINT_CONFIG.sleeve);
  }

  createSleeveUvPrint(texture, config) {
    const geometry = this.mainGarmentMesh.geometry;
    const uvAttribute = geometry.attributes.uv;
    const positionAttribute = geometry.attributes.position;
    if (!uvAttribute || !positionAttribute) {
      throw new Error('The garment has no UV coordinates for the sleeve print.');
    }

    // Reuse the tuned world-space controls only to locate the sleeve center.
    // The artwork itself is mapped through the model's authored UV island.
    const seed = new THREE.Vector3(
      this.center.x + config.position.x * (this.size.x * 0.5),
      this.bounds.min.y + config.position.y * this.size.y,
      this.center.z + config.position.z * (this.size.z * 0.5),
    );
    const configuredRotation = new THREE.Euler(
      config.rotation.x,
      config.rotation.y,
      config.rotation.z,
      'XYZ',
    );
    const outwardGuess = new THREE.Vector3(0, 0, 1)
      .applyEuler(configuredRotation)
      .normalize();
    const rayOrigin = seed
      .clone()
      .addScaledVector(outwardGuess, this.size.length() * 0.85);
    const raycaster = new THREE.Raycaster(rayOrigin, outwardGuess.clone().negate());
    const intersections = raycaster.intersectObject(this.mainGarmentMesh, false);

    if (!intersections.length || !intersections[0].uv || !intersections[0].face) {
      throw new Error('Could not locate the sleeve UV island on the garment.');
    }

    const hit = intersections[0];
    const normalMatrix = new THREE.Matrix3().getNormalMatrix(
      this.mainGarmentMesh.matrixWorld,
    );
    const surfaceNormal = hit.face.normal
      .clone()
      .applyMatrix3(normalMatrix)
      .normalize();
    const align = new THREE.Quaternion().setFromUnitVectors(
      new THREE.Vector3(0, 0, 1),
      surfaceNormal,
    );
    const roll = new THREE.Quaternion().setFromAxisAngle(
      surfaceNormal,
      config.rotation.z,
    );
    const printQuaternion = roll.multiply(align);
    const printXAxis = new THREE.Vector3(1, 0, 0).applyQuaternion(printQuaternion);
    const printYAxis = new THREE.Vector3(0, 1, 0).applyQuaternion(printQuaternion);

    // Derive the local UV-to-world Jacobian from the triangle under the print
    // center. It preserves the existing physical size and orientation while
    // allowing the authored sleeve unwrap to carry the PNG around the curve.
    const a = hit.face.a;
    const b = hit.face.b;
    const c = hit.face.c;
    const uvA = new THREE.Vector2().fromBufferAttribute(uvAttribute, a);
    const uvB = new THREE.Vector2().fromBufferAttribute(uvAttribute, b);
    const uvC = new THREE.Vector2().fromBufferAttribute(uvAttribute, c);
    const worldA = new THREE.Vector3()
      .fromBufferAttribute(positionAttribute, a)
      .applyMatrix4(this.mainGarmentMesh.matrixWorld);
    const worldB = new THREE.Vector3()
      .fromBufferAttribute(positionAttribute, b)
      .applyMatrix4(this.mainGarmentMesh.matrixWorld);
    const worldC = new THREE.Vector3()
      .fromBufferAttribute(positionAttribute, c)
      .applyMatrix4(this.mainGarmentMesh.matrixWorld);
    const deltaUv1 = uvB.clone().sub(uvA);
    const deltaUv2 = uvC.clone().sub(uvA);
    const determinant = deltaUv1.x * deltaUv2.y - deltaUv1.y * deltaUv2.x;

    if (Math.abs(determinant) < 1e-8) {
      throw new Error('The sleeve UV triangle is degenerate.');
    }

    const inverseDeterminant = 1 / determinant;
    const edge1 = worldB.clone().sub(worldA);
    const edge2 = worldC.clone().sub(worldA);
    const worldPerU = edge1
      .clone()
      .multiplyScalar(deltaUv2.y)
      .addScaledVector(edge2, -deltaUv1.y)
      .multiplyScalar(inverseDeterminant);
    const worldPerV = edge2
      .clone()
      .multiplyScalar(deltaUv1.x)
      .addScaledVector(edge1, -deltaUv2.x)
      .multiplyScalar(inverseDeterminant);

    const aspect = texture.image.width / texture.image.height;
    const width = this.size.x * config.scale.width;
    const height = width / aspect;
    const mapUFromU = worldPerU.dot(printXAxis) / width;
    const mapUFromV = worldPerV.dot(printXAxis) / width;
    const mapVFromU = worldPerU.dot(printYAxis) / height;
    const mapVFromV = worldPerV.dot(printYAxis) / height;
    const centerU = hit.uv.x;
    const centerV = hit.uv.y;

    texture.matrixAutoUpdate = false;
    texture.wrapS = THREE.ClampToEdgeWrapping;
    texture.wrapT = THREE.ClampToEdgeWrapping;
    texture.matrix.set(
      mapUFromU,
      mapUFromV,
      0.5 - mapUFromU * centerU - mapUFromV * centerV,
      mapVFromU,
      mapVFromV,
      0.5 - mapVFromU * centerU - mapVFromV * centerV,
      0,
      0,
      1,
    );

    const material = new THREE.MeshPhysicalMaterial({
      map: texture,
      color: 0xffffff,
      transparent: true,
      alphaTest: 0.015,
      depthTest: true,
      depthWrite: false,
      polygonOffset: true,
      polygonOffsetFactor: -4,
      polygonOffsetUnits: -4,
      roughness: 0.84,
      metalness: 0,
      sheen: 0.08,
      sheenColor: new THREE.Color(0x2b2022),
      sheenRoughness: 0.9,
      side: THREE.FrontSide,
    });

    // Sampling is limited to the PNG rectangle so clamped edge pixels cannot
    // leak onto any other UV region of the otherwise shared garment geometry.
    material.onBeforeCompile = (shader) => {
      shader.fragmentShader = shader.fragmentShader.replace(
        '#include <map_fragment>',
        `
          if (
            vMapUv.x < 0.0 || vMapUv.x > 1.0 ||
            vMapUv.y < 0.0 || vMapUv.y > 1.0
          ) discard;
          #include <map_fragment>
        `,
      );
    };
    material.customProgramCacheKey = () => 'sleeve-uv-print-v1';

    const print = new THREE.Mesh(geometry, material);
    print.name = 'sleeve-print-uv';
    print.renderOrder = 3;
    print.castShadow = false;
    print.receiveShadow = true;
    this.mainGarmentMesh.add(print);
    this.printMeshes.push(print);
    this.disposables.push(material);
    this.decalDebug.push({
      name: 'sleeve',
      position: hit.point.clone().addScaledVector(surfaceNormal, 0.0008),
      direction: surfaceNormal,
    });

    console.info(
      '[T-shirt viewer] sleeve UV print:',
      JSON.stringify({
        center: [centerU, centerV],
        physicalSize: [width, height],
        sourcePixels: [texture.image.width, texture.image.height],
      }),
    );
  }

  createPrint(name, texture, config) {
    const seed = new THREE.Vector3(
      this.center.x + config.position.x * (this.size.x * 0.5),
      this.bounds.min.y + config.position.y * this.size.y,
      this.center.z + config.position.z * (this.size.z * 0.5),
    );
    const configuredRotation = new THREE.Euler(
      config.rotation.x,
      config.rotation.y,
      config.rotation.z,
      'XYZ',
    );
    const outwardGuess = new THREE.Vector3(0, 0, 1)
      .applyEuler(configuredRotation)
      .normalize();
    const rayOrigin = seed
      .clone()
      .addScaledVector(outwardGuess, this.size.length() * 0.85);
    const raycaster = new THREE.Raycaster(rayOrigin, outwardGuess.clone().negate());
    const intersections = raycaster.intersectObject(this.mainGarmentMesh, false);

    if (!intersections.length) {
      throw new Error(`Could not project the ${name} print onto the garment.`);
    }

    const hit = intersections[0];
    const normalMatrix = new THREE.Matrix3().getNormalMatrix(
      this.mainGarmentMesh.matrixWorld,
    );
    const surfaceNormal = hit.face.normal
      .clone()
      .applyMatrix3(normalMatrix)
      .normalize();
    const position = hit.point.clone().addScaledVector(surfaceNormal, 0.0008);

    // Align local +Z to the fabric normal, then apply only the configured roll.
    // This keeps the PNG's left/right orientation intact and never mirrors it.
    const align = new THREE.Quaternion().setFromUnitVectors(
      new THREE.Vector3(0, 0, 1),
      surfaceNormal,
    );
    const roll = new THREE.Quaternion().setFromAxisAngle(
      surfaceNormal,
      config.rotation.z,
    );
    const orientation = new THREE.Euler().setFromQuaternion(
      roll.multiply(align),
      'XYZ',
    );

    const aspect = texture.image.width / texture.image.height;
    const width = this.size.x * config.scale.width;
    const height = width / aspect;
    const depth = this.size.z * config.scale.depth;
    const projectorSize = new THREE.Vector3(width, height, depth);
    const geometry = new DecalGeometry(
      this.mainGarmentMesh,
      position,
      orientation,
      projectorSize,
    );
    const material = new THREE.MeshStandardMaterial({
      map: texture,
      color: 0xffffff,
      transparent: true,
      alphaTest: 0.015,
      depthTest: true,
      depthWrite: false,
      polygonOffset: true,
      polygonOffsetFactor: -4,
      polygonOffsetUnits: -4,
      roughness: 0.84,
      metalness: 0,
      side: THREE.FrontSide,
    });
    const print = new THREE.Mesh(geometry, material);
    print.name = `${name}-print-decal`;
    print.renderOrder = 3;
    this.scene.add(print);
    this.printMeshes.push(print);
    this.disposables.push(geometry, material);
    this.decalDebug.push({ name, position, direction: surfaceNormal });

    console.info(
      `[T-shirt viewer] ${name} decal:`,
      JSON.stringify({
        position: position.toArray(),
        direction: surfaceNormal.toArray(),
        size: projectorSize.toArray(),
        sourcePixels: [texture.image.width, texture.image.height],
      }),
    );
  }

  configureCamera() {
    const verticalFov = THREE.MathUtils.degToRad(this.camera.fov);
    const fitHeightDistance = this.size.y / (2 * Math.tan(verticalFov / 2));
    const fitWidthDistance =
      this.size.x / (2 * Math.tan(verticalFov / 2) * this.camera.aspect);
    this.fitDistance = Math.max(fitHeightDistance, fitWidthDistance) * 1.2;
    this.controls.minDistance = Math.max(this.size.z * 1.7, this.fitDistance * 0.58);
    this.controls.maxDistance = this.fitDistance * 2.05;
    this.controls.target.set(0, this.size.y * 0.02, 0);
    this.viewPositions = {
      front: new THREE.Vector3(0, this.size.y * 0.02, this.fitDistance),
      back: new THREE.Vector3(0, this.size.y * 0.02, -this.fitDistance),
      sleeve: new THREE.Vector3(
        this.fitDistance * 0.9,
        this.size.y * 0.04,
        this.fitDistance * 0.47,
      ),
      reset: new THREE.Vector3(
        this.fitDistance * 0.43,
        this.size.y * 0.08,
        this.fitDistance * 0.92,
      ),
    };
    this.camera.position.copy(this.viewPositions.reset);
    this.camera.lookAt(this.controls.target);
    this.controls.update();
    this.render();
  }

  addDebugHelpers() {
    if (!DEBUG_DECALS) return;
    this.scene.add(new THREE.Box3Helper(this.bounds, 0x52d6ff));
    this.scene.add(new THREE.AxesHelper(1.4));
    this.decalDebug.forEach(({ name, position, direction }) => {
      const markerMaterial = new THREE.MeshBasicMaterial({ color: 0xff3b6b });
      const markerGeometry = new THREE.SphereGeometry(0.018, 12, 8);
      const marker = new THREE.Mesh(markerGeometry, markerMaterial);
      marker.name = `${name}-decal-center`;
      marker.position.copy(position);
      this.scene.add(marker);
      this.scene.add(
        new THREE.ArrowHelper(direction, position, 0.28, 0xffd166, 0.06, 0.035),
      );
      this.disposables.push(markerGeometry, markerMaterial);
    });
  }

  handleInteractionStart() {
    this.userHasInteracted = true;
    this.controls.autoRotate = false;
    this.cameraTween = null;
    this.startLoop();
  }

  goToView(name) {
    const destination = this.viewPositions?.[name];
    if (!destination) return;
    this.userHasInteracted = true;
    this.controls.autoRotate = false;
    const target = new THREE.Vector3(0, this.size.y * 0.02, 0);
    if (this.reducedMotion) {
      this.camera.position.copy(destination);
      this.controls.target.copy(target);
      this.controls.update();
      this.render();
      return;
    }
    this.cameraTween = {
      startedAt: performance.now(),
      duration: 760,
      from: this.camera.position.clone(),
      to: destination.clone(),
      targetFrom: this.controls.target.clone(),
      targetTo: target,
    };
    this.startLoop();
  }

  updateTween(now) {
    if (!this.cameraTween) return false;
    const elapsed = (now - this.cameraTween.startedAt) / this.cameraTween.duration;
    const t = Math.min(Math.max(elapsed, 0), 1);
    const eased = easeInOutCubic(t);
    this.camera.position.lerpVectors(this.cameraTween.from, this.cameraTween.to, eased);
    this.controls.target.lerpVectors(
      this.cameraTween.targetFrom,
      this.cameraTween.targetTo,
      eased,
    );
    if (t >= 1) this.cameraTween = null;
    return t < 1;
  }

  startLoop() {
    if (this.isRunning) return;
    this.isRunning = true;
    this.lastFrameAt = null;
    const tick = (now) => {
      const delta = this.lastFrameAt
        ? Math.min((now - this.lastFrameAt) / 1000, 0.05)
        : 1 / 60;
      this.lastFrameAt = now;
      const tweening = this.updateTween(now);
      const controlsChanged = this.controls.update(delta);
      this.render();

      if (this.settleFrames > 0) this.settleFrames -= 1;
      const keepRunning =
        this.controls.autoRotate || tweening || controlsChanged || this.settleFrames > 0;

      if (keepRunning) {
        this.frameId = requestAnimationFrame(tick);
      } else {
        this.isRunning = false;
        this.frameId = null;
        this.lastFrameAt = null;
      }
    };
    this.frameId = requestAnimationFrame(tick);
  }

  render() {
    this.renderer.render(this.scene, this.camera);
  }

  resize() {
    const width = Math.max(this.container.clientWidth, 1);
    const height = Math.max(this.container.clientHeight, 1);
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setSize(width, height, false);
    if (this.size) this.configureCameraDistancesOnly();
    this.render();
  }

  configureCameraDistancesOnly() {
    const verticalFov = THREE.MathUtils.degToRad(this.camera.fov);
    const heightDistance = this.size.y / (2 * Math.tan(verticalFov / 2));
    const widthDistance =
      this.size.x / (2 * Math.tan(verticalFov / 2) * this.camera.aspect);
    const nextFit = Math.max(heightDistance, widthDistance) * 1.2;
    const ratio = nextFit / this.fitDistance;
    this.camera.position
      .sub(this.controls.target)
      .multiplyScalar(ratio)
      .add(this.controls.target);
    Object.values(this.viewPositions).forEach((position) => position.multiplyScalar(ratio));
    this.fitDistance = nextFit;
    this.controls.minDistance = Math.max(this.size.z * 1.7, nextFit * 0.58);
    this.controls.maxDistance = nextFit * 2.05;
  }

  dispose() {
    this.resizeObserver?.disconnect();
    if (this.frameId) cancelAnimationFrame(this.frameId);
    this.controls?.dispose();
    this.garmentMeshes.forEach((mesh) => mesh.geometry?.dispose());
    this.printMeshes.forEach((mesh) => mesh.removeFromParent());
    this.disposables.forEach((resource) => resource?.dispose?.());
    this.renderer?.dispose();
    this.renderer?.domElement.remove();
  }
}
