<?php
$envoy_rest_api_utilities_plugin_options = null;
class Envoy_RestAPI_Utilities {
	private $plugin_options;
  //	-------
	//	Helpers
	//	-------
	static function getPluginSettingValue($field_id, $normalize_value = false){
    global $envoy_rest_api_utilities_plugin_options;

    if( !$envoy_rest_api_utilities_plugin_options ):
 			$envoy_plugin_options = get_option( sprintf('%s_option_name', EnvoyRestAPIEmailRouting::$NS) ); // Array of All Options
			$envoy_rest_api_utilities_plugin_options = $envoy_plugin_options;
		endif;

		//	Guard
		if( !isset( $envoy_rest_api_utilities_plugin_options[$field_id] ) ):
			return '';
		endif;

		$value = $envoy_rest_api_utilities_plugin_options[$field_id];

		if( $normalize_value ):
			return esc_attr( $value );
		endif;

		return $value;
	}
}