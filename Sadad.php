<?php

namespace homaworkshop\sadad;

use yii\base\Model;
use yii\web\HttpException;

class Sadad extends Model
{
    const merchant_id = '';
    const terminal_id = '';
    const terminal_key = '';
    private $system_trace_no;
    private $retrival_ref_no;

    public function request($amount, $order_id = NULL, $callback = NULL)
    {
        $SignData = $this->encrypt_function(self::terminal_id . ';' . $order_id . ';' . $amount, self::terminal_key);

        $data = array(
            'MerchantID' => self::merchant_id,
            'TerminalId' => self::terminal_id,
            'Amount' => $amount,
            'OrderId' => $order_id,
            'LocalDateTime' => date("m/d/Y g:i:s a"),
            'ReturnUrl' => $callback,
            'SignData' => $SignData,
        );

        $result = $this->call_api('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest', $data);

        if (!$result) {
            throw new HttpException(404, 'مشکلی در اتصال به درگاه پرداخت اینترنتی سداد پیش آمده است!');
        } else {
            if ($result->ResCode == 0) {
                $Token = $result->Token;
                $url = "https://sadad.shaparak.ir/VPG/Purchase?Token=$Token";
                header("Location:$url");
                exit;
            } else {
                throw new HttpException(404, $result->Description);
            }
        }
    }

    public function verify()
    {
        try {
            if ($_POST['OrderId'] && $_POST['token']) {
                if ($_POST['ResCode'] == "0") {
                    $Token = $_POST['token'];

                    // verify payment
                    $verifyData = array(
                        'Token' => $Token,
                        'SignData' => $this->encrypt_function($Token, self::terminal_key),
                    );

                    $result = $this->call_api('https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify', $verifyData);

                    if (!$result) {
                        throw new HttpException(404, 'پرداخت ناموفق بود در صورت کسر وجه تا 72 ساعت دیگر به حسابتان بازگشت داده میشود!');
                    } else {
                        if ($result->ResCode != -1 && $result->ResCode == 0) {
                            $this->system_trace_no = $result->SystemTraceNo;
                            $this->retrival_ref_no = $result->RetrivalRefNo;
                    
                            return $this;
                        } else {
                            throw new HttpException(404, 'پرداخت ناموفق بود در صورت کسر وجه تا 72 ساعت دیگر به حسابتان بازگشت داده میشود!');
                        }
                    }
                } else {
                    throw new HttpException(404, 'پرداخت ناموفق بود در صورت کسر وجه تا 72 ساعت دیگر به حسابتان بازگشت داده میشود!');
                }
            } else {
                throw new HttpException(404, 'پرداخت ناموفق بود در صورت کسر وجه تا 72 ساعت دیگر به حسابتان بازگشت داده میشود!');
            }
        } catch (\Throwable $e) {
            throw new HttpException(404, 'پرداخت ناموفق بود در صورت کسر وجه تا 72 ساعت دیگر به حسابتان بازگشت داده میشود!');
        }
    }

    public function getSystemTraceNo()
    {
        return $this->system_trace_no;
    }

    public function getRetrivalRefNo()
    {
        return $this->retrival_ref_no;
    }

    private function encrypt_function($data, $key)
    {
        $key = base64_decode($key);
        $ciphertext = OpenSSL_encrypt($data, "DES-EDE3", $key, OPENSSL_RAW_DATA);
        return base64_encode($ciphertext);
    }

    private function call_api($url, $data = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = curl_exec($ch);
        curl_close($ch);
        return !empty($result) ? json_decode($result) : false;
    }
}
