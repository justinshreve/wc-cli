<?php

namespace WC_CLI;

class WC_RestCommand extends RestCommand {

	public function __construct( $name, $route, $schema ) {
		parent::__construct( $name, $route, $schema );
	}

	public function create_item( $args, $assoc_args ) {
        $assoc_args = self::unflatten_array( $assoc_args );
        parent::create_item( $args, $assoc_args );
	}

	public function update_item( $args, $assoc_args ) {
		$assoc_args = self::unflatten_array( $assoc_args );
        parent::update_item( $args, $assoc_args );
	}

    /**
	 * Unflatten array will make key 'a-b-c' becomes nested array:
	 *
	 *     array(
	 *         'a' => array(
	 *             'b' => array(
	 *                 'c' => ...
	 *             )
	 *         )
	 *     )
	 *
	 * @param  array $arr Flattened array
	 * @return array
	 */
	protected function unflatten_array( $arr ) {
		$unflatten = array();

		foreach ( $arr as $key => $value ) {
			$key_list  = explode( '-', $key );
			$first_key = array_shift( $key_list );
			$first_key = self::get_normalized_array_key( $first_key );
			if ( sizeof( $key_list ) > 0 ) {
				$remaining_keys = implode( '-', $key_list );
				$subarray       = self::unflatten_array( array( $remaining_keys => $value ) );

				foreach ( $subarray as $sub_key => $sub_value ) {
					$sub_key = self::get_normalized_array_key( $sub_key );
					if ( ! empty( $unflatten[ $first_key ][ $sub_key ] ) ) {
						$unflatten[ $first_key ][ $sub_key ] = array_merge_recursive( $unflatten[ $first_key ][ $sub_key ], $sub_value );
					} else {
						$unflatten[ $first_key ][ $sub_key ] = $sub_value;
					}
				}
			} else {
				$unflatten[ $first_key ] = $value;
			}
		}

		return $unflatten;
	}

    /**
     * Get normalized array key. If key is a numeric one it will be converted
     * as absolute integer.
     *
     * @param  string $key Array key
     * @return string|int
    */
    protected function get_normalized_array_key( $key ) {
        if ( is_numeric( $key ) ) {
            $key = absint( $key );
        }
        return $key;
    }

}
