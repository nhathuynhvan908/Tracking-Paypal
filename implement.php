<?php
class DEVVPST_IMPLEMENTS{

    public function __construct(){
        // Order page metabox actions.
        add_action( 'wp_ajax_devvpst_shipment_tracking_delete_item', array( $this, 'meta_box_delete_tracking' ) );
        add_action( 'wp_ajax_devvpst_shipment_tracking_save_form', array( $this, 'save_meta_box_ajax' ) );
        add_action( 'wp_ajax_devvpst_shipment_tracking_get_items', array( $this, 'get_meta_box_items_ajax' ) );

		add_action('admin_head-edit.php', array( $this, 'addCustomImportButton'));
		add_action('admin_menu', array( $this, 'register_devvpst_shipment_tracking_page'));
		add_action( 'rest_api_init', array($this,'add_custom_tracking_api'));
		add_action( 'devvpst_update_tracking', array($this,'devvpst_update_tracking_func'));

		add_action( 'init', array( $this, 'register_updated_tracking_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_updated_tracking_to_order_statuses' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'include_updated_tracking_order_status_to_reports' ), 20, 1 );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'delivered_woocommerce_order_is_paid_statuses' ) );
		add_filter( 'devvpst_order_status_email_type', array( $this, 'devvpst_order_status_email_type' ), 50, 1 );
		add_action( 'woocommerce_order_status_updated-tracking', array( $this, 'email_trigger_updated_tracking' ), 10, 2 );	
		
		if(get_option( 'status_order_devvpst_partial_shipped_status' ) == 'yes') {
			add_action( 'init', array( $this, 'register_partial_shipped_order_status' ) );
			add_filter( 'wc_order_statuses', array( $this, 'add_partial_shipped_to_order_statuses' ) );
			add_filter( 'woocommerce_reports_order_statuses', array( $this, 'include_partial_shipped_order_status_to_reports' ), 20, 1 );
			add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'partial_shipped_woocommerce_order_is_paid_statuses' ) );
			add_action( 'woocommerce_order_status_partial-shipped', array( $this, 'email_trigger_partial_shipped' ), 10, 2 );
		}
		
		add_filter( 'wc_order_statuses', array( $this, 'wc_renaming_order_status' ) );	
    }

	public function devvpst_update_tracking_func($data) {
		update_post_meta( $data->custom_id, 'capture_transaction_id', $data->id );
	}

	/**
	 * Order Tracking Delete
	 *
	 * Function to delete a tracking item
	 */
	public function meta_box_delete_tracking() {
		global $devvpst_setting, $devvpst_helpers;
		check_ajax_referer( 'delete-tracking-item', 'security', true );

		$order_id    = wc_clean( $_POST['order_id'] );
		$tracking_id = wc_clean( $_POST['tracking_id'] );
		$data = $devvpst_setting->get_tracking_items( $order_id, true );
		if($data) {
			foreach ($data as $key => $item) {
				if($item["tracking_id"] == $tracking_id) {
					$tracking_number = $item["tracking_number"];
					$custom_tracking_provider = $item["custom_tracking_provider"];
					$tracking_provider = $item["tracking_provider"];
				}
			}

			$data_del = $devvpst_helpers->action_tracking_paypal('del', $order_id, $tracking_provider, $custom_tracking_provider, $tracking_number);
			
			$this->delete_tracking_item( $order_id, $tracking_id );
		}
	}

	/**
	 * Deletes a tracking item from post_meta array
	 *
	 * @param int    $order_id    Order ID
	 * @param string $tracking_id Tracking ID
	 *
	 * @return bool True if tracking item is deleted successfully
	 */
	public function delete_tracking_item( $order_id, $tracking_id ) {
		global $devvpst_setting;
		$tracking_items = $devvpst_setting->get_tracking_items( $order_id );

		$is_deleted = false;

		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $key => $item ) {
				if ( $item['tracking_id'] == $tracking_id ) {
					unset( $tracking_items[ $key ] );
					$is_deleted = true;
					break;
				}
			}
			$devvpst_setting->save_tracking_items( $order_id, $tracking_items );
		}

		$order = new WC_Order($order_id);
		if($is_deleted == true && get_option('status_order_devvpst_shipment_tracking') == 'yes') {
			if($order->get_status() == 'partial-shipped' && count( $tracking_items ) > 0) {
				WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
			} else {
				$order->update_status('on-hold', 'After delete tracking:');
				WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
			}
		} else if ($is_deleted == true) {
			WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
		}
		return $is_deleted;
	}

    /**
	 * Order Tracking Save AJAX
	 *
	 * Function for saving tracking items via AJAX
	 */
	public function save_meta_box_ajax() {
        global $devvpst_setting, $devvpst_helpers;
		check_ajax_referer( 'create-tracking-item', 'security', true );

		if ( isset( $_POST['tracking_number'] ) && strlen( $_POST['tracking_number'] ) > 0 ) {

			$order_id = wc_clean( $_POST['order_id'] );
			$args = array(
				'tracking_provider'        => wc_clean( $_POST['tracking_provider'] ),
				'custom_tracking_provider' => wc_clean( $_POST['custom_tracking_provider'] ),
				'custom_tracking_link'     => wc_clean( $_POST['custom_tracking_link'] ),
				'tracking_number'          => wc_clean( $_POST['tracking_number'] ),
				'date_shipped'             => wc_clean( $_POST['date_shipped'] ),
				'tracking_status'          => wc_clean( $_POST['tracking_status'] ),
			);

			// tracking_status_paypal
			$data_tracking = $devvpst_helpers->action_tracking_paypal('update', $order_id, $args['tracking_provider'], $args['custom_tracking_provider'], $args['tracking_number']);
			if($data_tracking) {
				// show status tracking paypal
				$args['tracking_code_curl'] = $data_tracking['code'];
				$args['tracking_status_paypal'] = $data_tracking['data'];
			}
			
			// show list item
			$tracking_item = $devvpst_setting->add_tracking_item( $order_id, $args );
			$order = new WC_Order($order_id);

			if(get_option('status_order_devvpst_shipment_tracking') == 'yes') {
				$items = $order->get_items();
				if(count($items) > 1 && get_option('status_order_devvpst_partial_shipped_status') == 'yes') {
					if($order->get_status() == 'partial-shipped') {
						WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
					} else {
						$order->update_status('partial-shipped', 'After add tracking:');
					}
				} else {
					$order->update_status('completed', 'After add tracking:');
				}
			} else {
			               
				if($args['tracking_status'] == 'completed') {
					if($order->get_status() == 'completed') {
						WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
					} else {
						$order->update_status('completed', 'After add tracking:');
					}
				} else if($args['tracking_status'] == 'partial-shipped') {
					if($order->get_status() == 'partial-shipped') {
						WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
					} else {
						$order->update_status('partial-shipped', 'After add tracking:');
					}
				} else {
				    if($order->get_status() ==  'on-hold') {
						$order->update_status('shipped-pre', 'After add tracking:');
					}
					WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
				}
			}

			$devvpst_setting->display_html_tracking_item_for_meta_box( $order_id, $tracking_item );
		}

		die();
	}

    
	/**
	 * Order Tracking Get All Order Items AJAX
	 *
	 * Function for getting all tracking items associated with the order
	 */
	public function get_meta_box_items_ajax() {
		global $devvpst_setting;
		check_ajax_referer( 'get-tracking-item', 'security', true );

		$order_id = wc_clean( $_POST['order_id'] );
		$tracking_items = $devvpst_setting->get_tracking_items( $order_id );

		foreach ( $tracking_items as $tracking_item ) {
			$devvpst_setting->display_html_tracking_item_for_meta_box( $order_id, $tracking_item );
		}

		die();
	}

	/**
	 * Adds "Import" button on module list page
	 */
	public function addCustomImportButton(){
		global $current_screen;
		if ('shop_order' != $current_screen->post_type) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready( function($)
			{
				jQuery('#wpbody-content .subsubsub').after("<a href='admin.php?page=devvpst-shipment-tracking-import' id='button-add-tracking-import' class='add-new-h2'>Import Tracking</a>");
			});
		</script>
		<?php
	}

	public function register_devvpst_shipment_tracking_page(){
		//add_submenu_page( 'woocommerce', 'Import Tracking', 'Import Tracking', 'manage_options', 'devvpst-shipment-tracking-import', array( $this,'devvpst_shipment_tracking_import') ); 
		add_submenu_page( 'woocommerce', 'Import Tracking', 'Import Tracking', 'manage_options', 'devvpst-shipment-tracking-import', array( $this,'devvpst_shipment_tracking_import') ); 
	}

	public function devvpst_shipment_tracking_import(){
		global $devvpst_setting, $devvpst_helpers;
		if(strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && $_POST['submit_tracking'] == 'Save') {
			$msg = '';
			if($_FILES["files_import"]) {
				$file = $_FILES['files_import']['tmp_name'];
				if (($handle = fopen($file, "r")) !== FALSE) {

					while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

						if(trim($data[0]) == 'order_id') {
							continue;
						}
						$order_id = trim($data[0]);

						$args = array(
							'tracking_provider'        => esc_attr(trim($data[1])),
							'custom_tracking_provider' => esc_attr(trim($data[2])),
							'custom_tracking_link'     => esc_attr(trim($data[3])),
							'tracking_number'          => esc_attr(trim($data[4])),
							'date_shipped'             => esc_attr(trim($data[5])),
							'tracking_status'          => esc_attr(trim($data[6])),
						);
						
						// tracking_status_paypal
						$data_tracking = $devvpst_helpers->action_tracking_paypal('update', $order_id, $args['tracking_provider'], $args['custom_tracking_provider'], $args['tracking_number'], $args['tracking_status']);

						if(isset($data_tracking) && !empty($data_tracking)) {
							// show status tracking paypal
							$args['tracking_code_curl'] = $data_tracking['code'];
							$args['tracking_status_paypal'] = $data_tracking['data'];

							$msg_pp = 'Done add tracking paypal.';
						} else {
							$msg_pp = 'Cant add tracking paypal.';
						}
						

						$tracking_item = $devvpst_setting->add_tracking_item( $order_id, $args );
						if(isset($tracking_item) && !empty($tracking_item)) {
							$order = new WC_Order($order_id);
							if(get_option('status_order_devvpst_shipment_tracking') == 'yes') {
								$items = $order->get_items();
								if(count($items) > 1 && get_option('status_order_devvpst_partial_shipped_status') == 'yes') {
									if($order->get_status() == 'partial-shipped') {
										WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
									} else {
										$order->update_status('partial-shipped', 'After add tracking:');
									}
								} else {
									$order->update_status('completed', 'After add tracking:');
								}
							} else {
								if($args['tracking_status'] == 'completed') {
									if($order->get_status() == 'completed') {
										WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
									} else {
										$order->update_status('completed', 'After add tracking:');
									}
								} else if($args['tracking_status'] == 'partial-shipped') {
									if($order->get_status() == 'partial-shipped') {
										WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
									} else {
										$order->update_status('partial-shipped', 'After add tracking:');
									}
								} else {
									if($order->get_status() ==  'on-hold') {
										$order->update_status('shipped-pre', 'After add tracking:');
									}
									WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger( $order_id );
								}
							}
							$msg_item = 'Done add tracking item';
						} else {
							$msg_item = 'Cant add tracking item';
						}
		
						$msg .= 'Order ID: ' . $order_id . ' - ' .$msg_item. ' - '. $msg_pp. '<br>';
					}
					fclose($handle);
				} else {
					$msg = 'Cant read file so plz check agian, thanks.';
				}
			} else {
				$msg = 'File does not exist, plz upload file, thanks.';
			}
		}
		?>
		<div class="add-tracking-import">
			<h3>Import Tracking</h3>
			<?php 
			if(isset($msg) && !empty($msg)) {
				echo '<p>'.$msg.'</p>';
			}
			?>
			<p>Link file import demo: <a href="<?php echo DEVVPST_URL.'demo/import-demo.zip'; ?>" target="_blank">Demo</a></p>
			<form method="post" accept-charset="utf-8" enctype="multipart/form-data">
				<input type="file" class="file-input" name="files_import" id="files_import">
				<input type="submit" class="button" name='submit_tracking' value="Save">
			</form>
		</div>
		<div class="list-tracking">
			<h3>List Provider</h3>
			<ul>
				<?php 
				$DEVVPST_SETTINGS = new DEVVPST_SETTINGS();
				$get_providers = $DEVVPST_SETTINGS->get_providers();
				if($get_providers) {
					foreach ( $get_providers as $provider_group => $providers ) {
						echo '<li>';
						echo '<strong>' . esc_attr( $provider_group ) . '</strong>';
						foreach ( $providers as $provider => $url ) {
							echo '<p>- '.esc_html( $provider ).': '.esc_attr( sanitize_title( $provider ) ).'</p>';
						}
						echo '</li>';
					}
				}
				?>
			</ul>
		</div>
		<?php
	}

	public function add_custom_tracking_api(){
		// Add
        register_rest_route( 'devvp_shipment_tracking/v1', '/add', array(
            'methods' => 'POST',
            'callback' => array($this,'add_tracking_api'),
        ));

		// Delete
        register_rest_route( 'devvp_shipment_tracking/v1', '/del', array(
            'methods' => 'POST',
            'callback' => array($this,'del_tracking_api'),
        ));

		// Get order detail by order_id
        register_rest_route( 'devvp_shipment_tracking/v1', '/order_id/(?P<order_id>[0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this,'get_tracking_by_order_id'),
        ));
        
        register_rest_route( 'product_dev/v1', '/exist/(?P<product_id>[0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this,'check_product_exist_by_id'),
        ));
    }

	public function add_tracking_api(WP_REST_Request $request){
		global $devvpst_setting, $devvpst_helpers;
		$params = wp_parse_args( $request->get_params());
	
		$order_id = esc_attr($params['order_id']);
		$args = json_decode($params['data'], true);
		if(!empty($order_id) && !empty($args)) {
			$msg = '';
			// tracking_status_paypal

			$data_tracking = $devvpst_helpers->action_tracking_paypal('update', $order_id, $args['tracking_provider'], $args['custom_tracking_provider'], $args['tracking_number'], $args['tracking_status']);

			if(isset($data_tracking) && !empty($data_tracking)) {
				// show status tracking paypal
				$args['tracking_code_curl'] = $data_tracking['code'];
				$args['tracking_status_paypal'] = $data_tracking['data'];
			} 

			$msg_pp = 'Paypal api: '.$data_tracking['data'];
			

			$tracking_item = $devvpst_setting->add_tracking_item( $order_id, $args );
			if(isset($tracking_item) && !empty($tracking_item)) {

				$order = new WC_Order($order_id);
				if(get_option('status_order_devvpst_shipment_tracking') == 'yes') {
					$items = $order->get_items();
					if(count($items) > 1 && get_option('status_order_devvpst_partial_shipped_status') == 'yes') {
						if($order->get_status() == 'partial-shipped') {
							WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
						} else {
							$order->update_status('partial-shipped', 'After add tracking:');
						}
					} else {
						$order->update_status('completed', 'After add tracking:');
					}
				} else {
					if($args['tracking_status'] == 'completed') {
						if($order->get_status() == 'completed') {
							WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
						} else {
							$order->update_status('completed', 'After add tracking:');
						}
					} else if($args['tracking_status'] == 'partial-shipped') {
						if($order->get_status() == 'partial-shipped') {
							WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
						} else {
							$order->update_status('partial-shipped', 'After add tracking:');
						}
					} else {
						WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
					}
				}

				$msg_item = 'Done add tracking item';
			} else {
				$msg_item = 'Cant add tracking item';
			}

			$msg = $msg_item. ' - '. $msg_pp;

			$return = array(
				'message'  => $msg,
				'ID'       => $order_id
			);
			 
			wp_send_json($return);
		}
	}

	public function del_tracking_api(WP_REST_Request $request){
		global $devvpst_setting, $devvpst_helpers;
		$params = wp_parse_args( $request->get_params());
		$order_id = esc_attr($params['order_id']);
		$tracking_id = esc_attr($params['tracking_id']);

		if(!empty($order_id) && !empty($tracking_id)) {
			$data = $devvpst_setting->get_tracking_items( $order_id, true );
			if($data) {
				foreach ($data as $key => $item) {
					if($item["tracking_id"] == $tracking_id) {
						$tracking_number = $item["tracking_number"];
						$custom_tracking_provider = $item["custom_tracking_provider"];
						$tracking_provider = $item["tracking_provider"];
					}
				}

				$data_del = $devvpst_helpers->action_tracking_paypal('del', $order_id, $tracking_provider, $custom_tracking_provider, $tracking_number);
				if(!empty($data_del)) {
					$msg_pp = 'Paypal api: '.$data_del['data'];
				} else {
					$msg_pp = 'Paypal api: ';
				}
				
				

				$rep_del = $this->delete_tracking_item( $order_id, $tracking_id );
				if($rep_del) {

					$order = new WC_Order($order_id);
					if(get_option('status_order_devvpst_shipment_tracking') == 'yes') {
						if($order->get_status() == 'partial-shipped' && count( $tracking_items ) > 0) {
							WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
						} else {
							$order->update_status('on-hold', 'After delete tracking:');
							WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
						}
					} else {
						WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
					}

					$return = array(
						'message'  => 'Successfully deleted tracking order - '.$msg_pp,
						'tracking_id' => $tracking_id,
						'ID'       => $order_id
					);
					wp_send_json($return);
				} else {
					$return = array(
						'message'  => 'Delete tracking order failed - '.$msg_pp,
						'tracking_id' => $tracking_id,
						'ID'       => $order_id
					);
					wp_send_json($return);
				}
			}
		}

		
	}

	public function get_tracking_by_order_id($data){
		$order_id = esc_attr($data['order_id']);
		
		if(!empty($order_id)) {
			$tracking_items = get_post_meta( $order_id, '_devvpst_shipment_tracking_items', true );
			if(!empty($tracking_items)) {
				$return = array(
					'message'  => 'List data tracking',
					'data'       => $tracking_items
				);
				wp_send_json($return);
			} else {
				$return = array(
					'message'  => 'No data tracking',
					'data'       => ''
				);
				wp_send_json($return);
			}
		}
	}
	
	public function check_product_exist_by_id($data){
	    $return = [];
        $product_id = esc_attr($data['product_id']);
        if(!empty($product_id)) {
            $product_data = new WC_Product($product_id);
            if(isset($product_data)) {

                $return = [
                    'id' => $product_id,
                ];
            }
        }
        wp_send_json($return);
    }

	/** 
	 * Register new status : Updated Tracking
	**/
	public function register_updated_tracking_order_status() {
		register_post_status( 'wc-updated-tracking', array(
			'label'                     => __( 'Updated Tracking', 'devvp' ),
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			/* translators: %s: replace with Updated Tracking Count */
			'label_count' => _n_noop( 'Updated Tracking <span class="count">(%s)</span>', 'Updated Tracking <span class="count">(%s)</span>', 'devvp' )
		) );		
	}
	
	/** 
	 * Register new status : Partially Shipped
	**/
	public function register_partial_shipped_order_status() {
		register_post_status( 'wc-partial-shipped', array(
			'label'                     => __( 'Partially Shipped', 'devvp' ),
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			/* translators: %s: replace with Partially Shipped Count */
			'label_count'  => _n_noop( 'Partially Shipped <span class="count">(%s)</span>', 'Partially Shipped <span class="count">(%s)</span>', 'devvp' )
		) );		
	}			
	
	/*
	* add status after completed
	*/
	public function add_updated_tracking_to_order_statuses( $order_statuses ) {		
		$new_order_statuses = array();
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			if ( 'wc-completed' === $key ) {
				$new_order_statuses['wc-updated-tracking'] = __( 'Updated Tracking', 'devvp' );				
			}
		}		
		return $new_order_statuses;
	}
	
	/*
	* add status after completed
	*/
	public function add_partial_shipped_to_order_statuses( $order_statuses ) {		
		$new_order_statuses = array();
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			if ( 'wc-completed' === $key ) {
				$new_order_statuses['wc-partial-shipped'] = __( 'Partially Shipped', 'devvp' );				
			}
		}		
		return $new_order_statuses;
	}
	
	/*
	* Adding the updated-tracking order status to the default woocommerce order statuses
	*/
	public function include_updated_tracking_order_status_to_reports( $statuses ) {
		if ( $statuses ) {
			$statuses[] = 'updated-tracking';
		}	
		return $statuses;
	}

	/*
	* mark status as a paid.
	*/
	public function delivered_woocommerce_order_is_paid_statuses( $statuses ) { 
		$statuses[] = 'delivered';
		return $statuses; 
	}

	/*
	* Adding the partial-shipped order status to the default woocommerce order statuses
	*/
	public function include_partial_shipped_order_status_to_reports( $statuses ) {
		if ( $statuses ) {
			$statuses[] = 'partial-shipped';
		}	
		return $statuses;
	}	
	
	/*
	* mark status as a paid.
	*/
	public function updated_tracking_woocommerce_order_is_paid_statuses( $statuses ) { 
		$statuses[] = 'updated-tracking';		
		return $statuses; 
	}
	
	/*
	* Give download permission to updated tracking order status
	*/
	public function add_updated_tracking_to_download_permission( $data, $order ) {
		if ( $order->has_status( 'updated-tracking' ) ) { 
			return true; 
		}
		return $data;
	}

	/*
	* mark status as a paid.
	*/
	public function partial_shipped_woocommerce_order_is_paid_statuses( $statuses ) { 
		$statuses[] = 'partial-shipped';		
		return $statuses; 
	}

	/*
	* Give download permission to partial shipped order status
	*/
	public function add_partial_shipped_to_download_permission( $data, $order ) {
		if ( $order->has_status( 'partial-shipped' ) ) { 
			return true; 
		}
		return $data;
	}	
	
	/*
	* add bulk action
	* Change order status to Updated Tracking
	*/
	public function add_bulk_actions_updated_tracking( $bulk_actions ) {
		$lable = wc_get_order_status_name( 'updated-tracking' );	
		/* translators: %s: search order status label */	
		$bulk_actions['mark_updated-tracking'] = sprintf( __( 'Change status to %s', 'devvp' ), $lable );
		return $bulk_actions;		
	}

	/*
	* add bulk action
	* Change order status to Partially Shipped
	*/
	public function add_bulk_actions_partial_shipped( $bulk_actions ) {
		$lable = wc_get_order_status_name( 'partial-shipped' );
		/* translators: %s: search order status label */
		$bulk_actions['mark_partial-shipped'] = sprintf( __( 'Change status to %s', 'devvp' ), $lable );
		return $bulk_actions;		
	}

	/*
	* add order again button for delivered order status	
	*/
	public function add_reorder_button_partial_shipped( $statuses ) {
		$statuses[] = 'partial-shipped';
		return $statuses;	
	}

	/*
	* add order again button for delivered order status	
	*/
	public function add_reorder_button_updated_tracking( $statuses ) {
		$statuses[] = 'updated-tracking';
		return $statuses;	
	}
	
	/*
	* add Updated Tracking in order status email customizer
	*/
	public function devvpst_order_status_email_type( $order_status ) {
		$updated_tracking_status = array(
			'updated_tracking' => __( 'Updated Tracking', 'devvp' ),
		);
		$order_status = array_merge( $order_status, $updated_tracking_status );
		return $order_status;
	}

	/**
	 * Send email when order status change to 'Partial Shipped'	 
	*/
	public function email_trigger_partial_shipped( $order_id, $order = false ) {					
		WC()->mailer()->emails['DEVVP_WC_Email_Customer_Partial_Shipped_Order']->trigger( $order_id, $order );
	}
	
	/**
	 * Send email when order status change to 'Updated Tracking'	 
	*/
	public function email_trigger_updated_tracking( $order_id, $order = false ) {						
		WC()->mailer()->emails['DEVVP_WC_Email_Customer_Updated_Tracking_Order']->trigger( $order_id, $order );
	}	
	

	/*
	* Rename WooCommerce Order Status
	*/
	public function wc_renaming_order_status( $order_statuses ) {
		
		$enable = get_option( 'status_order_devvpst_renaming_order_status', 0);
		if ( false == $enable ) {
			return $order_statuses;
		}	
		
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			if ( 'wc-completed' === $key ) {
				$order_statuses['wc-completed'] = esc_html__( 'Shipped', 'devvp' );
			}
		}		
		return $order_statuses;
	}		
}
new DEVVPST_IMPLEMENTS();
