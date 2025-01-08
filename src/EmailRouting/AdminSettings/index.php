<?php
/**
 * The work in this file is based around the output of an autogenerator:
 * https://jeremyhixon.com/tool/wordpress-option-page-generator/
 * These contents were heavily modified after generation so avoid copy/pasting new output from the generator.
 */
require_once( __DIR__ . '/../Utilities/index.php' );
class EnvoyRestAPIEmailRouting {

	static $MAXIMUM_MAPPING_VALUES_OF_FORM_CATEGORY_TO_EMAIL_ADDRESS = 5;
	static $NS			=	'envoy_rest_api_email_routing';
	static $NS_HANDLE	=	'envoy-rest-api-email-routing';
	static $ERROR_MESSAGE_INVALID_FIELD_PAIRING = 'Both category and email are required if one is defined.';

	private $envoy_rest_api_email_routing_options;

	public function __construct() {
		add_action( 'admin_menu', array( $this, sprintf('%s_add_plugin_page', SELF::$NS) ) );
		add_action( 'admin_init', array( $this, sprintf('%s_page_init', SELF::$NS) ) );
	}

	public function envoy_rest_api_email_routing_add_plugin_page() {

		//  -- This adds a top-level menu item --
		//	add_menu_page(
		//		'Envoy Rest API - Email Routing By State', // page_title
		//		'Envoy Rest API - Email Routing By State', // menu_title
		//		'manage_options', // capability
		//		'envoy-rest-api-email-routing-by-state', // menu_slug
		//		array( $this, 'envoy_rest_api_email_routing_create_admin_page' ), // function
		//		'dashicons-admin-generic', // icon_url
		//		80 // position
		//	);

		//  -- This adds a sub-level menu item under an existing top-level menu item --
		add_submenu_page(
			is_network_admin() ? 'settings.php' : 'options-general.php' , // parent_slug
			'Envoy Rest API - Email Routing', // page_title
			'Form Routing Emails', // menu_title
			'manage_options',
			SELF::$NS_HANDLE, // menu_slug
			array( $this, sprintf('%s_create_admin_page', SELF::$NS) ), // function
			80 // position
		);
	}

	//	-----------
	//	This merely is responsible for rendering the wrapper hml form contains input fields in the admin area.
	//	-----------
	public function envoy_rest_api_email_routing_create_admin_page() {
		$this->envoy_rest_api_email_routing_options = get_option( sprintf('%s_option_name', SELF::$NS) ); ?>

		<div class="wrap">
			<h2>Envoy Rest API - Email Routing (by `category` & `state`)</h2>
			<p></p>

			<?php settings_errors(); ?>

			<hr/>

			<form method="post" action="options.php">
				<?php
					settings_fields( sprintf('%s_option_group', SELF::$NS) );
					do_settings_sections( sprintf('%s-admin', SELF::$NS_HANDLE) );
					submit_button();
				?>
			</form>

			<hr/>

			<table class="wp-list-table widefat fixed striped">
				<caption style="color:DodgerBlue; font-weight:900;">API Endpoint(s)</caption>
				<thead>
					<tr>
						<th>Endpoint Method & Path</th>
						<th>Headers</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><pre>POST /wp-json/envoy/route_emails_by_state</pre></td>
						<td><pre>Content-Type: multipart/form-data; boundary=</pre></td>
						<td>This receives a POST request containing a `category` & `state` field from form data and relays the submitted data to email recipients defined in WordPress AdvancedCustomFields plugin option tables / settings.</td>
					</tr>
					<tr>
						<td><pre>POST /wp-json/envoy/route_emails_by_category</pre></td>
						<td><pre>Content-Type: multipart/form-data; boundary=</pre></td>
						<td>This receives a POST request containing a `category` field from form data and relays the submitted data to email recipients defined in plugin settings.</td>
					</tr>
				</tbody>
			</table>

		</div>

		<?php if(!$this->is_field_pairing_valid()): ?>
			<script>
			window.addEventListener('load',function(){
				window.alert(<?php echo json_encode(self::$ERROR_MESSAGE_INVALID_FIELD_PAIRING); ?>);
			},false);
			</script>
		<?php endif; ?>

	<?php }

	//	-------
	//	This is responsible for (in reverse order):
	//		-	Creating an admin setting field
	//		-	The setting section that contains that field
	//		-	The setting configuration that connects the field to the saved value in the database
	//	-------
	public function envoy_rest_api_email_routing_page_init() {
		register_setting(
			sprintf('%s_option_group', SELF::$NS), // option_group
			sprintf('%s_option_name', SELF::$NS), // option_name
			array( $this, sprintf('%s_sanitize', SELF::$NS) ) // sanitize_callback
		);


		//	----------------------
		//	Email Sending Settings
		//	----------------------
		add_settings_section(
			sprintf('%s_setting_section_email_sending', SELF::$NS),		// id
			'⚙️ Email Sending ⚙️',									//	title
			array( $this, sprintf('%s_section_info', SELF::$NS) ),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE)					// page
		);

