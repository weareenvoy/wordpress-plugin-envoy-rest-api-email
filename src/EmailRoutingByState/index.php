<?php
//	Reference(s):
//		https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
//		https://www.advancedcustomfields.com/resources/repeater/#:~:text=Accessing%20first%20row%20values&text=%3C%3Fphp%20%24rows%20%3D%20get_field('repeater_field_name'%20)%3B,step%20out%20at%20any%20time.
//		https://gist.github.com/BhargavBhandari90/1608cfecc56a4db3a966c03a900f851d
//		https://www.youtube.com/watch?v=iyhIbJyWTZY

require_once( __DIR__ . '/StateAbbreviations/index.php' );

// use WP_REST_Controller;
// use WP_REST_Server;
//	Right now, the following classes do not have an autoloader; they are already loaded from a `require_once()` which is called in a parent.
// use EnvoyRestAPIEmailRoutingByState;
// use StateAbbreviations;

class EmailRoutingByState extends WP_REST_Controller {

	static $NAMESPACE = 'envoy';
	static $HTTP_RESPONSE_200__category = [
		'status_code'	=>	200,
		'message'		=>	"This reqest was successful. But no action will be taken; there is no email routing needed when the form's 'category' field is neither 'claimant' nor 'provider' ."
	];
	static $HTTP_RESPONSE_400__state = [
		'status_code'	=>	400,
		'message'		=>	"The form's 'state' field must be present & contain a valid state name or abbreviation."
	];

	private $EMAIL_FROM_NAME	=	NULL;						//	default
	private $EMAIL_FROM_EMAIL	=	NULL;						//	default
	private $test_email_address	=	NULL;						//	default
	private $is_debug_mode		=	false;						//	default
	private $mapping_of_form_category_to_email_address	=	[];	//	default					//	default

	public function __construct() {

		$this->registerApiRoutes();
		$this->getPluginSettingsFromWordPress();

	}

	//	--------------
	//	Initialization
	//	--------------
	public function registerApiRoutes(){

		//	/wp-json/envoy/route_emails_by_state
		//	/wp-json/envoy/route_emails_by_state?category=claimant&state=maine
		//	/wp-json/envoy/route_emails_by_state?category=claimant&state=maine&subject=test_subject&first_name=test_fname&last_name=test_lname&email=test_email&phone=test_phone&company=test_company&city=test_city&message=test_message
		register_rest_route( SELF::$NAMESPACE, 'route_emails_by_state', array(
			array(
				'methods'	=>	implode(', ', [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE]),	//	GET, POST	//	We use GET for debugging. POST is what production will use.
				'callback'	=>	array( $this, 'routeEmailsByState' ),
				'permission_callback'	=>	'__return_true'
			)
		));

	}

