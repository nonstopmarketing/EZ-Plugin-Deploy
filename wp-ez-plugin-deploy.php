<?php
/**
 * Plugin Name: EZ Plugin Deploy
 * Plugin URI:  https://nonstopdev.us/plugin/ez-plugin-deploy-plugin/
 * Description: Large drag-and-drop zone on the Plugins page — deactivates & removes old version before installing.
 * Version:     1.7.1
 * Author:      NonStop Dev
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

// Guard against the old wp-ez-add.php being present in the same folder
if ( defined( 'WP_EZ_ADD_VERSION' ) ) {
	return;
}
define( 'WP_EZ_ADD_VERSION', '1.7.1' );

// Self-cleanup: delete the old filename if it still exists alongside this one
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$old = __DIR__ . '/wp-ez-add.php';
	if ( file_exists( $old ) ) {
		@unlink( $old );
	}
} );

/* ------------------------------------------------------------------ */
/*  Styles + JS injected into plugins.php <head>                       */
/* ------------------------------------------------------------------ */

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'plugin-install' ], true ) ) {
		return;
	}

	$nonce        = wp_create_nonce( 'wp_ez_add_upload' );
	$delete_nonce = wp_create_nonce( 'wp_ez_add_delete' );
	$ajax         = admin_url( 'admin-ajax.php' );
	?>
	<style id="wp-ez-add-styles">
		#wp-ez-add-wrap {
			/* Sits inside #wpbody-content, outside .wrap — full width minus WP body padding */
			padding: 16px 20px 0;
			box-sizing: border-box;
		}
		#wp-ez-add-zone {
			position: relative;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			gap: 10px;
			width: 100%;
			height: 160px;
			border: 3px dashed #a0aec0;
			border-radius: 10px;
			background: #f9fafb;
			cursor: pointer;
			transition: background 0.18s, border-color 0.18s;
			box-sizing: border-box;
		}
		#wp-ez-add-zone.ez-hover {
			background: #eaf4ff;
			border-color: #0073aa;
		}
		#wp-ez-add-zone.ez-uploading {
			pointer-events: none;
			opacity: 0.65;
		}
		#wp-ez-add-zone .ez-icon {
			font-size: 40px;
			line-height: 1;
			color: #a0aec0;
			transition: color 0.18s;
			pointer-events: none;
		}
		#wp-ez-add-zone.ez-hover .ez-icon { color: #0073aa; }
		#wp-ez-add-zone .ez-label {
			font-size: 15px;
			color: #555;
			text-align: center;
			pointer-events: none;
		}
		#wp-ez-add-zone .ez-sub {
			font-size: 12px;
			color: #999;
			pointer-events: none;
		}
		/* Invisible full-zone file input */
		#wp-ez-add-zone input[type="file"] {
			position: absolute;
			inset: 0;
			opacity: 0;
			cursor: pointer;
			width: 100%;
			height: 100%;
			font-size: 0;
		}
		#wp-ez-add-progress {
			display: none;
			margin: 10px 0 0;
			padding: 12px 16px;
			background: #fff;
			border: 1px solid #dde;
			border-radius: 6px;
			font-size: 13px;
			line-height: 1.6;
		}
		#wp-ez-add-progress.visible { display: block; }
		#wp-ez-add-progress .ez-ok  { color: #1a8a1a; font-weight: 600; }
		#wp-ez-add-progress .ez-err { color: #cc0000; font-weight: 600; }
		#wp-ez-add-progress pre {
			margin: 6px 0 0;
			font-size: 11px;
			white-space: pre-wrap;
			word-break: break-all;
			color: #555;
		}
		/* EZ Delete link injected into each plugin row */
		.ez-delete-link {
			color: #b32d2e !important;
		}
		.ez-delete-link:hover {
			color: #8a1a1b !important;
		}
		/* Dim a row while its delete is in flight */
		tr.ez-deleting td {
			opacity: 0.4;
			pointer-events: none;
		}
	</style>

	<script id="wp-ez-add-script">
	(function () {
		document.addEventListener('DOMContentLoaded', function () {

			// Move our wrapper to be the FIRST child of #wpbody-content so it spans
			// the full content area rather than being constrained by .wrap margins.
			var wrapper  = document.getElementById('wp-ez-add-wrap');
			var bodyContent = document.getElementById('wpbody-content');
			if (wrapper && bodyContent && bodyContent.firstChild) {
				bodyContent.insertBefore(wrapper, bodyContent.firstChild);
			}

			var zone      = document.getElementById('wp-ez-add-zone');
			var progress  = document.getElementById('wp-ez-add-progress');
			var fileInput = zone ? zone.querySelector('input[type="file"]') : null;

			if (!zone) return;

			// Drag highlight
			['dragenter', 'dragover'].forEach(function (evt) {
				zone.addEventListener(evt, function (e) {
					e.preventDefault();
					zone.classList.add('ez-hover');
				});
			});
			['dragleave', 'drop'].forEach(function (evt) {
				zone.addEventListener(evt, function () {
					zone.classList.remove('ez-hover');
				});
			});

			zone.addEventListener('drop', function (e) {
				e.preventDefault();
				var files = e.dataTransfer && e.dataTransfer.files;
				if (files && files.length) upload(files[0]);
			});

			if (fileInput) {
				fileInput.addEventListener('change', function () {
					if (fileInput.files && fileInput.files.length) {
						upload(fileInput.files[0]);
						fileInput.value = '';
					}
				});
			}

			function upload(file) {
				if (!file.name.toLowerCase().endsWith('.zip')) {
					showResult('error', 'Only .zip files are accepted.');
					return;
				}

				zone.classList.add('ez-uploading');
				progress.className = 'visible';
				progress.innerHTML = '<span>⏳ Installing <strong>' + esc(file.name) + '</strong>…</span>';

				var data = new FormData();
				data.append('action',    'wp_ez_add_upload');
				data.append('_nonce',    <?php echo wp_json_encode( $nonce ); ?>);
				data.append('pluginzip', file, file.name);

				var xhr = new XMLHttpRequest();
				xhr.open('POST', <?php echo wp_json_encode( $ajax ); ?>, true);

				xhr.upload.onprogress = function (e) {
					if (e.lengthComputable) {
						var pct = Math.round(e.loaded / e.total * 100);
						progress.innerHTML = '<span>⏳ Uploading… ' + pct + '%</span>';
					}
				};

				xhr.onload = function () {
					zone.classList.remove('ez-uploading');
					try {
						var res = JSON.parse(xhr.responseText);
						if (res.success) {
							showResult('ok', res.data.message, res.data.detail || '');
							setTimeout(function () { location.reload(); }, 1800);
						} else {
							showResult('error', res.data.message || 'Unknown error.', res.data.detail || '');
						}
					} catch (err) {
						showResult('error', 'Unexpected server response.', xhr.responseText.slice(0, 500));
					}
				};

				xhr.onerror = function () {
					zone.classList.remove('ez-uploading');
					showResult('error', 'Network error — upload failed.');
				};

				xhr.send(data);
			}

			function showResult(type, msg, detail) {
				progress.className = 'visible';
				var cls  = type === 'ok' ? 'ez-ok' : 'ez-err';
				var icon = type === 'ok' ? '✅' : '❌';
				progress.innerHTML =
					'<span class="' + cls + '">' + icon + ' ' + esc(msg) + '</span>' +
					(detail ? '<pre>' + esc(detail) + '</pre>' : '');
			}

			function esc(s) {
				return String(s)
					.replace(/&/g, '&amp;').replace(/</g, '&lt;')
					.replace(/>/g, '&gt;').replace(/"/g, '&quot;');
			}

			// ------------------------------------------------------------------
			// Inject "EZ Delete" into every inactive plugin row's action links.
			// Skips any row that already has a native Delete link working fine.
			// ------------------------------------------------------------------
			var deleteNonce = <?php echo wp_json_encode( $delete_nonce ); ?>;
			var ajaxUrl     = <?php echo wp_json_encode( $ajax ); ?>;
			var ownPlugin   = <?php echo wp_json_encode( plugin_basename( __FILE__ ) ); ?>;

			document.querySelectorAll('#the-list tr[data-plugin]').forEach(function (row) {
				var pluginFile = row.getAttribute('data-plugin');

				// Only add EZ Delete to this plugin's own row
				if (pluginFile !== ownPlugin) return;

				// Only when inactive
				if (row.classList.contains('active')) return;

				var actionDiv = row.querySelector('.row-actions');
				if (!actionDiv) return;

				// Don't double-add
				if (actionDiv.querySelector('.ez-delete-link')) return;

				var sep  = document.createElement('span');
				sep.textContent = ' | ';

				var link = document.createElement('a');
				link.href = '#';
				link.className = 'ez-delete-link';
				link.textContent = 'EZ Delete';
				link.setAttribute('data-plugin', pluginFile);

				link.addEventListener('click', function (e) {
					e.preventDefault();
					if (!confirm('Delete "' + pluginFile.replace(/"/g, '\\"') + '"? This cannot be undone.')) return;
					ezDeletePlugin(pluginFile, row);
				});

				var span = document.createElement('span');
				span.appendChild(sep);
				span.appendChild(link);
				actionDiv.appendChild(span);
			});

			function ezDeletePlugin(pluginFile, row) {
				if (row) row.classList.add('ez-deleting');

				var data = new FormData();
				data.append('action',      'wp_ez_add_delete');
				data.append('_nonce',      deleteNonce);
				data.append('plugin_file', pluginFile);

				var xhr = new XMLHttpRequest();
				xhr.open('POST', ajaxUrl, true);
				xhr.onload = function () {
					if (row) row.classList.remove('ez-deleting');
					try {
						var res = JSON.parse(xhr.responseText);
						if (res.success) {
							if (row) row.remove();
						} else {
							alert('EZ Delete failed: ' + (res.data && res.data.message || 'Unknown error'));
						}
					} catch (err) {
						alert('EZ Delete: unexpected server response.');
					}
				};
				xhr.onerror = function () {
					if (row) row.classList.remove('ez-deleting');
					alert('EZ Delete: network error.');
				};
				xhr.send(data);
			}
		});
	})();
	</script>
	<?php
} );

/* ------------------------------------------------------------------ */
/*  Drop zone markup — rendered via admin_notices, moved by JS         */
/* ------------------------------------------------------------------ */

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'plugin-install' ], true ) ) {
		return;
	}
	?>
	<div id="wp-ez-add-wrap">
		<div id="wp-ez-add-zone">
			<span class="ez-icon">📦</span>
			<span class="ez-label"><strong>Drop a plugin .zip here</strong> to install &amp; activate</span>
			<span class="ez-sub">Or click to browse &mdash; existing version is deactivated &amp; removed automatically</span>
			<span class="ez-sub"><a href="https://nonstopdev.us/plugin/ez-plugin-deploy-plugin/" target="_blank" rel="noopener" style="color:#0073aa;">EZ Plugin Deploy</a> &mdash; free download</span>
			<input type="file" accept=".zip" title="Select plugin zip">
		</div>
		<div id="wp-ez-add-progress"></div>
	</div>
	<?php
} );

