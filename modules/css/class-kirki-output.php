<?php
/**
 * Handles CSS output for fields.
 *
 * @package     Kirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.0
 */

/**
 * Handles field CSS output.
 */
class Kirki_Output {

	/**
	 * The Kirki configuration used in the field.
	 *
	 * @access protected
	 * @var string
	 */
	protected $config_id = 'global';

	/**
	 * The field's `output` argument.
	 *
	 * @access protected
	 * @var array
	 */
	protected $output = array();

	/**
	 * An array of the generated styles.
	 *
	 * @access protected
	 * @var array
	 */
	protected $styles = array();

	/**
	 * The value.
	 *
	 * @access protected
	 * @var string|array
	 */
	protected $value;

	/**
	 * The class constructor.
	 *
	 * @access public
	 * @param string       $config_id The config ID.
	 * @param array        $output    The output argument.
	 * @param string|array $value     The value.
	 */
	public function __construct( $config_id, $output, $value ) {

		$this->config_id = $config_id;
		$this->value     = $value;
		$this->output    = $output;

		$this->parse_output();
	}

	/**
	 * If we have a sanitize_callback defined, apply it to the value.
	 *
	 * @param array        $output The output args.
	 * @param string|array $value  The value.
	 *
	 * @return string|array
	 */
	protected function apply_sanitize_callback( $output, $value ) {

		if ( isset( $output['sanitize_callback'] ) && null !== $output['sanitize_callback'] ) {

			// If the sanitize_callback is invalid, return the value.
			if ( ! is_callable( $output['sanitize_callback'] ) ) {
				return $value;
			}
			return call_user_func( $output['sanitize_callback'], $this->value );
		}

		return $value;

	}

	/**
	 * If we have a value_pattern defined, apply it to the value.
	 *
	 * @param array        $output The output args.
	 * @param string|array $value  The value.
	 * @return string|array
	 */
	protected function apply_value_pattern( $output, $value ) {

		if ( isset( $output['value_pattern'] ) && ! empty( $output['value_pattern'] ) && is_string( $output['value_pattern'] ) ) {
			if ( ! is_array( $value ) ) {
				$value = str_replace( '$', $value, $output['value_pattern'] );
			}
			if ( is_array( $value ) ) {
				foreach ( array_keys( $value ) as $value_k ) {
					if ( ! is_string( $value[ $value_k ] ) ) {
						continue;
					}
					if ( isset( $output['choice'] ) ) {
						if ( $output['choice'] === $value_k ) {
							$value[ $output['choice'] ] = str_replace( '$', $value[ $output['choice'] ], $output['value_pattern'] );
						}
						continue;
					}
					$value[ $value_k ] = str_replace( '$', $value[ $value_k ], $output['value_pattern'] );
				}
			}
			$value = $this->apply_pattern_replace( $output, $value );
		} // End if().
		return $value;
	}

