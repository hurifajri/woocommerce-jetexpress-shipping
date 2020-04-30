<?php
/**
 * Plugin Name: JETexpress Shipping
 * Description: JETexpress shipping plugin for WooCommerce.
 * Version: 1.0.0
 * Author: Moonlay Technologies
 * Author URI: http://moonlay.com/
 *
 * WC requires at least: 2.2
 * WC tested up to: 2.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright 2020 Moonlay Technologies
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

  function jetexpress_shipping_method_init() {

    if ( ! class_exists( 'WC_Jetexpress_Shipping_Method' ) ) {

      class WC_Jetexpress_Shipping_Method extends WC_Shipping_Method {

        /**
         * Constructor
         *
         * @access public
         * @return void
         */
        public function __construct() {

          $this->id                 = 'jetexpress';
          $this->title              = __( 'JETexpress', 'jetexpress' );
          $this->method_title       = __( 'JETexpress', 'jetexpress' );
          $this->method_description = __( 'JETexpress shipping plugin for WooCommerce.', 'jetexpress' );
          $this->enabled            = $this->get_option( 'enabled' );
          $this->client_key         = $this->get_option( 'clientKey' );
          $this->origin             = $this->get_option( 'origin' );
          $this->origin_zipcode     = $this->get_option( 'originZipCode' );
          $this->is_insured         = $this->get_option( 'isInsured' );
          $this->init();

        }

        /**
         * Init settings
         *
         * @access public
         * @return void
         */
        function init() {

          // Load the settings API
          $this->init_form_fields();
          $this->init_settings();

          // Save settings in admin if there is any defined
          add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        }

        /**
         * Initialise gateway settings form fields
         */
        function init_form_fields() {

          $this->form_fields = array(
            'enabled'        => array(
              'type'         => 'checkbox',
              'title'        => __( 'Enable Module', 'jetexpress' ),
              'label'        => __( 'Enable', 'jetexpress' ),
              'description'  => __( 'Enable/disable this shipping extension.', 'jetexpress' ),
              'default'      => 'yes'
            ),
            'clientKey'      => array(
              'type'         => 'text',
              'title'        => __( 'Client Key', 'jetexpress' ),
              'description'  => __( 'This client key is used to get permission access to JETexpress API.', 'jetexpress' ),
            ),
            'origin'         => array(
              'type'         => 'text',
              'title'        => __( 'Origin', 'jetexpress' ),
              'description'  => __( 'Your origin city.', 'jetexpress' ),
            ),
            'originZipCode'  => array(
              'type'         => 'number',
              'title'        => __( 'Postcode', 'jetexpress' ),
              'description'  => __( 'Your origin postcode.', 'jetexpress' ),
            ),
            'isInsured'      => array(
              'type'         => 'checkbox',
              'title'        => __( 'Enable Insurance', 'jetexpress' ),
              'label'        => __( 'Enable', 'jetexpress' ),
              'description'  => __( 'Enable/disable JETexpress insurance.', 'jetexpress' ),
              'default' => 'yes'
            ),
          );

        }

        /**
         * calculate_shipping function.
         *
         * @access public
         * @param mixed $package
         * @return void
         */
        public function calculate_shipping( $package = array() ) {

          $client_key          = $this->client_key;
          $origin              = $this->origin;
          $origin_zipcode      = $this->origin_zipcode;
          $destination         = $package["destination"]["city"];
          $destination_zipcode = $package["destination"]["postcode"];
          $is_insured          = $this->is_insured === 'yes' ? true : false;
          $item_value          = (int)WC()->cart->cart_contents_total;
          $items               = array();

          global $woocommerce; $i = 0;
          foreach ( $woocommerce->cart->get_cart() as $key => $values ) {

            $items[$i]['weight'] = (float)$values['data']->weight;
            $items[$i]['height'] = (float)$values['data']->height;
            $items[$i]['width']  = (float)$values['data']->width;
            $items[$i]['length'] = (float)$values['data']->length;

            $i++;

          }

          // body data
				  $data = array(
    		  	'origin'             => strtoupper($origin),
				  	'OriginZipCode'      => $origin_zipcode,
				  	'destination'        => strtoupper($destination),
				  	'DestinationZipCode' => $destination_zipcode,
				  	'isInsured'          => $is_insured,
				  	'itemValue'          => $item_value,
				  	'items'              => $items
          );

          $payload = json_encode($data);

          // echo '<script>';
  			  // echo 'console.log('. json_encode($data) .')';
          // echo '</script>';

          // $url = 'http://api.sandbox.jetexpress.co.id/v2/pricings';
          $url = 'http://api.jetexpress.co.id/v2/pricings';

				  // prepare new cURL resource
				  $curl = curl_init();
				  curl_setopt($curl, CURLOPT_URL, $url);
				  curl_setopt($curl, CURLOPT_FAILONERROR, true);
				  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				  curl_setopt($curl, CURLINFO_HEADER_OUT, true);
				  curl_setopt($curl, CURLOPT_POST, true);
				  curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

				  // set HTTP Header for POST request
				  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    		  	'Content-Type: application/json',
    		  	'clientkey: ' . $client_key)
				  );

				  // submit the POST request
				  $response = curl_exec($curl);

				  // close cURL session handle
				  curl_close($curl);

				  // echo '<script>';
  			  // echo 'console.log('. $response .')';
          // echo '</script>';

          if ( $response ) {

            $response_info = array();
					  $response_parts = json_decode( $response, true );

					  $insurance_fee = $response_parts[ 'insuranceFee' ];
            $response_services = $response_parts[ 'services' ];

					  if($is_insured) {

              $raw_rate = array();

              foreach ( $response_services as $response_service ) {

                $rate_with_insurance = array(
						      'id'       => $this->id . '_' . $response_service['code'] . '_ins',
						      'label'    => $this->title . ' ' . $response_service['name'] . ' (inc. insurance) &bull; ' . $response_service['estimatedDelivery'],
						      'cost'     => $response_service['totalFee'] + $insurance_fee,
                  'taxes'    => '',   // Pass an array of taxes, or pass nothing to have it calculated for you, or pass 'false' to calculate no tax for this method
						      'calc_tax' => 'per_order' // Calc tax per_order or per_item. Per item needs an array of costs passed via 'cost'
                );

                $rate_without_insurance = array(
						      'id'       => $this->id . '_' . $response_service['code'],
						      'label'    => $this->title . ' ' . $response_service['name'] . ' &bull; ' . $response_service['estimatedDelivery'],
                  'cost'     => $response_service['totalFee'],
                  'taxes'    => '',   // Pass an array of taxes, or pass nothing to have it calculated for you, or pass 'false' to calculate no tax for this method
						      'calc_tax' => 'per_order' // Calc tax per_order or per_item. Per item needs an array of costs passed via 'cost'
                );

                array_push($raw_rate, $rate_with_insurance, $rate_without_insurance);

              }

              foreach( $raw_rate as $rate ) {

                // echo '<script>';
  			        // echo 'console.log('. json_encode($rate) .')';
                // echo '</script>';

                $this->add_rate( $rate );

              }

            } else {

              foreach ( $response_services as $response_service ) {

                $rate_without_insurance = array(
						      'id'       => $this->id . '_' . $response_service['code'],
                  'label'    => $this->title . ' ' . $response_service['name'] . ' &bull; ' . $response_service['estimatedDelivery'],
                  'cost'     => $response_service['totalFee'],
                  'taxes'    => '',   // Pass an array of taxes, or pass nothing to have it calculated for you, or pass 'false' to calculate no tax for this method
						      'calc_tax' => 'per_order' // Calc tax per_order or per_item. Per item needs an array of costs passed via 'cost'
                );

                $this->add_rate( $rate_without_insurance );

                // echo '<script>';
  			        // echo 'console.log('. json_encode($rate_without_insurance) .')';
                // echo '</script>';

              }
            }
          }
        }
      }
    }
  }

  add_action( 'woocommerce_shipping_init', 'jetexpress_shipping_method_init' );

  // Tell WooCommerce that class is exist
  function add_jetexpress_shipping_method( $methods ) {

    $methods['jetexpress'] = 'WC_Jetexpress_Shipping_Method';
    return $methods;

  }

  add_filter( 'woocommerce_shipping_methods', 'add_jetexpress_shipping_method', 10, 1 );

}