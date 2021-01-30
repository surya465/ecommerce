<?php
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WC_Payment_Gateway_Stripe_Local_Payment' ) ) {
	return;
}

/**
 *
 * @package Stripe/Gateways
 * @author PaymentPlugins
 *
 */
class WC_Payment_Gateway_Stripe_Klarna extends WC_Payment_Gateway_Stripe_Local_Payment {

	use WC_Stripe_Local_Payment_Charge_Trait;

	public function __construct() {
		$this->local_payment_type = 'klarna';
		$this->currencies         = array( 'EUR', 'SEK', 'NOK', 'DKK', 'GBP', 'USD' );
		$this->countries          = $this->limited_countries = array( 'US', 'AT', 'FI', 'DE', 'NL', 'DK', 'NO', 'SE', 'GB' );
		$this->id                 = 'stripe_klarna';
		$this->tab_title          = __( 'Klarna', 'woo-stripe-payment' );
		$this->template_name      = 'local-payment.php';
		$this->token_type         = 'Stripe_Local';
		$this->method_title       = __( 'Klarna', 'woo-stripe-payment' );
		$this->method_description = __( 'Klarna gateway that integrates with your Stripe account.', 'woo-stripe-payment' );
		$this->icon               = stripe_wc()->assets_url( 'img/klarna.svg' );
		parent::__construct();
		$this->template_name = 'klarna.php';
	}

	public function get_required_parameters() {
		return array(
			'USD' => array( 'US' ),
			'EUR' => array( 'AT', 'FI', 'DE', 'NL' ),
			'DKK' => array( 'DK' ),
			'NOK' => array( 'NO' ),
			'SEK' => array( 'SE' ),
			'GBP' => array( 'GB' ),
		);
	}

	/**
	 * @param string $currency
	 * @param string $billing_country
	 *
	 * @return bool
	 */
	public function validate_local_payment_available( $currency, $billing_country ) {
		if ( $billing_country ) {
			$params = $this->get_required_parameters();

			return isset( $params[ $currency ] ) && in_array( $billing_country, $params[ $currency ] ) !== false;
		}

		return false;
	}

	public function output_display_items( $page = 'checkout', $data = array() ) {
		parent::output_display_items( $page, array(
			'source_args'      => $this->get_klarna_args(),
			'payment_sections' => $this->get_option( 'payment_categories' )
		) );
	}

	public function enqueue_checkout_scripts( $scripts ) {
		stripe_wc()->scripts()->enqueue_script( 'klarna', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array(), stripe_wc()->version(), true );
		parent::enqueue_checkout_scripts( $scripts );
	}

