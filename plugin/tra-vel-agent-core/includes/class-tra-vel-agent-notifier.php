<?php
/**
 * Post-commit notification spine for durable assisted-quote milestones and
 * hourly-bounded provider-failure operator alerts.
 *
 * This class only observes actions that upstream code fires after a durable
 * database commit. It never opens a transaction, never mutates aggregate
 * state, and never blocks the traveler response on a failed channel. Every
 * outbound message carries opaque identifiers and the public TV reference:
 * no free-form trip text and no budget amounts. The operator-only email may
 * additionally carry the explicitly consented lead contact and bounded UTM
 * attribution; the webhook and customer channels never receive contact data
 * beyond a contact_provided boolean. Provider-failure alerts carry failure
 * codes only and never run identifiers or prompt content.
 *
 * @package TraVelAgent
 */

defined( 'ABSPATH' ) || exit;

class Tra_Vel_Agent_Notifier {
	const WEBHOOK_OPTION           = 'tra_vel_agent_notification_webhook_v1';
	const RECIPIENTS_OPTION        = 'tra_vel_agent_notification_recipients_v1';
	const WEBHOOK_TIMEOUT_SECONDS  = 5;
	const WEBHOOK_MAX_URL_LENGTH   = 500;
	const SENT_MARKER_TTL_DAYS     = 30;
	const MAX_OPERATOR_RECIPIENTS  = 10;
	const PAYLOAD_CONTRACT_VERSION = '1.2.0';

	/** @var Tra_Vel_Agent_Store|null Marker storage; injectable for deterministic tests. */
	private $marker_store;

	/** @var Tra_Vel_Quote_Case_Store|null Bounded case reads; injectable for tests. */
	private $case_store;

	public function __construct( $marker_store = null, $case_store = null ) {
		$this->marker_store = $marker_store ? $marker_store : ( class_exists( 'Tra_Vel_Agent_Store' ) ? new Tra_Vel_Agent_Store() : null );
		$this->case_store   = $case_store ? $case_store : ( class_exists( 'Tra_Vel_Quote_Case_Store' ) ? new Tra_Vel_Quote_Case_Store() : null );
	}

	/** Subscribe to the post-commit assisted-quote and provider-failure actions. */
	public function register_hooks() {
		add_action( 'tra_vel_quote_case_created', array( $this, 'handle_case_created' ), 10, 3 );
		add_action( 'tra_vel_assisted_proposal_published', array( $this, 'handle_proposal_published' ), 10, 3 );
		add_action( 'tra_vel_quote_case_traveler_action', array( $this, 'handle_traveler_action' ), 10, 3 );
		add_action( 'tra_vel_agent_provider_error', array( $this, 'handle_provider_error' ), 10, 2 );
	}

