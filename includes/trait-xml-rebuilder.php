<?php

namespace F2000CS;

use SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Trait XML_Rebuilder
 *
 * Shared helper that serialises a plain data array back into a
 * SimpleXMLElement tree, preserving attributes and nested children.
 *
 * Used by XML_Editor (via data arrays built by copy_offer_data) and by
 * XML_Export_Filter (inline data extraction in filter_xml_content).
 */
trait XML_Rebuilder {

	/**
	 * Re-create an element from its data structure inside a target element.
	 *
	 * @param array            $data   [ 'name', 'value', 'attributes' => [], 'children' => [] ]
	 * @param SimpleXMLElement $target Parent element to add the new child to.
	 * @return void
	 */
	private function data_to_xml( array $data, SimpleXMLElement $target ): void {
		$element = $target->addChild( $data['name'], htmlspecialchars( $data['value'], ENT_QUOTES ) );

		foreach ( $data['attributes'] as $attr_name => $attr_value ) {
			$element->addAttribute( $attr_name, $attr_value );
		}

		foreach ( $data['children'] as $child ) {
			$this->data_to_xml( $child, $element );
		}
	}
}
