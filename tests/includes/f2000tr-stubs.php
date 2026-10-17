<?php
/**
 * Minimal stubs for the Factorial2000 Translator classes used by the
 * Marketplace Prom feed (Russian/Ukrainian split).
 *
 * Only the reading API the feed uses is mirrored: Languages::get_languages(),
 * Languages::get_default_language() and Strings::translate() over whole-value
 * tagged strings like "[:uk]Текст[:ru]Текст[:]" — the shape the translator
 * stores.
 *
 * @package Factorial2000_Catalog_Sync
 */

namespace F2000TR;

if ( ! class_exists( 'F2000TR\Languages' ) ) {
	/**
	 * Enabled languages from the test state.
	 */
	class Languages {
		/**
		 * @return array<string, array{name:string,locale:string}>
		 */
		public static function get_languages() {
			return \F2000CS_Test_State::$f2000tr_languages;
		}

		/**
		 * @return string
		 */
		public static function get_default_language() {
			return \F2000CS_Test_State::$f2000tr_default_language;
		}
	}
}

if ( ! class_exists( 'F2000TR\Strings' ) ) {
	/**
	 * Tagged-string reader (subset: whole-value chunks + main-language fallback).
	 */
	class Strings {
		/**
		 * @param mixed  $text Value.
		 * @param string $lang Language code; the default language when empty.
		 * @return mixed
		 */
		public static function translate( $text, $lang = '' ) {
			if ( ! is_string( $text ) || false === strpos( $text, '[:' ) ) {
				return $text;
			}

			$lang = is_string( $lang ) && '' !== trim( $lang ) ? strtolower( trim( $lang ) ) : Languages::get_default_language();

			$map = array();

			if ( preg_match_all( '/\[:([a-z]{2}(?:-[a-z]{2,})?)\](.*?)(?=\[:[a-z]{2}(?:-[a-z]{2,})?\]|\[:\]|$)/is', $text, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$map[ strtolower( $match[1] ) ] = trim( $match[2] );
				}
			}

			if ( isset( $map[ $lang ] ) && '' !== $map[ $lang ] ) {
				return $map[ $lang ];
			}

			$main = Languages::get_default_language();

			return isset( $map[ $main ] ) ? $map[ $main ] : '';
		}
	}
}

if ( ! class_exists( 'F2000TR\WooCommerce' ) ) {
	/**
	 * WooCommerce integration stub: the getter filter callback the feed has to
	 * detach plus the group-wise display reader.
	 */
	class WooCommerce {
		/**
		 * One tagged group: a language tag up to the "[:]" closer or the end.
		 */
		const TAGGED_GROUP = '/\[:[a-z]{2}(?:-[a-z]{2,})?\].*?(?:\[:\]|$)/isu';

		/**
		 * Getter filter callback (the feed detaches and re-attaches this).
		 *
		 * @param mixed $text Text.
		 * @return mixed
		 */
		public function translate_text( $text ) {
			return $text;
		}

		/**
		 * Translate every tagged group inside a display string.
		 *
		 * @param mixed  $text Text.
		 * @param string $lang Language code.
		 * @return mixed
		 */
		public static function translate_display( $text, $lang = '' ) {
			if ( ! is_string( $text ) || false === strpos( $text, '[:' ) ) {
				return $text;
			}

			$out = preg_replace_callback(
				self::TAGGED_GROUP,
				static function ( $group ) use ( $lang ) {
					return (string) Strings::translate( $group[0], $lang );
				},
				$text
			);

			return is_string( $out ) ? $out : Strings::translate( $text, $lang );
		}
	}
}
