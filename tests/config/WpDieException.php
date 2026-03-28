<?php
/**
 * WpDieException stub class for tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Core;

/**
 * Exception thrown by the wp_die() stub so tests can assert it was called.
 *
 * This exception replaces the process-terminating wp_die() in unit tests,
 * allowing PHPUnit assertions on the message and title that would normally
 * be rendered and then terminate execution.
 *
 * @since 0.2.0
 */
class WpDieException extends \RuntimeException {
	/**
	 * The message passed to wp_die().
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public string $wp_message;

	/**
	 * The title passed to wp_die().
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public string $wp_title;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string $message WP die message.
	 * @param string $title   WP die title.
	 */
	public function __construct( string $message, string $title = '' ) {
		parent::__construct( $message );
		$this->wp_message = $message;
		$this->wp_title   = $title;
	}
}
