(function () {
  'use strict';

  const DEG = Math.PI / 180;
  const controllers = [];

  // Living-globe tuning (theme 1.24.0). Idle motion stays inside the
  // event-driven render contract: frames self-schedule only while an
  // animation or the guarded idle spin is actually running.
  const IDLE_SPIN_YAW_PER_MS = 0.00006;          // ~0.0576deg per frame at 60fps
  const IDLE_SPIN_RESUME_DELAY_MS = 4000;        // resume ~4s after the last direct interaction
  const IDLE_MARKER_SYNC_INTERVAL_MS = 33;       // ~30fps marker declutter budget during idle spin
  const TOUR_START_DELAY_MS = 3000;              // auto-fly tour arms after ~3s of load idle
  const TOUR_RETRY_DELAY_MS = 1500;              // re-check cadence while the tour is blocked
  const TOUR_DEFAULT_DWELL_MS = 2600;            // pause on each destination between hops
  const TOUR_DEFAULT_HOP_DURATION_MS = 1500;     // camera travel time per hop
  const DOUBLE_TAP_WINDOW_MS = 300;              // two taps inside this window dive
  const DOUBLE_TAP_RADIUS_PX = 24;               // and inside this radius
  const DOUBLE_CLICK_DIVE_DISTANCE = 0.6;        // camera distance removed per dive
  const DOUBLE_CLICK_DIVE_DURATION_MS = 700;
  const MARKER_COLLISION_BUDGET = 60;            // front-hemisphere markers entering the collision pass per frame
  const NEAR_LOD_DISTANCE = 2.8;                 // closer than this: budgeted hub city labels join the layout
  const NEAR_LOD_HUB_LABEL_BUDGET = 12;          // labeled hubs per frame at near zoom

  function shouldReduceMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches || navigator.connection?.saveData === true;
  }

  function multiply4(a, b) {
    const out = new Float32Array(16);
    for (let column = 0; column < 4; column += 1) {
      for (let row = 0; row < 4; row += 1) {
        out[column * 4 + row] =
          a[row] * b[column * 4] +
          a[4 + row] * b[column * 4 + 1] +
          a[8 + row] * b[column * 4 + 2] +
          a[12 + row] * b[column * 4 + 3];
      }
    }
    return out;
  }

  function perspective4(fov, aspect, near, far) {
    const f = 1 / Math.tan(fov / 2);
    const range = 1 / (near - far);
    return new Float32Array([
      f / aspect, 0, 0, 0,
      0, f, 0, 0,
      0, 0, (near + far) * range, -1,
      0, 0, near * far * 2 * range, 0
    ]);
  }

  function translation4(z) {
    return new Float32Array([
      1, 0, 0, 0,
      0, 1, 0, 0,
      0, 0, 1, 0,
      0, 0, z, 1
    ]);
  }

  function rotationX4(angle) {
    const c = Math.cos(angle);
    const s = Math.sin(angle);
    return new Float32Array([
      1, 0, 0, 0,
      0, c, s, 0,
      0, -s, c, 0,
      0, 0, 0, 1
    ]);
  }

  function rotationY4(angle) {
    const c = Math.cos(angle);
    const s = Math.sin(angle);
    return new Float32Array([
      c, 0, -s, 0,
      0, 1, 0, 0,
      s, 0, c, 0,
      0, 0, 0, 1
    ]);
  }

  function compileShader(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
      const message = gl.getShaderInfoLog(shader) || 'Unknown WebGL shader error';
      gl.deleteShader(shader);
      throw new Error(message);
    }
    return shader;
  }

  function createProgram(gl) {
    const vertex = compileShader(gl, gl.VERTEX_SHADER, `
      attribute vec3 aPosition;
      attribute vec2 aUv;
      uniform mat4 uModel;
      uniform mat4 uMvp;
      varying vec2 vUv;
      varying vec3 vNormal;
      void main() {
        gl_Position = uMvp * vec4(aPosition, 1.0);
        vUv = aUv;
        vNormal = normalize(mat3(uModel) * aPosition);
      }
    `);
    const fragment = compileShader(gl, gl.FRAGMENT_SHADER, `
      precision mediump float;
      uniform sampler2D uTexture;
      varying vec2 vUv;
      varying vec3 vNormal;
      void main() {
        vec3 color = texture2D(uTexture, vUv).rgb;
        vec3 lightDirection = normalize(vec3(-0.45, 0.65, 0.72));
        float diffuse = max(dot(normalize(vNormal), lightDirection), 0.0);
        float light = 0.50 + diffuse * 0.58;
        float rim = pow(1.0 - max(vNormal.z, 0.0), 2.2) * 0.16;
        gl_FragColor = vec4(color * light + vec3(0.02, 0.10, 0.14) * rim, 1.0);
      }
    `);
    const program = gl.createProgram();
    gl.attachShader(program, vertex);
    gl.attachShader(program, fragment);
    gl.linkProgram(program);
    gl.deleteShader(vertex);
    gl.deleteShader(fragment);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
      const message = gl.getProgramInfoLog(program) || 'Unknown WebGL program error';
      gl.deleteProgram(program);
      throw new Error(message);
    }
    return program;
  }

  function createSphere(gl, latitudeSegments = 56, longitudeSegments = 88) {
    const vertices = [];
    const indices = [];
    for (let latitudeIndex = 0; latitudeIndex <= latitudeSegments; latitudeIndex += 1) {
      const v = latitudeIndex / latitudeSegments;
      const latitude = (0.5 - v) * Math.PI;
      const cosLatitude = Math.cos(latitude);
      const sinLatitude = Math.sin(latitude);
      for (let longitudeIndex = 0; longitudeIndex <= longitudeSegments; longitudeIndex += 1) {
        const u = longitudeIndex / longitudeSegments;
        const longitude = (u - 0.5) * Math.PI * 2;
        vertices.push(
          cosLatitude * Math.sin(longitude),
          sinLatitude,
          cosLatitude * Math.cos(longitude),
          u,
          1 - v
        );
      }
    }
    for (let latitudeIndex = 0; latitudeIndex < latitudeSegments; latitudeIndex += 1) {
      for (let longitudeIndex = 0; longitudeIndex < longitudeSegments; longitudeIndex += 1) {
        const first = latitudeIndex * (longitudeSegments + 1) + longitudeIndex;
        const second = first + longitudeSegments + 1;
        indices.push(first, second, first + 1, second, second + 1, first + 1);
      }
    }
    const vertexBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, vertexBuffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertices), gl.STATIC_DRAW);
    const indexBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, indexBuffer);
    gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(indices), gl.STATIC_DRAW);
    return { vertexBuffer, indexBuffer, count: indices.length };
  }

  function createTexture(gl) {
    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texImage2D(
      gl.TEXTURE_2D,
      0,
      gl.RGBA,
      1,
      1,
      0,
      gl.RGBA,
      gl.UNSIGNED_BYTE,
      new Uint8Array([8, 32, 43, 255])
    );
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    return texture;
  }

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  }

  function easeOutCubic(value) {
    return 1 - Math.pow(1 - value, 3);
  }

  function normalizeAngle(value) {
    let angle = value;
    while (angle > Math.PI) angle -= Math.PI * 2;
    while (angle < -Math.PI) angle += Math.PI * 2;
    return angle;
  }

  function shortestAngle(from, to) {
    return normalizeAngle(to - from);
  }

  function greatCircleDistanceKm(first, second) {
    const latitudeDelta = (second.latitude - first.latitude) * DEG;
    const longitudeDelta = (second.longitude - first.longitude) * DEG;
    const firstLatitude = first.latitude * DEG;
    const secondLatitude = second.latitude * DEG;
    const haversine = Math.sin(latitudeDelta / 2) ** 2 +
      Math.cos(firstLatitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(Math.max(0, 1 - haversine)));
  }

  function validGeographicCandidate(candidate) {
    return candidate && typeof candidate.id === 'string' && candidate.id.length > 0
      && Number.isFinite(Number(candidate.latitude)) && Number(candidate.latitude) >= -90 && Number(candidate.latitude) <= 90
      && Number.isFinite(Number(candidate.longitude)) && Number(candidate.longitude) >= -180 && Number(candidate.longitude) <= 180;
  }

  function resolveGeographicSelection(point, destinations = [], hubs = [], destinationRadiusKm = 100) {
    if (!point || !Number.isFinite(Number(point.latitude)) || !Number.isFinite(Number(point.longitude))) {
      return { selectionKind: 'map_point', supported: false, planningAction: 'identify_coordinate' };
    }
    const destinationCandidates = destinations
      .filter(validGeographicCandidate)
      .map(destination => ({ ...destination, distanceKm: greatCircleDistanceKm(point, destination) }))
      .sort((first, second) => first.distanceKm - second.distanceKm);
    const nearestDestination = destinationCandidates[0] || null;
    const normalizedDestinationRadiusKm = clamp(Number(destinationRadiusKm) || 100, 40, 5000);
    if (nearestDestination && nearestDestination.distanceKm <= normalizedDestinationRadiusKm) {
      return {
        selectionKind: 'destination',
        supported: true,
        planningAction: 'open_destination',
        destination: nearestDestination,
        distanceKm: nearestDestination.distanceKm,
        supportedRadiusKm: normalizedDestinationRadiusKm
      };
    }
    const matchingHub = hubs
      .filter(validGeographicCandidate)
      .map(hub => ({ ...hub, radiusKm: Number(hub.radiusKm), distanceKm: greatCircleDistanceKm(point, hub) }))
      .filter(hub => Number.isInteger(hub.radiusKm) && hub.radiusKm >= 40 && hub.radiusKm <= 750 && hub.distanceKm <= hub.radiusKm)
      .sort((first, second) => first.distanceKm - second.distanceKm || first.radiusKm - second.radiusKm)[0] || null;
    if (matchingHub) {
      return {
        selectionKind: 'exploration_hub',
        supported: true,
        planningAction: 'open_hub',
        hub: matchingHub,
        distanceKm: matchingHub.distanceKm,
        supportedRadiusKm: matchingHub.radiusKm,
        nearestDestination
      };
    }
    return {
      selectionKind: 'map_point',
      supported: false,
      planningAction: 'identify_coordinate',
      nearestDestination,
      distanceKm: nearestDestination ? nearestDestination.distanceKm : null,
      supportedRadiusKm: normalizedDestinationRadiusKm
    };
  }

  function globePointFromScreen(x, y, width, height, state) {
    if (!(width > 0) || !(height > 0)) return null;
    const field = 1 / Math.tan(40 * DEG / 2);
    const aspect = width / height;
    const ndcX = (x / width) * 2 - 1;
    const ndcY = 1 - (y / height) * 2;
    const ray = [ndcX * aspect / field, ndcY / field, -1];
    const rayLength = Math.hypot(...ray);
    const direction = ray.map(component => component / rayLength);
    const cameraToCenter = state.distance;
    const projection = direction[2] * cameraToCenter;
    const discriminant = projection ** 2 - (cameraToCenter ** 2 - 1);
    if (discriminant < 0) return null;
    const distanceAlongRay = -projection - Math.sqrt(discriminant);
    if (!(distanceAlongRay > 0)) return null;

    const xYaw = direction[0] * distanceAlongRay;
    const yPitch = direction[1] * distanceAlongRay;
    const zPitch = direction[2] * distanceAlongRay + state.distance;
    const cosPitch = Math.cos(state.pitch);
    const sinPitch = Math.sin(state.pitch);
    const yWorld = yPitch * cosPitch + zPitch * sinPitch;
    const zYaw = -yPitch * sinPitch + zPitch * cosPitch;
    const cosYaw = Math.cos(state.yaw);
    const sinYaw = Math.sin(state.yaw);
    const xWorld = xYaw * cosYaw - zYaw * sinYaw;
    const zWorld = xYaw * sinYaw + zYaw * cosYaw;
    return {
      latitude: Math.asin(clamp(yWorld, -1, 1)) / DEG,
      longitude: Math.atan2(xWorld, zWorld) / DEG
    };
  }

  function projectedPoint(latitude, longitude, state, width, height) {
    const latitudeRadians = latitude * DEG;
    const longitudeRadians = longitude * DEG;
    const cosLatitude = Math.cos(latitudeRadians);
    const x = cosLatitude * Math.sin(longitudeRadians);
    const y = Math.sin(latitudeRadians);
    const z = cosLatitude * Math.cos(longitudeRadians);
    const cosYaw = Math.cos(state.yaw);
    const sinYaw = Math.sin(state.yaw);
    const xYaw = x * cosYaw + z * sinYaw;
    const zYaw = -x * sinYaw + z * cosYaw;
    const cosPitch = Math.cos(state.pitch);
    const sinPitch = Math.sin(state.pitch);
    const yPitch = y * cosPitch - zYaw * sinPitch;
    const zPitch = y * sinPitch + zYaw * cosPitch;
    const field = 1 / Math.tan(40 * DEG / 2);
    const denominator = state.distance - zPitch;
    const xNdc = xYaw * field / denominator;
    const yNdc = yPitch * field / denominator;
    return {
      x: (xNdc * 0.5 + 0.5) * width,
      y: (-yNdc * 0.5 + 0.5) * height,
      depth: zPitch,
      visible: zPitch > 1 / state.distance && denominator > 0
    };
  }

  function boxesOverlap(a, b, padding = 5) {
    return !(
      a.right + padding <= b.left ||
      b.right + padding <= a.left ||
      a.bottom + padding <= b.top ||
      b.bottom + padding <= a.top
    );
  }

  function collisionFreeMarkerPlacement(candidate, placed, width, height) {
    const halfWidth = candidate.width / 2;
    const halfHeight = candidate.height / 2;
    const baseX = clamp(candidate.point.x, halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
    const baseY = clamp(candidate.point.y, halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
    const offsets = [[0, 0]];
    if (candidate.active || candidate.focused) {
      const maximumRadius = Math.min(240, Math.max(48, Math.min(width, height) / 2));
      for (let radius = 48; radius <= maximumRadius; radius += 48) {
        offsets.push(
          [0, -radius], [radius, 0], [0, radius], [-radius, 0],
          [radius, -radius], [radius, radius], [-radius, radius], [-radius, -radius]
        );
      }
    }
    for (const [offsetX, offsetY] of offsets) {
      const x = clamp(baseX + offsetX, halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
      const y = clamp(baseY + offsetY, halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
      const box = {
        left: x - halfWidth,
        right: x + halfWidth,
        top: y - halfHeight,
        bottom: y + halfHeight
      };
      if (!placed.some(existing => boxesOverlap(box, existing))) {
        return { x, y, box, displaced: Math.abs(x - candidate.point.x) > 1 || Math.abs(y - candidate.point.y) > 1 };
      }
    }
    return null;
  }

  function createController(root) {
    const canvas = root.querySelector('[data-globe-canvas]');
    const routePath = root.querySelector('[data-globe-route]');
    const selectionMarker = root.querySelector('[data-globe-selection-point]');
    const liveRegion = root.querySelector('[data-globe-live]');
    if (!canvas) return null;

    const gl = canvas.getContext('webgl', {
      alpha: true,
      antialias: true,
      depth: true,
      powerPreference: 'high-performance',
      preserveDrawingBuffer: false
    });
    if (!gl) {
      root.classList.add('globe-3d-unavailable');
      if (liveRegion) liveRegion.textContent = 'תצוגת מפת העולם החלופית פעילה';
      return null;
    }

    let program;
    let sphere;
    let texture;
    try {
      program = createProgram(gl);
      sphere = createSphere(gl);
      texture = createTexture(gl);
    } catch (error) {
      root.classList.add('globe-3d-unavailable');
      if (liveRegion) liveRegion.textContent = 'תצוגת מפת העולם החלופית פעילה';
      console.warn('Tra-Vel globe could not initialize.', error);
      return null;
    }

    const locations = {
      position: gl.getAttribLocation(program, 'aPosition'),
      uv: gl.getAttribLocation(program, 'aUv'),
      model: gl.getUniformLocation(program, 'uModel'),
      mvp: gl.getUniformLocation(program, 'uMvp'),
      texture: gl.getUniformLocation(program, 'uTexture')
    };
    const state = {
      yaw: 0,
      pitch: 12 * DEG,
      distance: 3.15,
      selected: root.querySelector('.price-pin.is-active')?.dataset.destination || '',
      selectedHub: root.querySelector('.exploration-hub.is-active')?.dataset.explorationHub || '',
      selectedPoint: null,
      available: new Set(Array.from(root.querySelectorAll('.price-pin[data-destination]'), marker => marker.dataset.destination)),
      availableHubs: new Set(Array.from(root.querySelectorAll('.exploration-hub[data-exploration-hub]'), marker => marker.dataset.explorationHub)),
      visible: true,
      animation: null,
      frame: 0,
      pointer: null,
      routeTimer: 0,
      textureReady: false,
      failed: false,
      suppressPinActivationUntil: 0,
      lastMarkerSyncAt: 0,
      lastTap: null,
      suppressDiveUntil: 0,
      idleSpin: { lastTick: 0, resumeAt: 0, resumeTimer: 0 },
      tour: {
        active: false,
        cancelled: false,
        hopping: false,
        ids: [],
        index: 0,
        timer: 0,
        autoTimer: 0,
        dwell: TOUR_DEFAULT_DWELL_MS,
        duration: TOUR_DEFAULT_HOP_DURATION_MS,
        suspendedUntil: 0
      }
    };
    const origin = {
      latitude: Number(root.dataset.originLatitude || 32.0005),
      longitude: Number(root.dataset.originLongitude || 34.8708)
    };

    gl.enable(gl.DEPTH_TEST);
    gl.enable(gl.CULL_FACE);
    gl.cullFace(gl.BACK);
    gl.clearColor(0, 0, 0, 0);

    function markers() {
      return Array.from(root.querySelectorAll('.price-pin[data-destination]'));
    }

    function hubMarkers() {
      return Array.from(root.querySelectorAll('.exploration-hub[data-exploration-hub]'));
    }

    function activateStaticFallback(error = null) {
      state.failed = true;
      state.textureReady = false;
      state.animation = null;
      state.pointer = null;
      stopTour(true);
      if (state.idleSpin.resumeTimer) window.clearTimeout(state.idleSpin.resumeTimer);
      state.idleSpin.resumeTimer = 0;
      if (state.frame) window.cancelAnimationFrame(state.frame);
      state.frame = 0;
      root.classList.remove('is-webgl-ready', 'is-dragging', 'is-routing');
      root.classList.add('globe-3d-unavailable');
      if (routePath) routePath.setAttribute('d', '');
      if (liveRegion) liveRegion.textContent = 'תצוגת המפה החלופית פעילה';
      if (error) console.warn('Tra-Vel globe switched to its static fallback.', error);
    }

    function resize() {
      const rectangle = root.getBoundingClientRect();
      const ratio = Math.min(window.devicePixelRatio || 1, 1.75);
      const width = Math.max(1, Math.round(rectangle.width * ratio));
      const height = Math.max(1, Math.round(rectangle.height * ratio));
      if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
        canvas.style.width = `${rectangle.width}px`;
        canvas.style.height = `${rectangle.height}px`;
      }
      gl.viewport(0, 0, width, height);
      return { width: rectangle.width, height: rectangle.height };
    }

    function updateRoute(width, height, projected) {
      if (!routePath || (!state.selected && !state.selectedPoint)) return;
      const destination = state.selectedPoint
        ? projectedPoint(state.selectedPoint.latitude, state.selectedPoint.longitude, state, width, height)
        : projected.get(state.selected);
      const start = projectedPoint(origin.latitude, origin.longitude, state, width, height);
      if (!destination?.visible || !start.visible) {
        routePath.setAttribute('d', '');
        return;
      }
      const middleX = (start.x + destination.x) / 2;
      const middleY = Math.min(start.y, destination.y) - Math.max(26, Math.abs(start.x - destination.x) * 0.16);
      routePath.setAttribute(
        'd',
        `M ${start.x.toFixed(1)} ${start.y.toFixed(1)} Q ${middleX.toFixed(1)} ${middleY.toFixed(1)} ${destination.x.toFixed(1)} ${destination.y.toFixed(1)}`
      );
    }

    function updateMarkers(width, height) {
      const mobile = window.matchMedia('(max-width: 1000px)').matches;
      const homeGlobe = Boolean(root.closest('.home-globe-stack'));
      // Distance level of detail: far away the layout keeps destination price
      // pins plus hub dots; closer, a bounded set of front hubs gains its city
      // label. CSS reads the level from data-globe-lod.
      const nearLod = state.distance <= NEAR_LOD_DISTANCE;
      const lodLevel = nearLod ? 'near' : 'far';
      if (root.dataset.globeLod !== lodLevel) root.dataset.globeLod = lodLevel;
      const projected = new Map();
      const candidates = [];
      markers().forEach(marker => {
        if (!state.available.has(marker.dataset.destination)) {
          marker.hidden = true;
          return;
        }
        const latitude = Number(marker.dataset.latitude);
        const longitude = Number(marker.dataset.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
          marker.hidden = true;
          return;
        }
        const point = projectedPoint(latitude, longitude, state, width, height);
        projected.set(marker.dataset.destination, point);
        if (!point.visible) {
          marker.hidden = true;
          return;
        }
        const active = marker.dataset.destination === state.selected;
        const focused = document.activeElement === marker;
        const markerWidth = mobile && !active ? 44 : Math.min(112, Math.max(48, marker.textContent.trim().length * 9 + 22));
        const markerHeight = mobile ? 44 : 34;
        const collisionMarkerHeight = homeGlobe ? 44 : markerHeight;
        candidates.push({ marker, point, active, focused, width: markerWidth, height: collisionMarkerHeight, kind: 'destination', priority: 3 });
      });

      hubMarkers().forEach(marker => {
        const hubId = marker.dataset.explorationHub || '';
        if (!state.availableHubs.has(hubId)) {
          marker.hidden = true;
          return;
        }
        const latitude = Number(marker.dataset.latitude);
        const longitude = Number(marker.dataset.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
          marker.hidden = true;
          return;
        }
        const point = projectedPoint(latitude, longitude, state, width, height);
        if (!point.visible) {
          marker.hidden = true;
          return;
        }
        const active = hubId === state.selectedHub;
        const focused = document.activeElement === marker;
        marker.setAttribute('aria-pressed', String(active));
        const labelLength = String(marker.dataset.city || marker.textContent || '').trim().length;
        const markerWidth = active || focused ? Math.min(126, Math.max(72, labelLength * 9 + 30)) : 44;
		const markerHeight = active || focused ? 88 : 44;
		candidates.push({ marker, point, active, focused, width: markerWidth, height: markerHeight, kind: 'hub', priority: 1, labelLength });
      });

      candidates.sort((a, b) => Number(b.focused) - Number(a.focused) || Number(b.active) - Number(a.active) || b.priority - a.priority || b.point.depth - a.point.depth);
      // Marker budget: only the highest-priority front-hemisphere markers run
      // the O(n^2) collision pass each frame; anything beyond the budget stays
      // hidden until the camera brings it forward.
      let lodLabelSlots = nearLod ? NEAR_LOD_HUB_LABEL_BUDGET : 0;
      const placed = [];
      candidates.forEach((candidate, candidateIndex) => {
		if (candidateIndex >= MARKER_COLLISION_BUDGET && !candidate.active && !candidate.focused) {
			candidate.marker.hidden = true;
			return;
		}
		if (candidate.kind === 'hub') {
			const lodLabeled = !candidate.active && !candidate.focused && lodLabelSlots > 0;
			if (lodLabeled) {
				lodLabelSlots -= 1;
				candidate.width = Math.max(candidate.width, Math.min(126, Math.max(72, candidate.labelLength * 9 + 30)));
				candidate.height = Math.max(candidate.height, 88);
			}
			candidate.lodLabeled = lodLabeled;
		}
		let placement = collisionFreeMarkerPlacement(candidate, placed, width, height);
		if (!placement && (candidate.active || candidate.focused)) {
			const halfWidth = candidate.width / 2;
			const halfHeight = candidate.height / 2;
			const x = clamp(candidate.point.x, halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
			const y = clamp(candidate.point.y, halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
			placement = {
				x,
				y,
				box: { left: x - halfWidth, right: x + halfWidth, top: y - halfHeight, bottom: y + halfHeight },
				displaced: false,
				forced: true
			};
		}
		candidate.marker.hidden = !placement;
		if (!placement) return;
		candidate.marker.style.left = `${placement.x}px`;
		candidate.marker.style.top = `${placement.y}px`;
		candidate.marker.dataset.collisionDisplaced = String(placement.displaced);
		candidate.marker.dataset.collisionForced = String(Boolean(placement.forced));
		candidate.marker.style.setProperty('--globe-depth', String(clamp(0.86 + candidate.point.depth * 0.17, 0.82, 1.05)));
		if (candidate.kind === 'hub') {
			candidate.marker.setAttribute('aria-pressed', String(candidate.active));
			const labelState = candidate.active || candidate.focused || candidate.lodLabeled ? 'visible' : 'dot';
			if (candidate.marker.dataset.globeLabel !== labelState) candidate.marker.dataset.globeLabel = labelState;
		}
		placed.push(placement.box);
      });
      const originMarker = root.querySelector('[data-globe-origin]');
      if (originMarker) {
        const start = projectedPoint(origin.latitude, origin.longitude, state, width, height);
        originMarker.hidden = !start.visible;
        if (start.visible) {
          originMarker.style.left = `${start.x}px`;
          originMarker.style.top = `${start.y}px`;
        }
      }
      if (selectionMarker) {
        const selection = state.selectedPoint
          ? projectedPoint(state.selectedPoint.latitude, state.selectedPoint.longitude, state, width, height)
          : null;
        selectionMarker.hidden = !selection?.visible;
        if (selection?.visible) {
          selectionMarker.style.left = `${selection.x}px`;
          selectionMarker.style.top = `${selection.y}px`;
          selectionMarker.style.setProperty('--globe-depth', String(clamp(0.88 + selection.depth * 0.14, 0.84, 1.04)));
        }
      }
      updateRoute(width, height, projected);
    }

    function draw(timestamp) {
      state.frame = 0;
      if (!state.visible || state.failed) return;
      try {
        if (state.animation) {
          const elapsed = timestamp - state.animation.started;
          const progress = clamp(elapsed / state.animation.duration, 0, 1);
          const eased = easeOutCubic(progress);
          state.yaw = normalizeAngle(state.animation.fromYaw + state.animation.deltaYaw * eased);
          state.pitch = state.animation.fromPitch + (state.animation.toPitch - state.animation.fromPitch) * eased;
          state.distance = state.animation.fromDistance + (state.animation.toDistance - state.animation.fromDistance) * eased;
          if (progress >= 1) state.animation = null;
        }

        let idleSpinning = false;
        if (!state.animation && idleSpinEligible(timestamp)) {
          const step = state.idleSpin.lastTick > 0 ? Math.min(timestamp - state.idleSpin.lastTick, 64) : 16.7;
          state.yaw = normalizeAngle(state.yaw + IDLE_SPIN_YAW_PER_MS * step);
          state.idleSpin.lastTick = timestamp;
          idleSpinning = true;
        } else {
          state.idleSpin.lastTick = 0;
        }

        if (gl.isContextLost()) throw new Error('WebGL context is unavailable.');
        const dimensions = resize();
        const model = multiply4(rotationX4(state.pitch), rotationY4(state.yaw));
        const viewModel = multiply4(translation4(-state.distance), model);
        const projection = perspective4(40 * DEG, canvas.width / canvas.height, 0.1, 20);
        const mvp = multiply4(projection, viewModel);

        gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
        gl.useProgram(program);
        gl.bindBuffer(gl.ARRAY_BUFFER, sphere.vertexBuffer);
        gl.enableVertexAttribArray(locations.position);
        gl.vertexAttribPointer(locations.position, 3, gl.FLOAT, false, 20, 0);
        gl.enableVertexAttribArray(locations.uv);
        gl.vertexAttribPointer(locations.uv, 2, gl.FLOAT, false, 20, 12);
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, sphere.indexBuffer);
        gl.uniformMatrix4fv(locations.model, false, model);
        gl.uniformMatrix4fv(locations.mvp, false, mvp);
        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.uniform1i(locations.texture, 0);
        gl.drawElements(gl.TRIANGLES, sphere.count, gl.UNSIGNED_SHORT, 0);
        const renderError = gl.getError();
        if (renderError !== gl.NO_ERROR) throw new Error(`WebGL render error ${renderError}.`);
        // Idle spin moves slowly, so marker declutter can run at ~30fps while
        // the sphere itself stays at full frame rate. Interactions and camera
        // animations keep the full-rate marker path.
        if (!idleSpinning || timestamp - state.lastMarkerSyncAt >= IDLE_MARKER_SYNC_INTERVAL_MS) {
          updateMarkers(dimensions.width, dimensions.height);
          state.lastMarkerSyncAt = timestamp;
        }

        if (state.textureReady) root.classList.add('is-webgl-ready');
        if (state.animation || idleSpinning) requestRender();
      } catch (error) {
        activateStaticFallback(error);
      }
    }

    function requestRender() {
      if (!state.failed && !state.frame) state.frame = window.requestAnimationFrame(draw);
    }

    function animateTo(yaw, pitch, distance = state.distance, duration = 680, rotations = 0) {
      if (shouldReduceMotion()) {
        state.yaw = normalizeAngle(yaw);
        state.pitch = clamp(pitch, -70 * DEG, 70 * DEG);
        state.distance = clamp(distance, 2.25, 4.8);
        state.animation = null;
        requestRender();
        return;
      }
      state.animation = {
        fromYaw: state.yaw,
        deltaYaw: shortestAngle(state.yaw, yaw) + Math.PI * 2 * clamp(Number(rotations) || 0, -2, 2),
        toPitch: clamp(pitch, -70 * DEG, 70 * DEG),
        fromPitch: state.pitch,
        fromDistance: state.distance,
        toDistance: clamp(distance, 2.25, 4.8),
        duration,
        started: performance.now()
      };
      requestRender();
    }

    function pulseRoute() {
      root.classList.remove('is-routing');
      if (state.routeTimer) window.clearTimeout(state.routeTimer);
      if (!shouldReduceMotion()) {
        void root.offsetWidth;
        root.classList.add('is-routing');
        state.routeTimer = window.setTimeout(() => root.classList.remove('is-routing'), 920);
      }
    }

    function cancelMotion() {
      state.animation = null;
      if (state.routeTimer) window.clearTimeout(state.routeTimer);
      state.routeTimer = 0;
      root.classList.remove('is-routing');
      suspendTour(IDLE_SPIN_RESUME_DELAY_MS);
      requestRender();
      return true;
    }

    // --- Guarded idle motion (theme 1.24.0) -------------------------------
    // The slow ambient spin runs only while every guard holds: the globe is
    // inside the viewport, the tab is visible, no pointer is down, no camera
    // animation or tour dwell is in progress, and the visitor has not asked
    // for reduced motion. Any direct interaction pauses it for ~4s.
    function idleSpinEligible(now) {
      return !state.failed
        && state.visible
        && document.visibilityState === 'visible'
        && !state.pointer
        && !state.animation
        && !state.tour.active
        && now >= state.idleSpin.resumeAt
        && !shouldReduceMotion();
    }

    function scheduleIdleSpinResume() {
      if (state.idleSpin.resumeTimer) window.clearTimeout(state.idleSpin.resumeTimer);
      state.idleSpin.resumeTimer = window.setTimeout(() => {
        state.idleSpin.resumeTimer = 0;
        requestRender();
      }, IDLE_SPIN_RESUME_DELAY_MS + 60);
    }

    // A direct gesture on the globe pauses the idle spin for ~4s and cancels
    // the auto-fly tour permanently for this page view.
    function noteDirectInteraction() {
      state.idleSpin.resumeAt = performance.now() + IDLE_SPIN_RESUME_DELAY_MS;
      scheduleIdleSpinResume();
      stopTour(true);
    }

    // --- Auto-fly tour (theme 1.24.0) --------------------------------------
    // Programmatic camera work from the page (reveal previews, hydration
    // focus, zoom fallbacks) defers the next hop instead of fighting it.
    function suspendTour(milliseconds) {
      state.tour.suspendedUntil = Math.max(state.tour.suspendedUntil, performance.now() + milliseconds);
    }

    function stopTour(permanent = false) {
      if (permanent) state.tour.cancelled = true;
      if (state.tour.autoTimer) window.clearTimeout(state.tour.autoTimer);
      state.tour.autoTimer = 0;
      if (state.tour.timer) window.clearTimeout(state.tour.timer);
      state.tour.timer = 0;
      state.tour.active = false;
      return true;
    }

    function scheduleTourHop(delay) {
      if (state.tour.timer) window.clearTimeout(state.tour.timer);
      state.tour.timer = window.setTimeout(tourHop, delay);
    }

    function tourHop() {
      state.tour.timer = 0;
      if (!state.tour.active) return;
      if (state.tour.cancelled || state.failed || root.classList.contains('globe-3d-unavailable') || shouldReduceMotion()) {
        stopTour(true);
        return;
      }
      if (!state.visible || document.visibilityState !== 'visible') {
        scheduleTourHop(TOUR_RETRY_DELAY_MS);
        return;
      }
      const now = performance.now();
      if (state.pointer || state.animation || now < state.tour.suspendedUntil) {
        scheduleTourHop(Math.max(400, state.tour.suspendedUntil - now));
        return;
      }
      const ids = state.tour.ids.filter(id => state.available.has(id));
      if (ids.length < 2) {
        stopTour(false);
        return;
      }
      const id = ids[state.tour.index % ids.length];
      state.tour.index += 1;
      state.tour.hopping = true;
      const focused = focusDestination(id, {
        animate: true,
        pulse: true,
        announce: false,
        rotations: 0,
        duration: state.tour.duration
      });
      state.tour.hopping = false;
      if (!focused) {
        scheduleTourHop(TOUR_RETRY_DELAY_MS);
        return;
      }
      scheduleTourHop(state.tour.duration + state.tour.dwell);
    }

    function startTour(ids = null, options = {}) {
      if (state.tour.cancelled || state.failed || root.classList.contains('globe-3d-unavailable') || shouldReduceMotion()) return false;
      const requested = Array.isArray(ids) && ids.length
        ? ids.map(id => String(id))
        : markers().map(marker => marker.dataset.destination || '');
      const availableIds = requested.filter(id => state.available.has(id));
      if (availableIds.length < 2) return false;
      state.tour.ids = availableIds;
      state.tour.dwell = clamp(Number(options.dwell) || TOUR_DEFAULT_DWELL_MS, 800, 20000);
      state.tour.duration = clamp(Number(options.duration) || TOUR_DEFAULT_HOP_DURATION_MS, 180, 3200);
      const selectedIndex = availableIds.indexOf(state.selected);
      state.tour.index = selectedIndex >= 0 ? selectedIndex + 1 : 0;
      state.tour.active = true;
      scheduleTourHop(Math.max(0, Number(options.delay) || 0));
      return true;
    }

    // Google-Earth style dive: double-click or double-tap re-centers on the
    // struck coordinate and steps the camera closer. Zoom stays limited to
    // buttons, double activation, and the existing pinch path; the globe
    // never binds wheel or scroll listeners.
    function diveToScreenPoint(clientX, clientY) {
      if (root.classList.contains('globe-3d-unavailable')) return false;
      const rectangle = root.getBoundingClientRect();
      const point = globePointFromScreen(clientX - rectangle.left, clientY - rectangle.top, rectangle.width, rectangle.height, state);
      if (!point) return false;
      state.visible = document.visibilityState !== 'hidden';
      animateTo(
        -point.longitude * DEG,
        point.latitude * DEG,
        clamp(state.distance - DOUBLE_CLICK_DIVE_DISTANCE, 2.25, 4.8),
        DOUBLE_CLICK_DIVE_DURATION_MS
      );
      return true;
    }

    function focusDestination(id, options = true) {
      if (root.classList.contains('globe-3d-unavailable')) return false;
      if (!state.tour.hopping) suspendTour(IDLE_SPIN_RESUME_DELAY_MS);
      const animate = typeof options === 'object' ? options.animate !== false : Boolean(options);
      const pulse = typeof options === 'object' ? options.pulse === true : Boolean(options);
      const announce = typeof options === 'object' ? options.announce !== false : true;
      const rotations = typeof options === 'object' ? clamp(Number(options.rotations) || 0, -2, 2) : 0;
      const duration = typeof options === 'object' ? clamp(Number(options.duration) || 680, 180, 3200) : 680;
      const marker = root.querySelector(`.price-pin[data-destination="${CSS.escape(id)}"]`);
      if (!marker) return false;
      const latitude = Number(marker.dataset.latitude);
      const longitude = Number(marker.dataset.longitude);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
      if (!animate) state.animation = null;
      state.selected = id;
      state.selectedHub = '';
      if (pulse) pulseRoute();
      markers().forEach(item => {
        const active = item.dataset.destination === id;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      hubMarkers().forEach(item => {
        item.classList.remove('is-active');
        item.setAttribute('aria-pressed', 'false');
      });
      const targetYaw = -longitude * DEG;
      const targetPitch = latitude * DEG;
      if (animate) animateTo(targetYaw, targetPitch, Math.min(state.distance, 3.05), duration, rotations);
      else {
        state.yaw = normalizeAngle(targetYaw);
        state.pitch = clamp(targetPitch, -70 * DEG, 70 * DEG);
        requestRender();
      }
      if (liveRegion && announce) liveRegion.textContent = `${marker.getAttribute('aria-label') || marker.textContent.trim()} במרכז הגלובוס`;
      return true;
    }

    function hubFromMarker(marker) {
      if (!marker) return null;
      const id = String(marker.dataset.explorationHub || '');
      const city = String(marker.dataset.city || '').trim();
      const country = String(marker.dataset.country || '').trim();
      const latitude = Number(marker.dataset.latitude);
      const longitude = Number(marker.dataset.longitude);
      const radiusKm = Number(marker.dataset.radiusKm);
      const iataSearchCode = String(marker.dataset.iataSearchCode || '').trim().toUpperCase();
      const liveSearchScopes = String(marker.dataset.liveSearchScopes || '').split(',').filter(Boolean);
      if (!/^[a-z0-9-]{2,60}$/.test(id) || !city || !country
        || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
        || !Number.isFinite(longitude) || longitude < -180 || longitude > 180
        || !Number.isInteger(radiusKm) || radiusKm < 40 || radiusKm > 750
        || (iataSearchCode && !/^[A-Z]{3}$/.test(iataSearchCode))) return null;
      return { id, city, country, latitude, longitude, radiusKm, iataSearchCode, liveSearchScopes };
    }

    function publishSelection(detail, announcement) {
      state.selectedPoint = { latitude: detail.latitude, longitude: detail.longitude };
      if (selectionMarker) {
        selectionMarker.hidden = false;
        selectionMarker.classList.remove('is-new');
        if (!shouldReduceMotion()) {
          void selectionMarker.offsetWidth;
          selectionMarker.classList.add('is-new');
        }
      }
      requestRender();
      root.dispatchEvent(new CustomEvent('travelglobe:select', { bubbles: true, detail }));
      if (liveRegion) liveRegion.textContent = announcement;
      return true;
    }

    function selectHubMarker(marker, inputType = 'pointer') {
      const hub = hubFromMarker(marker);
      if (!hub || !state.availableHubs.has(hub.id)) return false;
      state.selected = '';
      state.selectedHub = hub.id;
      markers().forEach(item => {
        item.classList.remove('is-active');
        item.setAttribute('aria-pressed', 'false');
      });
      hubMarkers().forEach(item => {
        const active = item === marker;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      return publishSelection({
        latitude: Number(hub.latitude.toFixed(4)),
        longitude: Number(hub.longitude.toFixed(4)),
        inputType,
        supported: true,
        supportedRadiusKm: hub.radiusKm,
        selectionKind: 'exploration_hub',
        planningAction: 'open_hub',
        hubId: hub.id,
        hubCity: hub.city,
        hubCountry: hub.country,
        hubIataSearchCode: hub.iataSearchCode,
        hubLiveSearchScopes: hub.liveSearchScopes,
        hubDistanceKm: 0,
        nearestDestination: '',
        nearestLabel: '',
        distanceKm: 0
      }, `בחרתם את ${hub.city}, ${hub.country}. כל חלקי החופשה נפתחו לחיפוש חי מתחת למפה.`);
    }

    function focusHub(id, options = true) {
      const marker = root.querySelector(`.exploration-hub[data-exploration-hub="${CSS.escape(id)}"]`);
      const hub = hubFromMarker(marker);
      if (!hub || !state.availableHubs.has(hub.id)) return false;
      const unavailable = root.classList.contains('globe-3d-unavailable');
      suspendTour(IDLE_SPIN_RESUME_DELAY_MS);
      const animate = !unavailable && (typeof options === 'object' ? options.animate !== false : Boolean(options));
      const announce = typeof options === 'object' ? options.announce !== false : true;
      state.selected = '';
      state.selectedHub = hub.id;
      state.selectedPoint = { latitude: hub.latitude, longitude: hub.longitude };
      markers().forEach(item => {
        item.classList.remove('is-active');
        item.setAttribute('aria-pressed', 'false');
      });
      hubMarkers().forEach(item => {
        const active = item === marker;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      const targetYaw = -hub.longitude * DEG;
      const targetPitch = hub.latitude * DEG;
      if (animate) animateTo(targetYaw, targetPitch, Math.min(state.distance, 3.05), 680);
      else {
        state.animation = null;
        state.yaw = normalizeAngle(targetYaw);
        state.pitch = clamp(targetPitch, -70 * DEG, 70 * DEG);
        requestRender();
      }
      if (liveRegion && announce) liveRegion.textContent = `${hub.city}, ${hub.country} במרכז הגלובוס`;
      return true;
    }

    function selectScreenPoint(clientX, clientY, inputType = 'pointer') {
      if (!root.matches('[data-discovery-globe]')) {
        if (liveRegion) liveRegion.textContent = 'לבחירת נקודה חופשית, פתחו את מפת החופשות המלאה.';
        return false;
      }
      const rectangle = root.getBoundingClientRect();
      const point = globePointFromScreen(clientX - rectangle.left, clientY - rectangle.top, rectangle.width, rectangle.height, state);
      if (!point) {
        if (liveRegion) liveRegion.textContent = 'הנקודה מחוץ לכדור הארץ. נסו לבחור בתוך הגלובוס.';
        return false;
      }
      const destinationCandidates = markers()
        .filter(marker => state.available.has(marker.dataset.destination))
        .map(marker => ({
          id: marker.dataset.destination,
          label: marker.getAttribute('aria-label') || marker.textContent.trim(),
          latitude: Number(marker.dataset.latitude),
          longitude: Number(marker.dataset.longitude)
        }))
        .filter(marker => Number.isFinite(marker.latitude) && Number.isFinite(marker.longitude));
      const explorationCandidates = hubMarkers()
        .filter(marker => state.availableHubs.has(marker.dataset.explorationHub))
        .map(hubFromMarker)
        .filter(Boolean);
      const supportedRadiusKm = clamp(Number(root.dataset.supportedRadiusKm || 100), 100, 5000);
      const resolution = resolveGeographicSelection(point, destinationCandidates, explorationCandidates, supportedRadiusKm);
      const nearest = resolution.destination || resolution.nearestDestination || null;
      const hub = resolution.hub || null;
      const supported = resolution.supported;
      const detail = {
        latitude: Number(point.latitude.toFixed(4)),
        longitude: Number(point.longitude.toFixed(4)),
        inputType,
        supported,
        supportedRadiusKm: resolution.supportedRadiusKm,
        selectionKind: resolution.selectionKind,
        planningAction: resolution.planningAction,
        nearestDestination: resolution.selectionKind === 'destination' ? (nearest?.id || '') : '',
        nearestLabel: resolution.selectionKind === 'destination' ? (nearest?.label || '') : '',
        distanceKm: Number.isFinite(resolution.distanceKm) ? Math.round(resolution.distanceKm) : null,
        hubId: hub?.id || '',
        hubCity: hub?.city || '',
        hubCountry: hub?.country || '',
        hubIataSearchCode: hub?.iataSearchCode || '',
        hubLiveSearchScopes: hub?.liveSearchScopes || [],
        hubDistanceKm: hub ? Math.round(hub.distanceKm) : null
      };
      state.selected = resolution.selectionKind === 'destination' ? (nearest?.id || '') : '';
      state.selectedHub = resolution.selectionKind === 'exploration_hub' ? (hub?.id || '') : '';
      markers().forEach(item => {
        const active = detail.nearestDestination && item.dataset.destination === detail.nearestDestination;
        item.classList.toggle('is-active', Boolean(active));
        item.setAttribute('aria-pressed', String(Boolean(active)));
      });
      hubMarkers().forEach(item => {
        const active = detail.hubId && item.dataset.explorationHub === detail.hubId;
        item.classList.toggle('is-active', Boolean(active));
        item.setAttribute('aria-pressed', String(Boolean(active)));
      });
      state.selectedPoint = { latitude: detail.latitude, longitude: detail.longitude };
      if (selectionMarker) {
        selectionMarker.hidden = false;
        selectionMarker.classList.remove('is-new');
        if (!shouldReduceMotion()) {
          void selectionMarker.offsetWidth;
          selectionMarker.classList.add('is-new');
        }
      }
      requestRender();
      root.dispatchEvent(new CustomEvent('travelglobe:select', { bubbles: true, detail }));
      if (liveRegion && resolution.selectionKind === 'exploration_hub') {
        liveRegion.textContent = `האזור זוהה כ${hub.city}, ${hub.country}. תוכנית 360 מעלות נפתחה לחיפוש חי מתחת למפה.`;
      }
      if (liveRegion && resolution.selectionKind !== 'exploration_hub') {
        liveRegion.textContent = supported
          ? `בחרתם ב${nearest.label}. פרטי היעד מופיעים מתחת למפה.`
          : 'הנקודה נשמרה. אפשר לזהות את האזור ולפתוח ממנו תכנון חופשה מלא.';
      }
      return true;
    }

    function zoom(direction) {
      if (root.classList.contains('globe-3d-unavailable')) return false;
      noteDirectInteraction();
      state.visible = document.visibilityState !== 'hidden';
      const change = direction === 'in' ? -0.32 : 0.32;
      animateTo(state.yaw, state.pitch, clamp(state.distance + change, 2.25, 4.8), 260);
      return true;
    }

    function setDestinations(data) {
      state.available = new Set(Object.keys(data || {}));
      markers().forEach(marker => {
        const item = data?.[marker.dataset.destination];
        marker.hidden = !item;
        if (!item) {
          marker.removeAttribute('data-latitude');
          marker.removeAttribute('data-longitude');
          return;
        }
        if (Number.isFinite(Number(item.latitude))) marker.dataset.latitude = String(item.latitude);
        if (Number.isFinite(Number(item.longitude))) marker.dataset.longitude = String(item.longitude);
      });
      if (state.selected && !state.available.has(state.selected)) clearSelection({ preservePoint: true });
      requestRender();
    }

    function setExplorationHubs(data) {
      state.availableHubs = new Set(Object.keys(data || {}));
      hubMarkers().forEach(marker => {
        const item = data?.[marker.dataset.explorationHub];
        marker.hidden = !item;
        if (!item) return;
        if (Number.isFinite(Number(item.latitude))) marker.dataset.latitude = String(item.latitude);
        if (Number.isFinite(Number(item.longitude))) marker.dataset.longitude = String(item.longitude);
        if (Number.isInteger(Number(item.radiusKm))) marker.dataset.radiusKm = String(item.radiusKm);
      });
      if (state.selectedHub && !state.availableHubs.has(state.selectedHub)) clearSelection({ preservePoint: true });
      requestRender();
    }

    function clearSelection({ preservePoint = false } = {}) {
      state.selected = '';
      state.selectedHub = '';
      state.animation = null;
      suspendTour(IDLE_SPIN_RESUME_DELAY_MS);
      if (!preservePoint) state.selectedPoint = null;
      markers().forEach(marker => {
        marker.classList.remove('is-active');
        marker.setAttribute('aria-pressed', 'false');
      });
      hubMarkers().forEach(marker => {
        marker.classList.remove('is-active');
        marker.setAttribute('aria-pressed', 'false');
      });
      if (selectionMarker && !preservePoint) {
        selectionMarker.hidden = true;
        selectionMarker.classList.remove('is-new');
      }
      if (routePath) routePath.setAttribute('d', '');
      requestRender();
    }

    const image = new Image();
    image.decoding = 'async';
    image.addEventListener('load', () => {
      if (state.failed) return;
      try {
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, image);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.REPEAT);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.generateMipmap(gl.TEXTURE_2D);
        const textureError = gl.getError();
        if (textureError !== gl.NO_ERROR) throw new Error(`WebGL texture error ${textureError}.`);
        state.textureReady = true;
        requestRender();
        root.dispatchEvent(new CustomEvent('travelglobe:ready', { bubbles: true }));
      } catch (error) {
        activateStaticFallback(error);
      }
    }, { once: true });
    image.addEventListener('error', () => {
      activateStaticFallback(new Error('Earth texture failed to load.'));
    }, { once: true });
    image.src = root.dataset.texture;

    markers().forEach(marker => {
      marker.addEventListener('focus', requestRender);
      marker.addEventListener('blur', requestRender);
    });
    hubMarkers().forEach(marker => {
      marker.addEventListener('focus', requestRender);
      marker.addEventListener('blur', requestRender);
    });

    root.addEventListener('pointerdown', event => {
      noteDirectInteraction();
      if (!event.isPrimary || event.button !== 0) return;
      state.visible = document.visibilityState !== 'hidden';
      state.pointer = {
        id: event.pointerId,
        type: event.pointerType || 'mouse',
        x: event.clientX,
        y: event.clientY,
        startX: event.clientX,
        startY: event.clientY,
        mode: 'pending',
        moved: false,
        startedOnPin: Boolean(event.target.closest('.price-pin')),
        startedOnHub: Boolean(event.target.closest('[data-exploration-hub]')),
        startedAt: performance.now()
      };
    });
    root.addEventListener('click', event => {
      const pin = event.target.closest('.price-pin');
      const hub = event.target.closest('[data-exploration-hub]');
      if (!pin && !hub) return;
      noteDirectInteraction();
      if (performance.now() < state.suppressPinActivationUntil) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
      }
      if (hub) {
        if (selectHubMarker(hub, event.detail === 0 ? 'keyboard' : 'pointer')) event.preventDefault();
        return;
      }
      state.selectedPoint = null;
      if (selectionMarker) {
        selectionMarker.hidden = true;
        selectionMarker.classList.remove('is-new');
      }
    }, true);
    root.addEventListener('pointermove', event => {
      if (!state.pointer || state.pointer.id !== event.pointerId) return;
      const totalX = event.clientX - state.pointer.startX;
      const totalY = event.clientY - state.pointer.startY;
      const distance = Math.hypot(totalX, totalY);
      if (state.pointer.mode === 'pending' && distance < 8) return;
      if (state.pointer.mode === 'pending') {
        state.pointer.moved = true;
        if (state.pointer.type === 'touch') {
          const absoluteX = Math.abs(totalX);
          const absoluteY = Math.abs(totalY);
          if (absoluteY >= absoluteX) {
            state.pointer.mode = 'scroll';
            return;
          }
          if (absoluteX < absoluteY * 1.25) return;
        }
        state.pointer.mode = 'drag';
        state.animation = null;
        root.classList.add('is-dragging');
        root.setPointerCapture(event.pointerId);
      }
      if (state.pointer.mode !== 'drag') return;
      const deltaX = event.clientX - state.pointer.x;
      const deltaY = event.clientY - state.pointer.y;
      state.pointer.x = event.clientX;
      state.pointer.y = event.clientY;
      state.yaw = normalizeAngle(state.yaw + deltaX * 0.0062);
      state.pitch = clamp(state.pitch + deltaY * 0.0045, -70 * DEG, 70 * DEG);
      requestRender();
    });
    const endPointer = event => {
      if (!state.pointer || state.pointer.id !== event.pointerId) return;
      const pointer = state.pointer;
      state.pointer = null;
      root.classList.remove('is-dragging');
      if (root.hasPointerCapture(event.pointerId)) root.releasePointerCapture(event.pointerId);
      if (pointer.moved && pointer.startedOnPin) state.suppressPinActivationUntil = performance.now() + 500;
      if (pointer.moved && pointer.startedOnHub) state.suppressPinActivationUntil = performance.now() + 500;
      state.idleSpin.resumeAt = performance.now() + IDLE_SPIN_RESUME_DELAY_MS;
      scheduleIdleSpinResume();
      if (event.type === 'pointerup' && !pointer.moved && !pointer.startedOnPin && !pointer.startedOnHub) {
        const previousTap = state.lastTap;
        const tap = { at: performance.now(), x: event.clientX, y: event.clientY, touch: pointer.type === 'touch' };
        state.lastTap = tap;
        if (previousTap
          && tap.at - previousTap.at <= DOUBLE_TAP_WINDOW_MS
          && Math.hypot(tap.x - previousTap.x, tap.y - previousTap.y) <= DOUBLE_TAP_RADIUS_PX) {
          state.lastTap = null;
          if (tap.touch) {
            // Second touch tap dives directly; a synthetic dblclick that some
            // browsers still emit for the same gesture is swallowed below.
            state.suppressDiveUntil = tap.at + 420;
            diveToScreenPoint(event.clientX, event.clientY);
          }
          // The second half of a double activation never re-selects the point.
          return;
        }
      }
      if (event.type === 'pointerup' && !pointer.moved && !pointer.startedOnPin && !pointer.startedOnHub && performance.now() - pointer.startedAt < 700) {
        selectScreenPoint(event.clientX, event.clientY, 'pointer');
      }
    };
    root.addEventListener('pointerup', endPointer);
    root.addEventListener('pointercancel', endPointer);
    root.addEventListener('lostpointercapture', endPointer);
    root.addEventListener('dblclick', event => {
      if (event.target.closest('.price-pin,[data-exploration-hub]')) return;
      noteDirectInteraction();
      if (performance.now() < state.suppressDiveUntil) {
        event.preventDefault();
        return;
      }
      if (diveToScreenPoint(event.clientX, event.clientY)) event.preventDefault();
    });
    // Focus moving into the globe (root or any marker) is a direct engagement:
    // it permanently hands camera control back to the visitor for this view.
    root.addEventListener('focusin', () => {
      noteDirectInteraction();
    });
    root.addEventListener('keydown', event => {
      if (event.target.closest('.price-pin,[data-exploration-hub]')) return;
      noteDirectInteraction();
      state.visible = document.visibilityState !== 'hidden';
      const step = event.shiftKey ? 18 * DEG : 8 * DEG;
      if (event.key === 'ArrowLeft') {
        state.animation = null;
        state.yaw = normalizeAngle(state.yaw - step);
      } else if (event.key === 'ArrowRight') {
        state.animation = null;
        state.yaw = normalizeAngle(state.yaw + step);
      } else if (event.key === 'ArrowUp') {
        state.animation = null;
        state.pitch = clamp(state.pitch - step, -70 * DEG, 70 * DEG);
      } else if (event.key === 'ArrowDown') {
        state.animation = null;
        state.pitch = clamp(state.pitch + step, -70 * DEG, 70 * DEG);
      } else if (event.key === '+' || event.key === '=') zoom('in');
      else if (event.key === '-') zoom('out');
      else if (event.key === 'Home') animateTo(0, 12 * DEG, 3.15);
      else if (event.key === 'Enter' || event.key === ' ') {
        state.animation = null;
        const rectangle = root.getBoundingClientRect();
        selectScreenPoint(rectangle.left + rectangle.width / 2, rectangle.top + rectangle.height / 2, 'keyboard');
      }
      else return;
      event.preventDefault();
      requestRender();
    });

    canvas.addEventListener('webglcontextlost', event => {
      event.preventDefault();
      activateStaticFallback();
    });
    canvas.addEventListener('webglcontextrestored', () => {
      // Once a context has been lost, keep the known static Earth rather than reusing invalid GPU resources.
      activateStaticFallback();
    });

    const observer = new IntersectionObserver(entries => {
      state.visible = entries[0]?.isIntersecting !== false;
      if (state.visible) requestRender();
    }, { rootMargin: '120px' });
    observer.observe(root);

    const resizeObserver = new ResizeObserver(requestRender);
    resizeObserver.observe(root);
    document.addEventListener('visibilitychange', () => {
      state.visible = document.visibilityState === 'visible';
      if (state.visible) requestRender();
    });

    const contextualDestination = String(root.closest('[data-destination-map-state]')?.dataset.destinationMapState || '')
      .toLowerCase()
      .replace(/[^a-z0-9-]/g, '')
      .slice(0, 60);
    if (contextualDestination && state.available.has(contextualDestination)) {
      focusDestination(contextualDestination, { animate: false, pulse: false, announce: false });
    }
    // Discovery globes (homepage and travel map) arm the auto-fly tour after
    // ~3s of load idle. Guide and destination globes never tour.
    if (root.matches('[data-discovery-globe]')) {
      state.tour.autoTimer = window.setTimeout(() => {
        state.tour.autoTimer = 0;
        startTour();
      }, TOUR_START_DELAY_MS);
    }
    requestRender();
    return { root, focusDestination, focusHub, zoom, setDestinations, setExplorationHubs, clearSelection, pulseRoute, cancelMotion, requestRender, startTour, stopTour };
  }

  function initialize() {
    document.querySelectorAll('[data-globe-3d]').forEach(root => {
      const controller = createController(root);
      if (controller) controllers.push(controller);
    });
  }

  window.traVelGlobe3D = {
    focusDestination(id, options = true) {
      const targetRoot = typeof options === 'object' ? options.root : null;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) controller.focusDestination(id, options);
      });
    },
    setDestinations(data) {
      controllers.forEach(controller => controller.setDestinations(data));
    },
    setExplorationHubs(data) {
      controllers.forEach(controller => controller.setExplorationHubs(data));
    },
    focusHub(id, options = true) {
      const targetRoot = typeof options === 'object' ? options.root : null;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) controller.focusHub(id, options);
      });
    },
    clearSelection(options = {}) {
      const targetRoot = typeof options === 'object' ? options.root : null;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) controller.clearSelection(options);
      });
    },
    zoom(direction, options = {}) {
      const targetRoot = typeof options === 'object' ? options.root : null;
      let handled = false;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) handled = controller.zoom(direction) || handled;
      });
      return handled;
    },
    pulseRoute(targetRoot = null) {
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) controller.pulseRoute();
      });
    },
    cancelMotion(targetRoot = null) {
      let handled = false;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) handled = controller.cancelMotion() || handled;
      });
      return handled;
    },
    startTour(ids = null, options = {}) {
      const targetRoot = typeof options === 'object' && options ? options.root : null;
      let started = false;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) started = controller.startTour(ids, options) || started;
      });
      return started;
    },
    stopTour(options = {}) {
      const targetRoot = typeof options === 'object' && options ? options.root : null;
      const permanent = typeof options === 'object' && options ? options.permanent === true : false;
      let stopped = false;
      controllers.forEach(controller => {
        if (!targetRoot || controller.root === targetRoot) stopped = controller.stopTour(permanent) || stopped;
      });
      return stopped;
    },
    requestRender() {
      controllers.forEach(controller => controller.requestRender());
    },
    resolveSelection(point, destinations = [], hubs = [], destinationRadiusKm = 100) {
      return resolveGeographicSelection(point, destinations, hubs, destinationRadiusKm);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
}());
