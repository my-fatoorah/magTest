<?php

namespace MyFatoorah\Gateway\Model;

use MyFatoorah\Gateway\Controller\Checkout\Success;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order;
use MyFatoorah\Library\PaymentMyfatoorahApiV2;

class WebHook {

    private $scopeStore = ScopeInterface::SCOPE_STORE;
    private $scopeConfig;
    private $successObj;
    private $orderModel;

//-----------------------------------------------------------------------------------------------------------------------------------------
    public function __construct(
            ScopeConfigInterface $scopeConfig,
            Success $successObj,
            Order $orderModel
    ) {

        $this->scopeConfig = $scopeConfig;

        $this->successObj = $successObj;
        $this->orderModel = $orderModel;
    }

//-----------------------------------------------------------------------------------------------------------------------------------------    

    /**
     * {@inheritdoc}
     */
    public function execute($EventType, $Event, $DateTime, $CountryIsoCode, $Data) {

        //to allow the callback code run 1st. 
        sleep(30);

        if ($EventType != 1) {
            return;
        }

        $this->TransactionsStatusChanged($Data);
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
    function TransactionsStatusChanged($data) {

        $orderId = $data['CustomerReference'];
        try {
            //get the order to get its store
            $order = $this->orderModel->loadByIncrementId($orderId);
            if (!$order->getId()) {
                throw new \Exception('MyFatoorah returned an order that could not be retrieved');
            }

            //get the order store config
            $path    = 'payment/myfatoorah_payment/';
            $storeId = $order->getStoreId();

            $apiKey      = $this->scopeConfig->getValue($path . 'api_key', $this->scopeStore, $storeId);
            $isTesting   = $this->scopeConfig->getValue($path . 'is_testing', $this->scopeStore, $storeId);
            $countryMode = $this->scopeConfig->getValue($path . 'countryMode', $this->scopeStore, $storeId);

            $webhookSecretKey = $this->scopeConfig->getValue($path . 'webhookSecretKey', $this->scopeStore, $storeId);

            //get lib object
            $mfObj = new PaymentMyfatoorahApiV2($apiKey, $countryMode, $isTesting, MYFATOORAH_LOG_FILE);

            //get MyFatoorah Signature from request headers
            $apache      = apache_request_headers();
            $headers     = array_change_key_case($apache);
            $mfSignature = empty($headers['myfatoorah-signature']) ? die : $headers['myfatoorah-signature'];

            //validate signature
            if (!$mfObj->isSignatureValid($data, $webhookSecretKey, $mfSignature)) {
                return;
            }

            //update order status
            $this->successObj->checkStatus($data['InvoiceId'], 'InvoiceId', $mfObj, '-WebHook', $order->getRealOrderId());
        } catch (\Exception $ex) {
            error_log(PHP_EOL . date('d.m.Y h:i:s') . ' - ' . $ex->getMessage(), 3, MYFATOORAH_LOG_FILE);
        }
    }

//-----------------------------------------------------------------------------------------------------------------------------------------
}
