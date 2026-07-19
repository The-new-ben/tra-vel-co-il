<?php
/**
 * Validate the deterministic commerce search projection against its JSON
 * schemas and the cross-contract invariants in COMMERCE_SANDBOX_SYSTEM.md.
 *
 * This deliberately executes the existing runtime fixture test first so the
 * objects checked here are the real producer output, not hand-copied samples.
 */

$root = dirname( __DIR__, 2 );

ob_start();
require $root . '/scripts/ci/validate-commerce-search-runtime.php';
$runtime_output = trim( ob_get_clean() );

$failures   = array();
$assertions = 0;

function tra_vel_commerce_conformance_fail( &$failures, $message ) {
	$failures[] = $message;
}

function tra_vel_commerce_conformance_expect( $condition, &$failures, &$assertions, $message ) {
	$assertions++;
	if ( ! $condition ) {
		tra_vel_commerce_conformance_fail( $failures, $message );
	}
}

function tra_vel_commerce_conformance_json_object( $value ) {
	$encoded = json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $encoded ) ? json_decode( $encoded ) : null;
}

function tra_vel_commerce_conformance_schema( $path, &$failures ) {
	$contents = is_readable( $path ) ? file_get_contents( $path ) : false;
	$schema   = is_string( $contents ) ? json_decode( $contents ) : null;
	if ( ! is_object( $schema ) || JSON_ERROR_NONE !== json_last_error() ) {
		tra_vel_commerce_conformance_fail( $failures, 'Schema is missing or invalid JSON: ' . $path . '.' );
		return null;
	}
	return $schema;
}

function tra_vel_commerce_conformance_deep_equal( $left, $right ) {
	return json_encode( $left, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) === json_encode( $right, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function tra_vel_commerce_conformance_resolve_ref( $root_schema, $ref ) {
	if ( ! is_string( $ref ) || 0 !== strpos( $ref, '#/' ) ) {
		return null;
	}
	$target   = $root_schema;
	$segments = explode( '/', substr( $ref, 2 ) );
	foreach ( $segments as $segment ) {
		$segment = str_replace( array( '~1', '~0' ), array( '/', '~' ), $segment );
		if ( ! is_object( $target ) || ! property_exists( $target, $segment ) ) {
			return null;
		}
		$target = $target->{$segment};
	}
	return is_object( $target ) ? $target : null;
}

function tra_vel_commerce_conformance_string_length( $value ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $value, 'UTF-8' );
	}
	$count = preg_match_all( '/./us', $value, $matches );
	return false === $count ? strlen( $value ) : $count;
}

function tra_vel_commerce_conformance_type_matches( $value, $type ) {
	switch ( $type ) {
		case 'object':
			return is_object( $value );
		case 'array':
			return is_array( $value );
		case 'string':
			return is_string( $value );
		case 'integer':
			return is_int( $value );
		case 'number':
			return is_int( $value ) || is_float( $value );
		case 'boolean':
			return is_bool( $value );
		case 'null':
			return null === $value;
	}
	return false;
}

function tra_vel_commerce_conformance_format_valid( $value, $format ) {
	if ( ! is_string( $value ) ) {
		return true;
	}
	if ( 'date' === $format ) {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return false;
		}
		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}
	if ( 'date-time' === $format ) {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
		return false !== $date && $date->format( 'Y-m-d\TH:i:s\Z' ) === $value;
	}
	return true;
}

/**
 * Validate the Draft-07 keyword subset used by the three search contracts.
 */
