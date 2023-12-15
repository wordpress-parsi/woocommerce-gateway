<?php

if (!class_exists('VandaPardakht_Payment_Helper'))
{
	class VandaPardakht_Payment_Helper
	{
		public function __construct($pin = '')
		{
			$this->pin = $pin;
		}
		public function paymentRequest($data)
		{
			// ? $data['amount'] = intval($data['amount'] / 10);
			$result = $this->curl('https://vandapardakht.com/Request', $post = array(
					'pin'         => $this->pin,
					'price'       => $data['amount'],
					'callback'    => $data['callback'],
					'order_id'    => @$data['order_id'] ?: time(),
					'email'       => @$data['email'],
					'description' => @$data['description'],
					'name'        => @$data['name'],
					'tell'        => @$data['mobile'],
					'ip'          => @$data['ip'] ?: $_SERVER['REMOTE_ADDR'],
				));
			if (is_object($result) && isset($result->result)) {
				if ($result->result == 1) {
					$this->data = $result;
					return $result->vprescode;
				} else {
					$this->error = $this->getError($result->result);
				}
			} else {
				$this->error = 'خطا در ارتباط با درگاه وندا پرداخت';
			}
			return false;
		}
		public function paymentVerify($data)
		{
			// ? $data['price'] = intval($data['price'] / 10);
			$result = $this->curl('https://vandapardakht.com/Verify', $p = array_merge($data, array('pin' => $this->pin)));
			if (is_object($result) && isset($result->result)) {
				if ($result->result == 1) {
					$this->txn_id = $data['vprescode'];
					return true;
				} else {
					$this->error = $this->getError($result->result);
				}
			} else {
				$this->error = 'خطا در ارتباط با درگاه وندا پرداخت';
			}
			return false;
		}
		public function getError($code)
		{
			switch ($code)
			{
				case '1':	return 'تراکنش با موفقیت انجام شد';
				case '0':	return 'تراکنش لغو شد';
				case '-1':	return 'پارامترهای ارسالی برای متد مورد نظر ناقص یا خالی هستند . پارمترهای اجباری باید ارسال گردد';
				case '-2':	return 'دسترسی api برای شما مسدود است';
				case '-6':	return 'عدم توانایی اتصال به گیت وی بانک از سمت وبسرویس';
				case '-9':	return 'خطای ناشناخته';
				case '-14':	return 'پین نامعتبر';
				case '-20':	return 'پین نامعتبر';
				case '-21':	return 'ip نامعتبر';
				case '-22':	return 'مبلغ وارد شده کمتر از حداقل مجاز میباشد';
				case '-23':	return 'مبلغ وارد شده بیشتر از حداکثر مبلغ مجاز هست';
				case '-24':	return 'مبلغ وارد شده نامعتبر';
				case '-26':	return 'درگاه غیرفعال است';
				case '-27':	return 'آی پی مسدود شده است';
				case '-28':	return 'آدرس کال بک نامعتبر است ، احتمال مغایرت با آدرس ثبت شده';
				case '-29':	return 'آدرس کال بک خالی یا نامعتبر است';
				case '-30':	return 'چنین تراکنشی یافت نشد';
				case '-31':	return 'تراکنش ناموفق است';
				case '-32':	return 'مغایرت مبالغ اعلام شده با مبلغ تراکنش';
				case '-35':	return 'شناسه فاکتور اعلامی order_id نامعتبر است';
				case '-36':	return 'پارامترهای برگشتی بانک bank_return نامعتبر است';
				case '-38':	return 'تراکنش برای چندمین بار وریفای شده است';
				case '-39':	return 'تراکنش در حال انجام است';

			}
			return 'خطای غیر منتظره (کد : '.$code.')';
		}
		public function curl($url, $post)
		{
			if (!is_wp_error($result = wp_remote_post($url, array('body' => wp_json_encode($post), 'headers' => array('Content-Type' => 'application/json'), 'sslverify' => false))))
			{
				return json_decode($result['body']);
			}
		}
	}
}