	/**
	 * Alert operators that the AI provider is failing for live visitors.
	 *
	 * Fired by the controller only after the failed run state and its audit
	 * event have committed. The same claim_marker store bounds this channel to
	 * at most one email per provider code per UTC hour, so a provider outage
	 * cannot flood the operator inbox while every visitor request fails.
	 *
	 * @param string $error_code    Internal WP_Error code recorded on the run.
	 * @param string $provider_code Upstream provider failure code when known.
	 * @return void
	 */
	public function handle_provider_error( $error_code, $provider_code ) {
		$error_code    = sanitize_key( (string) $error_code );
		$provider_code = sanitize_key( (string) $provider_code );
		if ( '' === $error_code ) {
			$error_code = 'unknown';
		}
		if ( '' === $provider_code ) {
			$provider_code = 'unknown';
		}
		if ( ! $this->claim_marker( 'provider_error:' . $provider_code . ':' . gmdate( 'YmdH' ) ) ) {
			return;
		}
		$this->send_operator_email(
			'Tra-Vel: תקלת ספק AI (' . $provider_code . ')',
			array(
				'המתכנן הפרטי של Tra-Vel נתקל בתקלת ספק AI ואינו מצליח לפרש בקשות של מבקרים כרגע.',
				'קוד תקלה אצל הספק: ' . $provider_code,
				'קוד שגיאה פנימי: ' . $error_code,
				'מבקרים שפותחים תוכנית מקבלים הודעת כשל זמנית. לא נשמר מידע אישי ולא נשלחה פנייה לספקי נסיעות.',
				'מומלץ לבדוק את המפתח, המכסה והסטטוס אצל ספק ה-AI. התראה זו נשלחת לכל היותר פעם בשעה לכל קוד תקלה.',
			)
		);
		$this->send_operational_webhook( 'agent_provider.error', $error_code, $provider_code );
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
		$status      = is_array( $context ) && isset( $context['status'] ) ? sanitize_key( (string) $context['status'] ) : 'queued';
		$case        = $this->read_case( $case_id );
		$attribution = $this->case_attribution( $case );
		$contact     = $this->case_contact( $case );
		$lines       = array(
			'התקבלה בקשת סיוע חדשה במערכת Tra-Vel.',
			'אסמכתה: ' . $reference,
			'סטטוס: ' . $this->status_label( $status ),
		);
		if ( $attribution ) {
			$campaign_parts = array();
			foreach ( array( 'utm_source' => 'מקור', 'utm_medium' => 'ערוץ', 'utm_campaign' => 'קמפיין' ) as $field => $label ) {
				if ( isset( $attribution[ $field ] ) ) {
					$campaign_parts[] = $label . ': ' . $attribution[ $field ];
				}
			}
			$lines[] = 'שיוך שיווקי — ' . implode( ' | ', $campaign_parts );
		}
		if ( $contact ) {
			$lines[] = 'המטייל השאיר פרטי התקשרות בהסכמה מפורשת (' . sanitize_text_field( (string) ( $contact['consent_version'] ?? '' ) ) . '):';
			if ( '' !== (string) ( $contact['name'] ?? '' ) ) {
				$lines[] = 'שם: ' . sanitize_text_field( (string) $contact['name'] );
			}
			$lines[] = 'טלפון לחזרה: ' . sanitize_text_field( (string) ( $contact['phone'] ?? '' ) );
		}
		$lines[] = 'טיפול בתור המפעילים: ' . $this->operator_queue_url();
		$this->send_operator_email( 'Tra-Vel: בקשת סיוע חדשה ' . $reference, $lines );
		$this->send_webhook( 'quote_case.created', $case_id, $reference, null, null, null, $attribution ? $attribution : null, (bool) $contact );
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
			$this->send_webhook( 'assisted_proposal.published', $case_id, $reference, $proposal_id, $revision, null, null, (bool) $this->case_contact( $case ) );
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
		$this->send_webhook( 'quote_case.traveler_action', $case_id, $reference, $proposal_id, $revision > 0 ? $revision : null, $action, null, (bool) $this->case_contact( $case ) );
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
	 * Store the configured operator recipient list in a plain option.
	 *
	 * Recipient addresses are operator infrastructure, not traveler data and
	 * not secrets, so unlike the webhook endpoint they are stored without
	 * encryption. The list is validated, case-insensitively deduplicated, and
	 * bounded to MAX_OPERATOR_RECIPIENTS before anything is written.
	 *
	 * @param mixed $recipients Requested list of operator email addresses.
	 * @return true|WP_Error
	 */
	public static function store_recipients( $recipients ) {
		if ( ! is_array( $recipients ) || array() === $recipients ) {
			return new WP_Error( 'tra_vel_agent_recipients_invalid', 'Provide the operator notification recipients as a non-empty list of email addresses.', array( 'status' => 400 ) );
		}
		$valid = array();
		foreach ( $recipients as $recipient ) {
			$recipient = is_string( $recipient ) ? trim( $recipient ) : '';
			if ( '' === $recipient || ! function_exists( 'is_email' ) || ! is_email( $recipient ) ) {
				return new WP_Error( 'tra_vel_agent_recipient_email_invalid', 'Every operator notification recipient must be a valid email address.', array( 'status' => 400 ) );
			}
			$valid[ strtolower( $recipient ) ] = $recipient;
		}
		if ( count( $valid ) > self::MAX_OPERATOR_RECIPIENTS ) {
			return new WP_Error( 'tra_vel_agent_recipients_limit', 'At most ' . self::MAX_OPERATOR_RECIPIENTS . ' operator notification recipients can be stored.', array( 'status' => 400 ) );
		}
		$stored = update_option(
			self::RECIPIENTS_OPTION,
			array(
				'version'    => 1,
				'recipients' => array_values( $valid ),
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
		$exists = is_array( get_option( self::RECIPIENTS_OPTION, null ) );
		return ( $stored || $exists ) ? true : new WP_Error( 'tra_vel_agent_recipients_store_failed', 'The operator notification recipients could not be saved.', array( 'status' => 500 ) );
	}

	/**
	 * Remove the configured recipient list; delivery falls back to admin_email.
	 *
	 * @return bool
	 */
	public static function clear_recipients() {
		return delete_option( self::RECIPIENTS_OPTION );
	}

	/**
	 * Return the validated configured recipient list, empty when unset.
	 *
	 * @return string[]
	 */
	public static function stored_recipients() {
		$record = get_option( self::RECIPIENTS_OPTION, null );
		if ( ! is_array( $record ) || 1 !== (int) ( isset( $record['version'] ) ? $record['version'] : 0 ) || ! isset( $record['recipients'] ) || ! is_array( $record['recipients'] ) ) {
			return array();
		}
		$valid = array();
		foreach ( $record['recipients'] as $recipient ) {
			if ( ! is_string( $recipient ) || ! function_exists( 'is_email' ) || ! is_email( trim( $recipient ) ) ) {
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
	 * Safe recipient configuration state for admin responses.
	 *
	 * @return array
	 */
	public static function recipients_status() {
		$configured = self::stored_recipients();
		return array(
			'configured' => array() !== $configured,
			'count'      => count( $configured ),
		);
	}

	/**
	 * Truthful channel readiness for the public health endpoint.
	 *
	 * operator_email is true only when at least one syntactically valid
	 * recipient resolves; recipients_configured counts only the validated
	 * stored list, so a default admin_email fallback truthfully reports zero.
	 * customer_email reports the channel capability while per-case delivery
	 * still requires an account owner with a valid address.
	 *
	 * @return array
	 */
	public static function health() {
		$mail_available = function_exists( 'wp_mail' );
		return array(
			'operator_email'        => $mail_available && array() !== self::operator_recipients(),
			'recipients_configured' => count( self::stored_recipients() ),
			'webhook_configured'    => '' !== self::get_webhook_url(),
			'customer_email'        => $mail_available,
		);
	}

	/**
	 * Resolve, validate, and bound the operator recipient list.
	 *
	 * The configured option list wins when non-empty, admin_email remains the
	 * default otherwise, and the existing filter stays the final override.
	 *
	 * @return string[]
	 */
	public static function operator_recipients() {
		$configured = self::stored_recipients();
		$defaults   = array() !== $configured ? $configured : array( get_option( 'admin_email' ) );
		/**
		 * Filters the operator notification recipient list.
		 *
		 * @param string[] $recipients Email addresses receiving operator alerts.
		 */
		$recipients = apply_filters( 'tra_vel_agent_notification_recipients', $defaults );
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
	 * Extract the bounded, non-personal campaign attribution from a case.
	 *
	 * Only utm_source, utm_medium, and utm_campaign leave the aggregate;
	 * landing paths, referrer hosts, and terms stay operator-only in storage.
	 *
	 * @param array|null $case Hydrated quote case, or null on uncertainty.
	 * @return array<string,string>
	 */
	private function case_attribution( $case ) {
		$acquisition = is_array( $case ) && isset( $case['acquisition'] ) && is_array( $case['acquisition'] ) ? $case['acquisition'] : array();
		$attribution = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $field ) {
			$value = sanitize_text_field( (string) ( $acquisition[ $field ] ?? '' ) );
			if ( '' !== $value ) {
				$attribution[ $field ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 120 ) : substr( $value, 0, 120 );
			}
		}
		return $attribution;
	}

	/**
	 * Read the consented lead contact for the operator email only.
	 *
	 * @param array|null $case Hydrated quote case, or null on uncertainty.
	 * @return array<string,string>
	 */
	private function case_contact( $case ) {
		$contact = is_array( $case ) && isset( $case['contact'] ) && is_array( $case['contact'] ) ? $case['contact'] : array();
		return '' !== (string) ( $contact['phone'] ?? '' ) ? $contact : array();
	}

	/**
	 * POST one bounded JSON event to the optional encrypted webhook endpoint.
	 *
	 * One transport retry at most; HTTP rejections are never retried and never
	 * block the traveler response. The payload carries opaque identifiers, the
	 * public reference, at most the three bounded UTM attribution fields, and
	 * a contact_provided boolean. Lead names and phone numbers never leave the
	 * operator-readable store through this channel.
	 *
	 * @param string      $event            Namespaced event name.
	 * @param string      $case_id          Quote-case UUID.
	 * @param string      $reference        Public TV reference.
	 * @param string|null $proposal_id      Proposal UUID when applicable.
	 * @param int|null    $revision         Published revision when applicable.
	 * @param string|null $action           Traveler action when applicable.
	 * @param array|null  $acquisition      Bounded utm_source/utm_medium/utm_campaign attribution.
	 * @param bool        $contact_provided Whether a consented lead contact exists on the case.
	 * @return void
	 */
	private function send_webhook( $event, $case_id, $reference, $proposal_id, $revision, $action, $acquisition = null, $contact_provided = false ) {
		$this->post_webhook_payload(
			array(
				'contract_version' => self::PAYLOAD_CONTRACT_VERSION,
				'event'            => $event,
				'case_id'          => $case_id,
				'reference'        => $reference,
				'proposal_id'      => $proposal_id,
				'revision'         => $revision,
				'action'           => $action,
				'acquisition'      => is_array( $acquisition ) && array() !== $acquisition ? $acquisition : null,
				'contact_provided' => (bool) $contact_provided,
				'occurred_at'      => gmdate( 'c' ),
			)
		);
	}

	/**
	 * POST one bounded operational JSON event to the optional webhook channel.
	 *
	 * Operational events carry provider failure codes only: no run identifiers,
	 * no traveler data, and no prompt content ever ride this payload.
	 *
	 * @param string $event         Namespaced operational event name.
	 * @param string $error_code    Internal WP_Error code.
	 * @param string $provider_code Upstream provider failure code.
	 * @return void
	 */
	private function send_operational_webhook( $event, $error_code, $provider_code ) {
		$this->post_webhook_payload(
			array(
				'contract_version' => self::PAYLOAD_CONTRACT_VERSION,
				'event'            => $event,
				'error_code'       => $error_code,
				'provider_code'    => $provider_code,
				'occurred_at'      => gmdate( 'c' ),
			)
		);
	}

	/**
	 * Shared webhook transport with at most one transport retry.
	 *
	 * @param array $payload Bounded JSON payload.
	 * @return void
	 */
	private function post_webhook_payload( $payload ) {
		$url = self::get_webhook_url();
		if ( '' === $url ) {
			return;
		}
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
