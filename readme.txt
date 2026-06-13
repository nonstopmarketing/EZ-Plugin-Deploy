=== EZ Plugin Deploy ===
Contributors: nonstopmarketing
Tags: plugin, install, upload, developer, drag-and-drop
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.8.3
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Large drag-and-drop zone on the Plugins page for instant plugin installs. Deactivates and removes the old version automatically — built for fast plugin development.

== Description ==

EZ Plugin Deploy adds a large drag-and-drop zone to the top of wp-admin/plugins.php so you can install and activate a plugin in one step — no clicking through Upload Plugin, no "Destination folder already exists" errors, no manual deactivate-then-delete.

Drop a .zip from any window onto the zone and the plugin:

* Deactivates the existing version (if active)
* Removes the old plugin folder
* Installs the new version
* Activates it automatically

Also adds an **EZ Delete** link to every inactive plugin row that uses WP_Filesystem directly — useful on hosts where WordPress's native Delete link fails due to file permission issues.

Designed for plugin developers who iterate quickly and want to skip the normal upload workflow entirely.

== Installation ==

1. Download the zip from https://nonstopdev.us/plugin/ez-plugin-deploy-plugin/
2. Go to wp-admin > Plugins > Add New > Upload Plugin
3. Upload the zip and activate
4. The drop zone appears at the top of your Plugins page from that point on

== Frequently Asked Questions ==

= Does this work on production sites? =

It is designed for development environments. It requires Administrator-level access (install_plugins, activate_plugins, delete_plugins capabilities) so it cannot be used by lower-privileged users. That said, there is no technical reason it cannot run on production — use your own judgment.

= What happens if the plugin name changes between versions? =

The slug is read directly from inside the zip before installing. As long as the top-level folder name in the zip matches the installed plugin's folder, the old version is removed first. If the names differ, the old version stays installed and you can remove it with EZ Delete.

= Why can't I delete a plugin using WordPress's built-in Delete link? =

Some hosting environments write plugin files as a different system user than the one running WordPress's web process. The EZ Delete link uses WP_Filesystem through the same path as the installer, which typically has the right permissions.

= Is it safe? =

All AJAX actions are protected by nonce verification and capability checks. Uploaded files are validated against ZIP magic bytes server-side. Plugin folder names are restricted to a strict allowlist (letters, numbers, hyphens, underscores) before any filesystem operation. All delete paths are confined to WP_PLUGIN_DIR using realpath.

== Screenshots ==

1. The drop zone at the top of wp-admin/plugins.php
2. EZ Delete link on an inactive plugin row

== Changelog ==

= 1.8.3 =
* Fix: release zip is now built with git archive (forward-slash paths) instead of PowerShell Compress-Archive, which wrote Windows backslash separators that WordPress mis-unpacked into a nested, wrongly-named folder

= 1.8.2 =
* Fix: drop-zone installs now force the plugin into a correctly-named folder via upgrader_source_selection, collapsing any nesting (e.g. "my-plugin-1.2.3/my-plugin/") that caused "Plugin file does not exist" on activation

= 1.8.1 =
* Fix: "Plugin file does not exist" — fallback plugin scan now uses get_plugins() scoped to the slug folder instead of filtering against the pre-deactivation active list (which blocked finding updated plugins)

= 1.8.0 =
* Fix: EZ Delete now uses native PHP unlink/rmdir instead of WP_Filesystem — works on shared hosts where WP_Filesystem requires FTP credentials
* Fix: zip slug is now read from the original tmp file BEFORE File_Upload_Upgrader moves it — old version now reliably deactivates and is removed before install
* Added ez_rmdir() and ez_slug_from_zip() helpers

= 1.7.2 =
* Revert: EZ Delete restored on all inactive plugin rows (needed for dev workflow)

= 1.7.1 =
* Fix: EZ Delete link now only appears on the EZ Plugin Deploy row, not on all inactive plugins

= 1.7.0 =
* Drop zone now appears on wp-admin/plugin-install.php in addition to plugins.php

= 1.6.1 =
* Fix: wrap activate_plugin() in output buffering so stray echo output from activation hooks does not corrupt the JSON response and cause a 500 error

= 1.6.0 =
* Security: nonce verified before capability check on all AJAX handlers
* Security: ZIP magic-byte validation added server-side
* Security: strict [a-zA-Z0-9_-] allowlist on extracted zip slug
* Security: realpath confinement added to upload handler pre-install delete
* Fix: undefined $active_before variable in fallback plugin scan
* Fix: delete return value now checked in upload handler
* Fix: realpath prefix check uses trailing directory separator
* Fix: confirm dialog in EZ Delete now escapes the plugin filename
* Fix: detail messages no longer echo attacker-controlled values
* Fix: self-cleanup of old filename now requires manage_options capability

= 1.5.1 =
* Fix: guard against duplicate define() fatal when both old and new filenames exist in the same folder
* Self-cleanup: automatically removes old wp-ez-add.php on admin_init

= 1.5.0 =
* Renamed plugin to EZ Plugin Deploy
* Plugin URI updated to nonstopdev.us

= 1.4.1 =
* Added download link inside drop zone

= 1.4.0 =
* Added EZ Delete action link on inactive plugin rows
* Delete uses WP_Filesystem for compatibility with restrictive hosting

= 1.3.0 =
* Fix: "Destination folder already exists" — old folder deleted before installing
* Fix: ZipArchive slug extraction now iterates all entries instead of assuming index 0
* Added multiple fallback strategies for locating installed plugin's main file
* Graceful partial-success when activation cannot complete automatically

= 1.2.0 =
* Fix: drop zone moved outside .wrap via JS so it spans full content width
* Fix: centering issue where zone stopped at the Screen Options tab

= 1.1.0 =
* Switched to File_Upload_Upgrader for reliable installs
* Added fallback plugin file detection when plugin_info() returns null

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.6.1 =
Fixes a 500 / "Unexpected server response" error caused by plugin activation hooks that produce output.
