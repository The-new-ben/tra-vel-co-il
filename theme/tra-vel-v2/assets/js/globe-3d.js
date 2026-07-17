(function () {
  'use strict';

  const DEG = Math.PI / 180;
  const controllers = [];

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

  function createController(root) {
    const canvas = root.querySelector('[data-globe-canvas]');
    const routePath = root.querySelector('[data-globe-route]');
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
      available: new Set(Array.from(root.querySelectorAll('.price-pin[data-destination]'), marker => marker.dataset.destination)),
      visible: true,
      animation: null,
      frame: 0,
      pointer: null,
      routeTimer: 0,
      textureReady: false
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
      if (!routePath || !state.selected) return;
      const destination = projected.get(state.selected);
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
        const markerWidth = mobile && !active ? 44 : Math.min(112, Math.max(48, marker.textContent.trim().length * 9 + 22));
        const markerHeight = mobile ? 44 : 34;
        candidates.push({ marker, point, active, width: markerWidth, height: markerHeight });
      });

      candidates.sort((a, b) => Number(b.active) - Number(a.active) || b.point.depth - a.point.depth);
      const placed = [];
      candidates.forEach(candidate => {
        const box = {
          left: candidate.point.x - candidate.width / 2,
          right: candidate.point.x + candidate.width / 2,
          top: candidate.point.y - candidate.height / 2,
          bottom: candidate.point.y + candidate.height / 2
        };
        const collides = !candidate.active && placed.some(existing => boxesOverlap(box, existing));
        candidate.marker.hidden = collides;
        if (collides) return;
        candidate.marker.style.left = `${candidate.point.x}px`;
        candidate.marker.style.top = `${candidate.point.y}px`;
        candidate.marker.style.setProperty('--globe-depth', String(clamp(0.86 + candidate.point.depth * 0.17, 0.82, 1.05)));
        placed.push(box);
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
      updateRoute(width, height, projected);
    }

    function draw(timestamp) {
      state.frame = 0;
      if (!state.visible) return;
      if (state.animation) {
        const elapsed = timestamp - state.animation.started;
        const progress = clamp(elapsed / state.animation.duration, 0, 1);
        const eased = easeOutCubic(progress);
        state.yaw = normalizeAngle(state.animation.fromYaw + state.animation.deltaYaw * eased);
        state.pitch = state.animation.fromPitch + (state.animation.toPitch - state.animation.fromPitch) * eased;
        state.distance = state.animation.fromDistance + (state.animation.toDistance - state.animation.fromDistance) * eased;
        if (progress >= 1) state.animation = null;
      }

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
      updateMarkers(dimensions.width, dimensions.height);

      if (state.textureReady) root.classList.add('is-webgl-ready');
      if (state.animation) requestRender();
    }

    function requestRender() {
      if (!state.frame) state.frame = window.requestAnimationFrame(draw);
    }

    function animateTo(yaw, pitch, distance = state.distance, duration = 680) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        state.yaw = normalizeAngle(yaw);
        state.pitch = clamp(pitch, -70 * DEG, 70 * DEG);
        state.distance = clamp(distance, 2.25, 4.8);
        state.animation = null;
        requestRender();
        return;
      }
      state.animation = {
        fromYaw: state.yaw,
        deltaYaw: shortestAngle(state.yaw, yaw),
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
      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        void root.offsetWidth;
        root.classList.add('is-routing');
        state.routeTimer = window.setTimeout(() => root.classList.remove('is-routing'), 920);
      }
    }

    function focusDestination(id, options = true) {
      if (root.classList.contains('globe-3d-unavailable')) return false;
      const animate = typeof options === 'object' ? options.animate !== false : Boolean(options);
      const pulse = typeof options === 'object' ? options.pulse === true : Boolean(options);
      const marker = root.querySelector(`.price-pin[data-destination="${CSS.escape(id)}"]`);
      if (!marker) return false;
      const latitude = Number(marker.dataset.latitude);
      const longitude = Number(marker.dataset.longitude);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return false;
      if (!animate) state.animation = null;
      state.selected = id;
      if (pulse) pulseRoute();
      markers().forEach(item => {
        const active = item.dataset.destination === id;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      const targetYaw = -longitude * DEG;
      const targetPitch = latitude * DEG;
      if (animate) animateTo(targetYaw, targetPitch, Math.min(state.distance, 3.05));
      else {
        state.yaw = normalizeAngle(targetYaw);
        state.pitch = clamp(targetPitch, -70 * DEG, 70 * DEG);
        requestRender();
      }
      if (liveRegion) liveRegion.textContent = `${marker.getAttribute('aria-label') || marker.textContent.trim()} במרכז הגלובוס`;
      return true;
    }

    function selectScreenPoint(clientX, clientY, inputType = 'pointer') {
      if (!root.matches('[data-discovery-globe]')) {
        if (liveRegion) liveRegion.textContent = 'בחירת נקודה חופשית זמינה במסך המפה המלא.';
        return false;
      }
      const rectangle = root.getBoundingClientRect();
      const point = globePointFromScreen(clientX - rectangle.left, clientY - rectangle.top, rectangle.width, rectangle.height, state);
      if (!point) {
        if (liveRegion) liveRegion.textContent = 'הנקודה מחוץ לכדור הארץ. נסו לבחור בתוך הגלובוס.';
        return false;
      }
      const candidates = markers()
        .filter(marker => state.available.has(marker.dataset.destination))
        .map(marker => ({
          id: marker.dataset.destination,
          label: marker.getAttribute('aria-label') || marker.textContent.trim(),
          latitude: Number(marker.dataset.latitude),
          longitude: Number(marker.dataset.longitude)
        }))
        .filter(marker => Number.isFinite(marker.latitude) && Number.isFinite(marker.longitude))
        .map(marker => ({ ...marker, distanceKm: greatCircleDistanceKm(point, marker) }))
        .sort((first, second) => first.distanceKm - second.distanceKm);
      const nearest = candidates[0] || null;
      const supportedRadiusKm = clamp(Number(root.dataset.supportedRadiusKm || 100), 100, 5000);
      const supported = Boolean(nearest && nearest.distanceKm <= supportedRadiusKm);
      const detail = {
        latitude: Number(point.latitude.toFixed(4)),
        longitude: Number(point.longitude.toFixed(4)),
        inputType,
        supported,
        supportedRadiusKm,
        nearestDestination: nearest?.id || '',
        nearestLabel: nearest?.label || '',
        distanceKm: nearest ? Math.round(nearest.distanceKm) : null
      };
      root.dispatchEvent(new CustomEvent('travelglobe:select', { bubbles: true, detail }));
      if (liveRegion) {
        liveRegion.textContent = supported
          ? `${nearest.label} נבחרה. תוכנית 360 מעלות מתעדכנת מתחת למפה.`
          : `הנקודה נבחרה. אין עדיין כיסוי מובנה באזור הזה, והסוכן יכול להמשיך ממנה.`;
      }
      return true;
    }

    function zoom(direction) {
      if (root.classList.contains('globe-3d-unavailable')) return false;
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
      if (state.selected && !state.available.has(state.selected)) clearSelection();
      requestRender();
    }

    function clearSelection() {
      state.selected = '';
      state.animation = null;
      markers().forEach(marker => {
        marker.classList.remove('is-active');
        marker.setAttribute('aria-pressed', 'false');
      });
      if (routePath) routePath.setAttribute('d', '');
      requestRender();
    }

    const image = new Image();
    image.decoding = 'async';
    image.addEventListener('load', () => {
      gl.bindTexture(gl.TEXTURE_2D, texture);
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, image);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.REPEAT);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.generateMipmap(gl.TEXTURE_2D);
      state.textureReady = true;
      requestRender();
      root.dispatchEvent(new CustomEvent('travelglobe:ready', { bubbles: true }));
    }, { once: true });
    image.addEventListener('error', () => {
      root.classList.add('globe-3d-unavailable');
      if (liveRegion) liveRegion.textContent = 'תצוגת המפה החלופית פעילה';
    }, { once: true });
    image.src = root.dataset.texture;

    root.addEventListener('pointerdown', event => {
      if (!event.isPrimary || event.button !== 0 || event.target.closest('.price-pin')) return;
      state.visible = document.visibilityState !== 'hidden';
      state.animation = null;
      state.pointer = {
        id: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
        startedAt: performance.now()
      };
      root.classList.add('is-dragging');
      root.setPointerCapture(event.pointerId);
    });
    root.addEventListener('pointermove', event => {
      if (!state.pointer || state.pointer.id !== event.pointerId) return;
      const deltaX = event.clientX - state.pointer.x;
      const deltaY = event.clientY - state.pointer.y;
      if (Math.hypot(event.clientX - state.pointer.startX, event.clientY - state.pointer.startY) > 8) state.pointer.moved = true;
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
      if (event.type === 'pointerup' && !pointer.moved && performance.now() - pointer.startedAt < 700) {
        selectScreenPoint(event.clientX, event.clientY, 'pointer');
      }
    };
    root.addEventListener('pointerup', endPointer);
    root.addEventListener('pointercancel', endPointer);
    root.addEventListener('lostpointercapture', endPointer);
    root.addEventListener('keydown', event => {
      if (event.target.closest('.price-pin')) return;
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
      root.classList.remove('is-webgl-ready');
      root.classList.add('globe-3d-unavailable');
      if (liveRegion) liveRegion.textContent = 'תצוגת המפה החלופית פעילה';
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

    requestRender();
    return { focusDestination, zoom, setDestinations, clearSelection, pulseRoute, requestRender };
  }

  function initialize() {
    document.querySelectorAll('[data-globe-3d]').forEach(root => {
      const controller = createController(root);
      if (controller) controllers.push(controller);
    });
  }

  window.traVelGlobe3D = {
    focusDestination(id, options = true) {
      controllers.forEach(controller => controller.focusDestination(id, options));
    },
    setDestinations(data) {
      controllers.forEach(controller => controller.setDestinations(data));
    },
    clearSelection() {
      controllers.forEach(controller => controller.clearSelection());
    },
    zoom(direction) {
      let handled = false;
      controllers.forEach(controller => { handled = controller.zoom(direction) || handled; });
      return handled;
    },
    pulseRoute() {
      controllers.forEach(controller => controller.pulseRoute());
    },
    requestRender() {
      controllers.forEach(controller => controller.requestRender());
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
}());
