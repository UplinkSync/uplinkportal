<?php
/**
 * Template loader.
 *
 * @package UplinkPortal\ClientPortal
 */

namespace UplinkPortal\ClientPortal;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves template paths with theme overrides.
 */
class TemplateLoader {
	/**
	 * Loads a template with optional theme override.
	 *
	 * @param string              $template_name Template basename.
	 * @param array<string,mixed> $template_context Template context.
	 * @return string
	 */
	public function render( string $template_name, array $template_context = array() ): string {
		$template_name = sanitize_file_name( $template_name );

		if ( '.php' !== substr( $template_name, -4 ) ) {
			return '';
		}

		$template_path = $this->resolve_template_path( $template_name );

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		$brand_primary_color = isset( $template_context['brand_primary_color'] ) ? sanitize_hex_color( (string) $template_context['brand_primary_color'] ) : '#1d4ed8';
		if ( ! is_string( $brand_primary_color ) || '' === $brand_primary_color ) {
			$brand_primary_color = '#1d4ed8';
		}

		$context = array(
			'modules'                 => $this->sanitize_sections( $template_context['modules'] ?? array() ),
			'workspace_sections'      => $this->sanitize_sections( $template_context['workspace_sections'] ?? array() ),
			'client_documents'        => $this->sanitize_documents( $template_context['client_documents'] ?? array() ),
			'brand_primary_color'     => $brand_primary_color,
			'portal_welcome_message'  => isset( $template_context['portal_welcome_message'] ) ? sanitize_textarea_field( (string) $template_context['portal_welcome_message'] ) : '',
			'portal_footer_note'      => isset( $template_context['portal_footer_note'] ) ? sanitize_textarea_field( (string) $template_context['portal_footer_note'] ) : '',
			'purchase_request_status' => isset( $template_context['purchase_request_status'] ) ? sanitize_key( (string) $template_context['purchase_request_status'] ) : 'unknown',
			'request_items'           => $this->sanitize_request_items( $template_context['request_items'] ?? array() ),
		);

		ob_start();
		include $template_path;
		return (string) ob_get_clean();
	}

	/**
	 * Sanitizes client request item labels.
	 *
	 * @param mixed $request_items Request item map.
	 * @return array<string,string>
	 */
	private function sanitize_request_items( $request_items ): array {
		if ( ! is_array( $request_items ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $request_items as $item_key => $item_label ) {
			$key   = sanitize_key( (string) $item_key );
			$label = sanitize_text_field( (string) $item_label );
			if ( '' === $key || '' === $label ) {
				continue;
			}

			$sanitized[ $key ] = $label;
		}

		return $sanitized;
	}

	/**
	 * Sanitizes section payload for templates.
	 *
	 * @param mixed $sections Raw sections list.
	 * @return array<int,array<string,string>>
	 */
	private function sanitize_sections( $sections ): array {
		if ( ! is_array( $sections ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$id          = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
			$label       = isset( $section['label'] ) ? sanitize_text_field( (string) $section['label'] ) : '';
			$description = isset( $section['description'] ) ? sanitize_text_field( (string) $section['description'] ) : '';

			if ( '' === $id || '' === $label ) {
				continue;
			}

			$sanitized[] = array(
				'id'          => $id,
				'label'       => $label,
				'description' => $description,
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitizes document payload for templates.
	 *
	 * @param mixed $documents Raw document list.
	 * @return array<int,array<string,string>>
	 */
	private function sanitize_documents( $documents ): array {
		if ( ! is_array( $documents ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $documents as $document ) {
			if ( ! is_array( $document ) ) {
				continue;
			}

			$title      = isset( $document['title'] ) ? sanitize_text_field( (string) $document['title'] ) : '';
			$type       = isset( $document['type'] ) ? sanitize_text_field( (string) $document['type'] ) : '';
			$status     = isset( $document['status'] ) ? sanitize_text_field( (string) $document['status'] ) : '';
			$updated_at = isset( $document['updated_at'] ) ? sanitize_text_field( (string) $document['updated_at'] ) : '';
			$summary    = isset( $document['summary'] ) ? sanitize_textarea_field( (string) $document['summary'] ) : '';
			$url        = isset( $document['url'] ) ? esc_url_raw( (string) $document['url'] ) : '';

			if ( '' === $title ) {
				continue;
			}

			$sanitized[] = array(
				'title'      => $title,
				'type'       => $type,
				'status'     => $status,
				'updated_at' => $updated_at,
				'summary'    => $summary,
				'url'        => $url,
			);
		}

		return $sanitized;
	}

	/**
	 * Resolves theme override and plugin fallback template path.
	 *
	 * @param string $template_name Template file.
	 * @return string
	 */
	private function resolve_template_path( string $template_name ): string {
		$candidates = array(
			trailingslashit( get_stylesheet_directory() ) . 'uplinkportal/template-parts/' . $template_name,
			trailingslashit( get_stylesheet_directory() ) . 'uplinkportal/' . $template_name,
			UPLINKPORTAL_PATH . 'src/ClientPortal/templates/' . $template_name,
			UPLINKPORTAL_PATH . 'template-parts/' . $template_name,
			UPLINKPORTAL_PATH . 'templates/' . $template_name,
		);

		foreach ( $candidates as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