	public function get_local_payment_settings() {
		return wp_parse_args(
			array(
				'charge_type'        => array(
					'type'        => 'select',
					'title'       => __( 'Charge Type', 'woo-stripe-payment' ),
					'default'     => 'capture',
					'class'       => 'wc-enhanced-select',
					'options'     => array(
						'capture'   => __( 'Capture', 'woo-stripe-payment' ),
						'authorize' => __( 'Authorize', 'woo-stripe-payment' ),
					),
					'desc_tip'    => true,
					'description' => __( 'This option determines whether the customer\'s funds are captured immediately or authorized and can be captured at a later date.', 'woo-stripe-payment' ),
				),
				'payment_categories' => array(
					'title'       => __( 'Payment Categories', 'woo-stripe-payment' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'options'     => $this->get_payment_categories(),
					'default'     => array_keys( $this->get_payment_categories() ),
					'desc_tip'    => true,
					'description' => __(
						'These are the payment categories that will be displayed on the checkout page if they are supported. Note, depending on the customer\'s billing country, not all enabled options may show.',
						'woo-stripe-payment'
					),
				),
				'label_translation'  => array(
					'title'       => __( 'Use Stripe translation', 'woo-stripe-payment' ),
					'type'        => 'checkbox',
					'default'     => 'yes',
					'desc_tip'    => true,
					'description' => __( 'If enabled, the payment option labels will use the Stripe translated text.', 'woo-stripe-payment' )
				)
			),
			parent::get_local_payment_settings()
		);
	}

	public function get_update_source_args( $order ) {
		$args = $this->get_source_args( $order, true );
		unset( $args['type'], $args['currency'], $args['statement_descriptor'], $args['redirect'], $args['klarna']['product'], $args['klarna']['locale'], $args['klarna']['custom_payment_methods'] );

		return $args;
	}

	/**
	 * @return array
	 * @since 3.2.8
	 */
	public function get_klarna_args() {
		$customer = WC()->customer;
		$cart     = WC()->cart;
		$currency = get_woocommerce_currency();
		$args     = array(
			'type'     => $this->local_payment_type,
			'amount'   => wc_stripe_add_number_precision( $cart->total, $currency ),
			'currency' => $currency,
			//'statement_descriptor' => sprintf( __( 'Order %s', 'woo-stripe-payment' ), $order->get_order_number() ),
			'owner'    => array(
				'name'    => sprintf( '%s %s', $customer->get_billing_first_name(), $customer->get_billing_last_name() ),
				'email'   => $customer->get_billing_email(),
				'address' => array(
					'city'        => $customer->get_billing_city(),
					'country'     => $customer->get_billing_country(),
					'line1'       => $customer->get_billing_address_1(),
					'line2'       => $customer->get_billing_address_2(),
					'postal_code' => $customer->get_billing_postcode(),
					'state'       => $customer->get_billing_state(),
				)
			),
			'klarna'   => array(
				'product'          => 'payment',
				'purchase_country' => $customer->get_billing_country(),
				'first_name'       => $customer->get_billing_first_name(),
				'last_name'        => $customer->get_billing_last_name(),
			),
		);
		if ( 'US' === $customer->get_billing_country() ) {
			$args['klarna']['custom_payment_methods'] = 'payin4,installments';
		}
		$args['source_order'] = array();

		if ( ( $locale = get_locale() ) ) {
			$args['klarna']['locale'] = str_replace( '_', '-', $locale );
		}

		if ( $cart->needs_shipping() ) {
			$args['klarna']['shipping_first_name']       = $customer->get_shipping_first_name();
			$args['klarna']['shipping_last_name']        = $customer->get_shipping_last_name();
			$args['source_order']['shipping']['address'] = array(
				'city'        => $customer->get_shipping_city(),
				'country'     => $customer->get_shipping_country(),
				'line1'       => $customer->get_shipping_address_1(),
				'line2'       => $customer->get_shipping_address_2(),
				'postal_code' => $customer->get_shipping_postcode(),
				'state'       => $customer->get_shipping_state(),
			);
		}

		foreach ( $cart->get_cart_contents() as $item ) {
			/**
			 *
			 * @var WC_Order_Item_Product $item
			 */
			$args['source_order']['items'][] = array(
				'type'        => 'sku',
				'amount'      => wc_stripe_add_number_precision( $item['line_subtotal'], $currency ),
				'currency'    => $currency,
				'quantity'    => $item['quantity'],
				'description' => $item['data']->get_name(),
			);
		}
		// shipping
		if ( $cart->shipping_total ) {
			$args['source_order']['items'][] = array(
				'type'        => 'shipping',
				'amount'      => wc_stripe_add_number_precision( $cart->shipping_total, $currency ),
				'currency'    => $currency,
				'quantity'    => 1,
				'description' => __( 'Shipping', 'woo-stripe-payment' ),
			);
		}
		// discount
		if ( $cart->discount_cart ) {
			$args['source_order']['items'][] = array(
				'type'        => 'discount',
				'amount'      => - 1 * wc_stripe_add_number_precision( $cart->discount_cart, $currency ),
				'currency'    => $currency,
				'quantity'    => 1,
				'description' => __( 'Discount', 'woo-stripe-payment' ),
			);
		}
		// fees
		if ( 0 < $cart->fee_total ) {
			$args['source_order']['items'][] = array(
				'type'        => 'sku',
				'amount'      => wc_stripe_add_number_precision( $cart->fee_total, $currency ),
				'currency'    => $currency,
				'quantity'    => 1,
				'description' => __( 'Fee total', 'woo-stripe-payment' ),
			);
		}
		// tax
		if ( 0 < $cart->get_total_tax() ) {
			$args['source_order']['items'][] = array(
				'type'        => 'tax',
				'amount'      => wc_stripe_add_number_precision( $cart->get_total_tax(), $currency ),
				'description' => __( 'Tax', 'woo-stripe-payment' ),
				'quantity'    => 1,
				'currency'    => $currency,
			);
		}

		return $args;

	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see WC_Payment_Gateway_Stripe_Local_Payment::get_source_redirect_url()
	 */
	public function get_source_redirect_url( $source, $order ) {
		$klarna_categories                             = explode( ',', $source['klarna']['payment_method_categories'] );
		$klarna_categories                             = array_intersect( $klarna_categories, $this->get_option( 'payment_categories' ) );
		$source['klarna']['payment_method_categories'] = implode( $klarna_categories, ',' );
		$source['redirect']                            = add_query_arg( 'source', $order->get_meta( WC_Stripe_Constants::SOURCE_ID ), $this->get_local_payment_return_url( $order ) );

		return '#response=' . base64_encode( wp_json_encode( $source ) );
	}

	/**
	 *
	 * @return mixed
	 */
	public function get_payment_categories() {
		return apply_filters(
			'wc_stripe_klarna_payment_categries',
			array(
				'pay_now'       => __( 'Pay Now', 'woo-stripe-payment' ),
				'pay_later'     => __( 'Pay Later', 'woo-stripe-payment' ),
				'pay_over_time' => __( 'Pay Over Time', 'woo-stripe-payment' ),
			)
		);
	}

	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see WC_Payment_Gateway_Stripe_Local_Payment::get_payment_description()
	 */
	public function get_payment_description() {
		return '<p>' .
		       sprintf( __( 'Click %1$shere%2$s for Klarna test payment methods.', 'woo-stripe-payment' ), '<a target="_blank" href="https://stripe.com/docs/sources/klarna#testing-klarna-payments">', '</a>' ) .
		       '</p>' . parent::get_payment_description();
	}

	public function get_localized_params() {
		$params                             = parent::get_localized_params();
		$params['messages']['klarna_error'] = __( 'Your purchase is not approved.', 'woo-stripe-payment' );
		$params['klarna_loader']            = '<div class="wc-stripe-klarna-loader"><div></div><div></div><div></div></div>';
		$params['translate']                = $this->is_active( 'label_translation' );

		return $params;
	}
}
