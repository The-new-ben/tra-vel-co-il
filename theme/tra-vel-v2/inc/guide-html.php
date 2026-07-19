<?php
/**
 * Small, quote-aware HTML helpers used by destination-guide navigation.
 *
 * @package TraVelV2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return opening HTML tags without treating a greater-than sign inside a
 * quoted attribute as the end of the tag.
 *
 * This intentionally implements only the tokenizer boundary needed by the
 * guide shell. Attribute parsing remains limited to stable ID extraction.
 *
 * @param string $html Guide HTML.
 * @return array<int, string>
 */
function tra_vel_v2_tokenize_guide_html_tags( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return array();
	}

	$tags            = array();
	$length          = strlen( $html );
	$cursor          = 0;
	$whitespace      = " \t\r\n\f";
	$single_quote    = "'";
	$double_quote    = '"';

	while ( $cursor < $length ) {
		$tag_start = strpos( $html, '<', $cursor );
		if ( false === $tag_start ) {
			break;
		}

		if ( '<!--' === substr( $html, $tag_start, 4 ) ) {
			$comment_end = strpos( $html, '-->', $tag_start + 4 );
			if ( false === $comment_end ) {
				break;
			}
			$cursor = $comment_end + 3;
			continue;
		}

		if ( $tag_start + 1 >= $length || 1 !== preg_match( '/[a-z]/i', $html[ $tag_start + 1 ] ) ) {
			$cursor = $tag_start + 1;
			continue;
		}

		$state   = 'tag_name';
		$tag_end = null;
		for ( $index = $tag_start + 2; $index < $length; $index++ ) {
			$character = $html[ $index ];
			$is_space   = false !== strpos( $whitespace, $character );
			if ( 'attribute_value_double' === $state ) {
				if ( $double_quote === $character ) {
					$state = 'after_attribute_value_quoted';
				}
			} elseif ( 'attribute_value_single' === $state ) {
				if ( $single_quote === $character ) {
					$state = 'after_attribute_value_quoted';
				}
			} elseif ( 'before_attribute_value' === $state ) {
				if ( $is_space ) {
					continue;
				}
				if ( $double_quote === $character ) {
					$state = 'attribute_value_double';
				} elseif ( $single_quote === $character ) {
					$state = 'attribute_value_single';
				} elseif ( '>' === $character ) {
					$tag_end = $index;
				} else {
					$state = 'attribute_value_unquoted';
				}
			} elseif ( 'attribute_value_unquoted' === $state ) {
				if ( '>' === $character ) {
					$tag_end = $index;
				} elseif ( $is_space ) {
					$state = 'before_attribute_name';
				}
			} elseif ( 'attribute_name' === $state ) {
				if ( '>' === $character ) {
					$tag_end = $index;
				} elseif ( '=' === $character ) {
					$state = 'before_attribute_value';
				} elseif ( $is_space ) {
					$state = 'after_attribute_name';
				}
			} elseif ( 'after_attribute_name' === $state ) {
				if ( '>' === $character ) {
					$tag_end = $index;
				} elseif ( '=' === $character ) {
					$state = 'before_attribute_value';
				} elseif ( ! $is_space && '/' !== $character ) {
					$state = 'attribute_name';
				}
			} elseif ( 'after_attribute_value_quoted' === $state ) {
				if ( '>' === $character ) {
					$tag_end = $index;
				} elseif ( $is_space || '/' === $character ) {
					$state = 'before_attribute_name';
				} else {
					$state = 'attribute_name';
				}
			} elseif ( 'before_attribute_name' === $state ) {
				if ( '>' === $character ) {
					$tag_end = $index;
				} elseif ( ! $is_space && '/' !== $character ) {
					$state = 'attribute_name';
				}
			} elseif ( '>' === $character ) {
				$tag_end = $index;
			} elseif ( $is_space || '/' === $character ) {
				$state = 'before_attribute_name';
			}
			if ( null !== $tag_end ) {
				break;
			}
		}

		if ( null === $tag_end ) {
			$cursor = $tag_start + 1;
			continue;
		}

		$tags[] = substr( $html, $tag_start, $tag_end - $tag_start + 1 );
		$cursor = $tag_end + 1;
	}

	return $tags;
}

/**
 * Extract the first literal ID attribute from each opening guide tag.
 *
 * @param string $html Guide HTML.
 * @return array<string, bool>
 */
function tra_vel_v2_extract_guide_content_ids( $html ) {
	$content_ids = array();
	$id_pattern  = '/[\x20\t\r\n\f]id[\x20\t\r\n\f]*=[\x20\t\r\n\f]*(?:"([^"]*)"|\'([^\']*)\'|([^\x20\t\r\n\f"\'=<>`]+))/i';

	foreach ( tra_vel_v2_tokenize_guide_html_tags( $html ) as $tag ) {
		if ( 1 !== preg_match( $id_pattern, $tag, $id_match ) ) {
			continue;
		}

		$guide_id = '';
		foreach ( array( 1, 2, 3 ) as $id_index ) {
			if ( isset( $id_match[ $id_index ] ) && '' !== $id_match[ $id_index ] ) {
				$guide_id = $id_match[ $id_index ];
				break;
			}
		}
		if ( '' !== $guide_id && false === strpos( $guide_id, '&' ) ) {
			$content_ids[ $guide_id ] = true;
		}
	}

	return $content_ids;
}
