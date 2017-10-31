<?php
/**
 * Background Updater
 *
 * Uses https://github.com/A5hleyRich/wp-background-processing to handle DB
 * updates in the background.
 *
 * @class    RP_Background_Emailer
 * @version  1.5.1
 * @package  RestaurantPress/Classes
 * @category Class
 * @author   WPEverest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Async_Request', false ) ) {
	include_once( dirname( __FILE__ ) . '/libraries/wp-async-request.php' );
}

if ( ! class_exists( 'WP_Background_Process', false ) ) {
	include_once( dirname( __FILE__ ) . '/libraries/wp-background-process.php' );
}

/**
 * RP_Background_Emailer Class.
 */
class RP_Background_Emailer extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'rp_emailer';

	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'shutdown', array( $this, 'dispatch_queue' ) );
	}

	/**
	 * Schedule fallback event.
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time() + 10, $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param  array $callback Update callback function.
	 * @return mixed
	 */
	protected function task( $callback ) {
		if ( isset( $callback['filter'], $callback['args'] ) ) {
			try {
				RP_Emails::send_queued_notificational_email( $callback['filter'], $callback['args'] );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					trigger_error( 'Notificational email triggered fatal error for callback ' . $callback['filter'], E_USER_WARNING );
				}
			}
		}
		return false;
	}

	/**
	 * Save and run queue.
	 */
	public function dispatch_queue() {
		if ( ! empty( $this->data ) ) {
			$this->save()->dispatch();
		}
	}

	/**
	 * Get post args
	 *
	 * @return array
	 */
	protected function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		// Pass cookies through with the request so nonces function.
		$cookies = array();

		foreach ( $_COOKIE as $name => $value ) {
			if ( 'PHPSESSID' === $name ) {
				continue;
			}
			$cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => $value ) );
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => $this->data,
			'cookies'   => $cookies,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

	/**
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 */
	protected function handle() {
		$this->lock_process();

		do {
			$batch = $this->get_batch();

			if ( empty( $batch->data ) ) {
				break;
			}

			foreach ( $batch->data as $key => $value ) {
				$task = $this->task( $value );

				if ( false !== $task ) {
					$batch->data[ $key ] = $task;
				} else {
					unset( $batch->data[ $key ] );
				}

				// Update batch before sending more to prevent duplicate email possibility.
				$this->update( $batch->key, $batch->data );

				if ( $this->time_exceeded() || $this->memory_exceeded() ) {
					// Batch limits reached.
					break;
				}
			}
			if ( empty( $batch->data ) ) {
				$this->delete( $batch->key );
			}
		} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

		$this->unlock_process();

		// Start next batch or complete process.
		if ( ! $this->is_queue_empty() ) {
			$this->dispatch();
		} else {
			$this->complete();
		}
	}
}
