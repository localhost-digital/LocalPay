<?php
/*
 * Plugin Name: LocalPay - Offline Payments Extension
 * Plugin URI:
 * Description: Receive offline payments from any country and carrier on your website with WooCommerce + LocalPay (Use for Mobile Money, Western Union, MoneyGram)
 * Author: LocalHost Digital (contact@localhostkmer.xyz)
 * Author URI: https://localhostkmer.xyz
 * Version: 3.0.3
 * Version Date: Oct 16, 2020
 * Created: 2019
 * Copyright 2020 localhostkmer.xyz All rights reserved
 */
 
// Reach out to LocalHost Digital for website and mobile app development services at contact@localhostkmer.xyz
// or at www.localhostkmer.xyz or download our app at 
// If you are using this version, please send us some feedback
//via email at contact@localhostkmer.xyz on your thoughts and what you would like improved

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce fallback notice.
 * @return string
 */
function woocommerce_momo_missing_wc_notice() {
	echo '<div class="error"><p><strong>' , sprintf( esc_html__( 'LocalPay requires WooCommerce to be installed and active. You can download %s here.' , 'WC_LocalPay_Gateway' ) , '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) , '</strong></p></div>';
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways' , 'momo_add_gateway_class' );
function momo_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_LocalPay_Gateway'; // your class name is here
	return $gateways;
}

// display admin review notice
function review_notice() {
    global $pagenow;
    if ( $pagenow == 'admin.php' || $pagenow == 'index.php' ) {
		$user = wp_get_current_user();
		if ( in_array( 'administrator', (array) $user->roles ) ) {
			 echo '<div class="notice notice-info is-dismissible">
				 <div style="width:70%; display:inline-block;">
				 <p>Glad our LocalPay plugin could help you fulfil mobile orders. So, If you have a moment, </p> 
				 <p><strong>I would very much appreciate if you could quickly rate LocalPay on WP.</strong></p>
				 <p>It will help us spread the word to more who need LocalPay but can\'t find it yet.</p>
			 </div>
				 <div style="width:29%; display:inline-block;">
				 <p><a href="#" target="_blank"><img src="#" width="200px" height="auto"></a></p></div>
			 </div>';
		}
	}
}
add_action('admin_notices', 'review_notice');


/*
 * Dashboard Menu Button
 */
function momo_admin_menu(){
	add_menu_page( null , '<span style="color:yellow">LocalPay</span>' , 'manage_options' , 'wc-settings&tab=checkout&section=momo' , 'momo_admin_menu' , 'dashicons-money-alt' );
	//add_submenu_page( 'wc-settings&tab=checkout&section=momo' , 'Review LocalPay' , '<span style="color:yellow">Review >> </span>' , 'manage_options' , 'https://wordpress.org/support/plugin/momo-mobile-money-payments-woocommerce-extension/reviews/?filter=5' , null, null );
	//add_submenu_page( 'wc-settings&tab=checkout&section=momo' , 'Our Plugins' , 'Our Plugins' , 'manage_options' , '#' , null, null );
	add_submenu_page( 'wc-settings&tab=checkout&section=momo' , 'Upgrade LocalPay' , '<span style="color:yellow">Upgrade >> </span>' , 'manage_options' , 'https://localhostkmer.xyz' , null, null );
}
add_action('admin_menu' , 'momo_admin_menu');

/*
 * Settings Button
 */
function momo_settings_link( $links_array ){
	array_unshift( $links_array , '<a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=momo">Settings</a>' );
	return $links_array;
}
$plugin = plugin_basename(__FILE__); 
add_filter( "plugin_action_links_$plugin" , 'momo_settings_link' );

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded' , 'momo_init_gateway_class' );
function momo_init_gateway_class() {
    
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices' , 'woocommerce_momo_missing_wc_notice' );
		return;
	}

	class WC_LocalPay_Gateway extends WC_Payment_Gateway {

		/**
		 * Class constructor
		 */
		public function __construct() {
		$this->id = 'localpay'; // payment gateway plugin ID
		$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = true; // in case you need a custom form
		$this->method_title = 'LocalPay - Easy Offline Payments (Mobile Money, Western Union, MoneyGram)';
		$this->method_description = 'Easily receive Offline payments'; // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods
		$this->supports = array(
			'products'
		);

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->enabled = $this->get_option( 'enableMOMO' );
		$this->title = $this->get_option( 'checkout_title' );
		$this->ReceiverMOMONo = $this->get_option( 'ReceiverMOMONo' );
		$this->ReceiverMOMONoOwner = $this->get_option( 'ReceiverMOMONoOwner' );
	
		$this->ReceiverMOMOEmail = $this->get_option( 'ReceiverMOMOEmail' );
		
		$this->checkout_description = $this->get_option( 'checkout_description' );
		$this->momo_notice = $this->get_option( 'momo_notice' );
		$this->store_instructions = $this->get_option( 'store_instructions' );
		$this->toggleTutorial = $this->get_option( 'toggleTutorial' );
		$this->toggleCredits = $this->get_option( 'toggleCredits' );
		$this->ad = $this->get_option( 'ad' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts' , array( $this , 'payment_scripts' ) );

		//Thank you page
		add_action( 'woocommerce_before_thankyou' , array( $this , 'thankyou_page' ) );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table' , array( $this , 'instructions_sent' ), 10, 3 );

		}

			
		/**
		 * Plugin options
		 */
		public function init_form_fields(){

			$this->form_fields = array(
				'enableMOMO' => array(
					'title'       => 'Enable LocalPay',
					'label'       => 'Check to Enable/Uncheck to Disable',
					'type'        => 'checkbox',
					'default'     => 'no'
				),
				'checkout_title' => array(
					'title'       => 'Checkout Title',
					'type'        => 'text',
					'description' => 'This is the title which the user sees on the checkout page.',
					'default'     => 'LocalPay - Easy Offline Payments (Mobile Money, Western Union, MoneyGram etc..)',
					'placeholder' => 'LocalPay - Easy Offline Payments (Mobile Money, Western Union, MoneyGram etc..)',
				),
				'ReceiverMOMONo' => array(
					'title'       => 'Receiver Mobile Money No',
					'type'        => 'text',
					'description' => 'This is the phone number associated with your store mobile money account or your receiving Mobile Money account. Customers will send money to this number',
					'placeholder' => "+1234567890",
				),
				'ReceiverMOMONoOwner' => array(
					'title'       => "Receiver Mobile Money Owner's Name",
					'type'        => 'text',
					'description' => 'This is the name associated with your store mobile money account or your receiving Mobile Money account. Customers will send money to this name',
					'placeholder' => "John D",
				),

				'ReceiverMOMOEmail' => array(
					'title'       => "Receiver Mobile Money Owner's Email",
					'type'        => 'text',
					'description' => 'This is the email associated with your store mobile money account or your receiving Mobile Money account. Customers will send money to this email',
					'default'     => "@gmail.com",
					'placeholder' => "email@website.com",
				),
				'checkout_description' => array(
					'title'       => 'Checkout Page Notice <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank"><sup style="color:red">PRO</sup></a>',
					'type'        => 'textarea',
					'description' => 'This is the description which the user sees during checkout. <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank">EDIT WITH PRO</a>',
					'default'     => 'Please select your mobile money payment method to send the requested total amount via an online mobile money platform (LocalPay agent,  Western Union, Moneygram, WorldRemit) or via a local mobile money agent and fill these fields out below to confirm that you have sent the total requested amount',
					'css'     => 'width:80%; pointer-events: none;',
					'class'     => 'disabled',
				),
				'momo_notice'    => array(
					'title'       => 'Thank You Notice <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank"><sup style="color:red">PRO</sup></a>',
					'type'        => 'textarea',
					'description' => 'Notice that will be added to the thank you page before store instructions if any. <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank">EDIT WITH PRO</a>',
					'default'     => "<p>We are checking our systems to confirm that we received. If you haven't sent the money already, please make sure to do so now.</p>" . 
					'<p>Once confirmed, we will proceed with the shipping and delivery options you chose.</p>' . 
					'<p>Thank you for doing business with us! You will be updated regarding your order details soon.</p>',
					'css'     => 'width:80%; pointer-events: none;',
					'class'     => 'disabled',
				),
				'store_instructions'    => array(
					'title'       => 'Store Instructions <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank"><sup style="color:red">PRO</sup></a>',
					'type'        => 'textarea',
					'description' => 'Store Instructions that will be added to the thank you page and emails. <a style="text-decoration:none" href="https://localhostkmer.xyz/" target="_blank">EDIT WITH PRO</a>',
					'default'     => "Please send the total amount requested to our store if you haven't yet",
					'css'     => 'width:80%; pointer-events: none;',
					'class'     => 'disabled',
				),
				'toggleTutorial' => array(
					'title'       => 'Enable Tutorial to display 1min video link',
					'label'       => 'Check to Enable/Uncheck to Disable',
					'type'        => 'checkbox',
					'description' => 'Help your customers checkout with ease by showing this tutorial link',
					'default'     => 'no',
				),
				'toggleCredits' => array(
					'title'       => 'Enable Credits to display Powered by LocalHost Digital',
					'label'       => 'Check to Enable/Uncheck to Disable',
					'type'        => 'checkbox',
					'description' => 'Help us spread the word about this plugin by sharing that we made this plugin',
					'default'     => 'no',
				),
				'ad'    => array(
					'title'       => 'Upcoming Features in the next version',
					'type'        => 'textarea',
					'description' => 'Request features you would like <a href="mailto:contact@localhostkmer.xyz?subject=Feature request for LocalPay&body=Hi Jean,%0A%0AI love the LocalPay plugin by LocalHost Digital.%0AHowever, I thought you might want to add an option to" target="_blank">here</a>',
					'default' => '- Enable payment methods you use so they are the only ones showing, '.
					'- payment icon logos will be added, and more',
					'css'     => 'width:57%; pointer-events: none;',
					'class'     => 'disabled',
				),
			);
			
		}
		
		/**
		 * Custom form 
		 */
		public function payment_fields () {
			global $woocommerce, $total;
			
			// we need it to get any order details
			$order = wc_get_order( $order_id );

			$woocommerce->cart->get_cart();
			$total = $woocommerce->cart->get_total();
			
			$MOMOApp = esc_attr(sanitize_text_field(trim( 'MOMOApp' )));
			$CustomerMOMOName = esc_attr(sanitize_text_field(trim( 'CustomerMOMOName' )));
			$CustomerMOMONo = esc_attr(sanitize_text_field(trim( 'CustomerMOMONo' )));
			$MOMORefNo = esc_attr(sanitize_text_field(trim( 'MOMORefNo' )));
			
			echo '<fieldset id="wc-' , esc_attr( $this->id ) , 'form" style="background:white; padding:5%">';

			// Add this action hook if you want your custom payment gateway to support it
			do_action( 'woocommerce_form_start' , $this->id );
				
			echo esc_html( wp_kses_post($this->checkout_description) ) , '<br><br>';

			echo '<div class="form-row form-row-wide">
			
			<label for="' . $MOMOApp . '">Payment Transfer Method used <span class="required">*</span></label>
				<select id="' . $MOMOApp . '" name="' . $MOMOApp . '" style="width:95%; border:1px solid" type="text" autocomplete="off">
					<option value="' , esc_attr( 'empty' ) , '">Please select a mobile payment method of your choice</option>
					<option value="' , esc_attr( 'LocalPay agent' ) , '">LocalPay Agent to ' , esc_html( wp_kses_post( $this->ReceiverMOMONo ) ) , ' registered under ' , esc_html( wp_kses_post( $this->ReceiverMOMONoOwner ) ) , '</option>
		
					<option value="' , esc_attr( 'Western Union' ) , '">Western Union to ' , esc_html( wp_kses_post( $this->ReceiverMOMONoOwner ) ) , '</option>
					<option value="' , esc_attr( 'Moneygram' ) , '">MoneyGram to ' , esc_html( wp_kses_post( $this->ReceiverMOMONoOwner ) ) , '</option>
					<option value="' , esc_attr( 'Worldremit' ) , '">World Remit to ' , esc_html( wp_kses_post( $this->ReceiverMOMONoOwner ) ) , '</option>
				</select>
			</div>

			<div class="form-row form-row-wide">
			<label>Sender Name <span class="required">*</span></label>
			<input id="' . $CustomerMOMOName . '" style="text-transform:uppercase; width:95%; border:1px solid" name="' . $CustomerMOMOName . '" type="text" placeholder="Insert Full Name" autocomplete="off">
			</div>
			
			<div class="form-row form-row-wide">
			<label>Sender Phone Number <span class="required">*</span></label>
			<input id="' . $CustomerMOMONo . '" name="' . $CustomerMOMONo . '" style="width:95%; border:1px solid" type="text" min="111111" size="12" placeholder="+1234567890" autocomplete="off">
			</div>

			<div class="form-row form-row-wide">
			<label>Reference/Transaction Confirmation Code (if applicable)</label>
				<input id="' . $MOMORefNo . '" name="' . $MOMORefNo . '" style="width:95%; border:1px solid" type="text" placeholder="Insert Confirmation Code" autocomplete="off">
			</div>

			<div class="form-row form-row-wide">
				<label style="width:95%;">Please verify that the Amount Sent is the following: <span class="required">***</span></label>
				<p style="width:95%" class="input-group-text"><span style="margin:0 10px 0 0; font-weight: bold;">' . $total . '</span> <span style="font-size: 0.7em;"><em>(Add any applicable transfer fees)</em></span></p>
			</div>

			<div class="clear"></div>';
			
			// if toggle Tutorial is disabled, we do not show credits
			if ( 'no' === $this->toggleTutorial ) {
				echo '<br>';
			} else {
				echo '<br>See this <a href=' . esc_url("https://localhostkmer.xyzdemo") . ' style="text-decoration: underline" target="_blank">1min video demo</a> explaining how this works. <br>';
			}
			
			echo 'If you are having an issue, please call <a href="tel:' , esc_html( wp_kses_post($this->ReceiverMOMONo)) , '" target="_blank">' , esc_html( wp_kses_post($this->ReceiverMOMONo)) , '</a> or email <a href="mailto:' , esc_html( wp_kses_post($this->ReceiverMOMOEmail)) , '" target="_blank">' , esc_html( wp_kses_post($this->ReceiverMOMOEmail)) , '</a>';
			
			
			// if toggle Credits is disabled, we do not show credits
			if ( 'no' === $this->toggleCredits ) {
				echo '<br>';
			} else {
				echo '<br><br> <a href=' . esc_url("https://localhostkmer.xyz") . ' style="text-decoration: underline;" target="_blank">Powered by LocalHost Digital</a><br>';
			}

			do_action( 'woocommerce_form_end' , $this->id );

			echo '<div class="clear"></div></fieldset>';
		}

		public function thankyou_page( $order_id ) {
		    
			// we need it to get any order details
			$order = wc_get_order( $order_id );
			
    		if ( 'localpay' === $order->get_payment_method() ) {
    			if ( $this->momo_notice ) {
    				echo '<h2>LocalPay Notice</h2>' , wp_kses_post( wpautop( wptexturize( $this->momo_notice ) ) . PHP_EOL ) , '<br><hr><br>';
    			}
    			
    			if ( $this->store_instructions ) {
    				echo '<h2>Additional store instructions</h2><p>' , wp_kses_post( wpautop( wptexturize( $this->store_instructions ) ) . PHP_EOL ) , '</p><br><hr><br>';
    			}
    		}
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param WC_Order $order Order object.
		 * @param bool     $sent_to_admin Sent to admin.
		 * @param bool     $plain_text Email format: plain text or HTML.
		 */
		public function instructions_sent( $order, $sent_to_admin, $plain_text = false ) {
			
			$MOMOApp = esc_html(sanitize_text_field(trim($_POST[ 'MOMOApp' ])));
			$CustomerMOMOName = esc_html(sanitize_text_field(trim($_POST[ 'CustomerMOMOName' ])));
			$CustomerMOMONo = esc_html(sanitize_text_field(trim($_POST[ 'CustomerMOMONo' ])));
			$MOMORefNo = esc_html(sanitize_text_field(trim($_POST[ 'MOMORefNo' ])));
			
			if ( ! $sent_to_admin && 'localpay' === $order->get_payment_method() ) {
				
				echo '<h2>LocalPay Details</h2>';
			
				echo '<p>We are checking our systems to confirm that we received the total requested amount.</p>' , 
				'<p>In the meantime, here are the details we received from <strong style="text-transform:uppercase;">' . $CustomerMOMOName . 
				'</strong></p> <p>A payment was sent through <strong>' . $MOMOApp . '</strong> from the following phone number: <strong>' . 
				 $CustomerMOMONo . '</strong></p>' , 
				'<p>Here is the reference code <strong>' . $MOMORefNo . '</strong></p>' , 
				'<p>Once confirmed, we will proceed with the shipping and delivery options you chose.</p><br><br>';
				
				echo 'Thank you for doing business with us, <span style="text-transform:uppercase;">' . $CustomerMOMOName . 
				'!</span><br> You will be updated regarding your order details soon<br>';
				
				if ( $this->store_instructions ) {
					echo '<p>Here are some additional store instructions: </p><p>' , wp_kses_post( wpautop( wptexturize( $this->store_instructions ) ) . PHP_EOL ) , '</p>';
				}
				
			}

		}

		/*
		* Payment Custom JS and CSS
		*/
		public function payment_scripts() {

			// we need JavaScript to process a token only on cart/checkout pages, right?
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}

			// if our payment gateway is disabled, we do not have to enqueue JS too
			if ( 'no' === $this->enabled ) {
				return;
			}

			wp_enqueue_script( 'woocommerce_momo' );

		}
			
		/*
		* Fields validation
		*/
		public function validate_fields() {
			$customerPaymentMode = sanitize_text_field(trim($_POST[ 'MOMOApp' ]));
			$customerMomoName = sanitize_text_field(trim($_POST[ 'CustomerMOMOName' ]));
			$customerMomoNumber = sanitize_text_field(trim($_POST[ 'CustomerMOMONo' ]));
			
			if ( $customerPaymentMode == 'empty' ) {
				wc_add_notice(  'Please select a Payment Transfer Method' , 'error' );
			}

			if ( strlen($customerMomoName) < 3 ) {
				wc_add_notice(  'Mobile Money Customer Name ' . $customerMomoName . ' is too short or empty' , 'error' );
			}

			if ( strlen($customerMomoNumber) < 10 ) {
				wc_add_notice(  'Mobile Money Customer Phone Number ' . $customerMomoNumber . ' is too short!' , 'error' );
			}

			if ( strlen($customerMomoNumber) > 15 ) {
				wc_add_notice(  'Mobile Money Customer Phone Number ' . $customerMomoNumber . ' is too long!' , 'error' );
			}
			
			//$pattern = '/\+?([0-9]{1,3})+-?([0-9]{3,5})+-?([0-9]{3,5})+-?([0-9]{3,5})/s';
			//$pattern = '/\+?([0-9]{2})+-?([0-9]{3})+-?([0-9]{5,10})/s';
			//$pattern = '/\+?[0-9]{10,15}/s';

			if ( preg_match( '/\+?[0-9]{10,15}/s' , $customerMomoNumber) != 1 ) {
				wc_add_notice(  'Mobile Money Customer Phone Number ' . $customerMomoNumber . ' is invalid! Please submit a number in the format +1234567890 or 1234567890 with 10-15 digits' , 'error' );
			}
			
		}

		/*
		* Process Payment
		*/
		public function process_payment( $order_id ) {
			global $woocommerce, $total;
			
			// we need it to get any order details
			$order = wc_get_order( $order_id );

			$woocommerce->cart->get_cart();
			$total = $woocommerce->cart->get_total();
			
			$MOMOApp = esc_html(sanitize_text_field(trim($_POST['MOMOApp'])));
			$CustomerMOMOName = esc_html(sanitize_text_field(trim($_POST['CustomerMOMOName'])));
			$CustomerMOMONo = esc_html(sanitize_text_field(trim($_POST['CustomerMOMONo'])));
			$MOMORefNo = esc_html(sanitize_text_field(trim($_POST['MOMORefNo'])));
			
			if ( !is_wp_error($order) ) {

				// reduce inventory
				$order->reduce_order_stock();
				
				$note = '<p>Your order request was received!</p>' .
				'<p>In the meantime, here are the details we received from <strong style="text-transform:uppercase;">' . 
				$CustomerMOMOName . '</strong></p> <p>A payment was sent through <strong>' .  
				$MOMOApp . '</strong> from the following phone number: <strong>' . $CustomerMOMONo . '</strong></p>' .
				'<p>Here is the reference code <strong>' . $MOMORefNo . '</strong></p>' . 
				'<p>Once confirmed, we will proceed with the shipping and delivery options you chose.</p><br>' . 
				'<p>Thank you for doing business with us, <span style="text-transform:uppercase;">' . $CustomerMOMOName  . '!</span></p>' . 
				'<p>You will be updated regarding your order details soon.</p>';
			
			
				// some notes to customer (replace true with false to make it private)
				$order->add_order_note( $note , true );
				
				// Mark as on-hold (we're awaiting the payment).
				$order->update_status( apply_filters( 'woocommerce_momo_process_payment_order_status' , 'on-hold' , $order ), __( 'Checking for payment.<br>' , 'woocommerce' ) );
				
				// Empty cart
				$woocommerce->cart->empty_cart();
		
				// Redirect to the thank you page
				return array( 'result' => 'success' , 'redirect' => $this->get_return_url($order) );
		
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return false;
			}
			
		}
		
		public function webhook() {
			$order = wc_get_order( $_GET['id'] );
			$order->payment_complete();
			$order->reduce_order_stock();
			
			update_option('webhook_debug', $_GET);
		}
	}
		
}
// Reach out to us for website and mobile app development services at contact@localhostkmer.xyz
// If you are using this version, please send us some feedback
//via email at contact@localhostkmer.xyz on your thoughts and what you would like improved