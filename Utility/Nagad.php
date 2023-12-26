<?php

require_once 'Config.php';
require_once 'NagadUtility.php';

class Nagad
{
    private $tnxID;
    private $nagadHost;
    private $merchantAdditionalInfo = [];

    public function __construct()
    {
        date_default_timezone_set('Asia/Dhaka');

        if (NAGAD_MODE === 'sandbox') {
            $this->nagadHost = "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/";
        } else {
            $this->nagadHost = "https://api.mynagad.com/";
        }
    }

    public function getRedirectUrl($amount)
    {
        global $nagad_callback_url;
        $dateTime = Date('YmdHis');
        $invoiceNo = 'Inv' . $dateTime . rand(100, 1000);
        $this->tnxID = $invoiceNo;

        $SensitiveData = [
            'merchantId' => NAGAD_MERCHANT_ID,
            'datetime' => $dateTime,
            'orderId' => $invoiceNo,
            'challenge' => NagadUtility::generateRandomString()
        ];

        $PostData = [
            'accountNumber' => NAGAD_MERCHANT_NUMBER,
            'dateTime' => $dateTime,
            'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveData)),
            'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveData))
        ];

        $url = $this->nagadHost . "api/dfs/check-out/initialize/" . NAGAD_MERCHANT_ID . "/" . $invoiceNo;
        $Result_Data = NagadUtility::HttpPostMethod($url, $PostData);

        if (!empty($Result_Data['sensitiveData']) && !empty($Result_Data['signature'])) {
            $PlainResponse = json_decode(NagadUtility::DecryptDataWithPrivateKey($Result_Data['sensitiveData']), true);

            if (isset($PlainResponse['paymentReferenceId']) && isset($PlainResponse['challenge'])) {
                $paymentReferenceId = $PlainResponse['paymentReferenceId'];
                $randomServer = $PlainResponse['challenge'];

                $SensitiveDataOrder = [
                    'merchantId' => NAGAD_MERCHANT_ID,
                    'orderId' => $invoiceNo,
                    'currencyCode' => '050',
                    'amount' => $amount,
                    'challenge' => $randomServer
                ];

                if ($this->tnxID !== '') {
                    $this->merchantAdditionalInfo['tnx_id'] = $this->tnxID;
                }

                $PostDataOrder = [
                    'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveDataOrder)),
                    'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveDataOrder)),
                    'merchantCallbackURL' => $nagad_callback_url,
                    'additionalMerchantInfo' => (object)$this->merchantAdditionalInfo
                ];

                $OrderSubmitUrl = $this->nagadHost . "api/dfs/check-out/complete/" . $paymentReferenceId;
                $Result_Data_Order = NagadUtility::HttpPostMethod($OrderSubmitUrl, $PostDataOrder);

                try {
                    if (isset($Result_Data_Order['status']) && $Result_Data_Order['status'] == "Success") {
                        $url = ($Result_Data_Order['callBackUrl']);
                        return $url;

                        // header("Location: $url");
                        // exit();
                    } else {
                        return $Result_Data_Order['status'];
                    }
                } catch (\Exception $e) {
                    return "Error: " . $e->getMessage();
                }
            } else {
                return $PlainResponse;
            }
        } else {
            return $Result_Data;
        }
    }

    public function verify()
    {
        $Query_String = explode("&", explode("?", $_SERVER['REQUEST_URI'])[1]);
        $payment_ref_id = substr($Query_String[2], 15);
        $url = $this->nagadHost . "/verify/payment/" . $payment_ref_id;
        $json = NagadUtility::HttpGet($url);
        $arr = json_decode($json, true);
        return $arr;
    }
}
