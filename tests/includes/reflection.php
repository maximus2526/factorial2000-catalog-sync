<?php
/**
 * Reflection helpers for testing private / protected members.
 *
 * @package Factorial2000_Catalog_Sync
 */

/**
 * Reflection utilities.
 */
final class F2000CS_Test_Reflection {

	/**
	 * Invoke a private/protected method on an object or class.
	 *
	 * @param object|string $object Object instance or class name.
	 * @param string        $method Method name.
	 * @param array         $args   Arguments.
	 * @return mixed
	 */
	public static function invoke( $object, $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $object, $method );

		return $reflection->invokeArgs( $object, $args );
	}

	/**
	 * Get a private/protected property value.
	 *
	 * @param object $object Object instance.
	 * @param string $property Property name.
	 * @return mixed
	 */
	public static function get( $object, $property ) {
		$reflection = new ReflectionProperty( $object, $property );

		return $reflection->getValue( $object );
	}

	/**
	 * Set a private/protected property value.
	 *
	 * @param object $object Object instance.
	 * @param string $property Property name.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	public static function set( $object, $property, $value ) {
		$reflection = new ReflectionProperty( $object, $property );
		$reflection->setValue( $object, $value );
	}
}
