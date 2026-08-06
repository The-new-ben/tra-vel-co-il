(function () {
  'use strict';

  // Premium planet (theme 1.43.0). This file is the entire client side of the
  // photorealistic upgrade: it reveals the server-rendered control when every
  // capability guard holds, trades a REST grant for one streaming session,
  // injects the vendored Cesium build, and swaps the premium canvas into the
  // same .globe panel the legacy sphere paints in. The legacy globe stays the
  // first paint and the permanent fallback: any failure at any stage silently
  // restores it exactly as it was. Nothing from the vendor directory loads
  // before an explicit visitor tap, and the Google key exists here only for
  // the moment it is woven into the tileset URL.
  //
  // Scroll law: this file binds pointer, click, and key listeners only. The
  // vendored Cesium runtime owns its own canvas-scoped gesture handling.

  var DOUBLE_TAP_WINDOW_MS = 300;
  var DOUBLE_TAP_RADIUS_PX = 24;
  var TAP_MOVE_TOLERANCE_PX = 8;
  var TAP_MAX_DURATION_MS = 700;
  var SCRIPT_TIMEOUT_MS = 20000;
  var DEFAULT_ACTIVATION_TIMEOUT_MS = 45000;
  var EARTH_RADIUS_M = 6371000;
  // Calibrated against the legacy camera: apparent Earth size roughly matches
  // when legacy distance d maps to height (d - 1) * R * 0.63 under Cesium's
  // wider default field of view.
  var FOV_MATCH = 0.63;
  var MIN_CAMERA_HEIGHT_M = 260;
  var MAX_CAMERA_HEIGHT_M = 16000000;
  var ZOOM_STEP_FACTOR = 0.45;
  var FLY_IN_FACTOR = 0.5;
  var FLY_IN_SECONDS = 2.6;
  var DIVE_FACTOR = 0.55;
  var NEAR_LOD_PSEUDO_DISTANCE = 3.0;
  var NEAR_LOD_HUB_LABEL_BUDGET = 12;
  var MARKER_COLLISION_BUDGET = 60;

  var config = window.traVelV2Planet && typeof window.traVelV2Planet === 'object' ? window.traVelV2Planet : null;

  function activationTimeoutMs() {
    var value = config && Number(config.timeoutMs);
    return Number.isFinite(value) && value >= 5000 ? value : DEFAULT_ACTIVATION_TIMEOUT_MS;
  }

  function saveDataRequested() {
    return navigator.connection && navigator.connection.saveData === true;
  }

  function reducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function webgl2Available() {
    try {
      var probe = document.createElement('canvas');
      return Boolean(probe.getContext('webgl2'));
    } catch (error) {
      return false;
    }
  }

  function distanceToHeight(distance) {
    return Math.max(MIN_CAMERA_HEIGHT_M, (distance - 1) * EARTH_RADIUS_M * FOV_MATCH);
  }

  function heightToPseudoDistance(height) {
    return 1 + height / (EARTH_RADIUS_M * FOV_MATCH);
  }

  function clamp(value, minimum, maximum) {
    return Math.min(maximum, Math.max(minimum, value));
  }

  function boxesOverlap(a, b, padding) {
    var pad = typeof padding === 'number' ? padding : 5;
    return !(
      a.right + pad <= b.left ||
      b.right + pad <= a.left ||
      a.bottom + pad <= b.top ||
      b.bottom + pad <= a.top
    );
  }

  // ---------------------------------------------------------------------------
  // Vendor loading. One shared promise: the script and stylesheet inject once,
  // no matter how many globes upgrade in one page life.
  // ---------------------------------------------------------------------------
  var cesiumLoadPromise = null;

  function loadCesium() {
    if (window.Cesium) return Promise.resolve(window.Cesium);
    if (cesiumLoadPromise) return cesiumLoadPromise;
    cesiumLoadPromise = new Promise(function (resolve, reject) {
      var failed = false;
      var timer = window.setTimeout(function () {
        failed = true;
        reject(new Error('Cesium load timed out.'));
      }, SCRIPT_TIMEOUT_MS);
      window.CESIUM_BASE_URL = String(config.vendorBase || '');
      var style = document.createElement('link');
      style.rel = 'stylesheet';
      style.href = String(config.style || '');
      document.head.appendChild(style);
      var script = document.createElement('script');
      script.src = String(config.script || '');
      script.async = true;
      script.addEventListener('load', function () {
        window.clearTimeout(timer);
        if (failed) return;
        if (window.Cesium) resolve(window.Cesium);
        else reject(new Error('Cesium script loaded without its global.'));
      }, { once: true });
      script.addEventListener('error', function () {
        window.clearTimeout(timer);
        if (!failed) reject(new Error('Cesium script failed to load.'));
      }, { once: true });
      document.head.appendChild(script);
    });
    cesiumLoadPromise.catch(function () {
      // A failed injection may retry on the next explicit activation.
      cesiumLoadPromise = null;
    });
    return cesiumLoadPromise;
  }

  // ---------------------------------------------------------------------------
  // Grant. The only road to the key; a 403 is a final no for this page view.
  // ---------------------------------------------------------------------------
  function requestGrant() {
    return window.fetch(String(config.grantUrl || ''), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': String(config.nonce || '') }
    }).then(function (response) {
      if (response.status === 403) {
        var denial = new Error('Premium planet grant refused.');
        denial.permanentDenial = true;
        throw denial;
      }
      if (!response.ok) throw new Error('Premium planet grant failed.');
      return response.json();
    }).then(function (payload) {
      var key = payload && typeof payload.key === 'string' ? payload.key : '';
      if (!/^AIza[0-9A-Za-z_-]{35}$/.test(key)) throw new Error('Premium planet grant returned no usable session.');
      return key;
    });
  }

  // ---------------------------------------------------------------------------
  // Per-globe controller.
  // ---------------------------------------------------------------------------
  var controllers = [];

  function createController(root, control) {
    var legacyCanvas = root.querySelector('[data-globe-canvas]');
    var routePath = root.querySelector('[data-globe-route]');
    var selectionMarker = root.querySelector('[data-globe-selection-point]');
    var liveRegion = root.querySelector('[data-globe-live]');
    var discoveryGlobe = root.matches('[data-discovery-globe]');
    if (!legacyCanvas) return null;

    var state = {
      phase: 'idle',
      widget: null,
      tileset: null,
      stage: null,
      credits: null,
      chip: null,
      timeoutTimer: 0,
      observer: null,
      lastSelectPoint: null,
      tap: null,
      lastTapUp: null,
      pendingTapTimer: 0,
      suppressDiveUntil: 0,
      homeView: null
    };

    // The most recent published selection anchors the arrival card and the
    // selection marker on the premium planet. Listening from load means a
    // selection made before the upgrade still has its anchor afterwards.
    root.addEventListener('travelglobe:select', function (event) {
      var detail = event.detail || {};
      var latitude = Number(detail.latitude);
      var longitude = Number(detail.longitude);
      if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
        state.lastSelectPoint = { latitude: latitude, longitude: longitude };
      }
    });

    function legacyHealthy() {
      return root.classList.contains('is-webgl-ready') && !root.classList.contains('globe-3d-unavailable');
    }

    function revealControl() {
      if (state.phase !== 'idle') return;
      if (!legacyHealthy() || !webgl2Available() || saveDataRequested()) return;
      control.hidden = false;
    }

    if (legacyHealthy()) revealControl();
    else root.addEventListener('travelglobe:ready', revealControl, { once: true });

    // The control is interface, not Earth: its gestures must never become
    // globe taps, previews, or dives on the legacy controller beneath it.
    ['pointerdown', 'pointerup', 'dblclick'].forEach(function (type) {
      control.addEventListener(type, function (event) { event.stopPropagation(); });
    });
    control.addEventListener('click', function (event) {
      event.stopPropagation();
      activate();
    });

    function announce(message) {
      if (liveRegion) liveRegion.textContent = message;
    }

    function setChip(visible) {
      if (visible && !state.chip) {
        var chip = document.createElement('div');
        chip.className = 'globe-premium-chip';
        chip.setAttribute('role', 'status');
        var dot = document.createElement('span');
        dot.className = 'globe-premium-chip-dot';
        dot.setAttribute('aria-hidden', 'true');
        var label = document.createElement('span');
        label.textContent = 'טוען את כדור הארץ האמיתי';
        chip.append(dot, label);
        root.append(chip);
        state.chip = chip;
      } else if (!visible && state.chip) {
        state.chip.remove();
        state.chip = null;
      }
    }

    function restoreLegacy(allowRetry) {
      if (state.phase === 'restored') return;
      state.phase = 'restored';
      if (state.timeoutTimer) window.clearTimeout(state.timeoutTimer);
      state.timeoutTimer = 0;
      if (state.observer) {
        state.observer.disconnect();
        state.observer = null;
      }
      setChip(false);
      if (state.pendingTapTimer) window.clearTimeout(state.pendingTapTimer);
      state.pendingTapTimer = 0;
      if (state.widget) {
        try { state.widget.destroy(); } catch (error) { /* The widget may already be gone. */ }
        state.widget = null;
        state.tileset = null;
      }
      if (state.stage) {
        state.stage.remove();
        state.stage = null;
      }
      root.classList.remove('is-premium-planet-active', 'is-premium-planet-streaming');
      root.querySelectorAll('.price-pin, .exploration-hub').forEach(function (marker) {
        delete marker.dataset.premiumHidden;
      });
      if (window.traVelGlobe3D) window.traVelGlobe3D.requestRender();
      state.phase = 'idle';
      control.setAttribute('aria-pressed', 'false');
      if (allowRetry) revealControl();
    }

    function fail(reason, allowRetry) {
      // Deliberately generic: the tileset URL carries the key, so error
      // objects from the streaming path are never echoed anywhere.
      console.warn('Tra-Vel premium planet stayed on the classic Earth (' + reason + ').');
      restoreLegacy(allowRetry);
    }

    // -----------------------------------------------------------------------
    // Marker projection: the same pins, hubs, origin, selection marker, route
    // curve and arrival card, projected through the premium camera each frame.
    // -----------------------------------------------------------------------
    var Cesium = null;
    var scratchPosition = null;
    var scratchWindow = null;
    var scratchNormal = null;
    var scratchCameraNormal = null;
    var occluder = null;
    var toWindowCoordinates = null;

    function markerSize(marker, kind, active, focused, mobile, labelLength) {
      if (kind === 'destination') {
        var text = String(marker.textContent || '').trim();
        var width = mobile && !active ? 44 : Math.min(112, Math.max(48, text.length * 9 + 22));
        var homeGlobe = Boolean(root.closest('.home-globe-stack'));
        var height = mobile || homeGlobe ? 44 : 34;
        return { width: width, height: height };
      }
      if (active || focused) {
        return { width: Math.min(126, Math.max(72, labelLength * 9 + 30)), height: 88 };
      }
      return { width: 44, height: 44 };
    }

    function projectPoint(latitude, longitude, out) {
      Cesium.Cartesian3.fromDegrees(longitude, latitude, 0, Cesium.Ellipsoid.WGS84, scratchPosition);
      var visible = occluder.isPointVisible(scratchPosition);
      var windowPosition = toWindowCoordinates(state.widget.scene, scratchPosition, scratchWindow);
      if (!windowPosition) return null;
      Cesium.Cartesian3.normalize(scratchPosition, scratchNormal);
      Cesium.Cartesian3.normalize(state.widget.camera.positionWC, scratchCameraNormal);
      out.x = windowPosition.x;
      out.y = windowPosition.y;
      out.depth = Cesium.Cartesian3.dot(scratchNormal, scratchCameraNormal);
      out.visible = visible;
      return out;
    }

    function placeMarker(candidate, placed, width, height) {
      var halfWidth = candidate.width / 2;
      var halfHeight = candidate.height / 2;
      var baseX = clamp(candidate.point.x, halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
      var baseY = clamp(candidate.point.y, halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
      var offsets = [[0, 0]];
      if (candidate.active || candidate.focused) {
        var maximumRadius = Math.min(240, Math.max(48, Math.min(width, height) / 2));
        for (var radius = 48; radius <= maximumRadius; radius += 48) {
          offsets.push(
            [0, -radius], [radius, 0], [0, radius], [-radius, 0],
            [radius, -radius], [radius, radius], [-radius, radius], [-radius, -radius]
          );
        }
      }
      for (var index = 0; index < offsets.length; index++) {
        var x = clamp(baseX + offsets[index][0], halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
        var y = clamp(baseY + offsets[index][1], halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
        var box = { left: x - halfWidth, right: x + halfWidth, top: y - halfHeight, bottom: y + halfHeight };
        var collides = false;
        for (var existing = 0; existing < placed.length; existing++) {
          if (boxesOverlap(box, placed[existing])) { collides = true; break; }
        }
        if (!collides) return { x: x, y: y, box: box };
      }
      if (candidate.active || candidate.focused) {
        var forcedX = clamp(candidate.point.x, halfWidth + 4, Math.max(halfWidth + 4, width - halfWidth - 4));
        var forcedY = clamp(candidate.point.y, halfHeight + 4, Math.max(halfHeight + 4, height - halfHeight - 4));
        return {
          x: forcedX,
          y: forcedY,
          box: { left: forcedX - halfWidth, right: forcedX + halfWidth, top: forcedY - halfHeight, bottom: forcedY + halfHeight }
        };
      }
      return null;
    }

    function hideExternally(marker) {
      if (marker.dataset.premiumHidden === 'true') {
        marker.hidden = true;
      }
    }

    function syncOverlays() {
      if (!state.widget || state.phase !== 'active') return;
      var rectangle = root.getBoundingClientRect();
      var width = rectangle.width;
      var height = rectangle.height;
      if (!(width > 0) || !(height > 0)) return;
      occluder.cameraPosition = state.widget.camera.positionWC;
      var mobile = window.matchMedia('(max-width: 1000px)').matches;
      var cameraHeight = state.widget.camera.positionCartographic.height;
      var nearLod = heightToPseudoDistance(cameraHeight) <= NEAR_LOD_PSEUDO_DISTANCE;
      var lodLevel = nearLod ? 'near' : 'far';
      if (root.dataset.globeLod !== lodLevel) root.dataset.globeLod = lodLevel;

      var candidates = [];
      var projectedDestinations = new Map();
      var scratchOut = { x: 0, y: 0, depth: 0, visible: false };

      root.querySelectorAll('.price-pin[data-destination]').forEach(function (marker) {
        var latitude = Number(marker.dataset.latitude);
        var longitude = Number(marker.dataset.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
          hideExternally(marker);
          return;
        }
        var point = projectPoint(latitude, longitude, scratchOut);
        if (point) projectedDestinations.set(marker.dataset.destination, { x: point.x, y: point.y, visible: point.visible });
        if (!point || !point.visible) {
          marker.hidden = true;
          marker.dataset.premiumHidden = 'true';
          return;
        }
        var active = marker.classList.contains('is-active');
        var focused = document.activeElement === marker;
        var size = markerSize(marker, 'destination', active, focused, mobile, 0);
        candidates.push({
          marker: marker,
          point: { x: point.x, y: point.y, depth: point.depth },
          active: active,
          focused: focused,
          width: size.width,
          height: size.height,
          kind: 'destination',
          priority: 3
        });
      });

      var lodLabelSlots = nearLod ? NEAR_LOD_HUB_LABEL_BUDGET : 0;
      root.querySelectorAll('.exploration-hub[data-exploration-hub]').forEach(function (marker) {
        var latitude = Number(marker.dataset.latitude);
        var longitude = Number(marker.dataset.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
          hideExternally(marker);
          return;
        }
        var point = projectPoint(latitude, longitude, scratchOut);
        if (!point || !point.visible) {
          marker.hidden = true;
          marker.dataset.premiumHidden = 'true';
          return;
        }
        var active = marker.classList.contains('is-active');
        var focused = document.activeElement === marker;
        var labelLength = String(marker.dataset.city || marker.textContent || '').trim().length;
        var size = markerSize(marker, 'hub', active, focused, mobile, labelLength);
        candidates.push({
          marker: marker,
          point: { x: point.x, y: point.y, depth: point.depth },
          active: active,
          focused: focused,
          width: size.width,
          height: size.height,
          kind: 'hub',
          priority: 1,
          labelLength: labelLength
        });
      });

      candidates.sort(function (a, b) {
        return Number(b.focused) - Number(a.focused)
          || Number(b.active) - Number(a.active)
          || b.priority - a.priority
          || b.point.depth - a.point.depth;
      });

      var placed = [];
      candidates.forEach(function (candidate, candidateIndex) {
        if (candidateIndex >= MARKER_COLLISION_BUDGET && !candidate.active && !candidate.focused) {
          candidate.marker.hidden = true;
          candidate.marker.dataset.premiumHidden = 'true';
          return;
        }
        if (candidate.kind === 'hub') {
          var lodLabeled = !candidate.active && !candidate.focused && lodLabelSlots > 0;
          if (lodLabeled) {
            lodLabelSlots -= 1;
            candidate.width = Math.max(candidate.width, Math.min(126, Math.max(72, candidate.labelLength * 9 + 30)));
            candidate.height = Math.max(candidate.height, 88);
          }
          candidate.lodLabeled = lodLabeled;
        }
        var placement = placeMarker(candidate, placed, width, height);
        if (!placement) {
          candidate.marker.hidden = true;
          candidate.marker.dataset.premiumHidden = 'true';
          return;
        }
        candidate.marker.hidden = false;
        delete candidate.marker.dataset.premiumHidden;
        candidate.marker.style.left = placement.x + 'px';
        candidate.marker.style.top = placement.y + 'px';
        candidate.marker.style.setProperty('--globe-depth', String(clamp(0.86 + candidate.point.depth * 0.17, 0.82, 1.05)));
        if (candidate.kind === 'hub') {
          var labelState = candidate.active || candidate.focused || candidate.lodLabeled ? 'visible' : 'dot';
          if (candidate.marker.dataset.globeLabel !== labelState) candidate.marker.dataset.globeLabel = labelState;
        }
        placed.push(placement.box);
      });

      var originMarker = root.querySelector('[data-globe-origin]');
      var originLatitude = Number(root.dataset.originLatitude || 32.0005);
      var originLongitude = Number(root.dataset.originLongitude || 34.8708);
      var originPoint = projectPoint(originLatitude, originLongitude, scratchOut);
      var originScreen = originPoint && originPoint.visible ? { x: originPoint.x, y: originPoint.y } : null;
      if (originMarker) {
        originMarker.hidden = !originScreen;
        if (originScreen) {
          originMarker.style.left = originScreen.x + 'px';
          originMarker.style.top = originScreen.y + 'px';
        }
      }

      var selectionScreen = null;
      if (selectionMarker && state.lastSelectPoint) {
        var selectionPoint = projectPoint(state.lastSelectPoint.latitude, state.lastSelectPoint.longitude, scratchOut);
        if (selectionPoint && selectionPoint.visible) {
          selectionScreen = { x: selectionPoint.x, y: selectionPoint.y };
          selectionMarker.hidden = false;
          selectionMarker.style.left = selectionScreen.x + 'px';
          selectionMarker.style.top = selectionScreen.y + 'px';
          selectionMarker.style.setProperty('--globe-depth', String(clamp(0.88 + selectionPoint.depth * 0.14, 0.84, 1.04)));
        } else {
          selectionMarker.hidden = true;
        }
      }

      if (routePath) {
        var activePin = root.querySelector('.price-pin.is-active');
        var target = null;
        if (activePin) target = projectedDestinations.get(activePin.dataset.destination) || null;
        else if (selectionScreen) target = { x: selectionScreen.x, y: selectionScreen.y, visible: true };
        if (target && target.visible && originScreen) {
          var middleX = (originScreen.x + target.x) / 2;
          var middleY = Math.min(originScreen.y, target.y) - Math.max(26, Math.abs(originScreen.x - target.x) * 0.16);
          routePath.setAttribute('d', 'M ' + originScreen.x.toFixed(1) + ' ' + originScreen.y.toFixed(1) + ' Q ' + middleX.toFixed(1) + ' ' + middleY.toFixed(1) + ' ' + target.x.toFixed(1) + ' ' + target.y.toFixed(1));
        } else {
          routePath.setAttribute('d', '');
        }
      }

      var arrivalCard = root.querySelector('[data-globe-arrival-card]');
      if (arrivalCard && !arrivalCard.hidden && state.lastSelectPoint) {
        var cardPoint = projectPoint(state.lastSelectPoint.latitude, state.lastSelectPoint.longitude, scratchOut);
        if (cardPoint && cardPoint.visible) {
          var cardWidth = arrivalCard.offsetWidth || 236;
          var cardHeight = arrivalCard.offsetHeight || 138;
          var cardX = clamp(cardPoint.x, cardWidth / 2 + 6, Math.max(cardWidth / 2 + 6, width - cardWidth / 2 - 6));
          var cardY = clamp(cardPoint.y - cardHeight / 2 - 24, cardHeight / 2 + 6, Math.max(cardHeight / 2 + 6, height - cardHeight / 2 - 6));
          arrivalCard.style.left = cardX + 'px';
          arrivalCard.style.top = cardY + 'px';
        } else {
          arrivalCard.hidden = true;
        }
      }
    }

    // -----------------------------------------------------------------------
    // Selection publishing: the premium planet feeds the exact event the
    // legacy globe publishes, so the arrival card, the dive store, and the
    // proposal takeover behave identically on both renderers.
    // -----------------------------------------------------------------------
    function destinationCandidates() {
      var list = [];
      root.querySelectorAll('.price-pin[data-destination]').forEach(function (marker) {
        var latitude = Number(marker.dataset.latitude);
        var longitude = Number(marker.dataset.longitude);
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
        list.push({
          id: marker.dataset.destination,
          label: marker.getAttribute('aria-label') || String(marker.textContent || '').trim(),
          latitude: latitude,
          longitude: longitude
        });
      });
      return list;
    }

    function hubCandidates() {
      var list = [];
      root.querySelectorAll('.exploration-hub[data-exploration-hub]').forEach(function (marker) {
        var id = String(marker.dataset.explorationHub || '');
        var city = String(marker.dataset.city || '').trim();
        var country = String(marker.dataset.country || '').trim();
        var latitude = Number(marker.dataset.latitude);
        var longitude = Number(marker.dataset.longitude);
        var radiusKm = Number(marker.dataset.radiusKm);
        var iataSearchCode = String(marker.dataset.iataSearchCode || '').trim().toUpperCase();
        var liveSearchScopes = String(marker.dataset.liveSearchScopes || '').split(',').filter(Boolean);
        if (!/^[a-z0-9-]{2,60}$/.test(id) || !city || !country
          || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
          || !Number.isFinite(longitude) || longitude < -180 || longitude > 180
          || !Number.isInteger(radiusKm) || radiusKm < 40 || radiusKm > 750
          || (iataSearchCode && !/^[A-Z]{3}$/.test(iataSearchCode))) return;
        list.push({
          id: id,
          city: city,
          country: country,
          latitude: latitude,
          longitude: longitude,
          radiusKm: radiusKm,
          iataSearchCode: iataSearchCode,
          liveSearchScopes: liveSearchScopes
        });
      });
      return list;
    }

    function publishPoint(latitude, longitude, inputType) {
      if (!discoveryGlobe || !window.traVelGlobe3D || typeof window.traVelGlobe3D.resolveSelection !== 'function') return false;
      var point = { latitude: latitude, longitude: longitude };
      var supportedRadiusKm = clamp(Number(root.dataset.supportedRadiusKm || 100), 100, 5000);
      var resolution = window.traVelGlobe3D.resolveSelection(point, destinationCandidates(), hubCandidates(), supportedRadiusKm);
      var nearest = resolution.destination || resolution.nearestDestination || null;
      var hub = resolution.hub || null;
      var detail = {
        latitude: Number(latitude.toFixed(4)),
        longitude: Number(longitude.toFixed(4)),
        inputType: inputType,
        supported: resolution.supported,
        supportedRadiusKm: resolution.supportedRadiusKm,
        selectionKind: resolution.selectionKind,
        planningAction: resolution.planningAction,
        nearestDestination: resolution.selectionKind === 'destination' ? (nearest && nearest.id ? nearest.id : '') : '',
        nearestLabel: resolution.selectionKind === 'destination' ? (nearest && nearest.label ? nearest.label : '') : '',
        distanceKm: Number.isFinite(resolution.distanceKm) ? Math.round(resolution.distanceKm) : null,
        hubId: hub && hub.id ? hub.id : '',
        hubCity: hub && hub.city ? hub.city : '',
        hubCountry: hub && hub.country ? hub.country : '',
        hubIataSearchCode: hub && hub.iataSearchCode ? hub.iataSearchCode : '',
        hubLiveSearchScopes: hub && hub.liveSearchScopes ? hub.liveSearchScopes : [],
        hubDistanceKm: hub ? Math.round(hub.distanceKm) : null
      };
      root.querySelectorAll('.price-pin[data-destination]').forEach(function (item) {
        var active = Boolean(detail.nearestDestination) && item.dataset.destination === detail.nearestDestination;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      root.querySelectorAll('.exploration-hub[data-exploration-hub]').forEach(function (item) {
        var active = Boolean(detail.hubId) && item.dataset.explorationHub === detail.hubId;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      state.lastSelectPoint = { latitude: detail.latitude, longitude: detail.longitude };
      if (selectionMarker) {
        selectionMarker.hidden = false;
        selectionMarker.classList.remove('is-new');
        if (!reducedMotion()) {
          void selectionMarker.offsetWidth;
          selectionMarker.classList.add('is-new');
        }
      }
      root.dispatchEvent(new CustomEvent('travelglobe:select', { bubbles: true, detail: detail }));
      if (resolution.selectionKind === 'exploration_hub' && hub) {
        announce('האזור זוהה כ' + hub.city + ', ' + hub.country + '. תוכנית 360 מעלות נפתחה לחיפוש חי מתחת למפה.');
      } else if (resolution.supported && nearest) {
        announce('בחרתם ב' + nearest.label + '. פרטי היעד מופיעים מתחת למפה.');
      } else {
        announce('הנקודה נשמרה. אפשר לזהות את האזור ולפתוח ממנו תכנון חופשה מלא.');
      }
      return true;
    }

    // -----------------------------------------------------------------------
    // Premium camera work.
    // -----------------------------------------------------------------------
    function cameraHeight() {
      return state.widget ? state.widget.camera.positionCartographic.height : distanceToHeight(3.15);
    }

    function setViewAt(latitude, longitude, height) {
      state.widget.camera.setView({
        destination: Cesium.Cartesian3.fromDegrees(longitude, latitude, height),
        orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 }
      });
    }

    function flyToPoint(latitude, longitude, height, seconds) {
      if (!state.widget) return;
      if (reducedMotion()) {
        setViewAt(latitude, longitude, height);
        return;
      }
      state.widget.camera.flyTo({
        destination: Cesium.Cartesian3.fromDegrees(longitude, latitude, clamp(height, MIN_CAMERA_HEIGHT_M, MAX_CAMERA_HEIGHT_M)),
        orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 },
        duration: seconds
      });
    }

    function currentLatLng() {
      var cartographic = state.widget.camera.positionCartographic;
      return {
        latitude: Cesium.Math.toDegrees(cartographic.latitude),
        longitude: Cesium.Math.toDegrees(cartographic.longitude)
      };
    }

    function premiumZoom(direction) {
      var height = cameraHeight();
      var next = clamp(direction === 'in' ? height * ZOOM_STEP_FACTOR : height / ZOOM_STEP_FACTOR, MIN_CAMERA_HEIGHT_M, MAX_CAMERA_HEIGHT_M);
      var here = currentLatLng();
      flyToPoint(here.latitude, here.longitude, next, 0.26);
    }

    function pickLatLng(clientX, clientY) {
      if (!state.widget) return null;
      var rectangle = root.getBoundingClientRect();
      var windowPosition = new Cesium.Cartesian2(clientX - rectangle.left, clientY - rectangle.top);
      var picked = state.widget.camera.pickEllipsoid(windowPosition, Cesium.Ellipsoid.WGS84);
      if (!picked) return null;
      var cartographic = Cesium.Cartographic.fromCartesian(picked);
      return {
        latitude: Cesium.Math.toDegrees(cartographic.latitude),
        longitude: Cesium.Math.toDegrees(cartographic.longitude)
      };
    }

    function diveToScreen(clientX, clientY) {
      var point = pickLatLng(clientX, clientY);
      if (!point) return;
      if (discoveryGlobe) publishPoint(point.latitude, point.longitude, 'dive');
      flyToPoint(point.latitude, point.longitude, clamp(cameraHeight() * DIVE_FACTOR, MIN_CAMERA_HEIGHT_M, MAX_CAMERA_HEIGHT_M), 0.7);
    }

    // -----------------------------------------------------------------------
    // Stage gestures: taps select, double taps dive, keys steer. Everything
    // stops its propagation so the paused legacy controller never receives a
    // gesture aimed at the premium planet.
    // -----------------------------------------------------------------------
    function bindStageGestures(stage) {
      stage.addEventListener('pointerdown', function (event) {
        event.stopPropagation();
        if (!event.isPrimary || event.button !== 0) return;
        state.tap = { x: event.clientX, y: event.clientY, at: performance.now(), id: event.pointerId };
      });
      stage.addEventListener('pointerup', function (event) {
        event.stopPropagation();
        var tap = state.tap;
        state.tap = null;
        if (!tap || tap.id !== event.pointerId) return;
        var moved = Math.hypot(event.clientX - tap.x, event.clientY - tap.y) > TAP_MOVE_TOLERANCE_PX;
        var slow = performance.now() - tap.at > TAP_MAX_DURATION_MS;
        if (moved || slow) return;
        var previous = state.lastTapUp;
        var now = performance.now();
        state.lastTapUp = { x: event.clientX, y: event.clientY, at: now };
        if (previous && now - previous.at <= DOUBLE_TAP_WINDOW_MS
          && Math.hypot(event.clientX - previous.x, event.clientY - previous.y) <= DOUBLE_TAP_RADIUS_PX) {
          state.lastTapUp = null;
          if (state.pendingTapTimer) window.clearTimeout(state.pendingTapTimer);
          state.pendingTapTimer = 0;
          // The synthetic dblclick some browsers still emit for this same
          // gesture is swallowed below, exactly like the legacy globe.
          state.suppressDiveUntil = now + 420;
          diveToScreen(event.clientX, event.clientY);
          return;
        }
        var clientX = event.clientX;
        var clientY = event.clientY;
        if (state.pendingTapTimer) window.clearTimeout(state.pendingTapTimer);
        state.pendingTapTimer = window.setTimeout(function () {
          state.pendingTapTimer = 0;
          var point = pickLatLng(clientX, clientY);
          if (point) publishPoint(point.latitude, point.longitude, 'pointer');
        }, DOUBLE_TAP_WINDOW_MS);
      });
      stage.addEventListener('pointercancel', function (event) {
        event.stopPropagation();
        state.tap = null;
      });
      stage.addEventListener('dblclick', function (event) {
        event.stopPropagation();
        event.preventDefault();
        if (performance.now() < state.suppressDiveUntil) return;
        if (state.pendingTapTimer) window.clearTimeout(state.pendingTapTimer);
        state.pendingTapTimer = 0;
        diveToScreen(event.clientX, event.clientY);
      });
      stage.addEventListener('keydown', function (event) {
        var here;
        var step = event.shiftKey ? 18 : 8;
        var handled = true;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') {
          here = currentLatLng();
          var pseudo = heightToPseudoDistance(cameraHeight());
          var scale = clamp((pseudo - 1) / 2.15, 0.05, 1);
          if (event.key === 'ArrowLeft') here.longitude -= step * scale;
          if (event.key === 'ArrowRight') here.longitude += step * scale;
          if (event.key === 'ArrowUp') here.latitude = clamp(here.latitude + step * scale, -85, 85);
          if (event.key === 'ArrowDown') here.latitude = clamp(here.latitude - step * scale, -85, 85);
          setViewAt(here.latitude, here.longitude, cameraHeight());
        } else if (event.key === '+' || event.key === '=') premiumZoom('in');
        else if (event.key === '-') premiumZoom('out');
        else if (event.key === 'Home' && state.homeView) {
          flyToPoint(state.homeView.latitude, state.homeView.longitude, state.homeView.height, 0.8);
        } else handled = false;
        if (handled) {
          event.stopPropagation();
          event.preventDefault();
        }
      });
    }

    // -----------------------------------------------------------------------
    // Activation.
    // -----------------------------------------------------------------------
    function activate() {
      if (state.phase !== 'idle') return;
      if (!config || !legacyHealthy() || !webgl2Available() || saveDataRequested()) {
        control.hidden = true;
        return;
      }
      state.phase = 'granting';
      control.hidden = true;
      control.setAttribute('aria-pressed', 'true');
      var hadFocus = document.activeElement === control;
      if (window.traVelGlobe3D) {
        window.traVelGlobe3D.stopTour({ root: root, permanent: true });
        window.traVelGlobe3D.cancelMotion(root);
      }
      setChip(true);
      root.classList.add('is-premium-planet-streaming');

      var overallTimer = window.setTimeout(function () {
        fail('the stream did not start in time', true);
      }, activationTimeoutMs());
      state.timeoutTimer = overallTimer;

      requestGrant().then(function (key) {
        if (state.phase !== 'granting') throw new Error('Activation was cancelled.');
        state.phase = 'loading';
        return loadCesium().then(function (loaded) {
          Cesium = loaded;
          scratchPosition = new Cesium.Cartesian3();
          scratchWindow = new Cesium.Cartesian2();
          scratchNormal = new Cesium.Cartesian3();
          scratchCameraNormal = new Cesium.Cartesian3();
          occluder = new Cesium.EllipsoidalOccluder(Cesium.Ellipsoid.WGS84, new Cesium.Cartesian3(1, 0, 0));
          toWindowCoordinates = Cesium.SceneTransforms.worldToWindowCoordinates || Cesium.SceneTransforms.wgs84ToWindowCoordinates;
          if (typeof toWindowCoordinates !== 'function') throw new Error('Cesium projection API is unavailable.');
          return key;
        });
      }).then(function (key) {
        var stage = document.createElement('div');
        stage.className = 'globe-premium-stage';
        stage.setAttribute('data-planet-stage', 'true');
        stage.setAttribute('role', 'application');
        stage.setAttribute('aria-label', 'כדור הארץ האמיתי. גררו לסיבוב, גלגלו או צבטו להתקרבות.');
        stage.tabIndex = 0;
        var credits = document.createElement('div');
        credits.className = 'globe-premium-credits';
        credits.setAttribute('data-planet-credits', 'true');
        legacyCanvas.after(stage);
        stage.append(credits);
        state.stage = stage;
        state.credits = credits;

        var widget = new Cesium.CesiumWidget(stage, {
          baseLayer: false,
          globe: false,
          skyBox: false,
          skyAtmosphere: false,
          scene3DOnly: true,
          requestRenderMode: false,
          creditContainer: credits,
          contextOptions: { webgl: { alpha: true, powerPreference: 'high-performance' } }
        });
        state.widget = widget;
        widget.scene.backgroundColor = Cesium.Color.TRANSPARENT;
        if (widget.scene.sun) widget.scene.sun.show = false;
        if (widget.scene.moon) widget.scene.moon.show = false;
        if (widget.scene.fog) widget.scene.fog.enabled = false;
        widget.scene.screenSpaceCameraController.minimumZoomDistance = MIN_CAMERA_HEIGHT_M * 0.6;
        widget.scene.screenSpaceCameraController.maximumZoomDistance = MAX_CAMERA_HEIGHT_M;

        var opening = window.traVelGlobe3D ? window.traVelGlobe3D.cameraState(root) : null;
        var openingLatitude = opening && Number.isFinite(opening.latitude) ? opening.latitude : 31.5;
        var openingLongitude = opening && Number.isFinite(opening.longitude) ? opening.longitude : 34.9;
        var openingHeight = distanceToHeight(opening && Number.isFinite(opening.distance) ? opening.distance : 3.15);
        setViewAt(openingLatitude, openingLongitude, openingHeight);
        state.homeView = { latitude: openingLatitude, longitude: openingLongitude, height: openingHeight };

        var tilesetUrl = 'https://tile.googleapis.com/v1/3dtiles/root.json?key=' + encodeURIComponent(key);
        return Cesium.Cesium3DTileset.fromUrl(tilesetUrl, { showCreditsOnScreen: true }).then(function (tileset) {
          if (state.phase !== 'loading') {
            tileset.destroy();
            throw new Error('Activation was cancelled.');
          }
          state.tileset = tileset;
          widget.scene.primitives.add(tileset);
          return new Promise(function (resolve) {
            var settled = false;
            tileset.initialTilesLoaded.addEventListener(function () {
              if (settled) return;
              settled = true;
              resolve();
            });
          });
        });
      }).then(function () {
        if (state.phase !== 'loading') return;
        window.clearTimeout(state.timeoutTimer);
        state.timeoutTimer = 0;
        state.phase = 'active';
        setChip(false);
        root.classList.remove('is-premium-planet-streaming');
        root.classList.add('is-premium-planet-active');
        announce('כדור הארץ האמיתי פעיל. אפשר להתקרב עד רמת הרחוב.');
        bindStageGestures(state.stage);
        state.widget.scene.postRender.addEventListener(syncOverlays);
        state.observer = new IntersectionObserver(function (entries) {
          var onScreen = entries[0] ? entries[0].isIntersecting !== false : true;
          if (state.widget) state.widget.useDefaultRenderLoop = onScreen;
        }, { rootMargin: '120px' });
        state.observer.observe(root);
        if (hadFocus && state.stage) state.stage.focus({ preventScroll: true });
        var view = state.homeView;
        flyToPoint(view.latitude, view.longitude, clamp(view.height * FLY_IN_FACTOR, MIN_CAMERA_HEIGHT_M, MAX_CAMERA_HEIGHT_M), FLY_IN_SECONDS);
      }).catch(function (error) {
        var permanent = Boolean(error && error.permanentDenial);
        fail(permanent ? 'no session was granted' : 'the upgrade could not finish', !permanent);
      });
    }

    return {
      root: root,
      premiumActive: function () { return state.phase === 'active'; },
      premiumZoom: premiumZoom,
      flyToPoint: function (latitude, longitude, options) {
        var settings = options && typeof options === 'object' ? options : {};
        var pseudo = Number(settings.distance);
        var height = Number.isFinite(pseudo) ? distanceToHeight(pseudo) : clamp(cameraHeight(), MIN_CAMERA_HEIGHT_M, distanceToHeight(3.05));
        flyToPoint(latitude, longitude, height, 0.68);
      },
      cancelFlight: function () {
        if (state.widget) state.widget.camera.cancelFlight();
      }
    };
  }

  // ---------------------------------------------------------------------------
  // Public API bridge: the existing zoom buttons, discovery focus flights, and
  // region flights drive the premium camera whenever the target globe is
  // upgraded, and fall through untouched everywhere else. The legacy call
  // always runs first so pin classes, announcements, and the paused legacy
  // state stay perfectly in sync for an eventual restore.
  // ---------------------------------------------------------------------------
  function premiumFor(targetRoot) {
    for (var index = 0; index < controllers.length; index++) {
      if (!controllers[index].premiumActive()) continue;
      if (!targetRoot || controllers[index].root === targetRoot) return controllers[index];
    }
    return null;
  }

  function markerCoordinates(root, selector) {
    var marker = root.querySelector(selector);
    if (!marker) return null;
    var latitude = Number(marker.dataset.latitude);
    var longitude = Number(marker.dataset.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
    return { latitude: latitude, longitude: longitude };
  }

  function installApiBridge() {
    var api = window.traVelGlobe3D;
    if (!api || api.premiumPlanetBridged) return;
    api.premiumPlanetBridged = true;
    var original = {
      zoom: api.zoom,
      focusDestination: api.focusDestination,
      focusHub: api.focusHub,
      focusPoint: api.focusPoint,
      cancelMotion: api.cancelMotion
    };
    api.zoom = function (direction, options) {
      var targetRoot = options && typeof options === 'object' ? options.root : null;
      var premium = premiumFor(targetRoot);
      var handled = original.zoom.call(api, direction, options);
      if (premium) {
        premium.premiumZoom(direction === 'in' ? 'in' : 'out');
        return true;
      }
      return handled;
    };
    api.focusDestination = function (id, options) {
      var settings = typeof options === 'object' && options ? options : {};
      var premium = premiumFor(settings.root || null);
      if (!premium) return original.focusDestination.call(api, id, options);
      var result = original.focusDestination.call(api, id, Object.assign({}, settings, { animate: false }));
      var coordinates = markerCoordinates(premium.root, '.price-pin[data-destination="' + CSS.escape(String(id)) + '"]');
      if (coordinates) premium.flyToPoint(coordinates.latitude, coordinates.longitude, { distance: 3.05 });
      return result;
    };
    api.focusHub = function (id, options) {
      var settings = typeof options === 'object' && options ? options : {};
      var premium = premiumFor(settings.root || null);
      if (!premium) return original.focusHub.call(api, id, options);
      var result = original.focusHub.call(api, id, Object.assign({}, settings, { animate: false }));
      var coordinates = markerCoordinates(premium.root, '.exploration-hub[data-exploration-hub="' + CSS.escape(String(id)) + '"]');
      if (coordinates) premium.flyToPoint(coordinates.latitude, coordinates.longitude, { distance: 3.05 });
      return result;
    };
    api.focusPoint = function (latitude, longitude, options) {
      var settings = typeof options === 'object' && options ? options : {};
      var premium = premiumFor(settings.root || null);
      var handled = original.focusPoint.call(api, latitude, longitude, options);
      if (premium && Number.isFinite(Number(latitude)) && Number.isFinite(Number(longitude))) {
        premium.flyToPoint(Number(latitude), Number(longitude), { distance: Number(settings.distance) || 2.9 });
        return true;
      }
      return handled;
    };
    api.cancelMotion = function (targetRoot) {
      var premium = premiumFor(targetRoot || null);
      if (premium) premium.cancelFlight();
      return original.cancelMotion.call(api, targetRoot);
    };
  }

  function initialize() {
    if (!config || !window.traVelGlobe3D) return;
    installApiBridge();
    document.querySelectorAll('[data-globe-3d]').forEach(function (root) {
      var control = root.querySelector('[data-planet-upgrade]');
      if (!control) return;
      var controller = createController(root, control);
      if (controller) controllers.push(controller);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
}());
