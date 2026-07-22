(function () {
  'use strict';

  // Pillar Earth page module (theme 1.30.0). One small file, enqueued only
  // on the pillar template. It publishes pin activations as the same
  // travelglobe:select events the globe module already anchors its
  // selection card to, and keeps the below-globe site board in sync.
  // Pillar pins are server-rendered with data-selection-bound="true", so
  // the shared discovery pin binding never claims them; this module is
  // their only click owner. Scroll law: no wheel, touchmove or scroll
  // listeners of any kind.

  function markSelectedCard(siteId) {
    document.querySelectorAll('[data-pillar-site]').forEach(card => {
      const selected = card.dataset.pillarSite === siteId;
      card.classList.toggle('is-selected', selected);
      if (selected) card.setAttribute('aria-current', 'true');
      else card.removeAttribute('aria-current');
    });
  }

  function publishSiteSelection(root, pin, inputType) {
    const siteId = String(pin.dataset.destination || '');
    const latitude = Number(pin.dataset.latitude);
    const longitude = Number(pin.dataset.longitude);
    if (!siteId || !Number.isFinite(latitude) || latitude < -90 || latitude > 90
      || !Number.isFinite(longitude) || longitude < -180 || longitude > 180) return false;
    const siteName = String(pin.dataset.siteName || '').trim() || pin.textContent.trim();
    if (window.traVelGlobe3D) {
      window.traVelGlobe3D.focusDestination(siteId, { root, animate: true, pulse: false, announce: true, duration: 620 });
    }
    markSelectedCard(siteId);
    // The globe module anchors its selection card to this exact event on
    // pillar globes. nearestDestination stays empty on purpose: pillar
    // site identifiers are not map destinations, so the card's full-map
    // link must not carry one.
    root.dispatchEvent(new CustomEvent('travelglobe:select', {
      bubbles: true,
      detail: {
        latitude: Number(latitude.toFixed(4)),
        longitude: Number(longitude.toFixed(4)),
        inputType,
        supported: true,
        supportedRadiusKm: 100,
        selectionKind: 'destination',
        planningAction: 'open_destination',
        nearestDestination: '',
        nearestLabel: siteName,
        distanceKm: 0,
        hubId: '',
        hubCity: '',
        hubCountry: '',
        hubIataSearchCode: '',
        hubLiveSearchScopes: [],
        hubDistanceKm: null
      }
    }));
    return true;
  }

  function initialize() {
    const root = document.querySelector('[data-globe-3d][data-globe-pillar]');
    if (!root) return;

    // The globe module replays deferred marker taps through the synthetic
    // marker.click(), whose event.detail is always 0, so detail alone
    // cannot separate keyboard from pointer here. A recent pointerdown on
    // the Earth is the honest signal.
    let lastPointerAt = 0;
    root.addEventListener('pointerdown', () => { lastPointerAt = performance.now(); });

    root.addEventListener('click', event => {
      const pin = event.target.closest('.price-pin[data-destination]');
      if (!pin || pin.hidden) return;
      const inputType = performance.now() - lastPointerAt <= 1200 ? 'pointer' : 'keyboard';
      if (publishSiteSelection(root, pin, inputType)) event.preventDefault();
    });

    // "Show on the Earth" buttons ship hidden so a browser without
    // JavaScript never sees a dead control; this module reveals them.
    document.querySelectorAll('[data-pillar-site-focus]').forEach(button => {
      button.hidden = false;
      button.addEventListener('click', () => {
        const siteId = String(button.dataset.pillarSiteFocus || '');
        const pin = root.querySelector(`.price-pin[data-destination="${CSS.escape(siteId)}"]`);
        if (!pin) return;
        publishSiteSelection(root, pin, 'pointer');
      });
    });

    // The anchored card's details link lands on the selected site's own
    // entry when one is marked.
    document.addEventListener('click', event => {
      const detailsLink = event.target.closest('a[href="#pillar-sites"]');
      if (!detailsLink) return;
      const selected = document.querySelector('[data-pillar-site].is-selected');
      if (selected && typeof selected.scrollIntoView === 'function') {
        event.preventDefault();
        selected.scrollIntoView({ block: 'start' });
        selected.querySelector('[data-pillar-site-focus]')?.focus?.({ preventScroll: true });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
}());