function tra_vel_commerce_conformance_validate_schema( $instance, $schema, $root_schema, $path, &$errors ) {
	if ( ! is_object( $schema ) ) {
		$errors[] = $path . ': schema node is not an object.';
		return;
	}
	if ( property_exists( $schema, '$ref' ) ) {
		$target = tra_vel_commerce_conformance_resolve_ref( $root_schema, $schema->{'$ref'} );
		if ( null === $target ) {
			$errors[] = $path . ': unresolved schema reference ' . $schema->{'$ref'} . '.';
			return;
		}
		tra_vel_commerce_conformance_validate_schema( $instance, $target, $root_schema, $path, $errors );
		return;
	}

	if ( property_exists( $schema, 'type' ) ) {
		$types = is_array( $schema->type ) ? $schema->type : array( $schema->type );
		$valid = false;
		foreach ( $types as $type ) {
			if ( tra_vel_commerce_conformance_type_matches( $instance, $type ) ) {
				$valid = true;
				break;
			}
		}
		if ( ! $valid ) {
			$errors[] = $path . ': expected type ' . implode( '|', $types ) . '.';
			return;
		}
	}

	if ( property_exists( $schema, 'const' ) && ! tra_vel_commerce_conformance_deep_equal( $instance, $schema->const ) ) {
		$errors[] = $path . ': value does not match const.';
	}
	if ( property_exists( $schema, 'enum' ) ) {
		$found = false;
		foreach ( $schema->enum as $allowed ) {
			if ( tra_vel_commerce_conformance_deep_equal( $instance, $allowed ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$errors[] = $path . ': value is outside the enum.';
		}
	}

	if ( is_object( $instance ) ) {
		$properties = property_exists( $schema, 'properties' ) && is_object( $schema->properties ) ? $schema->properties : new stdClass();
		if ( property_exists( $schema, 'required' ) && is_array( $schema->required ) ) {
			foreach ( $schema->required as $required ) {
				if ( ! property_exists( $instance, $required ) ) {
					$errors[] = $path . ': missing required property ' . $required . '.';
				}
			}
		}
		foreach ( get_object_vars( $instance ) as $key => $value ) {
			if ( property_exists( $properties, $key ) ) {
				tra_vel_commerce_conformance_validate_schema( $value, $properties->{$key}, $root_schema, $path . '/' . $key, $errors );
			} elseif ( property_exists( $schema, 'additionalProperties' ) && false === $schema->additionalProperties ) {
				$errors[] = $path . ': unknown property ' . $key . '.';
			}
		}
	}

	if ( is_array( $instance ) ) {
		$count = count( $instance );
		if ( property_exists( $schema, 'minItems' ) && $count < $schema->minItems ) {
			$errors[] = $path . ': fewer than ' . $schema->minItems . ' items.';
		}
		if ( property_exists( $schema, 'maxItems' ) && $count > $schema->maxItems ) {
			$errors[] = $path . ': more than ' . $schema->maxItems . ' items.';
		}
		if ( property_exists( $schema, 'uniqueItems' ) && true === $schema->uniqueItems ) {
			$seen = array();
			foreach ( $instance as $item ) {
				$key = json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( isset( $seen[ $key ] ) ) {
					$errors[] = $path . ': contains a duplicate item.';
					break;
				}
				$seen[ $key ] = true;
			}
		}
		if ( property_exists( $schema, 'items' ) && is_object( $schema->items ) ) {
			foreach ( $instance as $index => $item ) {
				tra_vel_commerce_conformance_validate_schema( $item, $schema->items, $root_schema, $path . '/' . $index, $errors );
			}
		}
	}

	if ( is_string( $instance ) ) {
		$length = tra_vel_commerce_conformance_string_length( $instance );
		if ( property_exists( $schema, 'minLength' ) && $length < $schema->minLength ) {
			$errors[] = $path . ': string is shorter than ' . $schema->minLength . '.';
		}
		if ( property_exists( $schema, 'maxLength' ) && $length > $schema->maxLength ) {
			$errors[] = $path . ': string is longer than ' . $schema->maxLength . '.';
		}
		if ( property_exists( $schema, 'pattern' ) ) {
			$pattern = '~' . str_replace( '~', '\\~', $schema->pattern ) . '~u';
			if ( 1 !== @preg_match( $pattern, $instance ) ) {
				$errors[] = $path . ': string does not match ' . $schema->pattern . '.';
			}
		}
		if ( property_exists( $schema, 'format' ) && ! tra_vel_commerce_conformance_format_valid( $instance, $schema->format ) ) {
			$errors[] = $path . ': string is not a valid ' . $schema->format . '.';
		}
	}

	if ( is_int( $instance ) || is_float( $instance ) ) {
		if ( property_exists( $schema, 'minimum' ) && $instance < $schema->minimum ) {
			$errors[] = $path . ': value is below ' . $schema->minimum . '.';
		}
		if ( property_exists( $schema, 'maximum' ) && $instance > $schema->maximum ) {
			$errors[] = $path . ': value is above ' . $schema->maximum . '.';
		}
	}
}

function tra_vel_commerce_conformance_schema_errors( $value, $schema, $label ) {
	$errors   = array();
	$instance = tra_vel_commerce_conformance_json_object( $value );
	tra_vel_commerce_conformance_validate_schema( $instance, $schema, $schema, $label, $errors );
	return $errors;
}

function tra_vel_commerce_conformance_exact_keys( $value, $keys ) {
	if ( ! is_array( $value ) ) {
		return false;
	}
	$actual = array_keys( $value );
	sort( $actual, SORT_STRING );
	sort( $keys, SORT_STRING );
	return $actual === $keys;
}

function tra_vel_commerce_conformance_timestamp( $value ) {
	if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
		return false;
	}
	$parsed = strtotime( $value );
	return false === $parsed ? false : $parsed;
}

function tra_vel_commerce_conformance_offers( $result ) {
	$offers = array();
	foreach ( $result['groups'] as $group ) {
		foreach ( $group['offers'] as $offer ) {
			$offers[] = $offer;
		}
	}
	return $offers;
}

$schema_dir     = $root . '/plugin/tra-vel-agent-core/schemas';
$request_schema = tra_vel_commerce_conformance_schema( $schema_dir . '/commerce-search-request.schema.json', $failures );
$session_schema = tra_vel_commerce_conformance_schema( $schema_dir . '/commerce-search-session.schema.json', $failures );
$offer_schema   = tra_vel_commerce_conformance_schema( $schema_dir . '/commerce-offer.schema.json', $failures );

if ( ! isset( $request, $result, $engine, $context ) || ! is_array( $request ) || ! is_array( $result ) ) {
	tra_vel_commerce_conformance_fail( $failures, 'The search runtime did not leave its deterministic request/result fixtures available for conformance validation.' );
} elseif ( $request_schema && $session_schema && $offer_schema ) {
	foreach ( tra_vel_commerce_conformance_schema_errors( $request, $request_schema, 'request' ) as $error ) {
		tra_vel_commerce_conformance_fail( $failures, $error );
	}
	foreach ( tra_vel_commerce_conformance_schema_errors( $result['session'], $session_schema, 'session' ) as $error ) {
		tra_vel_commerce_conformance_fail( $failures, $error );
	}

	$offers = tra_vel_commerce_conformance_offers( $result );
	foreach ( $offers as $index => $offer ) {
		foreach ( tra_vel_commerce_conformance_schema_errors( $offer, $offer_schema, 'offers/' . $index ) as $error ) {
			tra_vel_commerce_conformance_fail( $failures, $error );
		}
	}

	tra_vel_commerce_conformance_expect(
		tra_vel_commerce_conformance_exact_keys( $result, array( 'contract_version', 'environment', 'catalog_digest', 'provider_network_digest', 'session', 'groups', 'sandbox_truth', 'data_boundary' ) ),
		$failures,
		$assertions,
		'The public search response envelope contains missing or unknown fields.'
	);

	$offer_by_ref       = array();
	$provider_run_pairs = array();
	$minimum_expiry     = null;
	foreach ( $result['session']['provider_runs'] as $run ) {
		$pair = $run['provider_id'] . '|' . $run['vertical'];
		tra_vel_commerce_conformance_expect( ! isset( $provider_run_pairs[ $pair ] ), $failures, $assertions, 'Provider run is duplicated: ' . $pair . '.' );
		$provider_run_pairs[ $pair ] = $run;
		$started   = tra_vel_commerce_conformance_timestamp( $run['started_at'] );
		$completed = tra_vel_commerce_conformance_timestamp( $run['completed_at'] );
		tra_vel_commerce_conformance_expect( false !== $started && false !== $completed && $completed >= $started, $failures, $assertions, 'Provider run timestamps are invalid or reversed: ' . $pair . '.' );
	}

	$group_keys = array();
	foreach ( $result['groups'] as $group_index => $group ) {
		tra_vel_commerce_conformance_expect( tra_vel_commerce_conformance_exact_keys( $group, array( 'vertical', 'currency', 'price_scope', 'offers' ) ), $failures, $assertions, 'Comparison group ' . $group_index . ' contains missing or unknown fields.' );
		$group_key = $group['vertical'] . '|' . $group['currency'] . '|' . $group['price_scope'];
		tra_vel_commerce_conformance_expect( ! isset( $group_keys[ $group_key ] ), $failures, $assertions, 'Comparison group is duplicated: ' . $group_key . '.' );
		$group_keys[ $group_key ] = true;
		$expected_rank = 1;
		foreach ( $group['offers'] as $offer ) {
			$ref = $offer['offer_ref'];
			tra_vel_commerce_conformance_expect( ! isset( $offer_by_ref[ $ref ] ), $failures, $assertions, 'Offer reference is duplicated: ' . $ref . '.' );
			$offer_by_ref[ $ref ] = $offer;
			tra_vel_commerce_conformance_expect( $group['vertical'] === $offer['vertical'] && $group['currency'] === $offer['pricing']['currency'] && $group['price_scope'] === $offer['pricing']['price_scope'], $failures, $assertions, 'Offer does not belong to its comparison group: ' . $ref . '.' );
			tra_vel_commerce_conformance_expect( $expected_rank === $offer['ranking']['rank'], $failures, $assertions, 'Ranks are not dense inside comparison group ' . $group_key . '.' );
			tra_vel_commerce_conformance_expect( $result['session']['session_ref'] === $offer['search_session_ref'], $failures, $assertions, 'Offer is not bound to the returned search session: ' . $ref . '.' );
			tra_vel_commerce_conformance_expect( $offer['status'] === $offer['availability']['state'], $failures, $assertions, 'Offer and availability status disagree: ' . $ref . '.' );

			$ledger = $offer['pricing'];
			$debits = $ledger['subtotal_amount_minor'] + $ledger['tax_amount_minor'] + $ledger['fee_amount_minor'];
			tra_vel_commerce_conformance_expect( $ledger['total_amount_minor'] === $debits - $ledger['credit_amount_minor'], $failures, $assertions, 'Money ledger does not reconcile: ' . $ref . '.' );
			$currency_exponent = Tra_Vel_Commerce_Money::exponent( $ledger['currency'] );
			tra_vel_commerce_conformance_expect( null !== $currency_exponent && $currency_exponent === $ledger['minor_unit'], $failures, $assertions, 'Money ledger exponent does not match its currency: ' . $ref . '.' );
			$line_totals = array( 'base' => 0, 'tax' => 0, 'fee' => 0, 'credit' => 0 );
			foreach ( $ledger['line_items'] as $line ) {
				if ( 'credit' === $line['direction'] ) {
					$line_totals['credit'] += $line['amount_minor'];
				} elseif ( 'tax' === $line['kind'] ) {
					$line_totals['tax'] += $line['amount_minor'];
				} elseif ( 'fee' === $line['kind'] ) {
					$line_totals['fee'] += $line['amount_minor'];
				} else {
					$line_totals['base'] += $line['amount_minor'];
				}
			}
			tra_vel_commerce_conformance_expect( $line_totals['base'] === $ledger['subtotal_amount_minor'] && $line_totals['tax'] === $ledger['tax_amount_minor'] && $line_totals['fee'] === $ledger['fee_amount_minor'] && $line_totals['credit'] === $ledger['credit_amount_minor'], $failures, $assertions, 'Money line items do not prove the ledger buckets: ' . $ref . '.' );

			$checked = tra_vel_commerce_conformance_timestamp( $offer['availability']['checked_at'] );
			$fresh   = tra_vel_commerce_conformance_timestamp( $offer['availability']['fresh_until'] );
			tra_vel_commerce_conformance_expect( false !== $checked && false !== $fresh && $fresh > $checked, $failures, $assertions, 'Offer freshness is invalid or non-positive: ' . $ref . '.' );
			tra_vel_commerce_conformance_expect( $offer['availability']['checked_at'] === $offer['evidence']['retrieved_at'] && $offer['availability']['fresh_until'] === $offer['evidence']['fresh_until'], $failures, $assertions, 'Availability and evidence windows disagree: ' . $ref . '.' );
			$minimum_expiry = null === $minimum_expiry ? $fresh : min( $minimum_expiry, $fresh );

			$place_refs = array();
			foreach ( $offer['geometry']['places'] as $place ) {
				tra_vel_commerce_conformance_expect( 1 === preg_match( '/^tv_place_[A-Za-z0-9_-]{16,96}$/', $place['place_ref'] ), $failures, $assertions, 'Place reference has the wrong opaque type: ' . $place['place_ref'] . '.' );
				$place_refs[ $place['place_ref'] ] = true;
			}
			foreach ( $offer['geometry']['segments'] as $segment ) {
				tra_vel_commerce_conformance_expect( 1 === preg_match( '/^tv_segment_[A-Za-z0-9_-]{16,96}$/', $segment['segment_ref'] ) && isset( $place_refs[ $segment['from_place_ref'] ], $place_refs[ $segment['to_place_ref'] ] ), $failures, $assertions, 'Route segment has a wrong opaque type or dangling endpoint: ' . $segment['segment_ref'] . '.' );
			}
			$expected_rank++;
		}
	}

	$ranked_refs = array();
	foreach ( $result['session']['ranked_offers'] as $ranked ) {
		$ref = $ranked['offer_ref'];
		tra_vel_commerce_conformance_expect( isset( $offer_by_ref[ $ref ] ), $failures, $assertions, 'Search session ranks an offer that is absent from the response: ' . $ref . '.' );
		tra_vel_commerce_conformance_expect( ! isset( $ranked_refs[ $ref ] ), $failures, $assertions, 'Search session ranks the same offer twice: ' . $ref . '.' );
		$ranked_refs[ $ref ] = true;
		if ( isset( $offer_by_ref[ $ref ] ) ) {
			$offer = $offer_by_ref[ $ref ];
			tra_vel_commerce_conformance_expect( $ranked['offer_version'] === $offer['version'] && $ranked['vertical'] === $offer['vertical'] && $ranked['currency'] === $offer['pricing']['currency'] && $ranked['price_scope'] === $offer['pricing']['price_scope'] && $ranked['rank'] === $offer['ranking']['rank'] && $ranked['score_bps'] === $offer['ranking']['score_bps'] && $ranked['reasons'] === $offer['ranking']['reasons'], $failures, $assertions, 'Search session rank projection differs from its exact comparison group: ' . $ref . '.' );
		}
	}
	tra_vel_commerce_conformance_expect( count( $ranked_refs ) === count( $offer_by_ref ), $failures, $assertions, 'Search session and response contain different offer sets.' );
	tra_vel_commerce_conformance_expect( $result['session']['counts']['providers_considered'] === count( $result['session']['provider_runs'] ), $failures, $assertions, 'Provider count does not equal the persisted provider-run set.' );
	tra_vel_commerce_conformance_expect( $result['session']['counts']['offers_validated'] === count( $offer_by_ref ), $failures, $assertions, 'Validated-offer count does not equal the public offer set.' );
	tra_vel_commerce_conformance_expect( null !== $minimum_expiry && $minimum_expiry === tra_vel_commerce_conformance_timestamp( $result['session']['expires_at'] ), $failures, $assertions, 'Search session expiry is not the earliest component offer expiry.' );

	$public_json = json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	tra_vel_commerce_conformance_expect( false !== $public_json && 0 === preg_match( '/\bpx_[a-z0-9_]{8,90}\b/', $public_json ) && false === strpos( $public_json, '"raw_supplier_reference":' ) && false === strpos( $public_json, '"commission":' ), $failures, $assertions, 'Public projection crosses a supplier-reference or commission data boundary.' );

	// A typed field must not accept an opaque reference belonging to another aggregate.
	$wrong_ref_session                = $result['session'];
	$wrong_ref_session['session_ref'] = $offers[0]['offer_ref'];
	$wrong_ref_errors                 = tra_vel_commerce_conformance_schema_errors( $wrong_ref_session, $session_schema, 'wrong-ref-session' );
	tra_vel_commerce_conformance_expect( ! empty( $wrong_ref_errors ), $failures, $assertions, 'commerce-search-session.schema.json accepts an offer reference in session_ref; replace the generic opaqueRef with typed sessionRef/requestRef/runRef/offerRef definitions.' );

	$wrong_ref_offer                           = $offers[0];
	$wrong_ref_offer['product']['product_ref'] = $result['session']['session_ref'];
	$wrong_offer_errors                        = tra_vel_commerce_conformance_schema_errors( $wrong_ref_offer, $offer_schema, 'wrong-ref-offer' );
	tra_vel_commerce_conformance_expect( ! empty( $wrong_offer_errors ), $failures, $assertions, 'commerce-offer.schema.json accepts a session reference in product_ref; replace generic opaqueRef usages with field-specific opaque reference definitions.' );

	// Canonical set-like preferences must not create a new identity solely by input order.
	$reordered_request = $request;
	$reordered_request['preferences']['priorities'] = array_reverse( $reordered_request['preferences']['priorities'] );
	$reordered_basis = $reordered_request;
	unset( $reordered_basis['request_digest'] );
	$reordered_request['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $reordered_basis );
	$reordered_result = $engine->search( $reordered_request, $context );
	tra_vel_commerce_conformance_expect( is_array( $reordered_result ) && $reordered_result['session']['session_ref'] === $result['session']['session_ref'], $failures, $assertions, 'Semantically identical set-like preferences produce a different session identity because request_digest is verified before normalization.' );

	// Exact dates and declared nights are one pricing basis, not alternatives.
	$reversed_dates = $request;
	$reversed_dates['trip']['date_window']['departure_earliest'] = '2026-08-10';
	$reversed_dates['trip']['date_window']['departure_latest']   = '2026-08-10';
	$reversed_dates['trip']['date_window']['return_earliest']    = '2026-08-01';
	$reversed_dates['trip']['date_window']['return_latest']      = '2026-08-01';
	$reversed_basis = $reversed_dates;
	unset( $reversed_basis['request_digest'] );
	$reversed_dates['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $reversed_basis );
	$reversed_result = $engine->search( $reversed_dates, $context );
	tra_vel_commerce_conformance_expect( is_wp_error( $reversed_result ), $failures, $assertions, 'Search accepts a return before departure and silently prices nights_min.' );

	$inconsistent_nights = $request;
	$inconsistent_nights['trip']['date_window']['nights_min'] = 6;
	$inconsistent_nights['trip']['date_window']['nights_max'] = 6;
	$inconsistent_basis = $inconsistent_nights;
	unset( $inconsistent_basis['request_digest'] );
	$inconsistent_nights['request_digest'] = Tra_Vel_Commerce_Policy::canonical_digest( $inconsistent_basis );
	$inconsistent_result = $engine->search( $inconsistent_nights, $context );
	tra_vel_commerce_conformance_expect( is_wp_error( $inconsistent_result ), $failures, $assertions, 'Search accepts exact dates seven nights apart with nights_min/nights_max set to six, causing a different stay price for the same dates.' );

	// A new observation time must not reuse version 1 of an immutable session/offer.
	$later_context        = $context;
	$later_context['now'] = $context['now'] + 60;
	$later_result         = $engine->search( $request, $later_context );
	tra_vel_commerce_conformance_expect( is_array( $later_result ) && $later_result['session']['session_ref'] !== $result['session']['session_ref'], $failures, $assertions, 'A search at a different observation time reuses the same session_ref and version while changing created_at/expires_at.' );
	if ( is_array( $later_result ) ) {
		$later_offers = tra_vel_commerce_conformance_offers( $later_result );
		$later_by_provider = array();
		foreach ( $later_offers as $later_offer ) {
			$later_by_provider[ $later_offer['provider_id'] ] = $later_offer;
		}
		foreach ( $offers as $offer ) {
			if ( isset( $later_by_provider[ $offer['provider_id'] ] ) ) {
				$later_offer = $later_by_provider[ $offer['provider_id'] ];
				tra_vel_commerce_conformance_expect( $later_offer['offer_ref'] !== $offer['offer_ref'] || $later_offer['version'] > $offer['version'], $failures, $assertions, 'A new observation changes timestamps under the same immutable offer_ref/version: ' . $offer['provider_id'] . '.' );
				tra_vel_commerce_conformance_expect( $later_offer['evidence']['evidence_digest'] !== $offer['evidence']['evidence_digest'], $failures, $assertions, 'Evidence digest does not bind retrieved_at/fresh_until for provider ' . $offer['provider_id'] . '.' );
			}
		}
	}

	// A catalog revision must be part of search-session identity.
	$catalog_path = $root . '/plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/product-catalog.json';
	$catalog_data = json_decode( file_get_contents( $catalog_path ), true );
	$temp_catalog = tempnam( sys_get_temp_dir(), 'travel-commerce-conformance-' );
	if ( is_array( $catalog_data ) && is_string( $temp_catalog ) ) {
		$catalog_data['products'][0]['pricing']['fee_amount_minor']++;
		file_put_contents( $temp_catalog, wp_json_encode( $catalog_data ) );
		$revised_catalog = new Tra_Vel_Commerce_Sandbox_Catalog( $temp_catalog );
		$revised_engine  = new Tra_Vel_Commerce_Search_Engine( $revised_catalog, 'runtime-server-offer-secret-0001' );
		$revised_result  = $revised_engine->search( $request, $context );
		tra_vel_commerce_conformance_expect( is_array( $revised_result ) && $revised_result['catalog_digest'] !== $result['catalog_digest'], $failures, $assertions, 'Catalog-revision probe did not change the catalog digest.' );
		tra_vel_commerce_conformance_expect( is_array( $revised_result ) && $revised_result['session']['session_ref'] !== $result['session']['session_ref'], $failures, $assertions, 'A different catalog snapshot reuses the same session_ref/version; bind catalog and provider-network digests into immutable session identity.' );
		unlink( $temp_catalog );
	} else {
		tra_vel_commerce_conformance_fail( $failures, 'Could not create the temporary catalog-revision probe.' );
	}

	// The search producer must be bound to the canonical provider descriptors.
	$network_path = $root . '/plugin/tra-vel-agent-core/assets/fixtures/commerce-sandbox/provider-network.json';
	$catalog_data = json_decode( file_get_contents( $catalog_path ), true );
	$network_data = json_decode( file_get_contents( $network_path ), true );
	if ( ! is_array( $catalog_data ) || ! is_array( $network_data ) ) {
		tra_vel_commerce_conformance_fail( $failures, 'Provider-network or product-catalog fixture is invalid JSON.' );
	} else {
		$network_by_id = array();
		foreach ( $network_data['providers'] as $descriptor ) {
			$network_by_id[ $descriptor['provider_id'] ] = $descriptor;
		}
		foreach ( $catalog_data['providers'] as $catalog_provider ) {
			$id = $catalog_provider['provider_id'];
			tra_vel_commerce_conformance_expect( isset( $network_by_id[ $id ] ), $failures, $assertions, 'Catalog provider has no canonical commerce-provider descriptor: ' . $id . '.' );
			if ( isset( $network_by_id[ $id ] ) ) {
				$descriptor = $network_by_id[ $id ];
				tra_vel_commerce_conformance_expect( 'ready' === $descriptor['readiness']['status'] && in_array( 'search', $descriptor['capabilities'], true ), $failures, $assertions, 'Search executes a provider that is not ready and search-capable: ' . $id . '.' );
				tra_vel_commerce_conformance_expect( ! array_diff( $catalog_provider['verticals'], $descriptor['verticals'] ), $failures, $assertions, 'Catalog provider verticals exceed its canonical descriptor: ' . $id . '.' );
			}
		}
		foreach ( $catalog_data['products'] as $product ) {
			$id = $product['provider_id'];
			if ( isset( $network_by_id[ $id ] ) ) {
				$missing_capabilities = array_values( array_diff( $product['capabilities'], $network_by_id[ $id ]['capabilities'] ) );
				tra_vel_commerce_conformance_expect( empty( $missing_capabilities ), $failures, $assertions, 'Product ' . $product['private_product_ref'] . ' advertises capabilities absent from provider ' . $id . ': ' . implode( ', ', $missing_capabilities ) . '.' );
			}
			if ( 'package' === $product['vertical'] ) {
				tra_vel_commerce_conformance_fail( $failures, 'Product ' . $product['private_product_ref'] . ' is an independently priced package offer; COMMERCE_SANDBOX_SYSTEM.md and commerce-package.schema.json require an atomic composition of versioned component offers.' );
			}
		}
	}
}

if ( $failures ) {
	echo 'Commerce search/schema conformance failed after ' . $assertions . ' checks.' . PHP_EOL;
	if ( '' !== $runtime_output ) {
		echo 'Runtime prerequisite: ' . $runtime_output . PHP_EOL;
	}
	foreach ( array_values( array_unique( $failures ) ) as $failure ) {
		echo '- ' . $failure . PHP_EOL;
	}
	exit( 1 );
}

echo 'Commerce search/schema conformance passed (' . $assertions . ' schema, linkage, money, ranking, freshness, and identity checks).' . PHP_EOL;
