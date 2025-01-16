<?php
class Envoy_Sentry {

  static function obfuscateEmail(String $string):String{
    try{
      @list($mailbox, $host) = explode('@', $string);
      $tld = @end( explode('.',$host) );
  
      //	Guard
      if( !$mailbox or !$host or !$tld ):
        throw new Exception('The given string is not a valid email');
      endif;
  
      $obfuscated_email_string = sprintf('%s%s%s@%s%s.%s',
        $mailbox[0],	//	Mailbox first character
        str_repeat('*',max(0,strlen($mailbox)-2)),	//	Stars
        substr($mailbox,-1, strlen($mailbox)-1),	//	Mailbox last character
        strlen($host) - strlen($tld) - 2 ? $host[0] : '*' ,	//	Host first character (but star if one character)
        str_repeat('*',max(0,strlen($host)-strlen($tld)-2)),	//	Stars
        $tld	//	Top-level domain
      );
      return  $obfuscated_email_string;
    }catch(Exception $e){
      //	Probably isn't an email string
      return $string;
    }//trycatch
  
  }//func

	static function deliverErrorToSentryIo($error, $form_data){

		// Set config to sentry credentials
		$CONFIG = [
			'SENTRY' => [
				'ENVIRONMENT_TAG' => WP_ENV,
				'HOST' => WP_SENTRY_HOST, // same for all 3 brands, specific to overall sentry account
				'PROJECT_ID' => WP_SENTRY_PROJECT_ID, // is different for each brand
				'PUBLIC_KEY' => WP_SENTRY_PUBLIC_KEY, // is different for each brand
			]
		];
		if (empty(WP_SENTRY_HOST) || empty(WP_SENTRY_PROJECT_ID) || empty(WP_SENTRY_PUBLIC_KEY)) {
			throw new \Exception('Missing Sentry configuration value from env. Requires all of WP_SENTRY_HOST, WP_SENTRY_PROJECT_ID, and WP_SENTRY_PUBLIC_KEY', 1);
		}

		// Construct the payload
		$payload = [
			'exception' => [
				'values' => [
					[
						'type' => $error['type'],
						'value' => $error['message'],
						'stacktrace' => [
							'frames' => array_map(function ($frame) {
								return [
									'filename' => $frame['file'] ?? 'Unknown',
									'function' => $frame['function'] ?? 'N/A',
									'lineno'   => $frame['line'] ?? 0,
								];
							}, $error['stacktrace'])
						]
					]
				]
			],
			'message' => $error['message'],
			'level' => 'error',
			'tags' => [
				'environment' => $CONFIG['SENTRY']['ENVIRONMENT_TAG'],
			],
			'user' => [
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
			],
			'extra' => [
				'email' => SELF::obfuscateEmail($form_data['email']) ?? '',
				'category' => $form_data['category'] ?? '',
				'subject' => $form_data['subject'] ?? '',
				'state' => $form_data['state'] ?? '',
				'formId' => $form_data['formId'] ?? '',
			]
		];

		$url = "https://" . $CONFIG['SENTRY']['HOST'] . "/api/" . $CONFIG['SENTRY']['PROJECT_ID'] . "/store/";

		// Headers
		$headers = [
			'Authorization' => 'Basic ' . base64_encode($CONFIG['SENTRY']['PUBLIC_KEY'] . ':'),
			'Content-Type'  => 'application/json',
		];

		// Send the request using wp_remote_post
		$response = wp_remote_post($url, [
			'headers' => $headers,
			'body'    => json_encode($payload),
			'timeout' => 10,
		]);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$data = [
				'success' => false,
				'message' => 'Sentry request failed',
				'error'   => $error_message
			];
			$response_http_status_code = 500;
		} else {
			$httpCode = wp_remote_retrieve_response_code($response);
			$responseBody = wp_remote_retrieve_body($response);
			$data = [
				'success' => true,
				'status_code' => $httpCode,
				'response_body' => $responseBody
			];
			$response_http_status_code = $httpCode;
		}

		$response = new WP_REST_Response($data, $response_http_status_code);
		$response->header('Access-Control-Allow-Origin', '*');

		return $response;
	}
}