# TN QR Codes

TN QR Codes adds QR codes to public WordPress post types. It also adds Tools > Trackable QR Codes, where administrators can create a QR code for any HTTP or HTTPS URL, set UTM values and additional query parameters, download the image, and see its visit count. Each scan passes through a site URL that increments the count and redirects to the saved destination.

## Release

Build the release ZIP with:

```bash
scripts/build-plugin-zip.sh
```

The ZIP is written to `dist/tn-qrcodes.zip` and contains the `tn-qrcodes/` plugin folder at the top level.

## Controller migration — 1.5.1

Updates are now supplied by [TN Update Controller](https://github.com/cchatterton/tn-update-controller). The old independent updater has been removed. Plugin identity, feature settings and activation scope are unchanged. Install/activate/check links use local controller detection and never fetch release metadata while rendering. Legacy update guidance below or in historical notes is superseded by this controller integration.

Release order: build and validate the ZIP, publish its matching GitHub release asset, then publish verified controller catalogue metadata. Existing update.json endpoints are maintained only for older, not-yet-migrated installations, after asset verification.
