<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Sales\Model\Order;

class Index extends AbstractAction {

    public $orderId = null;

//---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @return void
     */
    public function execute() {

        try {
            $order = $this->getOrder();

            $this->order   = $order;
            $this->orderId = $order->getRealOrderId();

            if ($order->getState() === Order::STATE_CANCELED) {
                $errorMessage = $this->getCheckoutSession()->getMyFatoorahErrorMessage(); //set in InitializationRequest
                if ($errorMessage) {
                    $this->getMessageManager()->addWarningMessage($errorMessage);
                    $errorMessage = $this->getCheckoutSession()->unsMyFatoorahErrorMessage();
                }
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
                $this->_redirect('checkout/cart');
            } else {
                $this->postToCheckout($order);
            }
        } catch (\Exception $ex) {
            $err = $ex->getMessage();
            $url = $this->getDataHelper()->getCancelledUrl($this->orderId, urlencode($err));
            $this->_redirect($url);
        }
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------

    /** @var \Magento\Sales\Model\Order $order */
    private function getPayload($order, $gateway = null) {

        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $addressObj = $order->getShippingAddress();
        if (!is_object($addressObj)) {
            $addressObj = $order->getBillingAddress();
            if (!is_object($addressObj)) {
                throw new \Exception('Billing Address or Shipping address Data Should be set to create the invoice');
            }
        }

        $addressData = $addressObj->getData();

        $countryCode = isset($addressData['country_id']) ? $addressData['country_id'] : '';
        $city        = isset($addressData['city']) ? $addressData['city'] : '';
        $postcode    = isset($addressData['postcode']) ? $addressData['postcode'] : '';
        $region      = isset($addressData['region']) ? $addressData['region'] : '';

        $street1 = isset($addressData['street']) ? $addressData['street'] : '';
        $street  = trim(preg_replace("/[\n]/", ' ', $street1 . ' ' . $region));

        $phoneNo = isset($addressData['telephone']) ? $addressData['telephone'] : '';

        //$order->getCustomerName()  //$order->getCustomerFirstname() //$order->getCustomerLastname()
        $fName = !empty($addressObj->getFirstname()) ? $addressObj->getFirstname() : '';
        $lName = !empty($addressObj->getLastname()) ? $addressObj->getLastname() : '';

        $email = $order->getData('customer_email'); //$order->getCustomerEmail()


        $getLocale = $this->objectManager->get('Magento\Framework\Locale\Resolver');
        $haystack  = $getLocale->getLocale();
        $lang      = strstr($haystack, '_', true);

        $phone = $this->mfObj->getPhone($phoneNo);
//        $url   = $this->getDataHelper()->getCompleteUrl();
        $url   = $this->getDataHelper()->getProcessUrl();

        $userDefinedField = ($this->_gatewayConfig->getSaveCard() && $order->getCustomerId()) ? 'CK-' . $order->getCustomerId() : null;

        $shippingMethod = $order->getShippingMethod();
        $isShipping     = null;
        if (($shippingMethod == 'myfatoorah_shipping_1') || ($shippingMethod == 'myfatoorah_shippingDHL_myfatoorah_shippingDHL')) {
            $isShipping = 1;
        } else if (($shippingMethod == 'myfatoorah_shipping_2') || ($shippingMethod == 'myfatoorah_shippingAramex_myfatoorah_shippingAramex')) {
            $isShipping = 2;
        }

        $shippingConsignee = !$isShipping ? '' : array(
            'PersonName'   => "$fName $lName",
            'Mobile'       => trim($phone[1]),
            'EmailAddress' => $email,
            'LineAddress'  => trim(preg_replace("/[\n]/", ' ', $street . ' ' . $region)),
            'CityName'     => $city,
            'PostalCode'   => $postcode,
            'CountryCode'  => $countryCode
        );

        $currency = $this->getCurrencyData($gateway);

        //$invoiceItemsArr
        if ($isShipping || $this->_gatewayConfig->listInvoiceItems()) {
            $invoiceValue    = 0;
            $invoiceItemsArr = $this->getCheckoutHelper()->getInvoiceItems($order, $currency['rate'], $isShipping, $invoiceValue, true);
        } else {
            $invoiceValue    = round($order->getBaseTotalDue() * $currency['rate'], 3);
            $invoiceItemsArr = [['ItemName' => "Total Amount Order #$this->orderId", 'Quantity' => 1, 'UnitPrice' => "$invoiceValue"]];
        }

        //ExpiryDate
        $expireAfter = $this->getPendingOrderLifetime(); //get Magento Pending Payment Order Lifetime (minutes)

        $ExpiryDate = new \DateTime('now', new \DateTimeZone('Asia/Kuwait'));
        $ExpiryDate->modify("+$expireAfter minute");
        return [
            'CustomerName'       => $fName . ' ' . $lName,
            'InvoiceValue'       => "$invoiceValue",
            'DisplayCurrencyIso' => $currency['code'], //$order->getOrderCurrencyCode(),
            'MobileCountryCode'  => trim($phone[0]),
            'CustomerMobile'     => trim($phone[1]),
            'CustomerEmail'      => $email,
            'CallBackUrl'        => $url,
            'ErrorUrl'           => $url,
            'Language'           => $lang,
            'CustomerReference'  => $this->orderId,
            'CustomerCivilId'    => null,
            'UserDefinedField'   => $userDefinedField,
            'ExpiryDate'         => $ExpiryDate->format('Y-m-d\TH:i:s'),
            'SourceInfo'         => 'Magento2 ' . $this->objectManager->get('Magento\Framework\App\ProductMetadataInterface')->getVersion() . ' - ' . $this->getGatewayConfig()->getCode() . ' ' . $this->getGatewayConfig()->getVersion(),
            'CustomerAddress'    => [
                'Block'               => '',
                'Street'              => '',
                'HouseBuildingNo'     => '',
                'Address'             => $city . ', ' . $region . ', ' . $postcode,
                'AddressInstructions' => $street
            ],
            'ShippingConsignee'  => $shippingConsignee,
            'ShippingMethod'     => $isShipping,
            'InvoiceItems'       => $invoiceItemsArr
        ];
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
    function getCurrencyData($gateway) {
        /** @var \Magento\Store\Model\StoreManagerInterface  $StoreManagerInterface */
        $store = $this->objectManager->create('Magento\Store\Model\StoreManagerInterface')->getStore();

        $KWDcurrencyRate = (double) $store->getBaseCurrency()->getRate('KWD');
        if ($gateway == 'kn' && !empty($KWDcurrencyRate)) {
            $currencyCode = 'KWD';
            $currencyRate = $KWDcurrencyRate;
        } else {
            $currencyCode = $store->getBaseCurrencyCode();
            $currencyRate = 1;
            //(double) $this->objectManager->create('Magento\Store\Model\StoreManagerInterface')->getStore()->getCurrentCurrencyRate();
        }
        return ['code' => $currencyCode, 'rate' => $currencyRate];
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------

    /** @var \Magento\Sales\Model\Order $order */
    private function postToCheckout($order) {

        $gatewayId = $this->getRequest()->get('pm') ?: 'myfatoorah';
        $sessionId = $this->getRequest()->get('sid') ?: null;

        if (!$sessionId && !$gatewayId) {
            throw new \Exception('Invalid Payment Session');
        }

        $curlData = $this->getPayload($order);
        $data     = $this->mfObj->getInvoiceURL($curlData, $gatewayId, $this->orderId, $sessionId);

        //save the invoice id in myfatoorah_invoice table 
        $mf = $this->objectManager->create('MyFatoorah\Gateway\Model\MyfatoorahInvoice');
        $mf->addData([
            'order_id'     => $this->orderId,
            'invoice_id'   => $data['invoiceId'],
            'gateway_name' => 'MyFatoorah',
            'invoice_url'  => $data['invoiceURL'],
        ]);
        $mf->save();
        $this->_redirect($data['invoiceURL']);
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
}