/* ------------------------------------------------------------------ */
/*  Helper: find the main plugin file inside any directory             */
/* ------------------------------------------------------------------ */

/**
 * Recursively scans $dir (up to 2 levels) for a PHP file with a Plugin Name header.
 * Returns a path relative to WP_PLUGIN_DIR, or empty string on failure.
 */
function ez_find_plugin_file_in_dir( $dir, $relative_prefix, $depth = 0 ) {
	if ( ! is_dir( $dir ) || $depth > 2 ) {
		return '';
	}
	foreach ( glob( $dir . '/*.php' ) ?: [] as $php_file ) {
		$headers = get_plugin_data( $php_file, false, false );
		if ( ! empty( $headers['Name'] ) ) {
			return ltrim( str_replace( WP_PLUGIN_DIR, '', $php_file ), '/\\' );
		}
	}
	// Recurse into subdirectories
	foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) ?: [] as $subdir ) {
		$found = ez_find_plugin_file_in_dir( $subdir, $relative_prefix, $depth + 1 );
		if ( $found ) {
			return $found;
		}
	}
	return '';
}

/* ------------------------------------------------------------------ */
/*  AJAX handler                                                        */
/* ------------------------------------------------------------------ */

add_action( 'wp_ajax_wp_ez_add_upload', function () {

	// MEDIUM-1: nonce first, capability second
	if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'wp_ez_add_upload' ) ) {
		wp_send_json_error( [ 'message' => 'Security check failed.' ] );
	}

	if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
	}

	if ( empty( $_FILES['pluginzip'] ) || $_FILES['pluginzip']['error'] !== UPLOAD_ERR_OK ) {
		$code = isset( $_FILES['pluginzip']['error'] ) ? (int) $_FILES['pluginzip']['error'] : -1;
		wp_send_json_error( [ 'message' => 'File upload error (code ' . $code . ').' ] );
	}

	// HIGH-4: verify ZIP magic bytes server-side before doing anything else
	$tmp_path = $_FILES['pluginzip']['tmp_name'];
	$fh       = @fopen( $tmp_path, 'rb' );
	if ( ! $fh ) {
		wp_send_json_error( [ 'message' => 'Could not read uploaded file.' ] );
	}
	$magic = fread( $fh, 4 );
	fclose( $fh );
	if ( $magic !== "PK\x03\x04" ) {
		wp_send_json_error( [ 'message' => 'Uploaded file is not a valid ZIP archive.' ] );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/class-file-upload-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	WP_Filesystem();
	global $wp_filesystem;

	// Move upload to WP-managed tmp path
	$file_upload = new File_Upload_Upgrader( 'pluginzip', 'package' );
	$package     = $file_upload->package;

	// ------------------------------------------------------------------
	// Read the top-level folder name from the zip BEFORE installing.
	// HIGH-3: enforce strict slug allowlist — letters, numbers, hyphens, underscores only.
	// ------------------------------------------------------------------
	$zip_slug = '';
	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( $zip->open( $package ) === true ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name  = $zip->getNameIndex( $i );
				$parts = explode( '/', ltrim( $name, '/' ) );
				$top   = $parts[0];
				// Strict allowlist: only safe plugin slug characters
				if ( $top && preg_match( '/^[a-zA-Z0-9_-]+$/', $top ) ) {
					$zip_slug = $top;
					break;
				}
			}
			$zip->close();
		}
	}

	// Fallback: derive slug from zip filename — sanitize strictly
	if ( ! $zip_slug ) {
		$raw      = basename( sanitize_file_name( $_FILES['pluginzip']['name'] ), '.zip' );
		$stripped = preg_replace( '/[\s_-]?v?\d[\d.]*(-[a-z0-9]+)?$/i', '', $raw );
		// MEDIUM-2: allowlist after stripping version suffix
		if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $stripped ) ) {
			$zip_slug = $stripped;
		}
	}

	$deactivated     = [];
	$old_dir_removed = false;
	// HIGH-1: snapshot active plugins BEFORE install (was wrongly named $active_before later)
	$active_plugins  = get_option( 'active_plugins', [] );

	if ( $zip_slug ) {
		// Deactivate any active plugin whose folder matches
		foreach ( $active_plugins as $active_file ) {
			$active_slug = ( dirname( $active_file ) === '.' )
				? basename( $active_file, '.php' )
				: dirname( $active_file );

			if ( strtolower( $active_slug ) === strtolower( $zip_slug ) ) {
				deactivate_plugins( $active_file, true );
				$deactivated[] = $active_file;
			}
		}

		// HIGH-2: confine $old_dir to WP_PLUGIN_DIR with realpath before deleting
		$old_dir         = WP_PLUGIN_DIR . '/' . $zip_slug;
		$real_plugin_dir = realpath( WP_PLUGIN_DIR );
		$real_old_dir    = realpath( $old_dir );

		if (
			$real_old_dir &&
			$real_plugin_dir &&
			strpos( $real_old_dir, $real_plugin_dir . DIRECTORY_SEPARATOR ) === 0 &&
			is_dir( $real_old_dir ) &&
			$wp_filesystem
		) {
			// LOW-2: check delete return value
			if ( $wp_filesystem->delete( $real_old_dir, true ) ) {
				$old_dir_removed = true;
			}
		}
	}

	// ------------------------------------------------------------------
	// Install
	// ------------------------------------------------------------------
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $package );

	$file_upload->cleanup();

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [
			'message' => 'Install failed: ' . $result->get_error_message(),
			'detail'  => implode( "\n", $skin->get_upgrade_messages() ),
		] );
	}

	if ( $result === false || $result === null ) {
		wp_send_json_error( [
			'message' => 'Install failed.',
			'detail'  => implode( "\n", $skin->get_upgrade_messages() ) ?: 'No details returned.',
		] );
	}

	// ------------------------------------------------------------------
	// Locate the installed plugin's main file — try several strategies
	// ------------------------------------------------------------------
	wp_cache_delete( 'plugins', 'plugins' );

	$plugin_file = $upgrader->plugin_info(); // e.g. "my-plugin/my-plugin.php"

	if ( $plugin_file && ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
		$plugin_file = null;
	}

	// Scan the known slug folder for a file with valid plugin headers
	if ( ! $plugin_file && $zip_slug ) {
		$plugin_file = ez_find_plugin_file_in_dir( WP_PLUGIN_DIR . '/' . $zip_slug, $zip_slug );
	}

	// HIGH-1: was referencing undefined $active_before — now correctly uses $active_plugins
	if ( ! $plugin_file ) {
		$all_plugins = get_plugins();
		$active_now  = get_option( 'active_plugins', [] );
		foreach ( array_keys( $all_plugins ) as $pfile ) {
			if ( ! in_array( $pfile, $active_plugins, true ) && ! in_array( $pfile, $active_now, true ) ) {
				if ( file_exists( WP_PLUGIN_DIR . '/' . $pfile ) ) {
					$plugin_file = $pfile;
					break;
				}
			}
		}
	}

	// MEDIUM-3: keep detail messages generic — no attacker-controlled values
	$detail = [];
	if ( $deactivated )     { $detail[] = 'Deactivated previous version.'; }
	if ( $old_dir_removed ) { $detail[] = 'Removed old plugin folder.'; }

	// ------------------------------------------------------------------
	// Activate — buffer any stray output from the plugin's activation
	// hook so it doesn't corrupt the JSON response
	// ------------------------------------------------------------------
	if ( ! $plugin_file ) {
		wp_send_json_success( [
			'message' => 'Plugin installed — please activate it manually.',
			'detail'  => implode( "\n", $detail ),
		] );
	}

	ob_start();
	$activate = activate_plugin( $plugin_file, '', false, true );
	ob_end_clean();

	if ( is_wp_error( $activate ) ) {
		wp_send_json_success( [
			'message' => 'Installed but activation failed — activate manually.',
			'detail'  => implode( "\n", $detail ),
		] );
	}

	$detail[] = 'Activated successfully.';

	wp_send_json_success( [
		'message' => 'Plugin installed and activated!',
		'detail'  => implode( "\n", $detail ),
	] );
} );

