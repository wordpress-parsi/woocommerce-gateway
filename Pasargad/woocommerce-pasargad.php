<?php
/*
  Plugin Name: درگاه پرداخت پاسارگاد برای ووکامرس
  Plugin URI: https://pep.co.ir
  Description: این افزونه درگاه پرداخت پاسارگاد را به روش های پرداختی ووکامرس اضافه می کند.
  Version: 1.0
  Author: شرکت پرداخت الکترونیک پاسارگاد
  Author URI: https://pep.co.ir
 */

function woocommerce_pasargad_payment_init()
{

    if (!class_exists('WC_Payment_Gateway'))
        return;
    add_filter('plugin_action_links', array('WC_Pasargad_Gateway', 'pep_wpwc_plugin_action_links'), 10, 2);

    class WC_Pasargad_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'pasargad';
            $this->method_title = 'بانک پاسارگاد';
            $this->method_description = 'تنظیمات درگاه امن پاسارگاد برای فروشگاه ساز ووکامرس';
            $this->has_fields = false;
            $this->redirect_uri = WC()->api_request_url('WC_Pasargad_Gateway');
            $this->icon = apply_filters('pasargad_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/images/logo.png');
            

            $this->init_form_fields();
            $this->init_settings();

            $this->pasargad_terminal_id = $this->settings['pasargad_terminal_id'];
            $this->pasargad_merchant_id = $this->settings['pasargad_merchant_id'];
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

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_pasargad', array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_pasargad_gateway', array($this, 'callback'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'فعال / غیرفعال :',
                    'type' => 'checkbox',
                    'label' => 'فعال یا غیرفعال سازی درگاه پرداخت پاسارگاد',
                    'default' => 'no'
                ),
                'pasargad_terminal_id' => array(
                    'title' => 'شماره ترمینال :',
                    'type' => 'text',
                    'required' => true,
                    'desc_tip' => true,
                ),
                'pasargad_merchant_id' => array(
                    'title' => 'شماره فروشگاه :',
                    'type' => 'text',
                    'required' => true,
                    'desc_tip' => true,
                ),
                'title' => array(
                    'title' => 'عنوان درگاه :',
                    'type' => 'text',
                    'description' => 'این نام در طی فرایند خرید به مشتری نمایش داده می شود',
                    'default' => 'بانک پاسارگاد'
                ),
                'description' => array(
                    'title' => 'توضیحات درگاه',
                    'type' => 'textarea',
                    'description' => 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد',
                    'default' => 'پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق بانک پاسارگاد'
                ),
                'success_massage' => array(
                    'title' => 'پیام پرداخت موفق',
                    'type' => 'textarea',
                    'description' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید ، می توانید از شورت کد {transaction_id} نیز برای نمایش کد رهگیری تراکنش و از شرت کد {SaleOrderId} برای شماره درخواست استفاده نمایید .',
                    'default' => 'با تشکر از شما . سفارش شما با موفقیت پرداخت شد .',
                ),
                'failed_massage' => array(
                    'title' => 'پیام پرداخت ناموفق',
                    'type' => 'textarea',
                    'description' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید ، می توانید از شورت کد {fault} نیز برای نمایش دلیل خطای رخ داده استفاده نمایید.',
                    'default' => 'پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .',
                ),
                'cancelled_massage' => array(
                    'title' => 'پیام انصراف از پرداخت',
                    'type' => 'textarea',
                    'description' => 'متن پیامی که میخواهید بعد از انصراف کاربر از پرداخت نمایش دهید را وارد نمایید . این پیام بعد از بازگشت از بانک نمایش داده خواهد شد .',
                    'default' => 'پرداخت به دلیل انصراف شما ناتمام باقی ماند .',
                ),
            );
        }

        public function admin_options()
        {

            if ($this->enviroment == 'production' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
                echo '<div class="error"><p>' . sprintf(__('%s Pasargad Sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
            }

            echo '<h3>تنظیمات درگاه پاسارگاد :</h3>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        public static function PepPayRequest($InvoiceNumber, $TerminalCode, $MerchantCode, $Amount, $RedirectAddress, $Mobile = '', $Email = '')
        {
            require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
            $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml', RSAKeyType::XMLFile);
            if (!function_exists('jdate')) {
                require_once(dirname(__FILE__) . '/includes/jdf.php');
            }
            $data = array(
                'InvoiceNumber' => $InvoiceNumber,
                'InvoiceDate' => jdate('Y/m/d'),
                'TerminalCode' => $TerminalCode,
                'MerchantCode' => $MerchantCode,
                'Amount' => $Amount,
                'RedirectAddress' => $RedirectAddress,
                'Timestamp' => date('Y/m/d H:i:s'),
                'Action' => 1003,
                'Mobile' => $Mobile,
                'Email' => $Email
            );

            $sign_data = json_encode($data);
            $sign_data = sha1($sign_data, true);
            $sign_data = $processor->sign($sign_data);
            $sign = base64_encode($sign_data);

            $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/GetToken');
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Sign: ' . $sign
                )
            );
            $result = json_decode(curl_exec($curl));
            curl_close($curl);

            return $result;
        }

        public static function PepCheckTransactionResult($TransactionReferenceID, $InvoiceNumber = '', $InvoiceDate = '', $TerminalCode = '', $MerchantCode = '')
        {
            $data = array(
                'InvoiceNumber' => $InvoiceNumber,
                'InvoiceDate' => $InvoiceDate,
                'TerminalCode' => $TerminalCode,
                'MerchantCode' => $MerchantCode,
                'TransactionReferenceID' => $TransactionReferenceID
            );
            $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult');
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json'
                )
            );
            $result = json_decode(curl_exec($curl));
            curl_close($curl);

            return $result;
        }

        public static function PepVerifyRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode, $Amount)
        {
            require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
            $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml', RSAKeyType::XMLFile);
            $data = array(
                'InvoiceNumber' => $InvoiceNumber,
                'InvoiceDate' => $InvoiceDate,
                'TerminalCode' => $TerminalCode,
                'MerchantCode' => $MerchantCode,
                'Amount' => $Amount,
                'Timestamp' => date('Y/m/d H:i:s')
            );

            $sign_data = json_encode($data);
            $sign_data = sha1($sign_data, true);
            $sign_data = $processor->sign($sign_data);
            $sign = base64_encode($sign_data);

            $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment');
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Sign: ' . $sign
                )
            );
            $result = json_decode(curl_exec($curl));
            curl_close($curl);

            return $result;
        }

        public static function PepReversalRequest($InvoiceNumber, $InvoiceDate, $TerminalCode, $MerchantCode)
        {
            require_once(dirname(__FILE__) . '/includes/RSAProcessor.class.php');
            $processor = new RSAProcessor(dirname(__FILE__) . '/includes/certificate.xml', RSAKeyType::XMLFile);
            $data = array(
                'InvoiceNumber' => $InvoiceNumber,
                'InvoiceDate' => $InvoiceDate,
                'TerminalCode' => $TerminalCode,
                'MerchantCode' => $MerchantCode,
                'Timestamp' => date('Y/m/d H:i:s')
            );

            $sign_data = json_encode($data);
            $sign_data = sha1($sign_data, true);
            $sign_data = $processor->sign($sign_data);
            $sign = base64_encode($sign_data);

            $curl = curl_init('https://pep.shaparak.ir/Api/v1/Payment/RefundPayment');
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Sign: ' . $sign
                )
            );
            $result = json_decode(curl_exec($curl));
            curl_close($curl);

            return $result;
        }

        public static function PecStatus($code = '', $error_page = 0)
        {

            switch ($code) {
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

        public static function PecReservoir($type, $params)
        {
            $post = "type=$type";
            foreach ($params as $key => $value) {
                $post .= "&$key=$value";
            }
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, 'https://pep.co.ir/dl/check_update/wordpress/index.php');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result = json_decode(curl_exec($curl));
            curl_close($curl);
            return $result;
        }

        public static function display_error($pay_status = '', $tran_id = '', $order_id = '', $is_callback = 1, $message)
        {
            $page_html = '<div align="center" dir="rtl" style="font-family:tahoma;font-size:12px;line-height: 25px;color:#000000;margin: -25px 0px -23px 0px;">';
            if ($pay_status == 'retry') {
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
            } elseif ($pay_status == 'reversal_done') {
                $page_title = 'مشکل در ارائه خدمات';
                $admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، دستور بازگشت وجه به حساب خریدار در بانک ثبت شد';
                $page_html .= '<span style="color:#ff0000;"><b>مشکل در ارائه خدمات</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= "پرداخت شما با شماره پیگیری $tran_id با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />";
                $page_html .= 'دستور بازگشت وجه به حساب شما در بانک ثبت شده است ، در صورتی که وجه پرداختی تا ساعات آینده به حساب شما بازگشت داده نشد با پشتیبانی تماس بگیرید (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($pay_status == 'reversal_error') {
                $page_title = 'مشکل در ارائه خدمات';
                $admin_mess = 'خریدار مبلغ را پرداخت کرد اما در هنگام بازگشت از بانک مشکلی در ارائه خدمات رخ داد ، ثبت دستور بازگشت وجه به حساب خریدار نیز با خطا روبرو بود ، به این خریدار می بایست یا خدمات ارائه شود و یا مبلغ به حساب بانکی وی عودت داده شود.';
                $page_html .= '<span style="color:#ff0000;"><b>مشکل در ارائه خدمات</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= "پرداخت شما با شماره پیگیری $tran_id با موفقیت در بانک انجام شده است اما در ارائه خدمات مشکلی رخ داده است !<br />";
                $page_html .= 'به منظور ثبت دستور بازگشت وجه به حساب شما در بانک اقدام شد اما متاسفانه با خطا روبرو شد ، لطفا به منظور دریافت خدمات و یا استرداد وجه پرداختی با پشتیبانی تماس بگیرید';
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($pay_status == 'already_been_completed') {
                $page_title = 'سفارش پیش از این موفق شده است';
                $page_html .= '<span style="color:#008800;"><b>سفارش پیش از این موفق شده است</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= "سفارش شما پیش از این با شماره پیگیری $tran_id با موفقیت ثبت شده است";
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($pay_status == 'order_not_exist') {
                $page_title = 'سفارش یافت نشد';
                $admin_mess = 'سفارش در سایت یافت نشد';
                $page_html .= '<span style="color:#ff0000;"><b>سفارش یافت نشد</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= 'متاسفانه سفارش شما در سایت یافت نشد ! در صورتی که وجه پرداختی از حساب بانکی شما کسر شده باشد به صورت خودکار از سوی بانک به حساب شما باز خواهد گشت (نهایت مدت زمان بازگشت به حساب 72 ساعت می باشد)';
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($pay_status == 'order_not_for_this_person') {
                $page_title = 'شماره سفارش نادرست است';
                $admin_mess = 'شماره سفارش نادرست است';
                $page_html .= '<span style="color:#ff0000;"><b>شماره سفارش نادرست است</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= 'شماره سفارش نادرست است ؛ در صورت نیاز به پشتیبانی تماس بگیرید';
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($pay_status == 'error_creating_order') {
                $page_title = 'مشکل در ثبت سفارش';
                $admin_mess = 'مشکل در ثبت سفارش';
                $page_html .= '<span style="color:#ff0000;"><b>مشکل در ثبت سفارش</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= 'مشکلی در ثبت سفارش وجود دارد ، لطفا این موضوع را با پشتیبانی در میان بگذارید';
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } elseif ($is_callback == 0) {
                $page_title = $admin_mess = 'خطا در ارسال به بانک';
                $page_html .= '<span style="color:#ff0000;"><b>خطا در ارسال به بانک</b></span><br/>';
                $page_html .= '<p style="font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= "خطا در ارسال به بانک : " . $message;
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';

            } else {
                $page_title = $admin_mess = 'پرداخت انجام نشد';
                $page_html .= '<span style="color:#ff0000;"><b>پرداخت انجام نشد</b></span><br/>';
                $page_html .= '<p style="margin-right:15px;font-size: 12px;margin: 15px 0px 20px 0px;line-height: 25px;">';
                $page_html .= $message;
                $page_html .= '</p>';
                $page_html .= '<a href="' . get_option('siteurl') . '" style="text-decoration:none;">بازگشت به صفحه نخست >></a><br/>';
            }
            $page_html .= '</div>';

            if (isset($admin_mess) && $order_id != '' && $pay_status != 'order_not_for_this_person') {
                $order = new WC_Order(substr($order_id, 0, -2));
                $order->add_order_note($admin_mess);
            }
            if($is_callback == 1) {
                wp_die($page_html, $page_title);
            }
            else {
                echo $page_html;
            }
        }

        public static function d_redirect($url = '')
        {
            if ($url != '') {
                if (headers_sent()) {
                    echo '<script type="text/javascript">window.location.assign("' . $url . '")</script>';
                } else {
                    header("Location: $url");
                }
                exit();
            }
        }

        public function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        public function receipt_page($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $currency = $order->get_order_currency();
            $amount = intval($order->order_total);
            if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN')
                || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN')
                || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN')
                || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN')
                || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
            )
                $amount = $amount * 10;
            else if (strtolower($currency) == strtolower('IRHT'))
                $amount = $amount * 1000 * 10;
            else if (strtolower($currency) == strtolower('IRHR'))
                $amount = $amount * 1000;

            $TerminalID = $this->pasargad_terminal_id;
            $MerchantID = $this->pasargad_merchant_id;
            $CallBackUrl = $this->redirect_uri . "?order_id=" . $order_id;
            $order_id = $order_id . mt_rand(10, 100);
            $Request = self::PepPayRequest($order_id, $TerminalID, $MerchantID, $amount, $CallBackUrl);
            if (isset($Request) && $Request->IsSuccess) {
                self::d_redirect('https://pep.shaparak.ir/payment.aspx?n=' . $Request->Token);
            }
            else {
                self::display_error('', '', $order_id, 0, isset($Request->Message) ? $Request->Message : 'خطای نامشخص');
            }

            return false;
        }

        public function callback()
        {
            global $woocommerce;
            $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';
            $TransactionReferenceID = isset($_REQUEST['tref']) ? $_REQUEST['tref'] : '';
            $InvoiceNumber = isset($_REQUEST['iN']) ? $_REQUEST['iN'] : '';
            $InvoiceDate = isset($_REQUEST['iD']) ? $_REQUEST['iD'] : '';
            $TerminalID = $this->pasargad_terminal_id;
            $MerchantID = $this->pasargad_merchant_id;

            if ($order_id == substr($InvoiceNumber, 0, -2)) {
                $order = new WC_Order($order_id);
                if (isset($order->id)) {
                    if ($order->post_status == 'wc-completed') {
                        $error_code = 'already_been_completed';
                    } else {
                        if ($TransactionReferenceID != '') {
                            $checkResult = self::PepCheckTransactionResult($TransactionReferenceID);
                        } else {
                            $checkResult = self::PepCheckTransactionResult(null, $InvoiceNumber, $InvoiceDate, $TerminalID, $MerchantID);
                        }

                        if (isset($checkResult) && $checkResult->IsSuccess && $checkResult->InvoiceNumber == $InvoiceNumber) {
                            $amount = $checkResult->Amount;
                            $Request = self::PepVerifyRequest($InvoiceNumber, $InvoiceDate, $TerminalID, $MerchantID, $amount);
                            if (isset($Request) && $Request->IsSuccess) {
                                if ($order->update_status('processing')) {
                                    $order->payment_complete();
                                    $order->add_order_note('پرداخت شما با موفقیت با شماره پیگیری ' . $TransactionReferenceID . ' انجام شد.', 1);
                                    $woocommerce->cart->empty_cart();
                                    wp_redirect($this->get_return_url($order));
                                    exit();
                                } else {
                                    $reversal_request = self::PepReversalRequest($InvoiceNumber, $InvoiceDate, $TerminalID, $MerchantID);
                                    if (isset($reversal_request) && $reversal_request->IsSuccess) {
                                        $error_code = 'reversal_done';
                                    } else {
                                        $error_code = 'reversal_error';
                                    }
                                }
                            }
                            else {
                                $message = $Request->Message;
                            }
                        }
                        else {
                            $message = 'پرداخت توسط شما انجام نشده است ، در صورت نیاز با پشتیبانی تماس بگیرید';
                        }
                    }
                } else {
                    $error_code = 'order_not_exist';
                }
            }
            else {
                $error_code = 'order_not_for_this_person';
            }
            self::display_error(isset($error_code) ? $error_code : null, $TransactionReferenceID, $sessionid, 1, isset($message) ? $message : '');
            exit;
        }

        public static function pep_wpwc_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (false === isset($this_plugin) || true === empty($this_plugin)) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pasargad') . '">تنظیمات</a>';
                array_unshift($links, $settings_link);
            }

            return $links;
        }
    }

    function woocommerce_add_pasargad_gateway_method($methods)
    {
        $methods[] = 'WC_Pasargad_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_pasargad_gateway_method');
}

add_action('plugins_loaded', 'woocommerce_pasargad_payment_init', 0);
