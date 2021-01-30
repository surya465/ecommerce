<?php
/*
Plugin Name: WooCommerce Paytm Payment Gateway  
Version: 1.1.3 
Description: This plugin is used to integrate Paytm payment gateway with Woocommerce.
Author: FTI Technologies
Author URI: https://www.freelancetoindia.com/
*/

define( 'PAYTM_PLUGIN_PATH', plugin_dir_path( __FILE__ ));
require_once(PAYTM_PLUGIN_PATH . 'include/function.php');
add_action('plugins_loaded', 'WC_paytmpay_init');

function WC_paytmpay_init(){

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    if(isset($_GET['msg'])){
        add_action('the_content', 'PaytmPayShowMsg');
    }
   
    function PaytmPayShowMsg($content){
        return '<div class="box '.htmlentities($_GET['type']).'-box">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }
    /**
     * WC_Gateway_paytmpay class
     */
    class WC_Gateway_paytmpay extends WC_Payment_Gateway {
        
    	protected $msg = array();
            
        /**
    	* Constructor for the gateway.
    	*/

        public function __construct(){ 
            $this->id = 'paytmpay';
            $this->has_fields = false;
            $this->order_button_text  = __('Pay via paytm', 'woocommerce' );
            $this->method_title = __('Pay With Paytm');
            $this->method_description = __('Your Payment pay with paytm.');
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.gif';
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchantID = $this->settings['merchantID'];
            $this->merchant_key = $this->settings['merchant_key'];            
            $this->industry_type_id = $this->settings['industry_type_id'];
            $this->paytm_channel_id = $this->settings['paytm_channel_id'];
            $this->website = $this->settings['website'];
            $this->mode = $this->settings['mode'];
            
            $this->msg['message'] = "";
            $this->msg['class'] = "";	
			
            add_action('woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'capture_paytm_response' ) );
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page')); 
        }
        

    	function init_form_fields(){   
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Paytm Payment Gateway.'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default' => __('pay with paytm')
                ),
                'description' => array(
                    'title' => __('Description'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default' => __('The best payment gateway provider in India for e-payment through credit card, debit card & netbanking.')
                ),
                'merchantID' => array(
                    'title' => __('Merchant ID'),
                    'type' => 'text',
                    'description' => __('This id(USER ID) available at "Generate Secret Key" of "Integration -> Card payments integration at paytm."')
                ),
                'merchant_key' => array(
                    'title' => __('Merchant Key'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paytm')
                ),
                'industry_type_id' => array(
                    'title' => __('Industry Type ID'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paytm')
                ),
                'paytm_channel_id' => array(
                    'title' => __('Channel ID'),
                    'type' => 'text',
                    'description' =>  __('WEB - for desktop websites / WAP - for mobile websites')),                
                'website' => array(
                    'title' => __('Website'),
                    'type' => 'text',
                    'description' =>  __('Given to Merchant by paytm'),
    			),
                'mode' => array(
                    'title' => __('Enable Test Mode'),
                    'type' => 'select',
                    'options'  => array('yes'=>'yes','no'=>'no'),
                    'label' => __('Select to enable Sandbox Enviroment'),
                    'default' => 'yes'                    
                )
            );
        }
        
        /**
         *  Payment form on checkout page.
         **/
        function payment_fields(){
            $description = $this->get_description();
            if ( $description ) {
                echo wpautop( wptexturize( trim( $description ) ) );
			}
        }
        
	    /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('Paytm Pay Payment Gateway').'</h3>';
            echo '<p>'.__('India online payment solutions for all your transactions by paytm').'</p>';
            echo '<table class="form-table">';
                $this->generate_settings_html();
            echo '</table>';
        }
        
        /**
    	 * Return the gateway's description.
    	 *
    	 * @return string
    	 */
    	public function get_description(){
    		return apply_filters( 'woocommerce_gateway_description', $this->description, $this->id );
    	}
        
        
        /**
         * Receipt Page
         **/
        function receipt_page($order){
            echo '<p><h4 style="text-align: center;">'.__('We’re processing your order. It may take more few seconds!<br />Please do not refresh or close this session.').'</h4></p>';
            echo $this->generate_paytm_form($order);
        }
        
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                $order = new WC_Order($order_id);
            } else {
                $order = new woocommerce_order($order_id);
            }
            return array('result' => 'success', 'redirect' => add_query_arg('order-pay', 
                $order->get_id(), add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url( true )))
            );
        }
		
	    /**
         * Check for valid paytm server callback // response processing //
        **/
        function capture_paytm_response(){	
		    global $woocommerce;		
            //echo '<pre>'; print_r($_POST); die('WooCommerce');	
			if(isset($_POST['ORDERID']) && isset($_POST['RESPCODE'])){
			    $order_sent = sanitize_text_field($_POST['ORDERID']);
			    $responseDescription = sanitize_text_field($_POST['RESPMSG']);
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					$order = new WC_Order($_POST['ORDERID']); 
				} else {
					$order = new woocommerce_order($_POST['ORDERID']); 
				}
				
				$this->msg['class'] = 'error';
				$this->msg['message'] = "Transaction has been Failed For Reason  : " . $responseDescription;
				if($_POST['RESPCODE'] == 01){ 
					
					$order_amount = $order->get_total();
					
					if((sanitize_text_field($_POST['TXNAMOUNT']) == $order_amount)){
						$order_sent			      = sanitize_text_field($_POST['ORDERID']);
						$res_code				  = sanitize_text_field($_POST['RESPCODE']);
						$responseDescription      = sanitize_text_field($_POST['RESPMSG']);
						$order_amount             = sanitize_text_field($_POST['TXNAMOUNT']);
						
						if(wppg_verifychecksum($_POST, $this->merchant_key, sanitize_text_field($_POST['CHECKSUMHASH'])) === "TRUE"){ 
							
							$requestParamList = array("MID" => $this->merchantID , "ORDERID" => $order_sent);
							$StatusCheckSum = wppg_getChecksumFromArray($requestParamList, $this->merchant_key);
							$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
							
							if($this->mode=='yes'){
								$check_status_url = 'https://securegw-stage.paytm.in/merchant-status/getTxnStatus';
							} else {
								$check_status_url = 'https://securegw.paytm.in/merchant-status/getTxnStatus';
							}

							$responseParamList = wppg_get_paytm_response_url($check_status_url, $requestParamList);
                                                        
							if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT'] == $order_amount)
							{
								if ( $order->has_status( 'pending' ) )
								{
									$this->msg['message'] = "Thank you for your order. Your transaction has been successful.";
									$this->msg['class'] = 'success';
									
									$order->payment_complete();
                                    $order->update_status('completed');
                                    $order->add_order_note($this->msg['message']);
                                    $woocommerce->cart->empty_cart();
								}
							} else {
								$this->msg['class'] = 'error';
								$this->msg['message'] = "It seems some issue in server to server communication. Kindly connect with administrator.";
								$order->update_status('failed');
								$order->add_order_note($this->msg['message']);
							}
						} else {
							$this->msg['class'] = 'error';
							$this->msg['message'] = "Severe Error Occur.";
							$order->update_status('failed');
							$order->add_order_note($this->msg['message']);
						}
					} else {
						$this->msg['class'] = 'error';
						$this->msg['message'] = "Order Mismatch Occur.";
						$order->update_status('failed');
						$order->add_order_note($this->msg['message']);
					}
				} else {
					$order->update_status('failed');
					$order->add_order_note('Failed');
					$order->add_order_note($this->msg['message']);
				}
				
                add_action('the_content', array(&$this, 'PaytmPayShowMsg'));
				
                $redirect_url = $order->get_checkout_order_received_url();
                $redirect_url = add_query_arg( array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url );
                wp_redirect( $redirect_url );
                exit;		
			} 
		}
		
		
	/**
         * Generate paytm button link
         **/
        public function generate_paytm_form($order_id){
            global $woocommerce;
            $txnDate = date('Y-m-d');			
            $milliseconds = (int) (1000 * (strtotime(date('Y-m-d'))));

            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                $order = new WC_Order($order_id);
            } else {
                $order = new woocommerce_order($order_id);
            }
           
            $redirect_url = $this->get_return_url( $order );
            
            $a = strstr($redirect_url,"?");
            if($a){ 
                $redirect_url .= "&wc-api=WC_Gateway_paytmpay";
            } else {
                $redirect_url .= "?wc-api=WC_Gateway_paytmpay";
            }
            
            //////////////
            $order_id = $order->get_id();
            $Order_Total =	$order->get_total();
            
            $email = $order->get_billing_email();
            $mobile_no = $order->get_billing_phone();

            $post_params = Array(
                "MID" => $this->merchantID,
                "ORDER_ID" => $order_id,
                "CUST_ID" => $email,
                "TXN_AMOUNT" => $Order_Total,
                "CHANNEL_ID" => $this->paytm_channel_id,
                "INDUSTRY_TYPE_ID" => $this->industry_type_id,
                "WEBSITE" => $this->website,
                "EMAIL" => $email,
                "MOBILE_NO" => $mobile_no
            );
           
            $post_params["CALLBACK_URL"] = get_site_url() . '/?page_id=7&wc-api=WC_Gateway_paytmpay';
            
            $checksum = wppg_getChecksumFromArray($post_params, $this->merchant_key);
			
            $paytm_args_array = array();
            $paytm_args_array[] = "<input type='hidden' name='MID' value='".  $this->merchantID ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='ORDER_ID' value='". $order_id ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='WEBSITE' value='". $this->website ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='INDUSTRY_TYPE_ID' value='". $this->industry_type_id ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='CHANNEL_ID' value='". $this->paytm_channel_id ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='TXN_AMOUNT' value='". $Order_Total ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='CUST_ID' value='". $email ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='EMAIL' value='". $email ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='MOBILE_NO' value='". $mobile_no ."'/>";
			
            $call = get_site_url() . '/?page_id=7&wc-api=WC_Gateway_paytmpay';
            $paytm_args_array[] = "<input type='hidden' name='CALLBACK_URL' value='" . $call . "'/>";
                
			$paytm_args_array[] = "<input type='hidden' name='txnDate' value='". date('Y-m-d H:i:s') ."'/>";
            $paytm_args_array[] = "<input type='hidden' name='CHECKSUMHASH' value='". $checksum ."'/>";
            
            if($this->mode=='yes'){
                $action_url = 'https://securegw-stage.paytm.in/theia/processTransaction';
            } else {
                $action_url = 'https://securegw.paytm.in/theia/processTransaction';
            }

            return '<form action="'.$action_url.'" method="post" id="paytm_payment_form" name="gopaytm">
                ' . implode('', $paytm_args_array) . '
                
                <script type="text/javascript">
					document.gopaytm.submit();
                </script>
            </form>';
        }
    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_paytm_gateway($methods) {
        $methods[] = 'WC_Gateway_paytmpay'; 
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_paytm_gateway' );
}

?>