# Tra-Vel 3D globe runtime

## Purpose

The production globe is a progressively enhanced travel-discovery surface. It uses a native WebGL sphere when the browser supports WebGL and preserves the existing Earth image as a fallback. Destination details, route comparisons, filters, and commercial actions remain outside the globe so map movement is never obstructed.

## Runtime architecture

- `assets/js/globe-3d.js` is loaded only for `page-map.php`.
- `earth-blue-marble-2048.jpg` is a 2048 by 1024 power-of-two texture optimized from the existing 5400 by 2700 source.
- The sphere contains 56 latitude segments and 88 longitude segments.
- Device pixel ratio is capped at 1.75 to control mobile GPU memory and fill rate.
- Rendering is event driven. The globe redraws after interaction, resize, selection, or focus animation instead of running a permanent animation loop.
- Intersection and document-visibility observers stop rendering work when the globe is outside the active view.
- WebGL context loss or initialization failure leaves the static Earth fallback visible.

## Interaction contract

- Pointer drag rotates the globe.
- Destination buttons remain real DOM controls above the canvas.
- Selecting or focusing a destination rotates it to the center and updates the information section below the map.
- Plus and minus controls provide a simple-pointer zoom alternative.
- Arrow keys rotate the globe, plus and minus zoom, and Home resets the view.
- Reduced-motion preferences disable animated camera transitions.
- Mobile non-selected destinations use 24 by 24 pixel hit targets with smaller visual dots.

## Geographic projection

Destination latitude and longitude come from the discovery contract. They are converted to sphere coordinates and projected with the same yaw, pitch, distance, and perspective used by the WebGL camera. Points on the rear hemisphere are hidden. Visible labels are ordered by selection and camera depth, then collision checked before display.

The selected route is drawn as a non-interactive SVG curve between Ben Gurion Airport and the selected destination when both endpoints are on the visible hemisphere.

## Data and commercial safety

The globe controls place and selection only. Price, availability, savings, ratings, route duration, and commercial conditions remain governed by the supplier data-mode gates in `app.js`. Demo fixtures cannot be converted into customer offers by the globe.

## Imagery

The texture is derived from NASA Blue Marble: Next Generation imagery. The public map page links to the NASA Visible Earth source. The optimized texture is bundled with the theme, so no third-party script, tile request, token, or browser-exposed credential is required.

## Future map-engine boundary

The native globe is the token-free production foundation. A licensed vector or terrain provider can later replace the sphere renderer without changing the destination, supplier, route, content, or commerce contracts. A provider upgrade must preserve:

- Map interaction without interface obstruction
- Keyboard and simple-pointer alternatives
- Collision and zoom-level label rules
- Supplier attribution and caching restrictions
- No client-side secrets
- No unverified commercial claims
- Mobile performance budgets

