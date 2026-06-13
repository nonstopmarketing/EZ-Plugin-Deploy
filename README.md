# EZ Plugin Deploy

A WordPress plugin that adds a large drag-and-drop zone to the top of **wp-admin/plugins.php** for instant plugin installs — no more clicking through the Upload Plugin flow. Designed for rapid plugin development: drop a zip, and the old version is deactivated, removed, and replaced automatically.

**[Free Download → nonstopdev.us](https://nonstopdev.us/plugin/ez-plugin-deploy-plugin/)**

---

## Features

- **Large drop zone** on the Plugins page — easy to target from another window without precise aiming
- **Auto-deactivate** the existing version before installing
- **Auto-delete** the old plugin folder so WordPress never chokes on "Destination folder already exists"
- **Auto-activate** the newly installed version
- **EZ Delete** link on every inactive plugin row — bypasses host permission issues that block WordPress's native delete
- Works as a **click-to-browse** file picker too, not just drag-and-drop
- Graceful partial-success if activation fails — installs the files and lets you activate manually

---

## Installation

1. Download `wp-ez-plugin-deploy.zip` from the [releases page](https://github.com/nonstopmarketing/EZ-Plugin-Deploy/releases) or [nonstopdev.us](https://nonstopdev.us/plugin/ez-plugin-deploy-plugin/)
2. Go to **wp-admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Done — the drop zone appears at the top of your Plugins page from now on

---

## How to Use

### Install / Update a Plugin

1. Go to **wp-admin → Plugins**
2. Drag a `.zip` file from your file manager and drop it anywhere in the blue-bordered zone at the top of the page
3. The plugin automatically:
   - Deactivates and removes the old version (if present)
   - Installs the new version
   - Activates it
4. The page reloads with the updated plugin active

You can also click the zone to open a file picker.

### Delete a Plugin

1. Deactivate the plugin using the normal **Deactivate** link
2. An **EZ Delete** link (in red) appears in the plugin row's action links
3. Click it → confirm → the plugin folder is deleted immediately without a page reload

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- User must have `install_plugins`, `activate_plugins`, and `delete_plugins` capabilities (Administrators by default)
- PHP `ZipArchive` extension (standard on most hosts; falls back to filename-based slug detection if unavailable)

---

## Security

All actions are protected by:

- **Nonce verification** on every AJAX request (checked before any capability check)
- **Capability checks** — `install_plugins` + `activate_plugins` to upload, `delete_plugins` to delete
- **ZIP magic-byte validation** — rejects non-ZIP files server-side even if renamed with a `.zip` extension
- **Strict slug allowlist** — plugin folder names extracted from the ZIP must match `[a-zA-Z0-9_-]` only; no path traversal possible
- **Realpath confinement** — all filesystem operations are verified to resolve inside `WP_PLUGIN_DIR` before execution
- **Activation hooks fire normally** — security/audit plugins that monitor plugin activation are not bypassed

This plugin is intended for **development environments and trusted administrators only**. It is not recommended for production sites with multiple user roles.

---

## Changelog

### 1.6.0
- Security hardening: nonce checked before capability on all AJAX handlers
- ZIP magic-byte validation added server-side
- Strict `[a-zA-Z0-9_-]` allowlist on extracted zip slug (prevents path traversal)
- Realpath confinement added to upload handler's pre-install directory deletion
- Fixed undefined `$active_before` variable in fallback plugin scan (was `$active_plugins`)
- Delete return value now checked in upload handler
- Activation hooks no longer suppressed (`$silent` flag removed)
- Self-cleanup of old filename now requires `manage_options` capability
- Confirm dialog in EZ Delete now escapes the plugin filename
- Detail messages no longer echo attacker-controlled values

### 1.5.1
- Self-cleanup: automatically removes old `wp-ez-add.php` file if present alongside renamed file
- Guard against duplicate `define()` fatal when both filenames exist simultaneously

### 1.5.0
- Renamed to EZ Plugin Deploy
- Plugin URI updated to nonstopdev.us

### 1.4.1
- Added download link inside drop zone

### 1.4.0
- Added **EZ Delete** action link on inactive plugin rows
- Delete uses `WP_Filesystem` for compatibility with restrictive hosting environments

### 1.3.0
- Fixed "Destination folder already exists" error — old folder is now deleted before installing
- ZipArchive slug extraction now iterates all entries instead of assuming index 0 is the folder
- Multiple fallback strategies for locating the installed plugin's main file
- Graceful partial-success when activation cannot complete automatically

### 1.2.0
- Drop zone moved outside `.wrap` via JS so it spans the full content width
- Fixed centering issue where zone stopped at the Screen Options tab

### 1.1.0
- Switched to `File_Upload_Upgrader` (WordPress's own file handler) for reliable installs
- Added fallback plugin file detection when `plugin_info()` returns null

### 1.0.0
- Initial release

---

## Author

**NonStop Dev** — [nonstopdev.us](https://nonstopdev.us)

GitHub: [github.com/nonstopmarketing](https://github.com/nonstopmarketing)

---

## License

GPL-2.0+
