<?php
/**
 * Asset URL and version helpers.
 *
 * Centralizes the prod/debug switching for JS assets.
 * In debug mode (`WP_DEBUG === true`) the source files are served.
 * In production the minified dist files from assets/js/dist/ are used, with
 * content-hash versions from asset-manifest.json for cache-busting.
 *
 * @package UplinkPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cached asset manifest loaded from assets/js/dist/asset-manifest.json.
 *
 * @var array<string,array{file:string,hash:string}>|null
 */
$_uplinkportal_asset_manifest = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Returns the URL for a plugin JS file.
 *
 * Resolves to the minified dist file in production and the source file in
 * debug mode. The relative_js_path must use forward slashes relative to the
 * plugin root (e.g. 'assets/js/portal.js').
 *
 * @param string $relative_js_path Relative path from plugin root to the source JS file.
 * @return string Full URL.
 */
function uplinkportal_js_url( string $relative_js_path ): string {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return plugins_url( ltrim( $relative_js_path, '/' ), UPLINKPORTAL_FILE );
	}

	$filename  = wp_basename( $relative_js_path, '.js' );
	$dist_path = UPLINKPORTAL_PATH . 'assets/js/dist/' . $filename . '.min.js';

	if ( file_exists( $dist_path ) ) {
		return plugins_url( 'assets/js/dist/' . $filename . '.min.js', UPLINKPORTAL_FILE );
	}

	return plugins_url( ltrim( $relative_js_path, '/' ), UPLINKPORTAL_FILE );
}

/**
 * Returns the version string for a plugin JS file.
 *
 * In debug mode returns UPLINKPORTAL_VERSION.
 * In production returns the content hash from the asset manifest, or
 * UPLINKPORTAL_VERSION if the manifest is unavailable.
 *
 * @param string $relative_js_path Relative path from plugin root to the source JS file.
 * @return string Version string.
 */
function uplinkportal_js_ver( string $relative_js_path ): string {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return UPLINKPORTAL_VERSION;
	}

	global $_uplinkportal_asset_manifest;

	if ( null === $_uplinkportal_asset_manifest ) {
		$manifest_path                = UPLINKPORTAL_PATH . 'assets/js/dist/asset-manifest.json';
		$_uplinkportal_asset_manifest = array();
		if ( file_exists( $manifest_path ) ) {
			$decoded = json_decode( (string) file_get_contents( $manifest_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $decoded ) ) {
				$_uplinkportal_asset_manifest = $decoded;
			}
		}
	}

	$filename = wp_basename( $relative_js_path );
	if ( isset( $_uplinkportal_asset_manifest[ $filename ]['hash'] ) ) {
		return (string) $_uplinkportal_asset_manifest[ $filename ]['hash'];
	}

	return UPLINKPORTAL_VERSION;
}