	/**
	 * If we have a value_pattern defined, apply it to the value.
	 *
	 * @param array        $output The output args.
	 * @param string|array $value  The value.
	 * @return string|array
	 */
	protected function apply_pattern_replace( $output, $value ) {
		if ( isset( $output['pattern_replace'] ) && is_array( $output['pattern_replace'] ) ) {
			$option_type = ( '' !== Kirki::get_config_param( $this->config_id, 'option_type' ) ) ? Kirki::get_config_param( $this->config_id, 'option_type' ) : 'theme_mod';
			$option_name = Kirki::get_config_param( $this->config_id, 'option_name' );
			$options     = array();
			if ( $option_name ) {
				$options = ( 'site_option' === $option_type ) ? get_site_option( $option_name ) : get_option( $option_name );
			}
			foreach ( $output['pattern_replace'] as $search => $replace ) {
				$replacement = '';
				switch ( $option_type ) {
					case 'option':
						if ( is_array( $options ) ) {
							if ( $option_name ) {
								$subkey = str_replace( array( $option_name, '[', ']' ), '', $replace );
								$replacement = ( isset( $options[ $subkey ] ) ) ? $options[ $subkey ] : '';
								break;
							}
							$replacement = ( isset( $options[ $replace ] ) ) ? $options[ $replace ] : '';
							break;
						}
						$replacement = get_option( $replace );
						break;
					case 'site_option':
						$replacement = ( is_array( $options ) && isset( $options[ $replace ] ) ) ? $options[ $replace ] : get_site_option( $replace );
						break;
					case 'user_meta':
						$user_id = get_current_user_id();
						if ( $user_id ) {
							// @codingStandardsIgnoreLine
							$replacement = get_user_meta( $user_id, $replace, true );
						}
						break;
					default:
						$replacement = get_theme_mod( $replace );
				}
				$replacement = ( false === $replacement ) ? '' : $replacement;
				if ( is_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						if ( isset( $value[ $v ] ) ) {
							$value[ $k ] = str_replace( $search, $replacement, $value[ $v ] );
						} else {
							$value[ $k ] = str_replace( $search, $replacement, $v );
						}
					}
					return $value;
				}
				$value = str_replace( $search, $replacement, $value );
			} // End foreach().
		} // End if().
		return $value;
	}

	/**
	 * Parses the output arguments.
	 * Calls the process_output method for each of them.
	 *
	 * @access protected
	 */
	protected function parse_output() {
		foreach ( $this->output as $output ) {
			$skip = false;

			// Apply any sanitization callbacks defined.
			$value = $this->apply_sanitize_callback( $output, $this->value );

			// Skip if value is empty.
			if ( '' === $this->value ) {
				$skip = true;
			}

			// No need to proceed this if the current value is the same as in the "exclude" value.
			if ( isset( $output['exclude'] ) && is_array( $output['exclude'] ) ) {
				foreach ( $output['exclude'] as $exclude ) {
					if ( is_array( $value ) ) {
						if ( is_array( $exclude ) ) {
							$diff1 = array_diff( $value, $exclude );
							$diff2 = array_diff( $exclude, $value );

							if ( empty( $diff1 ) && empty( $diff2 ) ) {
								$skip = true;
							}
						}
						// If 'choice' is defined check for sub-values too.
						// Fixes https://github.com/aristath/kirki/issues/1416.
						if ( isset( $output['choice'] ) && isset( $value[ $output['choice'] ] ) && $exclude == $value[ $output['choice'] ] ) {
							$skip = true;
						}
					}
					if ( $skip ) {
						continue;
					}

					// Skip if value is defined as excluded.
					if ( $exclude === $value || ( '' === $exclude && empty( $value ) ) ) {
						$skip = true;
					}
				}
			}
			if ( $skip ) {
				continue;
			}

			// Apply any value patterns defined.
			$value = $this->apply_value_pattern( $output, $value );

			if ( isset( $output['element'] ) && is_array( $output['element'] ) ) {
				$output['element'] = array_unique( $output['element'] );
				sort( $output['element'] );
				$output['element'] = implode( ',', $output['element'] );
			}

			$value = $this->process_value( $value, $output );
			$this->process_output( $output, $value );
		} // End foreach().
	}

	/**
	 * Parses an output and creates the styles array for it.
	 *
	 * @access protected
	 * @param array        $output The field output.
	 * @param string|array $value  The value.
	 *
	 * @return null
	 */
	protected function process_output( $output, $value ) {
		if ( ! isset( $output['element'] ) || ! isset( $output['property'] ) ) {
			return;
		}
		$output['media_query'] = ( isset( $output['media_query'] ) ) ? $output['media_query'] : 'global';
		$output['prefix']      = ( isset( $output['prefix'] ) ) ? $output['prefix'] : '';
		$output['units']       = ( isset( $output['units'] ) ) ? $output['units'] : '';
		$output['suffix']      = ( isset( $output['suffix'] ) ) ? $output['suffix'] : '';

		// Properties that can accept multiple values.
		// Useful for example for gradients where all browsers use the "background-image" property
		// and the browser prefixes go in the value_pattern arg.
		$accepts_multiple = array(
			'background-image',
			'background',
		);
		if ( in_array( $output['property'], $accepts_multiple, true ) ) {
			if ( isset( $this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ] ) && ! is_array( $this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ] ) ) {
				$this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ] = (array) $this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ];
			}
			$this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ][] = $output['prefix'] . $value . $output['units'] . $output['suffix'];
			return;
		}
		$this->styles[ $output['media_query'] ][ $output['element'] ][ $output['property'] ] = $output['prefix'] . $this->process_property_value( $output['property'], $value ) . $output['units'] . $output['suffix'];
	}

	/**
	 * Some CSS properties are unique.
	 * We need to tweak the value to make everything works as expected.
	 *
	 * @access protected
	 * @param string       $property The CSS property.
	 * @param string|array $value    The value.
	 *
	 * @return array
	 */
	protected function process_property_value( $property, $value ) {
		$properties = apply_filters(
			"kirki/{$this->config_id}/output/property-classnames", array(
				'font-family'         => 'Kirki_Output_Property_Font_Family',
				'background-image'    => 'Kirki_Output_Property_Background_Image',
				'background-position' => 'Kirki_Output_Property_Background_Position',
			)
		);
		if ( array_key_exists( $property, $properties ) ) {
			$classname = $properties[ $property ];
			$obj = new $classname( $property, $value );
			return $obj->get_value();
		}
		return $value;
	}

	/**
	 * Returns the value.
	 *
	 * @access protected
	 * @param string|array $value The value.
	 * @param array        $output The field "output".
	 * @return string|array
	 */
	protected function process_value( $value, $output ) {
		if ( isset( $output['property'] ) ) {
			return $this->process_property_value( $output['property'], $value );
		}
		return $value;
	}

	/**
	 * Exploses the private $styles property to the world
	 *
	 * @access protected
	 * @return array
	 */
	public function get_styles() {
		return $this->styles;
	}
}
