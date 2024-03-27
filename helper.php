<?php

class DEVVPST_HELPERS{

    public function __construct(){
        add_filter( 'woocommerce_email_classes', array( $this, 'custom_init_emails' ) );
    }

	function get_paypal_access_token($api, $client_id, $client_secret) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSLVERSION , 6);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_USERPWD, $client_id.":".$client_secret);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
		$response = curl_exec($ch);	
		curl_close($ch);
		
		$result = json_decode( $response );
		if ( isset($result->access_token) && !empty($result->access_token) ) {
			return $result->access_token;
		} else {
			return false;
		}
	}

	function add_tracking_pp($api, $access_token, $transaction_id, $tracking_number, $tracking_provider, $custom_tracking_provider) {
		$res = [];
		if ($tracking_provider) {
			$tracking_provider_new = strtoupper($tracking_provider);
		} else {
			$tracking_provider_new = 'OTHER';
		}

		$curl = curl_init($api);
		$data = array(
			"trackers" => array(
				array(
					"transaction_id" => $transaction_id,
					"tracking_number" => $tracking_number,
					"status" => "SHIPPED",
					"carrier" => $tracking_provider_new,
				)
			)
		);

		if($tracking_provider_new == 'OTHER') {
			$data['trackers'][0]['carrier_name_other'] = $custom_tracking_provider;
		}


		$data_string = json_encode($data);

		curl_setopt ($curl, CURLOPT_HEADER, 0);
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, 2);

		$headers = array (
			'Content-Type: application/json',
			'Authorization: Bearer '.$access_token,
		);

		curl_setopt ($curl, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($curl);

		if(empty($response)) {
			$data_update = 'Error: No response.';
		} else {
			$data_response = json_decode($response);
			if(curl_getinfo($curl, CURLINFO_HTTP_CODE) == '400') {
				$data_update = 'Wrong carrier, choose again Custom Provier!';
			} else {
				if(isset($data_response->errors) && !empty($data_response->errors)) {
					$error_msg = $data_response->errors;
					$data_update = 'Error: '.$error_msg[0]->message;
				} else {
					$data_update = 'Success: Response OK.';
				}
			}
		}

		curl_close($curl);

		$res = array(
			'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'data' => $data_update,
		);

		return $res;
	}

	function delete_tracking_pp($api, $access_token, $transaction_id, $tracking_number, $tracking_provider, $custom_tracking_provider) {
		$res = [];

		if ($tracking_provider) {
			$tracking_provider_new = strtoupper($tracking_provider);
		} else {
			$tracking_provider_new = 'OTHER';
		}

		$url_del =  $api.''.$transaction_id.'-'.$tracking_number;
		$curl = curl_init($url_del);
		$data = array(
			"transaction_id" => $transaction_id,
			"tracking_number" => $tracking_number,
			"status" => "CANCELLED",
			"carrier" => $tracking_provider_new,
		);

		$data_string = json_encode($data);

		curl_setopt ($curl, CURLOPT_HEADER, 0);
		curl_setopt ($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt ($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, 2);

		$headers = array (
			'X-PAYPAL-SECURITY-CONTEXT: ',
			'Content-Type: application/json',
			'Authorization: Bearer '.$access_token,
		);

		curl_setopt ($curl, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($curl);
		if(empty($response)) {
			$data_del = 'Error: No response.';
		} else {
			$data_del = 'Success: Del OK.';
		}

		curl_close($curl);

		$res = array(
			'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'data' => $data_del,
		);

		return $res;
	}

    /**
	 * Check Tracking Paypal
	 *
	 * @param string    $action    action
	 * @param string $order_id Order product ID
	 *
	 * @return bool True if updated tracking paypal successfully
	 */
	public function action_tracking_paypal($action, $order_id, $tracking_provider, $custom_tracking_provider, $tracking_number) {

		if(get_option('enabled_devvpst_shipment_tracking') == 'yes') {

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			global $devvpsp_helpers;

			$environment = '';
			$client_id = '';
			$client_secret = '';
			$transaction_id = '';

			if(get_option( 'devvpst_support_plugin' ) == 'paymentplugins') {
				$ppcp_api_settings = get_option('woocommerce_ppcp_api_settings');
				$environment = $ppcp_api_settings['environment'];

				if($environment == 'sandbox') {
					$client_id = $ppcp_api_settings['client_id_sandbox'];
					$client_secret = $ppcp_api_settings['secret_key_sandbox'];
				} else {
					$client_id = $ppcp_api_settings['client_id_production'];
					$client_secret = $ppcp_api_settings['secret_key_production'];
				}

				// Transaction id Paypal
				$capture_transaction_id = get_post_meta( $order_id, '_transaction_id', true );
				if($capture_transaction_id) {
					$transaction_id = $capture_transaction_id;
				}
			} 

			if($environment == 'sandbox') {
				$url_api = 'https://api.sandbox.paypal.com/v1/oauth2/token';
				$paypal_url = 'https://api.sandbox.paypal.com/v1/shipping/trackers-batch';
				$paypal_url_del = 'https://api.sandbox.paypal.com/v1/shipping/trackers/';

			} else {
				$url_api = 'https://api.paypal.com/v1/oauth2/token';
				$paypal_url = 'https://api.paypal.com/v1/shipping/trackers-batch';
				$paypal_url_del = 'https://api.paypal.com/v1/shipping/trackers/';
			}

			if(empty($client_id) && empty($secret_id)) {
				return array(
					'code' => 500,
					'data' => 'Error: empty the Client ID or Secret APP Paypal in database.',
				);

			} 
			
			if(empty($transaction_id)) {
				return array(
					'code' => 500,
					'data' => 'Error: empty the Transaction ID Paypal.',
				);
			} 

			$access_token = $this->get_paypal_access_token($url_api, $client_id, $client_secret);
			if ($access_token) {
				//Check update status transaction
				if ($action == 'update') {
					$update_tracking = $this->add_tracking_pp($paypal_url, $access_token, $transaction_id, $tracking_number, $tracking_provider, $custom_tracking_provider);
					return $update_tracking;
				}

				// Check del status del transaction
				if ($action == 'del') {
					$delete_tracking = $this->delete_tracking_pp($paypal_url_del, $access_token, $transaction_id, $tracking_number, $tracking_provider, $custom_tracking_provider);
					return $delete_tracking;
				}
			} else {
				// Đã có lỗi xảy ra khi lấy token
				return array(
					'code' => 500,
					'data' => 'Error: no get access token, pls check Client ID and Secret APP Paypal',
				);
			}
 		}

	}

	/**
	 * Code for include delivered email class
	 */
	public function custom_init_emails( $emails ) {

		// Include the email class file if it's not included already		
		$partial_shipped_status = get_option( 'status_order_devvpst_partial_shipped_status', 0 );
		if ( true == $partial_shipped_status ) {
			if ( ! isset( $emails[ 'DEVVP_WC_Email_Customer_Partial_Shipped_Order' ] ) ) {
				$emails[ 'DEVVP_WC_Email_Customer_Partial_Shipped_Order' ] = include_once( DEVVPST_PATH.'templates/class-shipment-partial-shipped-email.php' );
			}
		}

		if ( ! isset( $emails[ 'DEVVP_WC_Email_Customer_Updated_Tracking_Order' ] ) ) {
			$emails[ 'DEVVP_WC_Email_Customer_Updated_Tracking_Order' ] = include_once( DEVVPST_PATH.'templates/class-shipment-updated-tracking-email.php' );
		}

		return $emails;

	}

	/**
	 * Get blog name formatted for emails.
	 *
	 * @return string
	 */
	private function get_blogname() {
		return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}	
}

$GLOBALS['devvpst_helpers'] = new DEVVPST_HELPERS();