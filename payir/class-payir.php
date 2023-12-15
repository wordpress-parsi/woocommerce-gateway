<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'Woocommerce_PayIR', 0 );
function Woocommerce_PayIR() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_PayIR' ) ) {
		return;
	}

	/**
	 * Gateway class
	 */
	class WC_PayIR extends WC_Payment_Gateway {

		protected $order_id = 0;

		public function __construct() {
			$this->id                 = 'WC_PayIR';
			$this->method_title       = __( 'درگاه پی', 'woocommerce' );
			$this->method_description = __( 'تنظیمات درگاه پی برای ووکامرس', 'woocommerce' );
			$this->icon               = trailingslashit( WP_PLUGIN_URL ) . plugin_basename( dirname( __FILE__ ) ) . '/assets/logo.svg';
			$this->has_fields         = false;

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->settings['title'];
			$this->description = $this->settings['description'];

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
					$this,
					'process_admin_options'
				] );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', [ $this, 'process_admin_options' ] );
			}

			add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'process_payment_request' ] );
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), [
				$this,
				'process_payment_verify'
			] );
		}

		public function init_form_fields() {

			$shortcodes = [];
			foreach ( $this->fields_shortcodes() as $shortcode => $title ) {
				$shortcode    = '{' . trim( $shortcode, '\{\}' ) . '}';
				$shortcodes[] = "$shortcode:$title";
			}
			$shortcodes = '<br>' . implode( ' - ', $shortcodes );

			$this->form_fields = apply_filters( 'WC_PayIR_Config', [
				'enabled'           => [
					'title'       => __( 'فعالسازی/غیرفعالسازی', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'فعالسازی درگاه پی', 'woocommerce' ),
					'description' => __( 'برای فعالسازی درگاه پرداخت Pay.ir چک باکس را تیک بزنید', 'woocommerce' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				],
				'title'             => [
					'title'       => __( 'عنوان درگاه', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'عنوان درگاه که در طی خرید به مشتری نمایش داده می شود', 'woocommerce' ),
					'default'     => __( 'درگاه پی', 'woocommerce' ),
					'desc_tip'    => true,
				],
				'description'       => [
					'title'       => __( 'توضیحات درگاه', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'توضیحاتی که در زمان انتخاب درگاه نمایش داده خواهد شد', 'woocommerce' ),
					'default'     => __( 'پرداخت امن به وسیله کلیه کارت های عضو شبکه شتاب از طریق درگاه پی', 'woocommerce' ),
					'desc_tip'    => true,
				],
				'api'               => [
					'title'       => __( 'کلید API', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'کلید API دریاقتی از درگاه پی', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				],
				'sandbox'           => [
					'title'       => __( 'فعالسازی حالت آزمایشی', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'فعالسازی حالت آزمایشی درگاه پی', 'woocommerce' ),
					'description' => __( 'برای فعال سازی حالت آزمایشی Pay.ir چک باکس را تیک بزنید', 'woocommerce' ),
					'default'     => 'no',
					'desc_tip'    => true,
				],
				'direct_redirect'   => [
					'title'       => 'هدایت مستقیم به درگاه',
					'type'        => 'checkbox',
					'label'       => 'در صورتی که قصد دارید کاربر مستقیما به درگاه هدایت شود و در صفحه پیش فاکتور گزینه پرداخت را کلیک نکند، این گزینه را فعال نمایید.',
					'description' => 'به صورت پیشفرض (غیرفعال) خریدار قبل از هدایت به درگاه ابتدا شماره سفارش و قیمت نهایی را مشاهده میکند و سپس با زدن دکمه تایید به درگاه هدایت میشود.',
					'default'     => 'no',
					'desc_tip'    => true,
				],
				'completed_massage' => [
					'title'       => 'پیام پرداخت موفق',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کدهای زیر نیز استفاده نمایید.' . $shortcodes,
					'default'     => 'با تشکر از شما. سفارش شما با موفقیت پرداخت شد.',
				],
				'failed_massage'    => [
					'title'       => 'پیام پرداخت ناموفق',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید. این دلیل خطا از سایت درگاه ارسال میگردد.',
					'default'     => 'پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.',
				],
				'cancelled_massage' => [
					'title'       => 'پیام انصراف از پرداخت',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از انصراف کاربر از پرداخت نمایش دهید را وارد نمایید. این پیام بعد از بازگشت از بانک نمایش داده خواهد شد.',
					'default'     => 'پرداخت به دلیل انصراف شما ناتمام باقی ماند.',
				],
			] );
		}

		public function process_payment( $order ) {

			$order = $this->get_order( $order );

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			];
		}

		public function process_payment_request( $order_id ) {

			$this->order_id = $order_id;
			$this->session( 'set', 'order_id', $order_id );
			$order = $this->get_order( $order_id );
			$form  = $this->option( 'direct_redirect' ) != '1';

			if ( $form ) {
				$payment_form = '<form action="" method="POST" class="gateway-checkout-form" id="gateway-checkout-form-' . $this->id . '">
						<input type="submit" name="gateway_submit" class="gateway-submit button alt" value="پرداخت"/>
						<a class="gateway-cancel button cancel" href="' . $this->get_checkout_url() . '">بازگشت</a>
					 </form><br/>';

				echo wp_kses( $payment_form, [
					'form'  => [ 'action' => true, 'method' => true, 'class' => true, 'id' => true ],
					'input' => [ 'type' => true, 'name' => true, 'class' => true, 'value' => true ],
					'a'     => [ 'class' => true, 'href' => true ],
				] );
			}

			if ( ! $form || isset( $_POST['gateway_submit'] ) ) {
				$error = $this->request( $order );
				$this->set_message( 'failed', $error, true, false );
				$order->add_order_note( sprintf( 'در هنگام اتصال به درگاه %s خطای زیر رخ داده است.', $this->title ) . "<br>{$error}" );
			}
		}

		public function process_payment_verify() {

			$redirect = $this->get_checkout_url();

			$order_id = ! empty( $_GET['wc_order'] ) ? intval( $_GET['wc_order'] ) : $this->session( 'get', 'order_id' );

			if ( empty( $order_id ) ) {
				$this->set_message( 'failed', 'شماره سفارش وجود ندارد.', true, $redirect );
			}

			$order = $this->get_order( $order_id );

			if ( ! $this->needs_payment( $order ) ) {
				$this->set_message( 'failed', 'وضعیت تراکنش قبلا مشخص شده است.', true, $redirect, true );
			}

			$this->order_id = $order_id;

			$result = $this->verify( $order );

			if ( ! is_array( $result ) ) {
				$error = is_string( $result ) && strlen( $result ) > 5 ? $result : 'اطلاعات صحت سنجی تراکنش صحیح نیست.';
				$this->set_message( 'failed', $error, true, $redirect, true );
			}

			$error          = '';
			$status         = ! empty( $result['status'] ) ? $result['status'] : '';
			$transaction_id = ! empty( $result['transaction_id'] ) ? $result['transaction_id'] : '';

			if ( $status == 'completed' ) {

				$redirect = $this->get_return_url( $order );

				$order->payment_complete( $transaction_id );
				$this->empty_cart();
				$this->set_verification();

				$shortcodes = $this->get_shortcodes_values();
				$note       = [ 'تراکنش موفق بود.' ];
				foreach ( $this->fields_shortcodes() as $key => $value ) {
					$key    = trim( $key, '\{\}' );
					$note[] = "$value : {$shortcodes[$key]}";
				}
				$order->add_order_note( implode( "<br>", $note ), 1 );

			} elseif ( $status == 'cancelled' ) {
				$order->add_order_note( 'تراکنش به به علت انصراف کاربر ناتمام باقی ماند.', 1 );
			} else {
				$error = ! empty( $result['error'] ) ? $result['error'] : 'در حین پرداخت خطایی رخ داده است.';
				$order->add_order_note( sprintf( 'در هنگام بازگشت از درگاه %s خطای زیر رخ داده است.', $this->title ) . "<br>{$error}", 1 );
			}

			$this->set_message( $status, $error, true, $redirect );
			exit;
		}

		public function request( $order ) {

			$amount       = $this->get_total( 'IRR' );
			$callback     = $this->get_verify_url();
			$mobile       = $this->get_order_mobile();
			$order_number = $this->get_order_props( 'order_number' );
			$description  = 'شماره سفارش #' . $order_number;
			$apiID        = $this->option( 'sandbox' ) == '1' ? 'test' : $this->option( 'api' );

			$pay = wp_remote_post( 'https://pay.ir/pg/send', [
				'body' => [
					'api'          => $apiID,
					'amount'       => $amount,
					'redirect'     => urlencode( $callback ),
					'mobile'       => $mobile,
					'factorNumber' => $order_number,
					'description'  => $description,
					'resellerId'   => '1000000800',
				],
			] );

			$pay = wp_remote_retrieve_body( $pay );

			if ( empty( $pay ) ) {
				return 'خطایی در اتصال به درگاه رخ داده است!';
			}

			$pay = json_decode( $pay );

			if ( $pay->status ) {
				$this->redirect( 'https://pay.ir/pg/' . $pay->token );
			} else {
				return ! empty( $pay->errorMessage ) ? $pay->errorMessage : ( ! empty( $pay->errorCode ) ? $this->errors( $pay->errorCode ) : '' );
			}
		}

		public function verify( $order ) {

			$apiID = $this->option( 'sandbox' ) == '1' ? 'test' : $this->option( 'api' );
			$token = $this->get( 'token' );

			$this->check_verification( $token );

			$error  = '';
			$status = 'failed';

			if ( $this->get( 'status' ) ) {

				$pay = wp_remote_post( 'https://pay.ir/pg/verify', [
					'body' => [
						'api'   => $apiID,
						'token' => $token,
					],
				] );

				$pay = wp_remote_retrieve_body( $pay );

				if ( ! empty( $pay ) ) {

					$pay = json_decode( $pay );

					if ( $pay->status ) {
						$status = 'completed';
					} else {
						$error = ! empty( $pay->errorMessage ) ? $pay->errorMessage : ( ! empty( $pay->errorCode ) ? $this->errors( ( $pay->errorCode . '1' ) ) : '' );
					}

				}

			} else {
				$error = $this->post( 'message' );
			}

			$this->set_shortcodes( [ 'token' => $token ] );

			return compact( 'status', 'token', 'error' );
		}

		private function errors( $error ) {

			switch ( $error ) {

				case '-1' :
					$message = 'ارسال Api الزامی می باشد.';
					break;

				case '-2' :
					$message = 'ارسال Amount (مبلغ تراکنش) الزامی می باشد.';
					break;

				case '-3' :
					$message = 'مقدار Amount (مبلغ تراکنش)باید به صورت عددی باشد.';
					break;

				case '-4' :
					$message = 'Amount نباید کمتر از 1000 باشد.';
					break;

				case '-5' :
					$message = 'ارسال Redirect الزامی می باشد.';
					break;

				case '-6' :
					$message = 'درگاه پرداختی با Api ارسالی یافت نشد و یا غیر فعال می باشد.';
					break;

				case '-7' :
					$message = 'فروشنده غیر فعال می باشد.';
					break;

				case '-8' :
					$message = 'آدرس بازگشتی با آدرس درگاه پرداخت ثبت شده همخوانی ندارد.';
					break;

				case 'failed' :
					$message = 'تراکنش با خطا مواجه شد.';
					break;

				case '-11' :
					$message = 'ارسال Api الزامی می باشد.';
					break;

				case '-21' :
					$message = 'ارسال token الزامی می باشد.';
					break;

				case '-31' :
					$message = 'درگاه پرداختی با Api ارسالی یافت نشد و یا غیر فعال می باشد.';
					break;

				case '-41' :
					$message = 'فروشنده غیر فعال می باشد.';
					break;

				case '-51' :
					$message = 'تراکنش با خطا مواجه شده است.';
					break;

				default:
					$message = 'خطای ناشناخته رخ داده است.';
					break;
			}

			return $message;
		}

		/*
		 * ---------------------------------------------------
		 * */
		protected function order_id( $order ) {

			if ( is_numeric( $order ) ) {
				$order_id = $order;
			} elseif ( method_exists( $order, 'get_id' ) ) {
				$order_id = $order->get_id();
			} elseif ( ! ( $order_id = absint( get_query_var( 'order-pay' ) ) ) ) {
				$order_id = $order->id;
			}

			if ( ! empty( $order_id ) ) {
				$this->order_id = $order_id;
			}

			return $order_id;
		}

		protected function get_order( $order = 0 ) {

			if ( empty( $order ) ) {
				$order = $this->order_id;
			}

			if ( empty( $order ) ) {
				return (object) [];
			}

			if ( is_numeric( $order ) ) {
				$this->order_id = $order;

				$order = new WC_Order( $order );
			}

			return $order;
		}

		protected function get_order_props( $prop, $default = '' ) {

			if ( empty( $this->order_id ) ) {
				return '';
			}

			$order = $this->get_order();

			$method = 'get_' . $prop;

			if ( method_exists( $order, $method ) ) {
				$prop = $order->$method();
			} elseif ( ! empty( $order->{$prop} ) ) {
				$prop = $order->{$prop};
			} else {
				$prop = '';
			}

			return ! empty( $prop ) ? $prop : $default;
		}

		protected function get_order_items( $product = false ) {

			if ( empty( $this->order_id ) ) {
				return [];
			}

			$order = $this->get_order();
			$items = $order->get_items();

			if ( $product ) {
				$products = [];
				foreach ( (array) $items as $item ) {
					$products[] = $item['name'] . ' (' . $item['qty'] . ') ';
				}

				return implode( ' - ', $products );
			}

			return $items;
		}

		protected function get_order_mobile() {

			$Mobile = $this->get_order_props( 'billing_phone' );
			$Mobile = $this->get_order_props( 'billing_mobile', $Mobile );

			$Mobile = str_ireplace( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ],
				[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], $Mobile ); //farsi

			$Mobile = str_ireplace( [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ],
				[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], $Mobile ); //arabi

			$Mobile = preg_replace( '/\D/is', '', $Mobile );
			$Mobile = ltrim( $Mobile, '0' );
			$Mobile = substr( $Mobile, 0, 2 ) == '98' ? substr( $Mobile, 2 ) : $Mobile;

			return '0' . $Mobile;
		}

		protected function get_currency() {

			if ( empty( $this->order_id ) ) {
				return '';
			}

			$order = $this->get_order();

			$currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->get_order_currency();

			$irt = [ 'irt', 'toman', 'tomaan', 'iran toman', 'iranian toman', 'تومان', 'تومان ایران' ];
			if ( in_array( strtolower( $currency ), $irt ) ) {
				$currency = 'IRT';
			}

			$irr = [ 'irr', 'rial', 'iran rial', 'iranian rial', 'ریال', 'ریال ایران' ];
			if ( in_array( strtolower( $currency ), $irr ) ) {
				$currency = 'IRR';
			}

			return $currency;
		}

		protected function get_total( $to_currency = 'IRR' ) {

			if ( empty( $this->order_id ) ) {
				return 0;
			}

			$order = $this->get_order();

			if ( method_exists( $order, 'get_total' ) ) {
				$price = $order->get_total();
			} else {
				$price = intval( $order->order_total );
			}

			$currency    = strtoupper( $this->get_currency() );
			$to_currency = strtoupper( $to_currency );

			if ( in_array( $currency, [ 'IRHR', 'IRHT' ] ) ) {
				$currency = str_ireplace( 'H', '', $currency );
				$price    *= 1000;
			}

			if ( $currency == 'IRR' && $to_currency == 'IRT' ) {
				$price /= 10;
			}

			if ( $currency == 'IRT' && $to_currency == 'IRR' ) {
				$price *= 10;
			}

			return $price;
		}

		protected function needs_payment( $order = 0 ) {

			if ( empty( $order ) && empty( $this->order_id ) ) {
				return true;
			}

			$order = $this->get_order( $order );

			if ( method_exists( $order, 'needs_payment' ) ) {
				return $order->needs_payment();
			}

			if ( empty( $this->order_id ) && ! empty( $order ) ) {
				$this->order_id = $this->order_id( $order );
			}

			return ! in_array( $this->get_order_props( 'status' ), [ 'completed', 'processing' ] );
		}

		protected function get_verify_url() {
			return add_query_arg( 'wc_order', $this->order_id, WC()->api_request_url( get_class( $this ) ) );
		}

		protected function get_checkout_url() {
			if ( function_exists( 'wc_get_checkout_url' ) ) {
				return wc_get_checkout_url();
			} else {
				global $woocommerce;

				return $woocommerce->cart->get_checkout_url();
			}
		}

		protected function empty_cart() {
			if ( function_exists( 'wc_empty_cart' ) ) {
				wc_empty_cart();
			} elseif ( function_exists( 'WC' ) && ! empty( WC()->cart ) && method_exists( WC()->cart, 'empty_cart' ) ) {
				WC()->cart->empty_cart();
			} else {
				global $woocommerce;
				$woocommerce->cart->empty_cart();
			}
		}

		protected function fields_shortcodes( $fields = [] ) {

			return ! empty( $fields['shortcodes'] ) && is_array( $fields['shortcodes'] ) ? $fields['shortcodes'] : [];
		}

		protected function get_shortcodes_values() {

			$shortcodes = [];
			foreach ( $this->fields_shortcodes() as $key => $value ) {
				$key                = trim( $key, '\{\}' );
				$shortcodes[ $key ] = get_post_meta( $this->order_id, '_' . $key, true );
			}

			return $shortcodes;
		}

		protected function set_shortcodes( $shortcodes ) {

			$fields_shortcodes = $this->fields_shortcodes();

			foreach ( $shortcodes as $key => $value ) {

				if ( is_numeric( $key ) ) {
					$key = $fields_shortcodes[ $key ];
				}

				if ( ! empty( $key ) && ! is_array( $key ) ) {
					$key = trim( $key, '\{\}' );
					update_post_meta( $this->order_id, '_' . $key, $value );
				}
			}
		}

		protected function set_message( $status, $error = '', $notice = true, $redirect = false, $failed_note = false ) {

			if ( ! in_array( $status, [ 'completed', 'cancelled', 'failed' ] ) ) {
				$status = 'failed';
			}

			if ( ! empty( $error ) && $failed_note && ( $order = $this->get_order() ) && ! empty( $order ) ) {
				$order->add_order_note( 'خطا: ' . $error, 1 );
			}

			$shortcodes = array_merge( $this->get_shortcodes_values(), [ '{fault}' => $error ] );

			$message = $this->option( $status . '_massage' );
			$find    = array_map( function ( $value ) {
				return '{' . trim( $value, '\{\}' ) . '}';
			}, array_keys( $shortcodes ) );
			$message = str_ireplace( $find, array_values( $shortcodes ), $message );
			$message = wpautop( wptexturize( trim( $message ) ) );

			if ( $notice ) {
				wc_add_notice( $message, $status == 'completed' ? 'success' : 'error' );
			}

			if ( $redirect ) {
				wp_redirect( $redirect );
				exit;
			}

			return $message;
		}

		protected function check_verification( $params ) {

			if ( function_exists( 'func_get_args' ) ) {
				$args = func_get_args();
				if ( count( $args ) > 1 ) {
					$params = array_merge( array_values( $args ), $params );
					$params = implode( '_', array_unique( $params ) );
				}
			}

			if ( is_array( $params ) ) {
				$params = implode( '_', $params );
			}
			$params = $this->id . '_' . $params;

			global $wpdb;
			$query = "SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key='_verification_params' AND meta_value='%s'";
			$check = $wpdb->get_row( $wpdb->prepare( $query, $params ) );
			if ( ! empty( $check ) ) {
				return $this->set_message( 'failed', 'این تراکنش قبلا یکبار وریفای شده بود.', true, $this->get_checkout_url(), true );
			}
			$this->verification_params = $params;
		}

		protected function set_verification() {
			if ( ! empty( $this->verification_params ) ) {
				update_post_meta( $this->order_id, '_verification_params', $this->verification_params );
			}
		}

		/*
		 * Helpers
		 * */
		protected function option( $name ) {

			$option = '';
			if ( method_exists( $this, 'get_option' ) ) {
				$option = $this->get_option( $name );
			} elseif ( ! empty( $this->settings[ $name ] ) ) {
				$option = $this->settings[ $name ];
			}

			if ( in_array( strtolower( $option ), [ 'yes', 'on', 'true' ] ) ) {
				$option = '1';
			}
			if ( in_array( strtolower( $option ), [ 'no', 'off', 'false' ] ) ) {
				$option = false;
			}

			return $option;
		}

		protected function get( $name, $default = '' ) {
			return ! empty( $_GET[ $name ] ) ? sanitize_text_field( $_GET[ $name ] ) : $default;
		}

		protected function post( $name, $default = '' ) {
			return ! empty( $_POST[ $name ] ) ? sanitize_text_field( $_POST[ $name ] ) : $default;
		}

		protected function store_date( $key, $value ) {
			$this->session( 'set', $key, $value );
			update_post_meta( $this->order_id, '_' . $this->id . '_' . $key, $value );
		}

		protected function get_stored( $key ) {

			$value = get_post_meta( $this->order_id, '_' . $this->id . '_' . $key, true );

			return ! empty( $value ) ? $value : $this->session( 'get', $key );
		}

		protected function session( $action, $name, $value = '' ) {

			global $woocommerce;

			$name = $this->id . '_' . $name;

			$wc_session = function_exists( 'WC' ) && ! empty( WC()->session );

			if ( $action == 'set' ) {

				if ( $wc_session && method_exists( WC()->session, 'set' ) ) {
					WC()->session->set( $name, $value );
				} else {
					$woocommerce->session->{$name} = $value;
				}

			} elseif ( $action == 'get' ) {

				if ( $wc_session && method_exists( WC()->session, 'get' ) ) {
					$value = WC()->session->get( $name );
					unset( WC()->session->{$name} );
				} else {
					$value = $woocommerce->session->{$name};
					unset( $woocommerce->session->{$name} );
				}

				return $value;
			}

			return '';
		}

		protected function redirect( $url ) {
			if ( ! headers_sent() ) {
				header( 'Location: ' . trim( $url ) );
			} else {
				$RedirectforPay = "<script type='text/javascript'>window.onload = function () { top.location.href = '" . $url . "'; };</script>";
				echo strip_tags( $RedirectforPay, "<script>" );
			}
			exit;
		}

		protected function submit_form( $form ) {

			$name = 'pw_gateway_name_' . $this->id;

			$form    = explode( '>', $form );
			$form[0] = preg_replace( '/name=[\'\"].*?[\'\"]/i', '', $form[0] );
			$form    = implode( '>', $form );
			$form    = str_ireplace( "<form", "<form name=\"{$name}\"", $form );

			echo 'در حال هدایت به درگاه ....';
			$function = "document.{$name}.submit();";
			if ( headers_sent() ) {
				$script = "<script type=\"text/javascript\">function PWformSubmit(){ $function } PWformSubmit();";
				$script .= $function;
				$script .= "</script>";

				echo strip_tags( $script, "<script>" );
				echo strip_tags( $form, "<form><input>" );
			} else {
				$script = "<script type=\"text/javascript\">$function</script>";

				echo strip_tags( $form, "<form><input>" );
				echo strip_tags( $script, "<script>" );
			}
			die();
		}
	}

	/**
	 * Add the Gateway to WooCommerce
	 **/
	function woocommerce_add_payir( $methods ) {
		$methods[] = 'WC_PayIR';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_payir' );
}