import * as THREE from 'three';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

export function initViewer(container, stlUrl, options = {}) {
  const width = container.clientWidth;
  const height = container.clientHeight || 480;

  const scene = new THREE.Scene();
  scene.background = new THREE.Color(options.dark ? 0x1c1c22 : 0xf0f0f3);

  const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 5000);

  const renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
  renderer.setPixelRatio(window.devicePixelRatio);
  renderer.setSize(width, height);
  container.appendChild(renderer.domElement);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;

  scene.add(new THREE.AmbientLight(0xffffff, 0.6));
  const dir1 = new THREE.DirectionalLight(0xffffff, 0.8);
  dir1.position.set(1, 1, 1);
  scene.add(dir1);
  const dir2 = new THREE.DirectionalLight(0xffffff, 0.4);
  dir2.position.set(-1, -1, -1);
  scene.add(dir2);

  let mesh = null;
  let wireframe = false;

  const loader = new STLLoader();
  loader.load(stlUrl, (geometry) => {
    geometry.computeVertexNormals();
    geometry.center();

    const material = new THREE.MeshStandardMaterial({ color: 0x3a7bd5, metalness: 0.1, roughness: 0.7 });
    mesh = new THREE.Mesh(geometry, material);
    scene.add(mesh);

    const box = new THREE.Box3().setFromObject(mesh);
    const size = box.getSize(new THREE.Vector3()).length() || 1;
    const center = box.getCenter(new THREE.Vector3());

    camera.position.copy(center).add(new THREE.Vector3(size, size, size).multiplyScalar(0.7));
    camera.near = size / 100;
    camera.far = size * 100;
    camera.updateProjectionMatrix();
    controls.target.copy(center);
    controls.update();

    if (options.onLoaded) {
      // Render one frame first so the captured thumbnail isn't blank.
      renderer.render(scene, camera);
      options.onLoaded(renderer);
    }
  }, undefined, (err) => {
    if (options.onError) options.onError(err);
  });

  function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    const w = container.clientWidth;
    const h = container.clientHeight || 480;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
  });

  return {
    resetView(camPos) {
      controls.reset();
    },
    toggleWireframe() {
      wireframe = !wireframe;
      if (mesh) mesh.material.wireframe = wireframe;
      return wireframe;
    },
    toggleBackground() {
      const isDark = scene.background.getHex() === 0x1c1c22;
      scene.background.set(isDark ? 0xf0f0f3 : 0x1c1c22);
      return !isDark;
    },
  };
}

export function maybeUploadThumbnail(modelId, renderer) {
  const dataUrl = renderer.domElement.toDataURL('image/png');
  fetch(`/models/${modelId}/thumbnail`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken() },
    body: dataUrl,
  }).catch(() => {});
}