	//	----------
	//	Handler(s)
	//	----------
	public function routeEmailsByState( WP_REST_Request $request ){

		$form_data = $request->get_params();

		//	Retreive list of pertinent contacts that this form data should be forwarded to
		$routing_contacts = [];

		switch( @$form_data['category'] ):
			case 'claimant':
				$routing_contacts = $this->getAcfGlobalSettingsRouting('claimant_routing');
				break;
			case 'provider':
				$routing_contacts = $this->getAcfGlobalSettingsRouting('provider_routing');
				break;
			default:
				//	Deliver emails to the contact(s) defined in this plugin's settings for 'category'
				$email_result = $this->sendEmail($form_data);

				//	Respond to network request
				$data = [
					'delivered_to_recipients_count' => count(explode(',', $this->lookupPrimaryEmailRecipient($form_data)))
				];
				//	If testing - append extra data for debugging
				if( $this->is_debug_mode ):
					$data['test_email_address']	=	$this->test_email_address;
					$data['email_result']		=	$email_result;
					$data["all_defined_mappings_of_category_field_to_email"]				=	$this->mapping_of_form_category_to_email_address;
					$data['default_category_email_address']									=	$this->default_email_address;
					$data['primary_recipient_from_category_mappings']						=	$this->lookupPrimaryEmailRecipient($form_data, true);
				endif;

				$response_http_status_code = $email_result ? 201 : 500 ;
				$response = new WP_REST_Response( $data, $response_http_status_code );
				$response->header( 'Access-Control-Allow-Origin', '*' );

				return $response;
		endswitch;

		//	Further refine the list of contacts to align with the given state
		//		Guard against no 'state' being defined in the received form payload.
		if( !@$form_data['state'] ):
			$response = new WP_REST_Response(SELF::$HTTP_RESPONSE_400__state, 400 );
			$response->header( 'Access-Control-Allow-Origin', '*' );

			return $response;
		endif;
		$state_object_derived_from_form = StateAbbreviations::getStateObjectFromNameOrAbbreviation( $form_data['state'] );

		$contacts_to_send_to = array_filter($routing_contacts, function($contact) use ($state_object_derived_from_form): Bool{
			$contact_pertains_to_state_in_form_data = 0 === strcasecmp(trim($contact['state_code']), $state_object_derived_from_form['abbreviation']);
			return $contact_pertains_to_state_in_form_data;
		});

		//	Deliver emails
		$email_result = $this->sendEmail($form_data, $contacts_to_send_to);

		//	Respond to network request
		$data = [
			'given_form_data_category'	=>	$form_data['category'],
			'given_form_data_state'		=>	$form_data['state'],
			'state'						=>	$state_object_derived_from_form,
			'contacts_count'			=>	count($contacts_to_send_to)
		];

		//	If testing - append extra data for debugging
		if( $this->is_debug_mode ):
			$data['test_email_address']	=	$this->test_email_address;
			$data['contacts']			=	$contacts_to_send_to;
			$data['email_result']		=	$email_result;
			$data["all_defined_mappings_of_category_field_to_email"]				=	$this->mapping_of_form_category_to_email_address;
			$data['default_category_email_address']									=	$this->default_email_address;
			$data['primary_recipient_from_category_mappings']						=	$this->lookupPrimaryEmailRecipient($form_data, true);
			$data["all_possible_routing_contacts_count__{$form_data['category']}"]	=	count($routing_contacts);
			$data["all_possible_routing_contacts__{$form_data['category']}"]		=	$routing_contacts;
		endif;

		$response_http_status_code = $email_result ? 201 : 500 ;
		$response = new WP_REST_Response( $data, $response_http_status_code );
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;

	}

	//	---------
	//	Helper(s)
	//	---------
	private function getAcfGlobalSettingsRouting($field_name){
		if( !$field_name ) return [];

		$routing_contacts = [];

		// Check if rows exist
		if( have_rows($field_name, 'option') ):

			// Loop through rows
			while( have_rows($field_name, 'option') ): the_row();

				// Load sub-field values
				$state_code			=	get_sub_field('state_code');
				$routing_contact	=	get_sub_field('routing_contact');
				$routing_email		=	get_sub_field('routing_email');

				//	Append row data onto object being returned
				$routing_contacts[] = [
					'state_code'	=>	$state_code,
					'name'			=>	$routing_contact,
					'email'			=>	$routing_email
				];

			endwhile;

		endif;

		return $routing_contacts;
	}

