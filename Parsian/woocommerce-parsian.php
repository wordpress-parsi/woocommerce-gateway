<?php
/*
  Plugin Name: درگاه بانک پارسیان برای ووکامرس
  Plugin URI: https://plugins.pec.ir
  Description: این افزونه درگاه بانک پارسیان را به روش های پرداختی ووکامرس اضافه می کند.
  Version: 1.8
  Author: شرکت تجارت الکترونیک پارسیان
  Author URI: https://plugins.pec.ir
 */

function woocommerce_parsian_payment_init() {

    if (!class_exists('WC_Payment_Gateway'))
        return;

	$parsian_update = get_option('pec_need_update');
	if(isset($parsian_update) && !empty($parsian_update)){
		add_action('admin_menu', array('WC_Parsian_Gateway', 'update_menu'));
	}
	add_action('admin_init', array('WC_Parsian_Gateway', 'admin_init'));
	add_filter('plugin_action_links', array('WC_Parsian_Gateway', 'pec_wpwc_plugin_action_links'), 10, 2);
	
    class WC_Parsian_Gateway extends WC_Payment_Gateway {

		const CurrentVersion = '1.8';
		const PlgSlug = 'wpwc';
		const PlgName = 'درگاه بانک پارسیان برای ووکامرس';
        public function __construct() {

            $this->id = 'parsian';
            $this->method_title = 'بانک پارسیان';
            $this->has_fields = false;
            $this->redirect_uri = WC()->api_request_url('WC_Parsian_Gateway');

            $this->init_form_fields();
            $this->init_settings();

            $this->parsian_login_account = $this->settings['parsian_login_account'];
            $this->title = __($this->settings['title'], 'woocommerce');
            $this->description = __($this->settings['description'], 'woocommerce');
            $this->success_massage = __($this->settings['success_massage'], 'woocommerce');
            $this->failed_massage = __($this->settings['failed_massage'], 'woocommerce');
            $this->cancelled_massage = __($this->settings['cancelled_massage'], 'woocommerce');
            $this->msg['message'] = '';
            $this->msg['class'] = '';

            if (isset($this->settings['orderstatus'])) {
                $this->orderstatus = $this->settings['orderstatus'];
            } else {
                $this->orderstatus = 1;
            }

            if ($this->debugMode == 'on') {
                $this->logs = new WC_Logger();
            }

            if ($this->enviroment == 'sandbox') {
                $this->parsian_jsdomain = "https://stage.parsian.com/js/iframe.parsian.js";
            } else {
                $this->parsian_jsdomain = "https://parsian.com/js/iframe.parsian.js";
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_parsian', array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_parsian_gateway', array($this, 'callback'));
        }

		public static function admin_init()
		{
			if(current_user_can('manage_options')){
				$last_check_update = get_option('pec_last_check_update');
				if(time()-$last_check_update >= 432000){
					$Request = self::PecReservoir('last_version',array('plugin'=>self::PlgSlug));
					if(isset($Request->LastVersion) && $Request->LastVersion > 0 && $Request->LastVersion != self::CurrentVersion) {
						self::add_need_update(self::PlgSlug,self::PlgName,$Request->LastVersion,self::CurrentVersion);
					}
					update_option('pec_last_check_update',time());
				}
				
				$woocommerce_parsian_settings = get_option('woocommerce_parsian_settings');
				if(!isset($woocommerce_parsian_settings['parsian_login_account']) && (!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] != 'wc-settings' && $_GET['page'] != 'check_plg_version'))){
					echo '<div class="notice notice-warning is-dismissible"><p>افزونه <b>'.self::PlgName.'</b> با موفقیت نصب شده است ، تنظیمات افزونه را انجام دهید. <a style="text-decoration:none;color:#0000ff;" href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=parsian').'">(انجام تنظیمات)</a> - <a style="text-decoration:none;color:#0000ff;" href="https://plugins.pec.ir/%d9%be%d9%84%d8%a7%da%af%db%8c%d9%86-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d9%be%d8%a7%d8%b1%d8%b3%db%8c%d8%a7%d9%86-%d8%a8%d8%b1%d8%a7%db%8c-%d8%a7%d9%81%d8%b2%d9%88%d9%86%d9%87-woocommerce-%d9%88/" target="_blank">(آموزش تصویری)</a></p></div>';
				}
				
				if(!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] != 'wc-settings' && $_GET['page'] != 'check_plg_version'))
				{
					if(self::check_need_update(self::PlgSlug)){
						echo '<div class="notice notice-warning is-dismissible"><p>نسخه جدیدتر افزونه <b>'.self::PlgName.'</b> در دسترس است. <a style="text-decoration:none;color:#0000ff;" href="'.admin_url('admin.php?page=check_plg_version').'">(بروزرسانی خودکار)</a></p></div>';
					}
				}
			}
		}
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'فعال / غیرفعال :',
                    'type' => 'checkbox',
                    'label' => 'فعال یا غیرفعال سازی درگاه بانک پارسیان',
                    'default' => 'no'
                ),
                'parsian_login_account' => array(
                    'title' => 'رمز درگاه :',
                    'type' => 'text',
                    'required' => true,
                    'desc_tip' => true,
                ),
                'title' => array(
                    'title' => 'عنوان درگاه :',
                    'type' => 'text',
                    'description' => 'این نام در طی فرایند خرید به مشتری نمایش داده می شود',
                    'default' => 'بانک پارسیان'
                ),
                'description' => array(
                    'title' => 'توضیحات درگاه',
                    'type' => 'textarea',
					'description' => 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد',
					'default'     => 'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق بانک پارسیان'
                ),
				'success_massage' => array(
					'title'       => 'پیام پرداخت موفق',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید ، می توانید از شورت کد {transaction_id} نیز برای نمایش کد رهگیری تراکنش و از شرت کد {SaleOrderId} برای شماره درخواست استفاده نمایید .',
					'default'     => 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .',
				),
				'failed_massage' => array(
					'title'       => 'پیام پرداخت ناموفق',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید ، می توانید از شورت کد {fault} نیز برای نمایش دلیل خطای رخ داده استفاده نمایید.',
					'default'     => 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .',
				),
				'cancelled_massage' => array(
					'title'       => 'پیام انصراف از پرداخت',
					'type'        => 'textarea',
					'description' => 'متن پیامی که میخواهید بعد از انصراف کاربر از پرداخت نمایش دهید را وارد نمایید . این پیام بعد از بازگشت از بانک نمایش داده خواهد شد .',
					'default'     => 'پرداخت به دلیل انصراف شما ناتمام باقی ماند .',
				),
            );
        }

        public function admin_options() {

            if ($this->enviroment == 'production' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
                echo '<div class="error"><p>' . sprintf(__('%s Parsian Sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
            }

            echo '<h3>تنظیمات درگاه پارسیان :</h3>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
        }

		public static function PecPayRequest($LoginAccount,$amount,$order_id,$redirect,$additional='')
		{
			$parameters = array(
				'LoginAccount'		=> $LoginAccount,
				'Amount' 			=> $amount,
				'OrderId' 			=> $order_id,
				'CallBackUrl' 		=> $redirect,
				'AdditionalData' 	=> $additional
			);
			if(extension_loaded('soap')){
				try {
					$client	= new SoapClient('https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?WSDL',array('soap_version'=>'SOAP_1_1','cache_wsdl'=>WSDL_CACHE_NONE  ,'encoding'=>'UTF-8'));
					$result	= $client->SalePaymentRequest(array("requestData" => $parameters));
					$output = array(
						'Status'	=>	$result->SalePaymentRequestResult->Status,
						'Token'		=>	$result->SalePaymentRequestResult->Token
					);
				}
				catch(Exception $e){
					$output = array('Status' =>	'-1','Token' =>	'');
				}
			}
			else{
				$output = array('Status' =>	'-2','Token' =>	'');
			}
			return (object)$output;
		}
		public static function PecVerifyRequest($LoginAccount,$Token)
		{
			$parameters = array(
				'LoginAccount'		=> $LoginAccount,
				'Token' 			=> $Token
			);
			if(extension_loaded('soap')){
				try {
					$client	= new SoapClient('https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?WSDL',array('soap_version'=>'SOAP_1_1','cache_wsdl'=>WSDL_CACHE_NONE  ,'encoding'=>'UTF-8'));
					$result	= $client->ConfirmPayment(array("requestData" => $parameters));
					$output['Status'] = $result->ConfirmPaymentResult->Status;
					$output['RRN'] = $result->ConfirmPaymentResult->RRN;
					$output['CardNumberMasked'] = isset($result->ConfirmPaymentResult->CardNumberMasked) ? $result->ConfirmPaymentResult->CardNumberMasked : '';
				}
				catch(Exception $e){
					$output = array('Status' =>	'-1','RRN' => '');
				}
			}
			else{
				$output = array('Status' =>	'-2','RRN' => '');
			}
			return (object)$output;
		}
		public static function PecReversalRequest($LoginAccount,$Token)
		{
			$parameters = array(
				'LoginAccount'		=> $LoginAccount,
				'Token' 			=> $Token
			);
			if(extension_loaded('soap')){
				try {
					$client	= new SoapClient('https://pec.shaparak.ir/NewIPGServices/Reverse/ReversalService.asmx?WSDL',array('soap_version'=>'SOAP_1_1','cache_wsdl'=>WSDL_CACHE_NONE  ,'encoding'=>'UTF-8'));
					$result	= $client->ReversalRequest(array("requestData" => $parameters));
					$output['Status'] = $result->ReversalRequestResult->Status;
					$output['Token'] = $result->ReversalRequestResult->Token;
				}
				catch(Exception $e){
					$output = array('Status' =>	'-1','Token' => '');
				}
			}
			else{
				$output = array('Status' =>	'-2','Token' => '');
			}
			return (object)$output;
			
		}
		public static function PecStatus($code='',$error_page=0){
			
			switch($code){
				case '-32768':
					$response = 'خطای ناشناخته رخ داده است';
					break;
				case '-1552':
					$response = 'برگشت تراکنش مجاز نمی باشد';
					break;
				case '-1551':
					$response = 'برگشت تراکنش قبلاً انجام شده است';
					break;
				case '-1550':
					$response = 'برگشت تراکنش در وضعیت جاری امکان پذیر نمی باشد';
					break;
				case '-1549':
					$response = 'زمان مجاز برای درخواست برگشت تراکنش به اتمام رسیده است';
					break;
				case '-1548':
					$response = 'فراخوانی سرویس درخواست پرداخت قبض ناموفق بود';
					break;
				case '-1540':
					$response = 'تاييد تراکنش ناموفق مي باشد';
					break;
				case '-1536':
					$response = 'فراخوانی سرویس درخواست شارژ تاپ آپ ناموفق بود';
					break;
				case '-1533':
					$response = 'تراکنش قبلاً تایید شده است';
					break;
				case '1532':
					$response = 'تراکنش از سوی پذیرنده تایید شد';
					break;
				case '-1531':
					$response = 'تراکنش به دلیل انصراف شما در بانک ناموفق بود';
					break;
				case '-1530':
					$response = 'پذیرنده مجاز به تایید این تراکنش نمی باشد';
					break;
				case '-1528':
					$response = 'اطلاعات پرداخت یافت نشد';
					break;
				case '-1527':
					$response = 'انجام عملیات درخواست پرداخت تراکنش خرید ناموفق بود';
					break;
				case '-1507':
					$response = 'تراکنش برگشت به سوئیچ ارسال شد';
					break;
				case '-1505':
					$response = 'تایید تراکنش توسط پذیرنده انجام شد';
					break;
				case '-132':
					$response = 'مبلغ تراکنش کمتر از حداقل مجاز می باشد';
					break;
				case '-131':
					$response = 'Token نامعتبر می باشد';
					break;
				case '-130':
					$response = 'Token زمان منقضی شده است';
					break;
				case '-128':
					$response = 'قالب آدرس IP معتبر نمی باشد';
					break;
				case '-127':
					$response = 'آدرس اینترنتی معتبر نمی باشد';
					break;
				case '-126':
					$response = 'کد شناسایی پذیرنده معتبر نمی باشد';
					break;
				case '-121':
					$response = 'رشته داده شده بطور کامل عددی نمی باشد';
					break;
				case '-120':
					$response = 'طول داده ورودی معتبر نمی باشد';
					break;
				case '-119':
					$response = 'سازمان نامعتبر می باشد';
					break;
				case '-118':
					$response = 'مقدار ارسال شده عدد نمی باشد';
					break;
				case '-117':
					$response = 'طول رشته کم تر از حد مجاز می باشد';
					break;
				case '-116':
					$response = 'طول رشته بیش از حد مجاز می باشد';
					break;
				case '-115':
					$response = 'شناسه پرداخت نامعتبر می باشد';
					break;
				case '-114':
					$response = 'شناسه قبض نامعتبر می باشد';
					break;
				case '-113':
					$response = 'پارامتر ورودی خالی می باشد';
					break;
				case '-112':
					$response = 'شماره سفارش تکراری است';
					break;
				case '-111':
					$response = 'مبلغ تراکنش بیش از حد مجاز پذیرنده می باشد';
					break;
				case '-108':
					$response = 'قابلیت برگشت تراکنش برای پذیرنده غیر فعال می باشد';
					break;
				case '-107':
					$response = 'قابلیت ارسال تاییده تراکنش برای پذیرنده غیر فعال می باشد';
					break;
				case '-106':
					$response = 'قابلیت شارژ برای پذیرنده غیر فعال می باشد';
					break;
				case '-105':
					$response = 'قابلیت تاپ آپ برای پذیرنده غیر فعال می باشد';
					break;
				case '-104':
					$response = 'قابلیت پرداخت قبض برای پذیرنده غیر فعال می باشد';
					break;
				case '-103':
					$response = 'قابلیت خرید برای پذیرنده غیر فعال می باشد';
					break;
				case '-102':
					$response = 'تراکنش با موفقیت برگشت داده شد';
					break;
				case '-101':
					$response = 'پذیرنده اهراز هویت نشد';
					break;
				case '-100':
					$response = 'پذیرنده غیرفعال می باشد';
					break;
				case '-1':
					$response = 'خطای سرور';
					break;
				case '0':
					$response = 'عملیات موفق می باشد';
					break;
				case '1':
					$response = 'صادرکننده ی کارت از انجام تراکنش صرف نظر کرد';
					break;
				case '2':
					$response = 'عملیات تاییدیه این تراکنش قبلا باموفقیت صورت پذیرفته است';
					break;
				case '3':
					$response = 'پذیرنده ی فروشگاهی نامعتبر می باشد';
					break;
				case '5':
					$response = 'از انجام تراکنش صرف نظر شد';
					break;
				case '6':
					$response = 'بروز خطايي ناشناخته';
					break;
				case '8':
					$response = 'باتشخیص هویت دارنده ی کارت، تراکنش موفق می باشد';
					break;
				case '9':
					$response = 'درخواست رسيده در حال پي گيري و انجام است ';
					break;
				case '10':
					$response = 'تراکنش با مبلغي پايين تر از مبلغ درخواستي ( کمبود حساب مشتري ) پذيرفته شده است ';
					break;
				case '12':
					$response = 'تراکنش نامعتبر است';
					break;
				case '13':
					$response = 'مبلغ تراکنش نادرست است';
					break;
				case '14':
					$response = 'شماره کارت ارسالی نامعتبر است (وجود ندارد)';
					break;
				case '15':
					$response = 'صادرکننده ی کارت نامعتبراست (وجود ندارد)';
					break;
				case '17':
					$response = 'مشتري درخواست کننده حذف شده است ';
					break;
				case '20':
					$response = 'در موقعيتي که سوئيچ جهت پذيرش تراکنش نيازمند پرس و جو از کارت است ممکن است درخواست از کارت ( ترمينال) بنمايد اين پيام مبين نامعتبر بودن جواب است';
					break;
				case '21':
					$response = 'در صورتي که پاسخ به در خواست ترمينا ل نيازمند هيچ پاسخ خاص يا عملکردي نباشيم اين پيام را خواهيم داشت ';
					break;
				case '22':
					$response = 'تراکنش مشکوک به بد عمل کردن ( کارت ، ترمينال ، دارنده کارت ) بوده است لذا پذيرفته نشده است';
					break;
				case '30':
					$response = 'قالب پیام دارای اشکال است';
					break;
				case '31':
					$response = 'پذیرنده توسط سوئی پشتیبانی نمی شود';
					break;
				case '32':
					$response = 'تراکنش به صورت غير قطعي کامل شده است ( به عنوان مثال تراکنش سپرده گزاري که از ديد مشتري کامل شده است ولي مي بايست تکميل گردد';
					break;
				case '33':
					$response = 'تاریخ انقضای کارت سپری شده است';
					break;
				case '38':
					$response = 'تعداد دفعات ورود رمزغلط بیش از حدمجاز است. کارت توسط دستگاه ضبط شود';
					break;
				case '39':
					$response = 'کارت حساب اعتباری ندارد';
					break;
				case '40':
					$response = 'عملیات درخواستی پشتیبانی نمی گردد';
					break;
				case '41':
					$response = 'کارت مفقودی می باشد';
					break;
				case '43':
					$response = 'کارت مسروقه می باشد';
					break;
				case '45':
					$response = 'قبض قابل پرداخت نمی باشد';
					break;
				case '51':
					$response = 'موجودی کافی نمی باشد';
					break;
				case '54':
					$response = 'تاریخ انقضای کارت سپری شده است';
					break;
				case '55':
					$response = 'رمز کارت نا معتبر است';
					break;
				case '56':
					$response = 'کارت نا معتبر است';
					break;
				case '57':
					$response = 'انجام تراکنش مربوطه توسط دارنده ی کارت مجاز نمی باشد';
					break;
				case '58':
					$response = 'انجام تراکنش مربوطه توسط پایانه ی انجام دهنده مجاز نمی باشد';
					break;
				case '59':
					$response = 'کارت مظنون به تقلب است';
					break;
				case '61':
					$response = 'مبلغ تراکنش بیش از حد مجاز می باشد';
					break;
				case '62':
					$response = 'کارت محدود شده است';
					break;
				case '63':
					$response = 'تمهیدات امنیتی نقض گردیده است';
					break;
				case '65':
					$response = 'تعداد درخواست تراکنش بیش از حد مجاز می باشد';
					break;
				case '68':
					$response = 'پاسخ لازم براي تکميل يا انجام تراکنش خيلي دير رسيده است';
					break;
				case '69':
					$response = 'تعداد دفعات تکرار رمز از حد مجاز گذشته است ';
					break;
				case '75':
					$response = 'تعداد دفعات ورود رمزغلط بیش از حدمجاز است';
					break;
				case '78':
					$response = 'کارت فعال نیست';
					break;
				case '79':
					$response = 'حساب متصل به کارت نا معتبر است یا دارای اشکال است';
					break;
				case '80':
					$response = 'درخواست تراکنش رد شده است';
					break;
				case '81':
					$response = 'کارت پذيرفته نشد';
					break;
				case '83':
					$response = 'سرويس دهنده سوئيچ کارت تراکنش را نپذيرفته است';
					break;
				case '84':
					$response = 'در تراکنشهايي که انجام آن مستلزم ارتباط با صادر کننده است در صورت فعال نبودن صادر کننده اين پيام در پاسخ ارسال خواهد شد ';
					break;
				case '91':
					$response = 'سيستم صدور مجوز انجام تراکنش موقتا غير فعال است و يا  زمان تعيين شده براي صدور مجوز به پايان رسيده است';
					break;
				case '92':
					$response = 'مقصد تراکنش پيدا نشد';
					break;
				case '93':
					$response = 'امکان تکميل تراکنش وجود ندارد';
					break;
				default:
					$response = 'پرداخت تراکنش به دلیل انصراف در صفحه بانک ناموفق بود';
					break;
			}
			
			return $response;
		}
		public static function PecReservoir($type,$params)
		{
			$post = "type=$type";
			foreach($params as $key => $value){$post .= "&$key=$value";}
			$curl = curl_init();
			curl_setopt($curl,CURLOPT_URL,'https://plugins.pec.ir/dl/check_update/wordpress/index.php');
			curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
			curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
			curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
			$result = json_decode(curl_exec($curl));
			curl_close($curl);
			return $result;
		}
		public static function display_error($pay_status='',$tran_id='',$order_id='',$is_callback=1)
		{
			$page_html = '<div align="center" dir="rtl" style="font-family:tahoma;font-size:12px;line-height: 25px;color:#000000;margin: -25px 0px -23px 0px;">';
				if($pay_status == 'retry')
				{
					$page_title = 'خطای موقت در پرداخت';
					$admin_mess = 'در هنگام بازگشت خریدار از بانک سرور بانک پاسخ نداد ، از خریدار درخواست شد صفحه را رفرش کند';
					$page_html .= '
						<div style="color: #ff0000;font-weight: bold;font-size: 12px;margin: 25px;">::: خطای موقت :::</div>
						<div style="margin-bottom:21px;font-size: 12px;">
							سرور درگاه اینترنتی <span style="color:#ff0000;">به صورت موقت</span> با مشکل مواجه شده است ، جهت تکمیل تراکنش لحظاتی بعد بر روی دکمه زیر کلیک کنید
						</div>
						<div style="margin:20px 0px 25px 0px;color:#008800;" id="reqreload">
							<button onclick="reload_page()">تلاش مجدد</button>
						</div>
						<script>
							function reload_page(){
								document.getElementById("reqreload").innerHTML = "در حال تلاش مجدد لطفا صبر کنید ..";
								location.reload();
							}
						</script>
					';
				}
				elseif($pay_status == 'reversal_done')
				{
					$page_title = 'مشکل در ارائه خدمات';
					$admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، دستور بازگشت وجه به حساب خریدار در بانک ثبت شد';
					$page_html .= '<span style="color:#ff0000;"><b>مشکل در ارائه خدمات</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= "پرداخت شما با شماره پیگیری $tran_id با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />";
					$page_html .= 'دستور بازگشت وجه به حساب شما در بانک ثبت شده است ، در صورتی که وجه پرداختی تا ساعات آینده به حساب شما بازگشت داده نشد با پشتیبانی تماس بگیرید (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($pay_status == 'reversal_error')
				{
					$page_title = 'مشکل در ارائه خدمات';
					$admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، ثبت دستور بازگشت وجه به حساب خریدار نیز با خطا روبرو بود ، به این خریدار می بایست یا خدمات ارائه شود و یا مبلغ به حساب بانکی وی عودت داده شود.';
					$page_html .= '<span style="color:#ff0000;"><b>مشکل در ارائه خدمات</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= "پرداخت شما با شماره پیگیری $tran_id با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />";
					$page_html .= 'به منظور ثبت دستور بازگشت وجه به حساب شما در بانک اقدام شد اما متاسفانه با خطا روبرو شد ، لطفا به منظور دریافت خدمات و یا استرداد وجه پرداختی با پشتیبانی تماس بگیرید';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($pay_status == 'already_been_completed')
				{
					$page_title = 'سفارش پیش از این موفق شده است';
					$page_html .= '<span style="color:#008800;"><b>سفارش پیش از این موفق شده است</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= "سفارش شما پیش از این با شماره پیگیری $tran_id با موفقیت ثبت شده است";
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($pay_status == 'order_not_exist')
				{
					$page_title = 'سفارش یافت نشد';
					$admin_mess = 'سفارش در سایت یافت نشد';
					$page_html .= '<span style="color:#ff0000;"><b>سفارش یافت نشد</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= 'متاسفانه سفارش شما در سایت یافت نشد ! در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($pay_status == 'order_not_for_this_person')
				{
					$page_title = 'شماره سفارش نادرست است';
					$admin_mess = 'شماره سفارش نادرست است';
					$page_html .= '<span style="color:#ff0000;"><b>شماره سفارش نادرست است</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= 'شماره سفارش نادرست است ؛ در صورت نیاز به پشتیبانی تماس بگیرید';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($pay_status == 'error_creating_order')
				{
					$page_title = 'مشکل در ثبت سفارش';
					$admin_mess = 'مشکل در ثبت سفارش';
					$page_html .= '<span style="color:#ff0000;"><b>مشکل در ثبت سفارش</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= 'مشکلی در ثبت سفارش وجود دارد ، لطفا این موضوع را با پشتیبانی در میان بگذارید';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				elseif($is_callback == 0)
				{
					$page_title = $admin_mess = 'خطا در ارسال به بانک';
					$page_html .= '<span style="color:#ff0000;"><b>خطا در ارسال به بانک</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= "خطای $pay_status در ارسال به بانک ، ".self::PecStatus($pay_status);
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					
				}
				else
				{
					$page_title = 'پرداخت انجام نشد';
					$page_html .= '<span style="color:#ff0000;"><b>پرداخت انجام نشد</b></span><br/>';
					$page_html .= '<p style="text-align:center;margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
					$page_html .= self::PecStatus($pay_status);
					$page_html .= '؛ در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد) - در صورت نیاز با پشتیبانی تماس بگیرید.';
					$page_html .= '</p>';
					$page_html .= '<a href="'.get_option('siteurl').'" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
					$admin_mess = 'پرداخت انجام نشد '.self::PecStatus($pay_status);
					
				}
			$page_html .= '</div>';
				
			if(isset($admin_mess) && $order_id != '' && $pay_status != 'order_not_for_this_person'){
				$order = new WC_Order($order_id);
				$order->add_order_note($admin_mess);
			}
			wp_die($page_html,$page_title);
		}
		public static function d_redirect($url='')
		{
			if($url != ''){
				if(headers_sent()){
					echo '<script type="text/javascript">window.location.assign("'.$url.'")</script>';
				}
				else{
					header("Location: $url");
				}
				exit();
			}
		}
		public static function check_need_update($plg_key='')
		{
			if($plg_key != ''){
				$check_updates = get_option('pec_need_update');
				parse_str($check_updates,$plg);
				if(isset($plg["$plg_key"])){
					return true;
				}
				else{
					return false;
				}
			}
		}
		public static function add_need_update($plg_key='',$fa_name='',$new_ver='',$old_ver='')
		{
			if($plg_key != '' && $new_ver != ''){
				if($old_ver == ''){
					$old_ver = self::CurrentVersion;
				}
				$check_updates = get_option('pec_need_update');
				$plg = array();
				if(!empty($check_updates)){
					parse_str($check_updates,$plg);
				}
				$plg["$plg_key"] = $fa_name.','.$old_ver.','.$new_ver;
				$need_up = '';
				foreach($plg as $key => $value){
					if($key != ''){
						if($need_up != '') $need_up .= '&';
						$need_up .= "$key=$value";
					}
				}
				update_option('pec_need_update',$need_up);
			}
		}
		public static function remove_need_update($plg_key)
		{
			$check_updates = get_option('pec_need_update');
			parse_str($check_updates,$plg);
			unset($plg["$plg_key"]);
			if(count($plg) == 0){
				delete_option('pec_need_update');
			}
			else{
				$need_up = '';
				foreach($plg as $key => $value){
					if($key != ''){
						if($need_up != '') $need_up .= '&';
						$need_up .= "$key=$value";
					}
				}
				update_option('pec_need_update',$need_up);
			}
		}
		public static function update_menu() {
			if ( empty ($GLOBALS['admin_page_hooks']['check_plg_version'] ) )
			add_menu_page("بروزرسانی افزونه پرداخت","افزونه پرداخت <span class='awaiting-mod count-1'><span class='pending-count'>ارتقاء</span></span>", 'manage_options', 'check_plg_version', array(__CLASS__, 'check_plg_version'),plugins_url("images/update_icon.png", __FILE__),80);
		}
		public static function check_plg_version() {
			if (!current_user_can('manage_options'))  {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}
			if(isset($_GET['upgrade']) && isset($_GET['plg']) && $_GET['plg'] != ''){
				$plg_key = $_GET['plg'];
				$update_status = '<div style="font-weight:bold;">بروزرسانی خودکار :</div><ul style="padding-right: 25px;line-height: 25px;">';
				$Request = self::PecReservoir('last_version',array('plugin'=>$plg_key));
				$check_updates = get_option('pec_need_update');
				parse_str($check_updates,$plgs);
				$update_status .= '<li>- آغاز بروزرسانی خودکار ...</li>';
				if(isset($plgs["$plg_key"])){
					$get_info = explode(',',$plgs["$plg_key"]);
					if($Request->LastVersion != $get_info[1])
					{
						$update_result = 'error';
						$zip_fname = basename($Request->DownloadLink,'.zip');
						$PlgPath = pathinfo(dirname(__FILE__));
						self::rrmdir($PlgPath['dirname'].'/'.$Request->PlgFolderName.'/update');
						$update_status .= '<li>- ایجاد مسیر پشتیبان برای افزونه فعلی</li>';
						if(!file_exists($PlgPath['dirname'].'/'.$Request->PlgFolderName.'/update')){mkdir($PlgPath['dirname'].'/'.$Request->PlgFolderName.'/update');}
						$ch = curl_init();
						curl_setopt($ch,CURLOPT_URL,$Request->DownloadLink);
						curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
						$data = curl_exec($ch);
						curl_close ($ch);
						$update_status .= '<li>- درخواست آخرین نسخه پلاگین</li>';
						$file = fopen($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update/$zip_fname.zip", "w+");
						fputs($file,$data);
						fclose($file);
						$update_status .= '<li>- دریافت آخرین نسخه پلاگین</li>';
						if(file_exists($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update/$zip_fname.zip"))
						{
							$update_status .= '<li>- آخرین نسخه با موفقیت دریافت و ذخیر شد</li>';
							$zip = new ZipArchive;
							$update_status .= '<li>- آغاز بروزرسانی پلاگین</li>';
							if ($zip->open($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update/$zip_fname.zip")){
								$zip->extractTo($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update/");
								$zip->close();
								self::copy_update($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update/$zip_fname",$PlgPath['dirname'].'/'.$Request->PlgFolderName);
								$update_result = 'success';
								self::remove_need_update($plg_key);
								$update_status .= '<li style="color:#008800;font-weight:bold;">»» بروزرسانی با موفقیت انجام شد.</li>';
							} else {
								$error_code = '-1';
							}
							self::rrmdir($PlgPath['dirname'].'/'.$Request->PlgFolderName."/update");
						}
						else{
							$error_code = '-2';
						}
					}
					else{
						$update_status .= '<li style="color:#008800;">- شما از آخرین نسخه پلاگین استفاده می کنید.</li>';
						$update_result = 'warning';
					}
				}
				else{
					$update_status .= '<li style="color:#008800;">- شما از آخرین نسخه پلاگین استفاده می کنید.</li>';
					$update_result = 'warning';
				}
				
				if(isset($error_code)){
					$update_status .= '<li style="color:#ff0000;font-weight:bold;">»» خطا در بروزرسانی خودکار.</li>';
					self::PecReservoir('update_error',array('plugin'=>$plg_key,'code'=>$error_code,'login_account'=>get_option('parsian_login_account')));
				}
				$update_status .= "</ul>";
				if($update_result == 'error'){
					$up_result_txt = 'خطا در بروزرسانی خودکار ، فایل های آخرین نسخه را از <a href="https://plugins.pec.ir/dl/wordpress/WooCommerce-Parsian-Gateway.zip" target="_blank" style="color:#0000ff;text-decoration:none;">اینجا</a> دریافت و جایگزین فایل های فعلی نمایید.';
					$up_txt_color = 'ff0000;';
				}
				elseif($update_result == 'warning'){
					$up_result_txt = 'شما در حال حاضر از آخرین نسخه پلاگین استفاده می کنید.';
					$up_txt_color = 'abb649;';
				}
				elseif($update_result == 'success'){
					$up_result_txt = 'بروزرسانی افزونه با موفقیت انجام شد.';
					$up_txt_color = '008800;';
				}
				if(isset($up_result_txt)){
					echo '
						<div class="wrap">
							<h1>بروزرسانی افزونه پرداخت</h1>
							<div class="notice notice-'.$update_result.' is-dismissible"><p>'.$up_result_txt.'</p></div>
							<div style="margin-top:25px;">'.$update_status.'</div>
							<div style="margin-top:40px;"><a href="admin.php?page=check_plg_version" class="button button-primary regular">بازگشت >></a></div>
						</div>
					';
				}
			}
			else{
				?>
				<div class="wrap">
					<h1>بروزرسانی افزونه پرداخت</h1>
					<?php
						$check_updates = get_option('pec_need_update');
						if($check_updates != ''){
							parse_str($check_updates,$plg);
							foreach($plg as $key => $value){
								$ver = explode(',',$value);
								?>
								<div style="margin:30px 0px 5px;padding:15px;border-top:1px dashed #000000;color:#0000ff;font-weight:bold;">افزونه <?php echo $ver[0]; ?> :</div>
								<table style="margin:0px 40px 20px 0px;font-size:13px;line-height: 25px;">
									<tr>
										<td style="font-weight:bold;">- نسخه مورد استفاده :</td>
										<td style="padding-right:15px;"><?php echo $ver[1]; ?></td>
									</tr>
									<tr>
										<td style="font-weight:bold;">- آخرین نسخه انتشار یافته :</td>
										<td style="padding-right:15px;"><?php echo $ver[2]; ?></td>
									</tr>
									<tr>
										<td colspan="2" style="font-weight:bold;">
											<?php
												if($ver[1] == $ver[2]) {
													self::remove_need_update($key);
													echo '<span style="color:#008800;">شما از آخرین نسخه پلاگین استفاده می کنید</span>';
												}
												else{
													echo '<span style="color:#ff0000;">پلاگین فعلی شما نیاز به بروزرسانی دارد</span>';
												}
											?>
										</td>
									</tr>
									<?php
										if($ver[1] != $ver[2]){
											echo '<tr><td colspan="2" style="text-align:center;padding-top:10px;"><a href="admin.php?page=check_plg_version&plg='.$key.'&upgrade" class="button button-primary regular">بروزرسانی خودکار</a></tr>';
										}
									?>
								</table>
								<?php
							}
						}
						else{
							echo '<div style="margin:40px 0px 30px 0px;text-align:center;font-weight:bold;">هیچ کدام از افزونه های پرداخت شما نیاز به بروزرسانی ندارند</div>';
						}
					?>
				</div>
				<?php
			}
		}
        public function process_payment($order_id) {
            $order = new WC_Order($order_id);
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url(true)
			);
        }
		public static function copy_update($src,$dst){
			$dir = opendir($src); 
			@mkdir($dst); 
			while(false !== ($file = readdir($dir))){ 
				if(($file != '.') && ($file != '..')){ 
					if(is_dir($src.'/'.$file)){ 
						self::copy_update($src.'/'.$file,$dst.'/'.$file); 
					} 
					else { 
						copy($src.'/'.$file,$dst.'/'.$file); 
					} 
				} 
			} 
			closedir($dir); 
		}
		public static function rrmdir($dir){
			if(is_dir($dir)){
				$objects = scandir($dir);
				foreach ($objects as $object) {
				  if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") 
					   self::rrmdir($dir."/".$object); 
					else unlink($dir."/".$object);
				  }
				}
				reset($objects);
				rmdir($dir);
			}
		}

        public function receipt_page($order_id) {

            global $woocommerce;
            $order = new WC_Order($order_id);
			$currency = $order->get_order_currency();
			$amount = intval($order->order_total);
			if ( strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN')
				|| strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN')
				|| strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN')
				|| strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN')
				|| strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
			)
				$amount = $amount*10;
			else if ( strtolower($currency) == strtolower('IRHT') )							
				$amount = $amount*1000*10;
			else if ( strtolower($currency) == strtolower('IRHR') )							
				$amount = $amount*1000;
			
			if($order_id > 0)
			{
				$LoginAccount	= $this->parsian_login_account;
				$CallBackUrl	= $this->redirect_uri . "?order_id=" . $order_id;
				$order_id		= $order_id.mt_rand(10,100);
				$Request = self::PecPayRequest($LoginAccount,$amount,$order_id,$CallBackUrl);
				if($Request->Status == '0' && $Request->Token > 0){
					self::d_redirect('https://pec.shaparak.ir/NewIPG/?Token='.$Request->Token);
					exit();
				}
				else{
					$error_code = $Request->Status;
				}
			}
			else{
				$error_code = 'error_creating_order';
			}
			self::display_error($error_code,'',$order_id,0);

            return false;
        }

        public function callback() {
			
            global $woocommerce;
			$order_id	= isset($_GET['order_id']) ? $_GET['order_id'] : '';
			$Token		= isset($_REQUEST['Token']) ? $_REQUEST['Token'] : '';
			$status		= isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
			$OrderId	= isset($_REQUEST['OrderId']) ? $_REQUEST['OrderId'] : '';
			$TerminalNo	= isset($_REQUEST['TerminalNo']) ? $_REQUEST['TerminalNo'] : '';
			$RRN		= isset($_REQUEST['RRN']) ? $_REQUEST['RRN'] : '';

			if($status == '0' && $Token > 0)
			{
				if($order_id == substr($OrderId,0,-2)){
					$order = new WC_Order($order_id);
					if(isset($order->id)){
						if($order->post_status == 'wc-completed'){
							$error_code = 'already_been_completed';
						}
						else{
							$LoginAccount = $this->parsian_login_account;
							$Request = self::PecVerifyRequest($LoginAccount,$Token);
							if($Request->Status == '0' && $Request->RRN > 0)
							{
								if($order->update_status('completed')){
									$order->payment_complete();
									$order->add_order_note('پرداخت شما با موفقیت با شماره پیگیری '.$Token.' انجام شد.', 1);
									$woocommerce->cart->empty_cart();
									wp_redirect($this->get_return_url($order));
									exit();
								}
								else{
									$reversal_request = self::PecReversalRequest($LoginAccount,$Token);
									if($reversal_request == '0'){
										$error_code = 'reversal_done';
									}
									else{
										$error_code = 'reversal_error';
									}
								}
							}
							elseif($Request->Status == '-1'){
								$error_code = 'retry';
							}
							else{
								$error_code = $Request->Status;
							}
						}
					}
					else{
						$error_code = 'order_not_exist';
					}
				}
				else{
					$error_code = 'order_not_for_this_person';
				}
			}
			else{
				$error_code = $status;
			}
			self::display_error($error_code,$Token,$order_id);
            exit;
        }
		public static function pec_wpwc_plugin_action_links($links, $file){
			static $this_plugin;

			if (false === isset($this_plugin) || true === empty($this_plugin)) {
				$this_plugin = plugin_basename(__FILE__);
			}

			if ($file == $this_plugin) {
				if(self::check_need_update(self::PlgSlug)){
					$help_link = '<a href="'.admin_url('admin.php?page=check_plg_version').'"><b style="color:#ff0000;">نیاز به بروزرسانی</b></a>';
					array_unshift($links, $help_link);
				}
				else{
					$help_link = '<a href="https://plugins.pec.ir/%d9%be%d9%84%d8%a7%da%af%db%8c%d9%86-%d9%be%d8%b1%d8%af%d8%a7%d8%ae%d8%aa-%d9%be%d8%a7%d8%b1%d8%b3%db%8c%d8%a7%d9%86-%d8%a8%d8%b1%d8%a7%db%8c-%d8%a7%d9%81%d8%b2%d9%88%d9%86%d9%87-woocommerce-%d9%88/">آموزش نصب</a>';
					array_unshift($links, $help_link);
					$settings_link = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=parsian').'">تنظیمات</a>';
					array_unshift($links, $settings_link);
				}
			}

			return $links;
		}
    }

    function woocommerce_add_parsian_gateway_method($methods) {
        $methods[] = 'WC_Parsian_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_parsian_gateway_method');
}

add_action('plugins_loaded', 'woocommerce_parsian_payment_init', 0);
