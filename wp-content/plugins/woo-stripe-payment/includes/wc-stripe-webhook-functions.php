<?php
defined( 'ABSPATH' ) || exit();

/**
 * Processes the charge via webhooks for local payment methods like P24, EPS, etc.
 *
 * @param \Stripe\Source $source
 * @param WP_REST_Request $request
 *
 * @since 3.0.0
 * @package Stripe/Functions
 */
function wc_stripe_process_source_chargeable( $source, $request ) {
	/*
	 *  There is no order ID in the metadata which means this source was created
	 *  client side using stripe.createsource()
	 *  It will be processed via the checkout page.
	 */
	if ( $source->flow === 'none' && ! $source->metadata['order_id'] ) {
		return;
	}
	$order = wc_get_order( wc_stripe_filter_order_id( $source->metadata['order_id'], $source ) );
	if ( ! $order ) {
		wc_stripe_log_error( sprintf( 'Could not create a charge for source %s. No order ID was found in your WordPress database.', $source->id ) );

		return;
	}

	/**
	 *
	 * @var WC_Payment_Gateway_Stripe $payment_method
	 */
	$payment_method = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ];

	// if the order has a transaction ID, then a charge has already been created.
	if ( $payment_method->has_order_lock( $order ) || ( $transaction_id = $order->get_transaction_id() ) ) {
		wc_stripe_log_info( sprintf( 'source.chargeable event received. Charge has already been created for order %s. Event exited.', $order->get_id() ) );

		return;
	}
	$payment_method->set_order_lock( $order );
	$payment_method->set_new_source_token( $source->id );
	$result = $payment_method->payment_object->process_payment( $order );

	if ( ! is_wp_error( $result ) && $result->complete_payment ) {
		$payment_method->payment_object->payment_complete( $order, $result->charge );
	}
}

/**
 * When the charge has succeeded, the order should be completed.
 *
 * @param \Stripe\Charge $charge
 * @param WP_REST_Request $request
 *
 * @since 3.0.5
 * @package Stripe/Functions
 */
function wc_stripe_process_charge_succeeded( $charge, $request ) {
	// charges that belong to a payment intent can be  skipped
	// because the payment_intent.succeeded event will be called.
	if ( $charge->payment_intent ) {
		return;
	}
	$order = wc_get_order( wc_stripe_filter_order_id( $charge->metadata['order_id'], $charge ) );
	if ( ! $order ) {
		wc_stripe_log_error( sprintf( 'Could not complete payment for charge %s. No order ID %s was found in your WordPress database.', $charge->id, $charge->metadata['order_id'] ) );

		return;
	}

	/**
	 *
	 * @var WC_Payment_Gateway_Stripe $payment_method
	 */
	$payment_method = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ];
	/**
	 * Only process for local payment methods. We make sure the payment method is asynchronous
	 * because synchronous payments are handled via the source.chargeable event which processes the payment.
	 * This event is relevant for payment methods that receive a charge.succeeded event at some arbitrary amount of time
	 * after the source is chargeable.
	 */
	if ( $payment_method instanceof WC_Payment_Gateway_Stripe_Local_Payment && ! $payment_method->synchronous ) {

		// If the order's charge status is not equal to charge status from Stripe, then complete_payment.
		if ( $order->get_meta( WC_Stripe_Constants::CHARGE_STATUS ) != $charge->status ) {
			// want to prevent plugin from processing capture_charge since charge has already been captured.
			remove_action( 'woocommerce_order_status_completed', 'wc_stripe_order_status_completed' );

			// call payment complete so shipping, emails, etc are triggered.
			$payment_method->payment_object->payment_complete( $order, $charge );
			$order->add_order_note( __( 'Charge.succeeded webhook received. Payment has been completed.', 'woo-stripe-payment' ) );
		}
	}
}

/**
 *
 * @param \Stripe\PaymentIntent $intent
 * @param WP_REST_Request $request
 *
 * @since 3.1.0
 * @package Stripe/Functions
 */
