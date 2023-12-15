<?php

if ( !defined( 'ABSPATH' ) )
  exit;

function Load_aqayepardakht_Gateway() {

  if ( class_exists( 'WC_Payment_Gateway' ) && !class_exists( 'WC_Gateway_aqayepardakht' ) && !function_exists( 'Woocommerce_Add_aqayepardakht_Gateway' ) ) {

    add_filter( 'woocommerce_payment_gateways', 'Woocommerce_Add_aqayepardakht_Gateway' );

    function Woocommerce_Add_aqayepardakht_Gateway( $methods ) {
      $methods[] = 'WC_Gateway_aqayepardakht';
      return $methods;
    }

    class WC_Gateway_aqayepardakht extends WC_Payment_Gateway {

      public function __construct() {


        $this->author = 'aqayepardakht.ir';


        $this->id = 'aqayepardakht';
        $this->method_title = __( 'آقای پرداخت', 'woocommerce' );
        $this->method_description = __( 'تنظیمات درگاه پرداخت آقای پرداخت برای افزونه فروشگاه ساز ووکامرس', 'woocommerce' );
        $this->icon = apply_filters( 'WC_aqayepardakht_logo', WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/assets/images/logo.png' );
        $this->has_fields = false;
        
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings[ 'title' ];
        $this->description = $this->settings[ 'description' ];

        $this->pin = $this->settings[ 'pin' ];
        $this->sandbox = $this->settings[ 'sandbox' ];

        $this->success_massage = $this->settings[ 'success_massage' ];
        $this->failed_massage = $this->settings[ 'failed_massage' ];

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
          add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        else
          add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id . '', array( $this, 'Send_to_aqayepardakht_Gateway_Aqaye_Pardakht' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ) . '', array( $this, 'Return_from_aqayepardakht_Gateway_Aqaye_Pardakht' ) );

      }


      public function admin_options() {
        $action = $this->author;
        do_action( 'WC_Gateway_Payment_Actions', $action );
        parent::admin_options();
      }

      public function init_form_fields() {
        $this->form_fields = apply_filters( 'WC_aqayepardakht_Config',
          array(

            'base_confing' => array(
              'title' => __( 'تنظیمات پایه ای', 'woocommerce' ),
              'type' => 'title',
              'description' => '',
            ),
            'enabled' => array(
              'title' => __( 'فعالسازی/غیرفعالسازی', 'woocommerce' ),
              'type' => 'checkbox',
              'label' => __( 'فعالسازی درگاه آقای پرداخت', 'woocommerce' ),
              'description' => __( 'برای فعالسازی درگاه پرداخت آقای پرداخت باید چک باکس را تیک بزنید', 'woocommerce' ),
              'default' => 'yes',
              'desc_tip' => true,
            ),
            'title' => array(
              'title' => __( 'عنوان درگاه', 'woocommerce' ),
              'type' => 'text',
              'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce' ),
              'default' => __( 'آقای پرداخت', 'woocommerce' ),
              'desc_tip' => true,
            ),
            'description' => array(
              'title' => __( 'توضیحات درگاه', 'woocommerce' ),
              'type' => 'text',
              'desc_tip' => true,
              'description' => __( 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce' ),
              'default' => __( 'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه آقای پرداخت', 'woocommerce' )
            ),
            'account_confing' => array(
              'title' => __( 'تنظیمات حساب آقای پرداخت', 'woocommerce' ),
              'type' => 'title',
              'description' => '',
            ),
            'pin' => array(
              'title' => __( 'پین', 'woocommerce' ),
              'type' => 'text',
              'description' => __( 'پین درگاه آقای پرداخت', 'woocommerce' ),
              'default' => '',
              'desc_tip' => true
            ),
            'sandbox' => array(
              'title' => __( 'فعالسازی حالت آزمایشی', 'woocommerce' ),
              'type' => 'checkbox',
              'label' => __( 'فعالسازی حالت آزمایشی آقای پرداخت', 'woocommerce' ),
              'description' => __( 'برای فعال سازی حالت آزمایشی آقای پرداخت چک باکس را تیک بزنید .', 'woocommerce' ),
              'default' => 'no',
              'desc_tip' => true,
            ),
            'payment_confing' => array(
              'title' => __( 'تنظیمات عملیات پرداخت', 'woocommerce' ),
              'type' => 'title',
              'description' => '',
            ),
            'success_massage' => array(
              'title' => __( 'پیام پرداخت موفق', 'woocommerce' ),
              'type' => 'textarea',
              'description' => __( 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (کد تراکنش آقای پرداخت) استفاده نمایید .', 'woocommerce' ),
              'default' => __( 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce' ),
            ),
            'failed_massage' => array(
              'title' => __( 'پیام پرداخت ناموفق', 'woocommerce' ),
              'type' => 'textarea',
              'description' => __( 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت آقای پرداخت ارسال میگردد .', 'woocommerce' ),
              'default' => __( 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce' ),
            )
          )
        );
      }

      public function process_payment( $order_id ) {
        $order = new WC_Order( $order_id );
        return array(
          'result' => 'success',
          'redirect' => $order->get_checkout_payment_url( true )
        );
      }

      public function Send_to_aqayepardakht_Gateway_Aqaye_Pardakht( $order_id ) {
        ob_start();
        global $woocommerce;
        $woocommerce->session->order_id_aqayepardakht = $order_id;
        $order = new WC_Order( $order_id );
        $currency = $order->get_currency();
        $currency = apply_filters( 'WC_aqayepardakht_Currency', $currency, $order_id );
        $action = $this->author;
        do_action( 'WC_Gateway_Payment_Actions', $action );
        $form = '<form action="" method="POST" class="aqayepardakht-checkout-form" id="aqayepardakht-checkout-form">
						<input type="submit" name="aqayepardakht_submit" class="button alt" id="aqayepardakht-payment-button" value="' . __( 'پرداخت', 'woocommerce' ) . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __( 'بازگشت', 'woocommerce' ) . '</a>
					 </form><br/>';
        $form = apply_filters( 'WC_aqayepardakht_Form', $form, $order_id, $woocommerce );

        do_action( 'WC_aqayepardakht_Gateway_Before_Form', $order_id, $woocommerce );
        echo $form;
        do_action( 'WC_aqayepardakht_Gateway_After_Form', $order_id, $woocommerce );

        $action = $this->author;
        do_action( 'WC_Gateway_Payment_Actions', $action );
        if ( !extension_loaded( 'curl' ) ) {
          $order->add_order_note( __( 'تابع cURL روی هاست شما فعال نیست .', 'woocommerce' ) );
          wc_add_notice( __( 'تابع cURL روی هاست فروشنده فعال نیست .', 'woocommerce' ), 'error' );
          return false;
        }

        $Amount = intval( $order->get_total() );

        $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency );
        if ( strtolower( $currency ) == strtolower( 'IRT' ) || strtolower( $currency ) == strtolower( 'TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran_TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian_TOMAN' ) || strtolower( $currency ) == strtolower( 'تومان' ) || strtolower( $currency ) == strtolower( 'تومان ایران' ) )
          $Amount = $Amount;
        else if ( strtolower( $currency ) == strtolower( 'IRHT' ) )
          $Amount = $Amount * 1000;
        else if ( strtolower( $currency ) == strtolower( 'IRHR' ) )
          $Amount = $Amount * 10000;
        else if ( strtolower( $currency ) == strtolower( 'IRR' ) )
          $Amount = $Amount / 10;

        $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency );
        $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_irr', $Amount, $currency );
        $Amount = apply_filters( 'woocommerce_order_amount_total_aqayepardakht_gateway', $Amount, $currency );
        $products = array();
        $order_items = $order->get_items();
        foreach ( $order_items as $product ) {
          $products[] = $product[ 'name' ] . ' (' . $product[ 'qty' ] . ') ';
        }
        $products = implode( ' - ', $products );
        $Description = 'خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' | محصولات : ' . $products;
        $Tell = intval( $order->get_billing_phone() );
        $Email = $order->get_billing_email();

        $Description = apply_filters( 'WC_aqayepardakht_Description', $Description, $order_id );
        do_action( 'WC_aqayepardakht_Gateway_Payment', $order_id, $Description );

        $CallbackURL = add_query_arg( 'wc_order', $order_id, WC()->api_request_url( 'WC_Gateway_aqayepardakht' ) );

        $Sandbox = $this->sandbox;

        if ( $Sandbox == "yes" || $Sandbox == "1" || $Sandbox == 1 ) {

          $url = 'https://panel.aqayepardakht.ir/api/v2/create';
          $send = 'https://panel.aqayepardakht.ir/startpay/sandbox/%s';
          $apiID = 'sandbox';

        } else {

          $url = 'https://panel.aqayepardakht.ir/api/v2/create';
          $send = 'https://panel.aqayepardakht.ir/startpay/%s';
          $apiID = $this->pin;

        }

        $data = [
          'pin' => $apiID,
          'amount' => $Amount,
          'callback' => $CallbackURL,
          'invoice_id' => $order_id,
          'mobile' => $Tell,
          'email' => $Email,
          'description' => $Description
        ];

        $data = json_encode( $data );
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLINFO_HEADER_OUT, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'Content-Length: ' . strlen( $data ) ) );
        $result = curl_exec( $ch );
        curl_close( $ch );
        $result = json_decode( $result );


        if ( $result === false ) {
          echo "cURL Error";
        } else {
          if ( $result->status == "success" ) {
            wp_redirect( sprintf( $send, $result->transid ) );
            exit;
          } else {
            $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result->code;
            $Fault = '';
          }
        }

        if ( !empty( $Message ) && $Message ) {

          $Note = sprintf( __( 'خطا در هنگام ارسال به بانک : %s', 'woocommerce' ), $Message );
          $Note = apply_filters( 'WC_aqayepardakht_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault );
          $order->add_order_note( $Note );


          $Notice = sprintf( __( 'در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce' ), $Message );
          $Notice = apply_filters( 'WC_aqayepardakht_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault );
          if ( $Notice )
            wc_add_notice( $Notice, 'error' );

          do_action( 'WC_aqayepardakht_Send_to_Gateway_Failed', $order_id, $Fault );
        }
      }

      public function Return_from_aqayepardakht_Gateway_Aqaye_Pardakht() {

        $status = sanitize_text_field( $_POST[ 'status' ] );
        $transid = sanitize_text_field( $_POST[ 'transid' ] );
        $tracking_number = sanitize_text_field( $_POST[ 'tracking_number' ] );
        $card_number = sanitize_text_field( $_POST[ 'cardnumber' ] );

        global $woocommerce;
        $action = $this->author;
        do_action( 'WC_Gateway_Payment_Actions', $action );

        if ( isset( $_GET[ 'wc_order' ] ) )
          $order_id = sanitize_text_field( $_GET[ 'wc_order' ] );
        else
          $order_id = $woocommerce->session->order_id_aqayepardakht;
        unset( $woocommerce->session->order_id_aqayepardakht );

        if ( $order_id ) {

          $order = new WC_Order( $order_id );
          $currency = $order->get_currency();
          $currency = apply_filters( 'WC_aqayepardakht_Currency', $currency, $order_id );


          $Amount = ( int )$order->get_total();
          $Amount = apply_filters( 'woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency );
          if ( strtolower( $currency ) == strtolower( 'IRT' ) || strtolower( $currency ) == strtolower( 'TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian-TOMAN' ) || strtolower( $currency ) == strtolower( 'Iran_TOMAN' ) || strtolower( $currency ) == strtolower( 'Iranian_TOMAN' ) || strtolower( $currency ) == strtolower( 'تومان' ) || strtolower( $currency ) == strtolower( 'تومان ایران' ) )
            $Amount = $Amount;
          else if ( strtolower( $currency ) == strtolower( 'IRHT' ) )
            $Amount = $Amount * 1000;
          else if ( strtolower( $currency ) == strtolower( 'IRHR' ) )
            $Amount = $Amount * 10000;
          else if ( strtolower( $currency ) == strtolower( 'IRR' ) )
            $Amount = $Amount / 10;

          $Sandbox = $this->sandbox;

          if ( $Sandbox == "yes" || $Sandbox == "1" || $Sandbox == 1 ) {
            $url = 'https://panel.aqayepardakht.ir/api/v2/verify';
            $apiID = 'sandbox';
          } else {
            $url = 'https://panel.aqayepardakht.ir/api/v2/verify';
            $apiID = $this->pin;
          }

          if ( $order->status != 'completed' ) {

            if ( $status == '1' ) {

              $data = [
                'pin' => $apiID,
                'amount' => $Amount,
                'transid' => $transid
              ];

              $data = json_encode( $data );
              $ch = curl_init( $url );
              curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
              curl_setopt( $ch, CURLINFO_HEADER_OUT, true );
              curl_setopt( $ch, CURLOPT_POST, true );
              curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

              curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen( $data ) ) );
              $result = curl_exec( $ch );
              curl_close( $ch );
              $result = json_decode( $result );

              if ( $result->code == "1" ) {
                $Status = 'completed';
                $Transaction_ID = $transid;
                $verify_cardnum = $card_number;
                $verify_tracking = $tracking_number;
                $Fault = '';
                $Message = '';
              } elseif ( $result->code == "2" ) {

                $Message = 'این تراکنش قبلا تایید شده است';
                $Notice = wpautop( wptexturize( $Message ) );
                wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
                exit;
              } else {
                $Status = 'failed';
                $Fault = $result->code;
                $Message = 'تراکنش ناموفق بود';
              }
            } else {
              $Status = 'failed';
              $Fault = '';
              $Message = 'تراکنش انجام نشد .';
            }

            if ( $Status == 'completed' && isset( $Transaction_ID ) && $Transaction_ID != 0 ) {
              $action = $this->author;
              do_action( 'WC_Gateway_Payment_Actions', $action );
              update_post_meta( $order_id, '_transaction_id', $Transaction_ID );
              update_post_meta( $order_id, '_card_number', $verify_cardnum );
              update_post_meta( $order_id, '_tracking_number', $verify_tracking );

              $order->payment_complete( $Transaction_ID );
              $woocommerce->cart->empty_cart();

              $Note = sprintf( __( 'پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce' ), $Transaction_ID );
              $Note .= sprintf( __( '<br/> شماره کارت پرداخت کننده : %s', 'woocommerce' ), $verify_cardnum );
              $Note .= sprintf( __( '<br/> شماره تراکنش : %s', 'woocommerce' ), $verify_tracking );
              $Note = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID, $verify_cardnum, $verify_tracking );
              if ( $Note )
                $order->add_order_note( $Note, 1 );


              $Notice = wpautop( wptexturize( $this->success_massage ) );

              $Notice = str_replace( "{transaction_id}", $Transaction_ID, $Notice );

              $Notice = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID );
              if ( $Notice )
                wc_add_notice( $Notice, 'success' );

              do_action( 'WC_aqayepardakht_Return_from_Gateway_Success', $order_id, $Transaction_ID );

              wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
              exit;
            } else {

              $action = $this->author;
              do_action( 'WC_Gateway_Payment_Actions', $action );
              $tr_id = ( $Transaction_ID && $Transaction_ID != 0 ) ? ( '<br/>کد تراکنش : ' . $Transaction_ID ) : '';

              $Note = sprintf( __( 'خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce' ), $Message, $tr_id );

              $Note = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault );
              if ( $Note )
                $order->add_order_note( $Note, 1 );

              $Notice = wpautop( wptexturize( $this->failed_massage ) );

              $Notice = str_replace( "{transaction_id}", $Transaction_ID, $Notice );

              $Notice = str_replace( "{fault}", $Message, $Notice );
              $Notice = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault );
              if ( $Notice )
                wc_add_notice( $Notice, 'error' );

              do_action( 'WC_aqayepardakht_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault );

              wp_redirect( $woocommerce->cart->get_checkout_url() );
              exit;
            }
          } else {

            $Transaction_ID = get_post_meta( $order_id, '_transaction_id', true );

            $Notice = wpautop( wptexturize( $this->success_massage ) );

            $Notice = str_replace( "{transaction_id}", $Transaction_ID, $Notice );

            $Notice = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID );
            if ( $Notice )
              wc_add_notice( $Notice, 'success' );


            do_action( 'WC_aqayepardakht_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID );

            wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
            exit;
          }
        } else {

          $Fault = __( 'شماره سفارش وجود ندارد .', 'woocommerce' );
          $Notice = wpautop( wptexturize( $this->failed_massage ) );
          $Notice = str_replace( "{fault}", $Fault, $Notice );
          $Notice = apply_filters( 'WC_aqayepardakht_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault );
          if ( $Notice )
            wc_add_notice( $Notice, 'error' );

          do_action( 'WC_aqayepardakht_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault );

          wp_redirect( $woocommerce->cart->get_checkout_url() );
          exit;
        }
      }
    }
  }
}
add_action( 'plugins_loaded', 'Load_aqayepardakht_Gateway', 0 );