/* ------------------------------------------------------------------ */
/*  AJAX handler — EZ Delete                                           */
/* ------------------------------------------------------------------ */

add_action( 'wp_ajax_wp_ez_add_delete', function () {

	// MEDIUM-1: nonce first
	if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'wp_ez_add_delete' ) ) {
		wp_send_json_error( [ 'message' => 'Security check failed.' ] );
	}

	if ( ! current_user_can( 'delete_plugins' ) ) {
		wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
	}

	$plugin_file = isset( $_POST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_file'] ) ) : '';
	if ( ! $plugin_file ) {
		wp_send_json_error( [ 'message' => 'No plugin specified.' ] );
	}

	// Safety: must be relative, no traversal
	if ( strpos( $plugin_file, '..' ) !== false || path_is_absolute( $plugin_file ) ) {
		wp_send_json_error( [ 'message' => 'Invalid plugin path.' ] );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	WP_Filesystem();
	global $wp_filesystem;

	// Must be inactive
	if ( is_plugin_active( $plugin_file ) ) {
		wp_send_json_error( [ 'message' => 'Deactivate the plugin before deleting.' ] );
	}

	$slug    = dirname( $plugin_file );
	$abs_dir = ( $slug === '.' )
		? WP_PLUGIN_DIR . '/' . basename( $plugin_file )  // single-file plugin
		: WP_PLUGIN_DIR . '/' . $slug;

	// LOW-3: use trailing separator in realpath prefix check to prevent prefix confusion
	$real_plugin_dir = realpath( WP_PLUGIN_DIR );
	$real_target     = realpath( $abs_dir );
	if ( ! $real_target || ! $real_plugin_dir || strpos( $real_target, $real_plugin_dir . DIRECTORY_SEPARATOR ) !== 0 ) {
		wp_send_json_error( [ 'message' => 'Path is outside the plugins directory.' ] );
	}

	if ( is_dir( $abs_dir ) ) {
		$deleted = $wp_filesystem->delete( $abs_dir, true );
	} elseif ( is_file( $abs_dir ) ) {
		$deleted = $wp_filesystem->delete( $abs_dir );
	} else {
		wp_send_json_error( [ 'message' => 'Plugin directory not found: ' . $slug ] );
	}

	if ( ! $deleted ) {
		wp_send_json_error( [ 'message' => 'Filesystem delete failed. Check server permissions.' ] );
	}

	wp_send_json_success( [ 'message' => 'Plugin deleted.' ] );
} );