function wc_stripe_process_payment_intent_succeeded( $intent, $request ) {
	$order = wc_get_order( wc_stripe_filter_order_id( $intent->metadata['order_id'], $intent ) );
	if ( ! $order ) {
		wc_stripe_log_error( sprintf( 'Could not complete payment for payment_intent %s. No order ID was found in your WordPress database.', $intent->id ) );

		return;
	}
	$payment_method = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ];

	if ( $payment_method instanceof WC_Payment_Gateway_Stripe_Local_Payment ) {
		/**
		 * Delay the event by one second to allow the redirect handler to process
		 * the payment.
		 */
		sleep( 1 );

		if ( $payment_method->has_order_lock( $order ) || ( $transaction_id = $order->get_transaction_id() ) ) {
			wc_stripe_log_info( sprintf( 'payment_intent.succeeded event received. Intent has been completed for order %s. Event exited.', $order->get_id() ) );

			return;
		}

		$payment_method->set_order_lock( $order );
		$result = $payment_method->payment_object->process_payment( $order );

		if ( ! is_wp_error( $result ) && $result->complete_payment ) {
			$payment_method->payment_object->payment_complete( $order, $result->charge );
			$order->add_order_note( __( 'payment_intent.succeeded webhook received. Payment has been completed.', 'woo-stripe-payment' ) );
		}
	}
}

/**
 *
 * @param \Stripe\Charge $charge
 * @param WP_REST_Request $request
 *
 * @since 3.1.1
 * @package Stripe/Functions
 */
function wc_stripe_process_charge_failed( $charge, $request ) {
	$order = wc_get_order( wc_stripe_filter_order_id( $charge->metadata['order_id'], $charge ) );

	if ( $order ) {
		$payment_methods = WC()->payment_gateways()->payment_gateways();
		if ( isset( $payment_methods[ $order->get_payment_method() ] ) ) {
			/**
			 *
			 * @var WC_Payment_Gateway_Stripe $payment_method
			 */
			$payment_method = $payment_methods[ $order->get_payment_method() ];
			// only update order status if this is an asynchronous payment method,
			// and there is no completed date on the order. If there is a complete date it
			// means payment_complete was called on the order at some point
			if ( ! $payment_method->synchronous && ! $order->get_date_completed() ) {
				$order->update_status( 'failed', $charge->failure_message );
			}
		}
	}
}

/**
 * Function that processes the charge.refund webhook. If the refund is created in the Stripe dashboard, a
 * refund will be created in the WC system to keep WC and Stripe in sync.
 *
 * @param \Stripe\Charge $charge
 *
 * @since 3.2.15
 */
function wc_stripe_process_create_refund( $charge ) {
	$mode  = $charge->livemode ? 'live' : 'test';
	$order = null;
	// get the order ID from the charge
	if ( isset( $charge->metadata['order_id'] ) ) {
		$order = wc_get_order( absint( $charge->metadata['order_id'] ) );
	} else {
		// charge didn't have order ID for whatever reason, so get order from charge ID
		$order = wc_stripe_get_order_from_transaction( $charge->id );
	}
	try {
		if ( ! $order ) {
			throw new Exception( sprintf( 'Could not match order with charge %s.', $charge->id ) );
		}
		// get the list of refunds and loop through them. Find the refund that doesn't have the required metadata attributes.
		foreach ( $charge->refunds as $refund ) {
			/**
			 * @var \Stripe\Refund $refund
			 */
			// refund was not created via WC
			if ( ! isset( $refund->metadata['order_id'], $refund->metadata['created_via'] ) ) {
				$args = array(
					'amount'         => wc_stripe_remove_number_precision( $refund->amount, $order->get_currency() ),
					'order_id'       => $order->get_id(),
					'reason'         => $refund->reason,
					'refund_payment' => false
				);
				// if the order has been fully refunded, items should be re-stocked
				if ( $order->get_total() == ( $args['amount'] + $order->get_total_refunded() ) ) {
					$args['restock_items'] = true;
					$line_items            = array();
					foreach ( $order->get_items() as $item_id => $item ) {
						$line_items[ $item_id ] = array(
							'qty' => $item->get_quantity()
						);
					}
					$args['line_items'] = $line_items;
				}
				// create the refund
				$result = wc_create_refund( $args );

				// Update the refund in Stripe with metadata
				if ( ! is_wp_error( $result ) ) {
					$order->add_order_note( sprintf( __( 'Order refunded in Stripe. Amount: %s', 'woo-stripe-payment' ), $result->get_formatted_refund_amount() ) );
					WC_Stripe_Gateway::load( $mode )->refunds->update( $refund->id, array(
						'metadata' => array(
							'order_id'    => $order->get_id(),
							'created_via' => 'stripe_dashboard'
						)
					) );
				} else {
					throw new Exception( $result->get_error_message() );
				}
			}
		}
	} catch ( Exception $e ) {
		wc_stripe_log_error( sprintf( 'Error processing refund webhook. Error: %s', $e->getMessage() ) );
	}
}