		add_settings_field(
			'send_email_from_name_0',								// id
			'📇 *Send from Email Name',								// title
			array( $this, 'send_email_from_name_0_callback'),		// callback
			sprintf('%s-admin', SELF::$NS_HANDLE),					// page
			sprintf('%s_setting_section_email_sending', SELF::$NS)	// section
		);

		add_settings_field(
			'send_email_from_email_0',								// id
			'📧 *Send from Email Address',								// title
			array( $this, 'send_email_from_email_0_callback' ),		// callback
			sprintf('%s-admin', SELF::$NS_HANDLE),					// page
			sprintf('%s_setting_section_email_sending', SELF::$NS)	// section
		);

		//	----------------------
		//	Email Mapping Settings
		//	----------------------
		add_settings_section(
			sprintf('%s_setting_section_mappings_general', SELF::$NS),		// id
			'⚙️ Mappings: General ⚙️',									//	title
			array( $this, sprintf('%s_section_info', SELF::$NS) ),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE)					// page
		);

		add_settings_field(
			'default_email_address_0',											// id
			'📧 *Default recipient Email Address',			// title
			array( $this, 'default_category_email_address_0_callback'),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE),				// page
			sprintf('%s_setting_section_mappings_general', SELF::$NS)			// section
		);

		//	-------------------------------------------------------
		//	Table of 'Category' field value to Email Routing Values
		//	-------------------------------------------------------
		add_settings_section(
			sprintf('%s_setting_section_mapping_of_category_to_email', SELF::$NS), // id
			"⚙️ Mappings: Form 'category' → Recipient Emails ⚙️", // title
			array( $this, sprintf('%s_section_info', SELF::$NS) ), // callback
			sprintf('%s-admin', SELF::$NS_HANDLE) // page
		);
		//	https://wordpress.stackexchange.com/questions/21256/how-to-pass-arguments-from-add-settings-field-to-the-callback-function
		for( $i=0; $i<SELF::$MAXIMUM_MAPPING_VALUES_OF_FORM_CATEGORY_TO_EMAIL_ADDRESS; $i++):

			//	The form's 'category' value that needs to trigger the email(s) being sent
			$_field_id = sprintf('category_%s_form_value_0', $i);
			$_arguments_given_to_callback = [
				'id' => $i,
				'field_id' => $_field_id
			];
			add_settings_field(
				$_field_id,											// id
				sprintf('%s - Category Target Value🔍', $i+1),			// title
				array( $this, 'category_field_value_callback'),		// callback
				sprintf('%s-admin', SELF::$NS_HANDLE),				// page
				sprintf('%s_setting_section_mapping_of_category_to_email', SELF::$NS),			// section
				$_arguments_given_to_callback
			);

			//	The email address(es) to send to for this form 'category' value
			$_field_id = sprintf('category_%s_email_address_0', $i);
			$_arguments_given_to_callback = [
				'id' => $i,
				'field_id' => $_field_id
			];
			add_settings_field(
				$_field_id,											// id
				sprintf('%s - Category Email Address📧', $i+1),		// title
				array( $this, 'category_email_address_callback'),	// callback
				sprintf('%s-admin', SELF::$NS_HANDLE),				// page
				sprintf('%s_setting_section_mapping_of_category_to_email', SELF::$NS),			// section
				$_arguments_given_to_callback
			);

		endfor;

		//	----------------------------
		//	Testing & Debugging Settings
		//	----------------------------
		add_settings_section(
			sprintf('%s_setting_section_testing', SELF::$NS),		// id
			'⚙️ Testing & Debugging ⚙️',									//	title
			array( $this, sprintf('%s_section_info', SELF::$NS) ),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE)					// page
		);

		add_settings_field(
			'is_debug_mode_0',									// id
			'⚠️ Include Debug Information in API Reponses',		// title
			array( $this, 'is_debug_mode_0_callback' ),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE),				// page
			sprintf('%s_setting_section_testing', SELF::$NS)	// section
		);

		add_settings_field(
			'test_email_address_0',								// id
			'⚠️ Test Email - Debugging Override',				// title
			array( $this, 'test_email_address_0_callback' ),	// callback
			sprintf('%s-admin', SELF::$NS_HANDLE),				// page
			sprintf('%s_setting_section_testing', SELF::$NS)	// section
		);

	}

	//	-----------
	//	This merely is responsible for form-submission validation of WordPress admin area submission.
	//	It will mutate the submission data upon save and hand it off to the database after mutation.
	//	If fields are not explicity placed here into the sanitized output then they will not be saved.
	//	-----------
	public function envoy_rest_api_email_routing_sanitize($input) {
		$sanitary_values = array();

		//	Pass this one text field straight through.
		//	Don't treat this like the rest of the fields; don't lower-case it.
		$sanitary_values['send_email_from_name_0'] = trim($input['send_email_from_name_0']);
		// $sanitary_values['is_debug_mode_0'] = $input['is_debug_mode_0'];

		//	Add our static field ids
		$field_ids_to_sanitize = [
			'is_debug_mode_0',
			'test_email_address_0',
			'default_email_address_0',
			// 'send_email_from_name_0',	//	Don't treat this like the rest of the fields.
			'send_email_from_email_0'
		];

		//	Add our dynamically generated field_ids
		for( $i=0; $i<SELF::$MAXIMUM_MAPPING_VALUES_OF_FORM_CATEGORY_TO_EMAIL_ADDRESS; $i++):
			$_field_id_1 = sprintf('category_%s_form_value_0', $i);
			$_field_id_2 = sprintf('category_%s_email_address_0', $i);
			$field_ids_to_sanitize[] = $_field_id_1;
			$field_ids_to_sanitize[] = $_field_id_2;
		endfor;

		foreach( $field_ids_to_sanitize as $_field_id ):
			if( isset( $input[$_field_id] ) ):
				$sanitary_values[$_field_id] = strtolower(trim(sanitize_text_field( $input[$_field_id] )));
			endif;
		endforeach;

		if (!$this ->is_field_pairing_valid()): 
			add_settings_error(
					sprintf('%s_option_group', SELF::$NS),
					'category_email_required',
					SELF::$ERROR_MESSAGE_INVALID_FIELD_PAIRING,
					'error'
			);

		endif;

		return $sanitary_values;
	}

	private function is_field_pairing_valid() {
		for ($i = 0; $i < SELF::$MAXIMUM_MAPPING_VALUES_OF_FORM_CATEGORY_TO_EMAIL_ADDRESS; $i++):
			
			$category_value = Envoy_RestAPI_Utilities::getPluginSettingValue(sprintf('category_%s_form_value_0', $i));
			$category_email = Envoy_RestAPI_Utilities::getPluginSettingValue(sprintf('category_%s_email_address_0', $i));
			// If a category target value is set but email address is empty, or vice versa.
			$conditions = [
				(int)empty($category_value), 
				(int)empty($category_email),
			];
			$conditions_that_are_true = array_sum($conditions);

			if ($conditions_that_are_true === 1):
					return false;
			endif;
			
		endfor;

		return true;
	}

	public function envoy_rest_api_email_routing_section_info() {
	}

	//	-----------
	//	This merely is responsible for rendering the input field in the admin area.
	//	-----------
	public function is_debug_mode_0_callback() {
		$field_id = 'is_debug_mode_0';
		printf(
			'<input class="regular-checkbox" type="checkbox" name="%s_option_name[%s]" id="%s" %s value="1">
			<div style="color:lightslategrey; font-style:italic;">Leave this OFF IN PRODUCTION. If enabled, extra debugging information will be included in API responses.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) && esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) == 1 ? 'checked' : ''
		);
	}
	public function test_email_address_0_callback() {
		$field_id = 'test_email_address_0';
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:lightslategrey; font-style:italic;">Leave this BLANK IN PRODUCTION. When this is defined, all routing emails will go to this address instead of anything else.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : ''
		);
	}
	public function send_email_from_name_0_callback() {
		$field_id = 'send_email_from_name_0';
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:lightslategrey; font-style:italic;">This will show as the email sender\'s Name.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : ''
		);
	}

	public function send_email_from_email_0_callback() {
		$field_id = 'send_email_from_email_0';
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:lightslategrey; font-style:italic;">This will show as the email sender\'s Email Address.<br />If using AWS SES to send email, this value MUST be a "verified sender" within AWS SES.<br />Otherwise emails will fail to send.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : ''
		);
	}
	public function default_category_email_address_0_callback() {
		$field_id = 'default_email_address_0';
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:black; font-style:italic;">A fallback; when a form data \'category\' value is received for which mapping is NOT defined below, this default email address will receive the form submission data.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : ''
		);
	}

	public function category_field_value_callback($args) {
		$field_id = $args['field_id'];
		$text_color = $args['id'] % 2 == 0 ? 'cornflowerblue' : 'darkseagreen' ;
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:%s; font-style:italic;">Enter a "category" value to listen for in form submissions.</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : '',
			$text_color	//	Info text - font color
		);
	}
	public function category_email_address_callback($args) {
		$field_id = $args['field_id'];
		$text_color = $args['id'] % 2 == 0 ? 'cornflowerblue' : 'darkseagreen' ;
		printf(
			'<input class="regular-text" type="text" name="%s_option_name[%s]" id="%s" value="%s">
			<div style="color:%s; font-style:italic;">Enter a corresponding email address or comma-separated email addresses</div>',
			SELF::$NS,
			$field_id,
			$field_id,
			isset( $this->envoy_rest_api_email_routing_options[$field_id] ) ? esc_attr( $this->envoy_rest_api_email_routing_options[$field_id]) : '',
			$text_color	//	Info text - font color
		);
	}

}
if ( is_admin() )
	$envoy_rest_api_email_routing = new EnvoyRestAPIEmailRouting();

/* 
 * Retrieve this value with:
 * $envoy_rest_api_email_routing_options = get_option( 'envoy_rest_api_email_routing_option_name' ); // Array of All Options
 * $test_email_address_0 = $envoy_rest_api_email_routing_options['test_email_address_0']; // Test Email Address
 */