	private function sendEmail($form_data, Array $bcc_contacts = []){

		$to_addresses_from_wordpress_settings = $this->lookupPrimaryEmailRecipient($form_data);
		$to_addresses = array_map(
			function($email_address){
				return strtolower(trim($email_address));
			},
			explode(',', $to_addresses_from_wordpress_settings)
		);

		//	'TO' might have comma-separated values in it. We take the first one as 'TO' and put the rest into 'CC'.
		$to = reset($to_addresses);
		$cc_addresses = array_slice($to_addresses,1);
		$subject = 'Contact Us - Form Request';

		//	-------------
		//	Build Headers
		//	-------------
		//	FROM
		$headers[] = sprintf("From: %s <%s>",$this->EMAIL_FROM_NAME, $this->EMAIL_FROM_EMAIL);
		//	CC
		if( !$this->test_email_address ):

			foreach($cc_addresses as $contact):
				//	CC Addresses derived from comma-separated string values are not objects like the BCC list is
				//	You may notice the difference here between how we handle CC versus BCC $contacts
				$headers[] = sprintf("Cc: %s",$contact);
			endforeach;
			//	BCC
			foreach($bcc_contacts as $contact):
				$headers[] = sprintf("Bcc: %s",$contact['email']);
			endforeach;
		endif;

		//	----------
		//	Build Body
		//	----------
		$message = implode("\r\n", [
			sprintf("From: %s %s - <%s>",
				@$form_data['first_name'],
				@$form_data['last_name'],
				@$form_data['email'],
			),
			sprintf("Category: %s",			$form_data['category']),
			"\r\n",
			"Message Body:",
			"\r\n",
			sprintf("Category: %s",			$form_data['category']),
			sprintf("Subject: %s",			@$form_data['subject']),
			sprintf("First Name: %s",		@$form_data['first_name']),
			sprintf("Last Name: %s",		@$form_data['last_name']),
			sprintf("Email: %s",			@$form_data['email']),
			sprintf("Phone: %s",			@$form_data['phone']),
			sprintf("Company: %s",			@$form_data['company']),
			sprintf("City: %s",				@$form_data['city']),
			sprintf("State: %s",			$form_data['state']),
			sprintf("Message: %s",			@$form_data['message']),
			sprintf("Routing Names: %s",	implode(', ',array_map(function($_c){return $_c['name'];},$bcc_contacts))),
			sprintf("Routing Emails: %s",	implode(', ',array_map(function($_c){return $_c['email'];},$bcc_contacts))),
		]);

		$success = wp_mail( $to, $subject, $message, $headers );

		return [
			'success'	=>	$success,
			'to'		=>	$to,
			'subject'	=>	$subject,
			'headers'	=>	$headers,
			'message'	=>	$message,
			'logging'	=>	[
				'to_addresses_from_raw_wordpress_settings'	=> $to_addresses_from_wordpress_settings,
				'cc_addresses'	=>	$cc_addresses,
				'bcc_contacts'	=>	$bcc_contacts
			]
		];
	}

	private function lookupPrimaryEmailRecipient($form_data, $bypass_guards = false){

		//	Guard against testing scenarios
		if( !$bypass_guards && $this->test_email_address ):
			return $this->test_email_address;
		endif;

		//	Decide primary recipient based on the 'category' field value in the form data we received.
		$recipient_email_by_category = @$this->mapping_of_form_category_to_email_address[ $form_data['category'] ];

		if( !empty($recipient_email_by_category) ):
			return $recipient_email_by_category;
		endif;

		return $this->default_email_address;

	}

	//	--------------------------------------------
	//	Get settings saved into WordPress admin area
	//	--------------------------------------------
	private function getPluginSettingsFromWordPress(){

		//	Load the array of options from WordPress for us to read values out of
		$wordpress_plugin_option_key	=	sprintf('%s_option_name', EnvoyRestAPIEmailRoutingByState::$NS);
		$wordpress_plugin_options		=	get_option( $wordpress_plugin_option_key ); // Array of All Options

		//	WordPress setting that returns extra unformation in API responses; unsafe for production use.
		$this->is_debug_mode			=	&boolval( $wordpress_plugin_options['is_debug_mode_0'] );

		//	WordPress setting that defines a override test email address during debugging
		$this->test_email_address		=	&$wordpress_plugin_options['test_email_address_0'];

		//	WordPress setting that defines a default email address during debugging
		$this->default_email_address	=	&$wordpress_plugin_options['default_email_address_0'];

		$this->EMAIL_FROM_NAME			=	&$wordpress_plugin_options['send_email_from_name_0'];
		$this->EMAIL_FROM_EMAIL			=	&$wordpress_plugin_options['send_email_from_email_0'];

		//	WordPress setting(s) that define a mapping of form category to destination email address
		for( $i=0; $i<EnvoyRestAPIEmailRoutingByState::$MAXIMUM_MAPPING_VALUES_OF_FORM_CATEGORY_TO_EMAIL_ADDRESS; $i++):
			$_category_field_id = sprintf('category_%s_form_value_0', $i);
			$_category_field_value = &$wordpress_plugin_options[$_category_field_id];
			$_email_field_id = sprintf('category_%s_email_address_0', $i);
			$_email_field_value = &$wordpress_plugin_options[$_email_field_id];

			if( !empty($_category_field_value) && !empty($_email_field_value) ):
				$this->mapping_of_form_category_to_email_address[$_category_field_value] = $_email_field_value;
			endif;
		endfor;

		return $this;
	}

}//class
