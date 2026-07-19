<?php
/**
 * Post-commit notification spine for durable assisted-quote milestones.
 *
 * This class only observes actions that upstream code fires after a durable
 * database commit. It never opens a transaction, never mutates aggregate
 * state, and never blocks the traveler response on a failed channel. Every
 * outbound message carries opaque identifiers and the public TV reference
 * only: no traveler personal data, no free-form trip text, no budget amounts.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Notifier {
	const WEBHOOK_OPTION           = 'tra_vel_agent_notification_webhook_v1';
	const WEBHOOK_TIMEOUT_SECONDS  = 5;
	const WEBHOOK_MAX_URL_LENGTH   = 500;
	const SENT_MARKER_TTL_DAYS     = 30;
	const MAX_OPERATOR_RECIPIENTS  = 10;
	const PAYLOAD_CONTRACT_VERSION = '1.0.0';

	/** @var Tra_Vel_Agent_Store|null Marker storage; injectable for deterministic tests. */
	private $marker_store;

	/** @var Tra_Vel_Quote_Case_Store|null Bounded case reads; injectable for tests. */
	private $case_store;

	public function __construct( $marker_store = null, $case_store = null ) {
		$this->marker_store = $marker_store ? $marker_store : ( class_exists( 'Tra_Vel_Agent_Store' ) ? new Tra_Vel_Agent_Store() : null );
		$this->case_store   = $case_store ? $case_store : ( class_exists( 'Tra_Vel_Quote_Case_Store' ) ? new Tra_Vel_Quote_Case_Store() : null );
	}

	/** Subscribe to the three post-commit assisted-quote lifecycle actions. */
	public function register_hooks() {
		add_action( 'tra_vel_quote_case_created', array( $this, 'handle_case_created' ), 10, 3 );
		add_action( 'tra_vel_assisted_proposal_published', array( $this, 'handle_proposal_published' ), 10, 3 );
		add_action( 'tra_vel_quote_case_traveler_action', array( $this, 'handle_traveler_action' ), 10, 3 );
	}

	/**
	 * Notify operators that one new assisted-quote case was committed.
	 *
	 * @param string $case_id   Public quote-case UUID.
	 * @param string $reference Public TV-XXXXXXXX reference.
	 * @param array  $context   Bounded, PII-free creation context.
	 * @return void
	 */
	public function handle_case_created( $case_id, $reference, $context ) {
		$case_id   = $this->sanitize_uuid( $case_id );
		$reference = $this->sanitize_reference( $reference );
		if ( '' === $case_id || '' === $reference ) {
			return;
		}
		if ( ! $this->claim_marker( 'case_created:' . $case_id . ':1' ) ) {
			return;
		}
		$status = is_array( $context ) && isset( $context['status'] ) ? sanitize_key( (string) $context['status'] ) : 'queued';
		$this->send_operator_email(
			'Tra-Vel: בקשת סיוע חדשה ' . $reference,
			array(
				'התקבלה בקשת סיוע חדשה במערכת Tra-Vel.',
				'אסמכתה: ' . $reference,
				'סטטוס: ' . $this->status_label( $status ),
				'טיפול בתור המפעילים: ' . $this->operator_queue_url(),
			)
		);
		$this->send_webhook( 'quote_case.created', $case_id, $reference, null, null, null );
	}

	/**
	 * Notify operators, and the account owner when reachable, that an operator
	 * published or revised one assisted proposal.
	 *
	 * @param string $case_id     Public quote-case UUID.
	 * @param string $proposal_id Public proposal UUID.
	 * @param int    $revision    Published immutable revision number.
	 * @return void
	 */
	public function handle_proposal_published( $case_id, $proposal_id, $revision ) {
		$case_id     = $this->sanitize_uuid( $case_id );
		$proposal_id = $this->sanitize_uuid( $proposal_id );
		$revision    = absint( $revision );
		if ( '' === $case_id || '' === $proposal_id || $revision < 1 ) {
			return;
		}
		$case = $this->read_case( $case_id );
		if ( ! is_array( $case ) ) {
			return;
		}
		$reference = $this->sanitize_reference( $case['reference_code'] ?? '' );
		if ( '' === $reference ) {
			return;
		}

		if ( $this->claim_marker( 'proposal_published:' . $case_id . ':' . $proposal_id . ':' . $revision ) ) {
			$this->send_operator_email(
				'Tra-Vel: הצעה פורסמה לבקשה ' . $reference,
				array(
					'הצעת סיוע פורסמה או עודכנה עבור בקשה קיימת.',
					'אסמכתה: ' . $reference,
					'סטטוס הבקשה: ' . $this->status_label( (string) ( $case['status'] ?? '' ) ),
					'מהדורת הצעה: ' . $revision,
					'טיפול בתור המפעילים: ' . $this->operator_queue_url(),
				)
			);
			$this->send_webhook( 'assisted_proposal.published', $case_id, $reference, $proposal_id, $revision, null );
		}

		if ( $this->claim_marker( 'customer_proposal:' . $case_id . ':' . $proposal_id . ':' . $revision ) ) {
			$this->send_customer_publication_email( $case, $reference );
		}
	}

	/**
	 * Notify operators that a traveler recorded one material proposal decision.
	 *
	 * @param string $case_id     Public quote-case UUID.
	 * @param string $proposal_id Public proposal UUID.
	 * @param string $action      authorize_contact|request_changes|decline.
	 * @return void
	 */
	public function handle_traveler_action( $case_id, $proposal_id, $action ) {
		$case_id     = $this->sanitize_uuid( $case_id );
		$proposal_id = $this->sanitize_uuid( $proposal_id );
		$action      = sanitize_key( (string) $action );
		if ( '' === $case_id || '' === $proposal_id || ! in_array( $action, array( 'authorize_contact', 'request_changes', 'decline' ), true ) ) {
			return;
		}
		$case = $this->read_case( $case_id );
		if ( ! is_array( $case ) ) {
			return;
		}
		$reference = $this->sanitize_reference( $case['reference_code'] ?? '' );
		if ( '' === $reference ) {
			return;
		}
		$revision = $this->published_revision( $proposal_id );
		if ( ! $this->claim_marker( 'traveler_action:' . $case_id . ':' . $proposal_id . ':' . $action . ':' . $revision ) ) {
			return;
		}
		$labels = array(
			'authorize_contact' => 'המטייל אישר יצירת קשר להמשך טיפול.',
			'request_changes'   => 'המטייל ביקש שינויים בהצעה.',
			'decline'           => 'המטייל דחה את ההצעה.',
		);
		$this->send_operator_email(
			'Tra-Vel: תגובת מטייל לבקשה ' . $reference,
			array(
				$labels[ $action ],
				'אסמכתה: ' . $reference,
				'סטטוס הבקשה: ' . $this->status_label( (string) ( $case['status'] ?? '' ) ),
				'טיפול בתור המפעילים: ' . $this->operator_queue_url(),
			)
		);
		$this->send_webhook( 'quote_case.traveler_action', $case_id, $reference, $proposal_id, $revision > 0 ? $revision : null, $action );
	}

	/**
	 * Store the operator webhook endpoint encrypted with installation salts.
	 *
	 * The same sodium secretbox pattern protects the OpenAI credential. The URL
	 * is operator infrastructure, not traveler data, but it still never appears
	 * in plaintext options, REST responses, or logs.
	 *
	 * @param string $webhook_url HTTPS endpoint that receives JSON events.
	 * @return true|WP_Error
	 */
	public static function store_webhook_url( $webhook_url ) {
		$webhook_url = esc_url_raw( trim( (string) $webhook_url ), array( 'https' ) );
		$host        = (string) wp_parse_url( $webhook_url, PHP_URL_HOST );
		$user        = wp_parse_url( $webhook_url, PHP_URL_USER );
		$password    = wp_parse_url( $webhook_url, PHP_URL_PASS );
		if ( '' === $webhook_url || strlen( $webhook_url ) > self::WEBHOOK_MAX_URL_LENGTH || 'https' !== strtolower( (string) wp_parse_url( $webhook_url, PHP_URL_SCHEME ) ) || '' === $host || $user || $password ) {
			return new WP_Error( 'tra_vel_agent_webhook_invalid', 'The notification webhook must be a bounded HTTPS URL without embedded credentials.', array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'sodium_crypto_secretbox' ) || ! function_exists( 'random_bytes' ) ) {
			return new WP_Error( 'tra_vel_agent_webhook_encryption_unavailable', 'Secure webhook storage is unavailable on this host.', array( 'status' => 503 ) );
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $webhook_url, $nonce, self::encryption_key() );
		$stored     = update_option(
			self::WEBHOOK_OPTION,
			array(
				'version'    => 1,
				'nonce'      => base64_encode( $nonce ),
				'ciphertext' => base64_encode( $ciphertext ),
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
		$exists = is_array( get_option( self::WEBHOOK_OPTION, null ) );
		sodium_memzero( $webhook_url );
		return ( $stored || $exists ) ? true : new WP_Error( 'tra_vel_agent_webhook_store_failed', 'The encrypted webhook endpoint could not be saved.', array( 'status' => 500 ) );
	}

	/**
	 * Remove the encrypted webhook endpoint.
	 *
	 * @return bool
	 */
	public static function clear_webhook_url() {
		return delete_option( self::WEBHOOK_OPTION );
	}

	/**
	 * Return the decrypted webhook endpoint, or an empty string when the
	 * channel is unconfigured or the stored record cannot be verified.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		$record = get_option( self::WEBHOOK_OPTION, null );
		if ( ! is_array( $record ) || 1 !== (int) ( isset( $record['version'] ) ? $record['version'] : 0 ) ) {
			return '';
		}
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) || empty( $record['nonce'] ) || empty( $record['ciphertext'] ) ) {
			return '';
		}

		$nonce      = base64_decode( (string) $record['nonce'], true );
		$ciphertext = base64_decode( (string) $record['ciphertext'], true );
		if ( false === $nonce || false === $ciphertext || SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== strlen( $nonce ) ) {
			return '';
		}

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, self::encryption_key() );
		return false === $plaintext ? '' : trim( $plaintext );
	}

	/**
	 * Safe webhook configuration state for admin responses and health checks.
	 *
	 * @return array
	 */
	public static function webhook_status() {
		return array(
			'configured' => '' !== self::get_webhook_url(),
			'encryption' => function_exists( 'sodium_crypto_secretbox' ) ? 'sodium_secretbox' : 'unavailable',
		);
	}

	/**
	 * Truthful channel readiness for the public health endpoint.
	 *
	 * operator_email is true only when at least one syntactically valid
	 * recipient resolves; customer_email reports the channel capability while
	 * per-case delivery still requires an account owner with a valid address.
	 *
	 * @return array
	 */
	public static function health() {
		$mail_available = function_exists( 'wp_mail' );
		return array(
			'operator_email'     => $mail_available && array() !== self::operator_recipients(),
			'webhook_configured' => '' !== self::get_webhook_url(),
			'customer_email'     => $mail_available,
		);
	}

	/**
	 * Resolve, validate, and bound the operator recipient list.
	 *
	 * @return string[]
	 */
	public static function operator_recipients() {
		/**
		 * Filters the operator notification recipient list.
		 *
		 * @param string[] $recipients Email addresses receiving operator alerts.
		 */
		$recipients = apply_filters( 'tra_vel_agent_notification_recipients', array( get_option( 'admin_email' ) ) );
		$valid      = array();
		foreach ( is_array( $recipients ) ? $recipients : array() as $recipient ) {
			if ( ! is_string( $recipient ) || ! function_exists( 'is_email' ) || ! is_email( $recipient ) ) {
				continue;
			}
			$valid[ strtolower( trim( $recipient ) ) ] = trim( $recipient );
			if ( count( $valid ) >= self::MAX_OPERATOR_RECIPIENTS ) {
				break;
			}
		}
		return array_values( $valid );
	}

	/**
	 * Atomically claim one send-once marker for a notification identity.
	 *
	 * The existing AgentRun limits table provides the INSERT IGNORE first-winner
	 * guarantee, so duplicate action fires and concurrent workers cannot
	 * double-send. When marker storage is unavailable this fails closed: no
	 * marker means no send, never an unbounded duplicate risk.
	 *
	 * @param string $identity Event-scoped notification identity.
	 * @return bool
	 */
	private function claim_marker( $identity ) {
		if ( ! $this->marker_store instanceof Tra_Vel_Agent_Store || ! Tra_Vel_Agent_Store::is_ready() ) {
			return false;
		}
		return (bool) $this->marker_store->consume_limit( 'notify:v1:' . $identity, 1, time() + self::SENT_MARKER_TTL_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Send one plain-text Hebrew operator email; failures are non-fatal.
	 *
	 * @param string   $subject Subject without traveler data or budget amounts.
	 * @param string[] $lines   Body lines without traveler personal data.
	 * @return void
	 */
	private function send_operator_email( $subject, $lines ) {
		$recipients = self::operator_recipients();
		if ( ! $recipients || ! function_exists( 'wp_mail' ) ) {
			return;
		}
		wp_mail(
			$recipients,
			$subject,
			implode( "\n", $lines ) . "\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Email the account owner that a personal proposal is ready.
	 *
	 * Sent only when the case belongs to a WordPress account whose stored email
	 * passes the same server-side validity check used before contact consent.
	 * The message carries the public reference and the workspace link only:
	 * no destinations, dates, prices, or proposal content.
	 *
	 * @param array  $case      Hydrated quote case.
	 * @param string $reference Public TV reference.
	 * @return void
	 */
	private function send_customer_publication_email( $case, $reference ) {
		$owner_user_id = absint( $case['owner_user_id'] ?? 0 );
		if ( $owner_user_id < 1 || ! function_exists( 'get_userdata' ) || ! function_exists( 'wp_mail' ) ) {
			return;
		}
		$owner = get_userdata( $owner_user_id );
		$email = is_object( $owner ) ? (string) ( $owner->user_email ?? '' ) : '';
		if ( ! function_exists( 'is_email' ) || ! is_email( $email ) ) {
			return;
		}
		wp_mail(
			$email,
			'Tra-Vel: הצעה אישית חדשה לבקשה ' . $reference,
			implode(
				"\n",
				array(
					'שלום,',
					'',
					'הצעה אישית חדשה פורסמה עבור בקשת הסיוע שלך ' . $reference . '.',
					'אפשר לצפות בה באזור האישי המאובטח:',
					home_url( '/saved/' ),
					'',
					'מטעמי פרטיות הודעה זו אינה כוללת את פרטי הנסיעה.',
					'צוות Tra-Vel',
				)
			) . "\n",
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * POST one bounded JSON event to the optional encrypted webhook endpoint.
	 *
	 * One transport retry at most; HTTP rejections are never retried and never
	 * block the traveler response. The payload carries opaque identifiers and
	 * the public reference only.
	 *
	 * @param string      $event       Namespaced event name.
	 * @param string      $case_id     Quote-case UUID.
	 * @param string      $reference   Public TV reference.
	 * @param string|null $proposal_id Proposal UUID when applicable.
	 * @param int|null    $revision    Published revision when applicable.
	 * @param string|null $action      Traveler action when applicable.
	 * @return void
	 */
	private function send_webhook( $event, $case_id, $reference, $proposal_id, $revision, $action ) {
		$url = self::get_webhook_url();
		if ( '' === $url ) {
			return;
		}
		$payload = array(
			'contract_version' => self::PAYLOAD_CONTRACT_VERSION,
			'event'            => $event,
			'case_id'          => $case_id,
			'reference'        => $reference,
			'proposal_id'      => $proposal_id,
			'revision'         => $revision,
			'action'           => $action,
			'occurred_at'      => gmdate( 'c' ),
		);
		$args = array(
			'timeout'     => self::WEBHOOK_TIMEOUT_SECONDS,
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'data_format' => 'body',
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			// Exactly one transport retry; HTTP status rejections are final.
			wp_remote_post( $url, $args );
		}
	}

	/**
	 * Read one case with bounded storage guards; null on any uncertainty.
	 *
	 * @param string $case_id Quote-case UUID.
	 * @return array|null
	 */
	private function read_case( $case_id ) {
		if ( ! $this->case_store instanceof Tra_Vel_Quote_Case_Store || ! Tra_Vel_Quote_Case_Store::is_ready() ) {
			return null;
		}
		$case = $this->case_store->get_case_by_uuid( $case_id );
		return is_array( $case ) ? $case : null;
	}

	/**
	 * Read the published revision for a traveler-action marker; zero on
	 * uncertainty so deduplication stays conservative.
	 *
	 * @param string $proposal_id Proposal UUID.
	 * @return int
	 */
	private function published_revision( $proposal_id ) {
		if ( ! class_exists( 'Tra_Vel_Assisted_Proposal_Store' ) || ! method_exists( 'Tra_Vel_Assisted_Proposal_Store', 'is_ready' ) || ! Tra_Vel_Assisted_Proposal_Store::is_ready() ) {
			return 0;
		}
		$store = new Tra_Vel_Assisted_Proposal_Store();
		if ( ! method_exists( $store, 'get_by_uuid' ) ) {
			return 0;
		}
		$head = $store->get_by_uuid( $proposal_id );
		return is_array( $head ) ? absint( $head['published_revision'] ?? 0 ) : 0;
	}

	private function status_label( $status ) {
		if ( class_exists( 'Tra_Vel_Quote_Case_Policy' ) && method_exists( 'Tra_Vel_Quote_Case_Policy', 'status_label' ) ) {
			return Tra_Vel_Quote_Case_Policy::status_label( $status );
		}
		return sanitize_key( $status );
	}

	private function operator_queue_url() {
		$slug = class_exists( 'Tra_Vel_Quote_Case_Admin' ) ? Tra_Vel_Quote_Case_Admin::PAGE_SLUG : 'tra-vel-quote-cases';
		return admin_url( 'admin.php?page=' . $slug );
	}

	private function sanitize_uuid( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value ) ? $value : '';
	}

	private function sanitize_reference( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		return 1 === preg_match( '/^TV-[A-Z0-9]{8}$/', $value ) ? $value : '';
	}

	/**
	 * Derive a fixed-size encryption key from per-installation WordPress salts.
	 * The context string differs from the credential vault so the two secrets
	 * never share a key.
	 *
	 * @return string Binary key.
	 */
	private static function encryption_key() {
		$material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|tra-vel-agent-notify-v1';
		return hash( 'sha256', $material, true );
	}
}
