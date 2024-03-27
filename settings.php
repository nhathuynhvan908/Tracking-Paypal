<?php
class DEVVPST_SETTINGS{
    
    public function __construct(){
        add_action( 'add_meta_boxes', array( $this, 'devvpst_add_meta_box' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_meta_box' ), 0, 2 );

		// View Order Page.
		add_action( 'woocommerce_view_order', array( $this, 'display_tracking_info' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_display' ), 0, 4 );

		// Custom tracking column in admin orders list.
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'shop_order_columns' ), 99 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_shop_order_columns' ) );

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_config_settings') );
    }
	public function register_menu(){
		add_menu_page( esc_html__( 'Tracking Paypal', 'devvp' ), esc_html__( 'Tracking Paypal', 'devvp' ), 'manage_options', 'multiple-shipment-tracking', array($this,'devvpst_settings_tracking'),'dashicons-admin-generic');
	}

	function register_config_settings() {
        register_setting( 'devvpst-settings-configs-group', 'enabled_devvpst_shipment_tracking');
		register_setting( 'devvpst-settings-configs-group', 'devvpst_support_plugin');
		register_setting( 'devvpst-settings-configs-group', 'status_order_devvpst_renaming_order_status');
		register_setting( 'devvpst-settings-configs-group', 'status_order_devvpst_updated_tracking_status');
    }

	public function devvpst_settings_tracking(){
        ?>
        <div class="banner-plugin">
            <h2>Tracking Paypal – For WooCommerce</h2>
        </div>

        <div class="main-plugin">
            <div class="main-left">
					<form method="post" action="options.php">
					<?php settings_fields( 'devvpst-settings-configs-group'); ?>
					<?php do_settings_sections( 'devvpst-settings-configs-group'); ?>
					<h3>Settings</h3>
					<p class="description">Configure the parameters used in tracking. Apply to plugins: </p>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">Enable</th>
								<td>
									<input name="enabled_devvpst_shipment_tracking" type="checkbox" id="enabled_devvpst_shipment_tracking" value="yes" class="regular-text" <?php echo get_option( 'enabled_devvpst_shipment_tracking' ) == 'yes' ? 'checked="checked"' : ''; ?> />
									<p class="description">Allow feature tracking working</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Support Plugin</th>
								<td>
									<select name="devvpst_support_plugin" id="devvpst_support_plugin">
									<?php 
									if(get_option( 'devvpst_support_plugin' ) == 'paymentplugins' || empty( get_option( 'devvpst_support_plugin' ) )) {
										?>
										<option value="paymentplugins" selected="selected">Payment Plugins for PayPal WooCommerce - By Payment Plugins, support@paymentplugins.com</option>
										<option value="">Update soon...</option>
										<?php
									} else {
										?>
										<option value="" selected="selected">Update soon...</option>
										<option value="paymentplugins">Payment Plugins for PayPal WooCommerce - By Payment Plugins, support@paymentplugins.com</option>
										<?php
									}
									?>
									</select>
									<p class="description">Choose plugin support.</p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">Status Order</th>
								<td>
									<select name="status_order_devvpst_shipment_tracking" id="status_order_devvpst_shipment_tracking">
									<?php 
									if(get_option( 'status_order_devvpst_shipment_tracking' ) == 'no' || empty( get_option( 'status_order_devvpst_shipment_tracking' ) )) {
										?>
										<option value="no" selected="selected">No</option>
										<option value="yes">Yes</option>
										<?php
									} else {
										?>
										<option value="no">No</option>
										<option value="yes" selected="selected">Yes</option>
										<?php
									}
									?>
									</select>
									<p class="description">Automatic change status to Order On-hold to Complete or "Partial Shipped" if there is more than one product in the order after tracking.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Rename Order Status</th>
								<td>
									<select name="status_order_devvpst_renaming_order_status" id="status_order_devvpst_renaming_order_status">
									<?php 
									if(get_option( 'status_order_devvpst_renaming_order_status' ) == 'no' || empty( get_option( 'status_order_devvpst_renaming_order_status' ) )) {
										?>
										<option value="no" selected="selected">No</option>
										<option value="yes">Yes</option>
										<?php
									} else {
										?>
										<option value="no">No</option>
										<option value="yes" selected="selected">Yes</option>
										<?php
									}
									?>
									</select>
									<p class="description">Rename Order Status "Complete" to "Shipped"</p>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button(); ?>
				</form>
            </div>

            <div class="main-right">
                <div class="devvp-widgets info-contact">
                    <h3>Contact</h3>
                    <ul>
                        <li><a href="https://nhathuynhvan.com/" target="_blank">Website: NhatHuynhVan.com</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
		
    public function devvpst_add_meta_box() {
        add_meta_box( 'multiple-shipment-tracking', __( 'Tracking Paypal', 'multiple-shipment-tracking' ), array( $this, 'devvpst_meta_box' ), 'shop_order', 'side', 'high' );
    }

    /**
	 * Get shiping providers.
	 *
	 * @return array
	 */
	public function get_providers() {
		return apply_filters( 'devvpst_shipment_tracking_get_providers', array(
			'Australia' => array(
				'Australia Post'   => 'http://auspost.com.au/track/track.html?id=%1$s',
				'Fastway Couriers' => 'http://www.fastway.com.au/courier-services/track-your-parcel?l=%1$s',
			),
			'Austria' => array(
				'post.at' => 'http://www.post.at/sendungsverfolgung.php?pnum1=%1$s',
				'dhl.at'  => 'http://www.dhl.at/content/at/de/express/sendungsverfolgung.html?brand=DHL&AWB=%1$s',
				'DPD.at'  => 'https://tracking.dpd.de/parcelstatus?locale=de_AT&query=%1$s',
			),
			'Brazil' => array(
				'Correios' => 'http://websro.correios.com.br/sro_bin/txect01$.QueryList?P_LINGUA=001&P_TIPO=001&P_COD_UNI=%1$s',
			),
			'Belgium' => array(
				'bpost' => 'https://track.bpost.be/btr/web/#/search?itemCode=%1$s',
			),
			'Canada' => array(
				'Canada Post' => 'http://www.canadapost.ca/cpotools/apps/track/personal/findByTrackNumber?trackingNumber=%1$s',
			),
			'Czech Republic' => array(
				'PPL.cz'      => 'http://www.ppl.cz/main2.aspx?cls=Package&idSearch=%1$s',
				'Česká pošta' => 'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo?parcelNumbers=%1$s',
				'DHL.cz'      => 'http://www.dhl.cz/cs/express/sledovani_zasilek.html?AWB=%1$s',
				'DPD.cz'      => 'https://tracking.dpd.de/parcelstatus?locale=cs_CZ&query=%1$s',
			),
			'Finland' => array(
				'Itella' => 'http://www.posti.fi/itemtracking/posti/search_by_shipment_id?lang=en&ShipmentId=%1$s',
			),
			'France' => array(
				'Colissimo' => 'http://www.colissimo.fr/portail_colissimo/suivre.do?language=fr_FR&colispart=%1$s',
			),
			'Germany' => array(
				'DHL Intraship (DE)' => 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=%1$s&rfn=&extendedSearch=true',
				'Hermes'             => 'https://tracking.hermesworld.com/?TrackID=%1$s',
				'Deutsche Post DHL'  => 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=%1$s',
				'UPS Germany'        => 'http://wwwapps.ups.com/WebTracking/processInputRequest?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=de_DE&InquiryNumber1=%1$s',
				'DPD.de'             => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=en_DE',
			),
			'Ireland' => array(
				'DPD.ie'  => 'http://www2.dpd.ie/Services/QuickTrack/tabid/222/ConsignmentID/%1$s/Default.aspx',
				'An Post' => 'https://track.anpost.ie/TrackingResults.aspx?rtt=1&items=%1$s',
			),
			'Italy' => array(
				'BRT (Bartolini)' => 'http://as777.brt.it/vas/sped_det_show.hsm?referer=sped_numspe_par.htm&Nspediz=%1$s',
				'DHL Express'     => 'http://www.dhl.it/it/express/ricerca.html?AWB=%1$s&brand=DHL',
			),
			'India' => array(
				'DTDC' => 'http://www.dtdc.in/tracking/tracking_results.asp?Ttype=awb_no&strCnno=%1$s&TrkType2=awb_no',
			),
			'Netherlands' => array(
				'PostNL' => 'https://postnl.nl/tracktrace/?B=%1$s&P=%2$s&D=%3$s&T=C',
				'DPD.NL' => 'http://track.dpdnl.nl/?parcelnumber=%1$s',
				'UPS Netherlands'        => 'http://wwwapps.ups.com/WebTracking/processInputRequest?sort_by=status&tracknums_displayed=1&TypeOfInquiryNumber=T&loc=nl_NL&InquiryNumber1=%1$s',
			),
			'New Zealand' => array(
				'Courier Post' => 'http://trackandtrace.courierpost.co.nz/Search/%1$s',
				'NZ Post'      => 'http://www.nzpost.co.nz/tools/tracking?trackid=%1$s',
				'Fastways'     => 'http://www.fastway.co.nz/courier-services/track-your-parcel?l=%1$s',
				'PBT Couriers' => 'http://www.pbt.com/nick/results.cfm?ticketNo=%1$s',
			),
			'Romania' => array(
				'Fan Courier'      => 'https://www.fancourier.ro/awb-tracking/?xawb=%1$s',
				'DPD Romania'     => 'https://tracking.dpd.de/parcelstatus?query=%1$s&locale=ro_RO',
				'Urgent Cargus' => 'https://app.urgentcargus.ro/Private/Tracking.aspx?CodBara=%1$s',
			),
			'South African' => array(
				'SAPO' => 'http://sms.postoffice.co.za/TrackingParcels/Parcel.aspx?id=%1$s',
			),
			'Sweden' => array(
				'PostNord Sverige AB' => 'http://www.postnord.se/sv/verktyg/sok/Sidor/spara-brev-paket-och-pall.aspx?search=%1$s',
				'DHL.se'              => 'http://www.dhl.se/content/se/sv/express/godssoekning.shtml?brand=DHL&AWB=%1$s',
				'Bring.se'            => 'http://tracking.bring.se/tracking.html?q=%1$s',
				'UPS.se'              => 'http://wwwapps.ups.com/WebTracking/track?track=yes&loc=sv_SE&trackNums=%1$s',
				'DB Schenker'         => 'http://privpakportal.schenker.nu/TrackAndTrace/packagesearch.aspx?packageId=%1$s',
			),
			'United Kingdom' => array(
				'DHL'                       => 'http://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=%1$s',
				'DPD.co.uk'                 => 'http://www.dpd.co.uk/tracking/trackingSearch.do?search.searchType=0&search.parcelNumber=%1$s',
				'InterLink'                 => 'http://www.interlinkexpress.com/apps/tracking/?reference=%1$s&postcode=%2$s#results',
				'ParcelForce'               => 'http://www.parcelforce.com/portal/pw/track?trackNumber=%1$s',
				'Royal Mail'                => 'https://www.royalmail.com/track-your-item/?trackNumber=%1$s',
				'TNT Express (consignment)' => 'http://www.tnt.com/webtracker/tracking.do?requestType=GEN&searchType=CON&respLang=en&respCountry=GENERIC&sourceID=1&sourceCountry=ww&cons=%1$s&navigation=1&genericSiteIdent=',
				'TNT Express (reference)'   => 'http://www.tnt.com/webtracker/tracking.do?requestType=GEN&searchType=REF&respLang=en&respCountry=GENERIC&sourceID=1&sourceCountry=ww&cons=%1$s&navigation=1&genericSiteIdent=',
				'UK Mail'                   => 'https://www.ukmail.com/manage-my-delivery/manage-my-delivery?ConsignmentNumber=%1$s',
			),
			'United States' => array(
				'Fedex'         => 'https://www.fedex.com/fedextrack/?action=track&tracknumbers=%1$s',
				'FedEx Sameday' => 'https://www.fedexsameday.com/fdx_dotracking_ua.aspx?tracknum=%1$s',
				'OnTrac'        => 'https://www.ontrac.com/tracking/?number=%1$s',
				'UPS'           => 'http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=%1$s',
				'USPS'          => 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=%1$s',
				// 'DHL US'        => 'https://www.logistics.dhl/us-en/home/tracking/tracking-ecommerce.html?tracking-id=%1$s',
			),
		) );
	}

    /**
	 * Show the meta box for shipment info on the order page
	 */
	public function devvpst_meta_box() {
		global $post;

		$tracking_items = $this->get_tracking_items( $post->ID );
        if(get_option('status_order_devvpst_shipment_tracking') == 'yes') {
			$class_status_order = 'change-status-order';
		} else {
			$class_status_order = '';
		}
		echo '<div id="tracking-items" class="'.$class_status_order.'">';

		if ( count( $tracking_items ) > 0 ) {
			foreach ( $tracking_items as $tracking_item ) {
				$this->display_html_tracking_item_for_meta_box( $post->ID, $tracking_item );
			}
		}

		echo '</div>';
		
		echo '<button class="button button-show-form" type="button">' . __( 'Add Tracking Number', 'woocommerce-shipment-tracking' ) . '</button>';

		echo '<div id="shipment-tracking-form" class="'.$class_status_order.'">';

		// Providers
		echo '<p class="form-field tracking_provider_field"><label for="tracking_provider">' . __( 'Provider:', 'devvp' ) . '</label><br/><select id="tracking_provider" name="tracking_provider" class="chosen_select" style="width:100%;">';

		echo '<option value="">' . __( 'Custom Provider', 'devvp' ) . '</option>';

		$selected_provider = '';

		if ( ! $selected_provider ) {
			$selected_provider = sanitize_title( apply_filters( 'devvpst_woocommerce_shipment_tracking_default_provider', '' ) );
		}

		foreach ( $this->get_providers() as $provider_group => $providers ) {
			echo '<optgroup label="' . esc_attr( $provider_group ) . '">';
			foreach ( $providers as $provider => $url ) {
				echo '<option value="' . esc_attr( sanitize_title( $provider ) ) . '" ' . selected( sanitize_title( $provider ), $selected_provider, true ) . '>' . esc_html( $provider ) . '</option>';
			}
			echo '</optgroup>';
		}

		echo '</select> ';

		woocommerce_wp_hidden_input( array(
			'id'    => 'devvpst_shipment_tracking_get_nonce',
			'value' => wp_create_nonce( 'get-tracking-item' ),
		) );

		woocommerce_wp_hidden_input( array(
			'id'    => 'devvpst_shipment_tracking_delete_nonce',
			'value' => wp_create_nonce( 'delete-tracking-item' ),
		) );

		woocommerce_wp_hidden_input( array(
			'id'    => 'devvpst_shipment_tracking_create_nonce',
			'value' => wp_create_nonce( 'create-tracking-item' ),
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'custom_tracking_provider',
			'label'       => __( 'Provider Name:', 'devvp' ),
			'placeholder' => '',
			'description' => '',
			'value'       => '',
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'tracking_number',
			'label'       => __( 'Tracking number:', 'devvp' ),
			'placeholder' => '',
			'description' => '',
			'value'       => '',
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'custom_tracking_link',
			'label'       => __( 'Tracking link:', 'devvp' ),
			'placeholder' => 'http://',
			'description' => '',
			'value'       => '',
		) );

		woocommerce_wp_text_input( array(
			'id'          => 'date_shipped',
			'label'       => __( 'Date shipped:', 'devvp' ),
			'placeholder' => date_i18n( __( 'Y-m-d', 'devvp' ), time() ),
			'description' => '',
			'class'       => 'date-picker-field',
			'value'       => date_i18n( __( 'Y-m-d', 'devvp' ), current_time( 'timestamp' ) ),
		) );

		if(get_option('status_order_devvpst_shipment_tracking') == 'no' || empty(get_option('status_order_devvpst_shipment_tracking'))) {

			echo '<p class="form-field tracking_status_field"><label for="tracking_status">' . __( 'Change Status:', 'devvp' ) . '</label><br/><select id="tracking_status" name="tracking_status" class="chosen_select" style="width:100%;">';
				echo '<option value="">' . __( 'Default', 'devvp' ) . '</option>';
				echo '<option value="completed">' . __( 'Complete', 'devvp' ) . '</option>';
			echo '</select></p>';
		}

		echo '<button type="button" class="button button-primary button-save-form">' . __( 'Save Tracking', 'devvp' ) . '</button>';

		// Live preview
		echo '<p class="preview_tracking_link">' . __( 'Preview:', 'devvp' ) . ' <a href="" target="_blank">' . __( 'Click here to track your shipment', 'devvp' ) . '</a></p>';

		echo '</div>';

		$provider_array = array();

		foreach ( $this->get_providers() as $providers ) {
			foreach ( $providers as $provider => $format ) {
				$provider_array[ sanitize_title( $provider ) ] = urlencode( $format );
			}
		}

        $js = "
			jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field ').hide();

			jQuery( 'input#custom_tracking_link, input#tracking_number, #tracking_provider' ).change( function() {

				var tracking  = jQuery( 'input#tracking_number' ).val();
				var provider  = jQuery( '#tracking_provider' ).val();
				var providers = JSON.parse( decodeURIComponent( '" . rawurlencode( wp_json_encode( $provider_array ) ) . "' ) );

				var postcode = jQuery( '#_shipping_postcode' ).val();

				if ( ! postcode.length ) {
					postcode = jQuery( '#_billing_postcode' ).val();
				}

				postcode = encodeURIComponent( postcode );

				let country = jQuery( '#_shipping_country' ).val();
				country = encodeURIComponent(country);

				var link = '';

				if ( providers[ provider ] ) {
					link = providers[provider];
					link = link.replace( '%251%24s', tracking );
					link = link.replace( '%252%24s', postcode );
					link = link.replace( '%253%24s', country );
					link = decodeURIComponent( link );

					jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field' ).hide();
				} else {
					jQuery( 'p.custom_tracking_link_field, p.custom_tracking_provider_field' ).show();

					link = jQuery( 'input#custom_tracking_link' ).val();
				}

				if ( link ) {
					jQuery( 'p.preview_tracking_link a' ).attr( 'href', link );
					jQuery( 'p.preview_tracking_link' ).show();
				} else {
					jQuery( 'p.preview_tracking_link' ).hide();
				}

			} ).change();";

		if ( function_exists( 'wc_enqueue_js' ) ) {
			wc_enqueue_js( $js );
		} else {
			WC()->add_inline_js( $js );
		}

	}

    /**
	 * Returns a HTML node for a tracking item for the admin meta box
	 */
	public function display_html_tracking_item_for_meta_box( $order_id, $item ) {
			$formatted = $this->get_formatted_tracking_item( $order_id, $item );
			?>
			<div class="tracking-item" id="tracking-item-<?php echo esc_attr( $item['tracking_id'] ); ?>">
				<p class="tracking-content">
					<strong><?php echo esc_html( $formatted['formatted_tracking_provider'] ); ?></strong>
					<?php if ( strlen( $formatted['formatted_tracking_link'] ) > 0 ) : ?>
						- <?php echo sprintf( '<a href="%s" target="_blank" title="' . esc_attr( __( 'Click here to track your shipment', 'devvp' ) ) . '">' . __( 'Track', 'devvp' ) . '</a>', esc_url( $formatted['formatted_tracking_link'] ) ); ?>
					<?php endif; ?>
					<br/>
					<em><?php echo esc_html( $item['tracking_number'] ); ?></em>
					<br/>
					<?php if($item['tracking_status_paypal']) { ?>
						<em>Code: <?php echo esc_html( $item['tracking_code_curl'] ); ?> | Status Tracking Paypal: <?php echo esc_html( $item['tracking_status_paypal'] ); ?></em>
					<?php } ?>
					<?php if($formatted['formatted_tracking_status']) { ?>
						<em>Status order: <?php echo esc_html( $formatted['formatted_tracking_status'] ); ?></em>
					<?php } ?>
					
				</p>
				<p class="meta">
					<?php /* translators: 1: shipping date */ ?>
					<?php echo esc_html( sprintf( __( 'Shipped on %s', 'devvp' ), date_i18n( 'Y-m-d', $item['date_shipped'] ) ) ); ?>
					<a href="#" class="delete-tracking" rel="<?php echo esc_attr( $item['tracking_id'] ); ?>"><?php _e( 'Delete', 'devvp' ); ?></a>
				</p>
			</div>
			<?php
	}

    /*
	 * Works out the final tracking provider and tracking link and appends then to the returned tracking item
	 *
	*/
	public function get_formatted_tracking_item( $order_id, $tracking_item ) {
		$formatted = array();

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$postcode = get_post_meta( $order_id, '_shipping_postcode', true );
			$country_code = get_post_meta( $order_id, '_shipping_country', true);
		} else {
			$order    = new WC_Order( $order_id );
			$postcode = $order->get_shipping_postcode();
			$country_code = $order->get_shipping_country();
		}

		$formatted['formatted_tracking_provider'] = '';
		$formatted['formatted_tracking_link']     = '';

		if ( empty( $postcode ) ) {
			$postcode = get_post_meta( $order_id, '_shipping_postcode', true );
		}

		$formatted['formatted_tracking_status'] = $tracking_item['tracking_status'];

		if ( $tracking_item['custom_tracking_provider'] ) {
			$formatted['formatted_tracking_provider'] = $tracking_item['custom_tracking_provider'];
			$formatted['formatted_tracking_link'] = $tracking_item['custom_tracking_link'];
		} else {

			$link_format = '';

			foreach ( $this->get_providers() as $providers ) {
				foreach ( $providers as $provider => $format ) {
					if ( sanitize_title( $provider ) === $tracking_item['tracking_provider'] ) {
						$link_format = $format;
						$formatted['formatted_tracking_provider'] = $provider;
						break;
					}
				}

				if ( $link_format ) {
					break;
				}
			}

			if ( $link_format ) {
				$formatted['formatted_tracking_link'] = sprintf( $link_format, $tracking_item['tracking_number'], urlencode( $postcode ), $country_code );
			}
		}

		return $formatted;
	}

    /*
	 * Gets all tracking itesm fron the post meta array for an order
	 *
	 * @param int  $order_id  Order ID
	 * @param bool $formatted Wether or not to reslove the final tracking link
	 *                        and provider in the returned tracking item.
	 *                        Default to false.
	 *
	 * @return array List of tracking items
	 */
	public function get_tracking_items( $order_id, $formatted = false ) {
		global $wpdb;

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$tracking_items = get_post_meta( $order_id, '_devvpst_shipment_tracking_items', true );
		} else {
			$order          = new WC_Order( $order_id );
			$tracking_items = $order->get_meta( '_devvpst_shipment_tracking_items', true );
		}

		if ( is_array( $tracking_items ) ) {
			if ( $formatted ) {
				foreach ( $tracking_items as &$item ) {
					$formatted_item = $this->get_formatted_tracking_item( $order_id, $item );
					$item           = array_merge( $item, $formatted_item );
				}
			}
			return $tracking_items;
		} else {
			return array();
		}
	}

    /**
	 * Order Tracking Save
	 *
	 * Function for saving tracking items
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( isset( $_POST['tracking_number'] ) && strlen( $_POST['tracking_number'] ) > 0 ) {
			$args = array(
				'tracking_provider'        => wc_clean( $_POST['tracking_provider'] ),
				'custom_tracking_provider' => wc_clean( $_POST['custom_tracking_provider'] ),
				'custom_tracking_link'     => wc_clean( $_POST['custom_tracking_link'] ),
				'tracking_number'          => wc_clean( $_POST['tracking_number'] ),
				'date_shipped'             => wc_clean( $_POST['date_shipped'] ),
				'tracking_status'          => wc_clean( $_POST['tracking_status'] ),
			);
            
			$this->add_tracking_item( $post_id, $args );
		}
	}

    /*
	 * Adds a tracking item to the post_meta array
	 *
	 * @param int   $order_id    Order ID
	 * @param array $tracking_items List of tracking item
	 *
	 * @return array Tracking item
	 */
	public function add_tracking_item( $order_id, $args ) {
		$tracking_item = array();

		$tracking_item['tracking_provider']        = wc_clean( $args['tracking_provider'] );
		$tracking_item['custom_tracking_provider'] = wc_clean( $args['custom_tracking_provider'] );
		$tracking_item['custom_tracking_link']     = wc_clean( $args['custom_tracking_link'] );
		$tracking_item['tracking_number']          = wc_clean( $args['tracking_number'] );
		$tracking_item['date_shipped']             = wc_clean( strtotime( $args['date_shipped'] ) );
		$tracking_item['tracking_status']          = wc_clean( $args['tracking_status'] );
		$tracking_item['tracking_code_curl']       = wc_clean( $args['tracking_code_curl'] );
		$tracking_item['tracking_status_paypal']   = wc_clean( $args['tracking_status_paypal'] );
		
		if ( 0 == (int) $tracking_item['date_shipped'] ) {
			 $tracking_item['date_shipped'] = time();
		}

		if ( $tracking_item['custom_tracking_provider'] ) {
			$tracking_item['tracking_id'] = md5( "{$tracking_item['custom_tracking_provider']}-{$tracking_item['tracking_number']}" . microtime() );
		} else {
			$tracking_item['tracking_id'] = md5( "{$tracking_item['tracking_provider']}-{$tracking_item['tracking_number']}" . microtime() );
		}
		if ( FALSE === get_post_status( $order_id ) ) {
			return '';
		} else {
			$tracking_items   = $this->get_tracking_items( $order_id );
			$tracking_items[] = $tracking_item;
			$this->save_tracking_items( $order_id, $tracking_items );
			return $tracking_item;
		}		
	}

    /**
	 * Saves the tracking items array to post_meta.
	 *
	 * @param int   $order_id       Order ID
	 * @param array $tracking_items List of tracking item
	 */
	public function save_tracking_items( $order_id, $tracking_items ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_post_meta( $order_id, '_devvpst_shipment_tracking_items', $tracking_items );
		} else {
			$order = new WC_Order( $order_id );
			$order->update_meta_data( '_devvpst_shipment_tracking_items', $tracking_items );
			$order->save_meta_data();
		}
	}
    
	/**
	 * Display Shipment info in the frontend (order view/tracking page).
	 */
	public function display_tracking_info( $order_id ) {
		wc_get_template( 'my-account/view-order.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ) ), 'woocommerce-shipment-tracking/', DEVVPST_PATH. '/templates/' );
	}

	/**
	 * Display shipment info in customer emails.
	 *
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether the email is being sent to admin or not.
	 * @param bool     $plain_text    Whether email is in plain text or not.
	 * @param WC_Email $email         Email object.
	 */
	public function email_display( $order, $sent_to_admin, $plain_text = null, $email = null ) {
		/**
		 * Don't include tracking information in refunded email.
		 *
		 * When email instance is `WC_Email_Customer_Refunded_Order`, it may
		 * full or partial refund.
		 *
		 * @see https://github.com/woocommerce/woocommerce-shipment-tracking/issues/61
		 */
		if ( is_a( $email, 'WC_Email_Customer_Refunded_Order' ) ) {
			return;
		}

		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		if ( true === $plain_text ) {
			wc_get_template( 'email/plain/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ) ), 'woocommerce-shipment-tracking/', DEVVPST_PATH . '/templates/' );
		} else {
			wc_get_template( 'email/tracking-info.php', array( 'tracking_items' => $this->get_tracking_items( $order_id, true ) ), 'woocommerce-shipment-tracking/', DEVVPST_PATH . '/templates/' );
		}
	}


	/**
	 * Define shipment tracking column in admin orders list.
	 *
	 * @param array $columns Existing columns
	 *
	 * @return array Altered columns
	 */
	public function shop_order_columns( $columns ) {
		$columns['shipment_tracking'] = __( 'Tracking Paypal', 'woocommerce-shipment-tracking' );
		return $columns;
	}

	/**
	 * Render shipment tracking in custom column.
	 *
	 * @param string $column Current column
	 */
	public function render_shop_order_columns( $column ) {
		global $post;

		if ( 'shipment_tracking' === $column ) {
			echo $this->get_shipment_tracking_column( $post->ID );
		}
	}

	/**
	 * Get content for shipment tracking column.
	 *
	 * @param int $order_id Order ID
	 *
	 * @return string Column content to render
	 */
	public function get_shipment_tracking_column( $order_id ) {
		ob_start();

		$tracking_items = $this->get_tracking_items( $order_id );

		if ( count( $tracking_items ) > 0 ) {
			echo '<ul>';

			foreach ( $tracking_items as $tracking_item ) {
				$formatted = $this->get_formatted_tracking_item( $order_id, $tracking_item );
				printf(
					'<li><a href="%s" target="_blank">%s</a></li>',
					esc_url( $formatted['formatted_tracking_link'] ),
					esc_html( $tracking_item['tracking_number'] )
				);
			}
			echo '</ul>';
		} else {
			echo '–';
		}

		return apply_filters( 'woocommerce_shipment_tracking_get_shipment_tracking_column', ob_get_clean(), $order_id, $tracking_items );
	}
}
$GLOBALS['devvpst_setting'] = new DEVVPST_SETTINGS();

