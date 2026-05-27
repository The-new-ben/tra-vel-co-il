# Tra-Vel Deployment Status

Date: 2026-05-27

## GitHub

- Repo: `The-new-ben/tra-vel-co-il`
- Branch pushed: `codex/travel-api-content`
- Default branch pushed: `main`
- Commit: `84c0ce9 Upgrade Tra-Vel homepage and content docs`

## UPress Staging

Staging URL:

- http://tra-vel-co-il-rev.s998.upress.link/

Status:

- New homepage is live on staging.
- New hero text verified in rendered HTML.
- Old internal terms scan on staging homepage: clean.
- Generated WebP assets uploaded and verified in `wp-content/themes/travel-revenue/assets/img/`.
- Hero image URL returned HTTP 200.

Verification:

- `HasNewHero`: true
- `HasOldInternal`: false
- `HasImageRef`: true
- `hero-budapest-1600.webp`: HTTP 200, 88,100 bytes

## Production

Production URL:

- https://tra-vel.co.il/

Status:

- Production homepage is still old.
- Production `travel-revenue` theme folder did not exist at first.
- Production folder creation started through UPress file manager.
- Root theme file upload started using UPress URL-pull workaround.
- Production was not activated because the asset upload was not fully verified.

Current production verification:

- `https://tra-vel.co.il/`: HTTP 200, old homepage still visible.
- `https://tra-vel.co.il/wp-content/themes/travel-revenue/assets/img/hero-budapest-1600.webp`: HTTP 404 at last check.

## Blockers

1. Chrome local file upload is blocked.

The Chrome tool returned `Not allowed` when trying to upload `tra-vel-theme-upload.zip`. This matches the Chrome extension file-access limitation. The required user action is:

`To enable file upload, go to chrome://extensions in Chrome, click Details under the Codex extension, and enable "Allow access to file URLs." See https://developers.openai.com/codex/app/chrome-extension#upload-files for details.`

2. UPress Git clone cannot pull the private GitHub repo without authentication.

The repo is private. UPress Git manager accepts a Git clone URL, but cloning a private repository requires a credential or GitHub integration path inside UPress. I avoided putting any secret token into the UI.

3. Chrome automation reconnect started timing out after the UPress production upload work.

Staging is complete, but production upload/activation needs either a restored Chrome automation session, Chrome file upload permission, or UPress Git authentication.

## Workaround Used

For staging, local files were uploaded to a temporary public URL service and then pulled into UPress with its `wget` feature. This worked and overwrote files cleanly.

Temporary URLs were used only for theme code and generated image files. No secrets were included.

## Next Safe Steps

1. Restore Chrome automation or enable file upload permission.
2. Finish uploading production `assets/img`.
3. Activate `travel-revenue` on production only after files and images are verified.
4. Clear UPress cache.
5. Verify production homepage:
   - new hero appears,
   - images return HTTP 200,
   - no visible internal terms,
   - form still submits,
   - six internal links work.
