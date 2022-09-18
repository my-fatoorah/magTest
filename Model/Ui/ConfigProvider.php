<?php

namespace MyFatoorah\Gateway\Model\Ui;

use MyFatoorah\Gateway\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Cart;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var Config
     */
    private $_gatewayConfig;

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Cart
     */
    private $cart;

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function __construct(
        Config $gatewayConfig,
        Resolver $localeResolver,
        CustomerSession $customerSession,
        Cart $cart
    ) {
        $this->_gatewayConfig  = $gatewayConfig;
        $this->localeResolver  = $localeResolver;
        $this->customerSession = $customerSession;
        $this->cart            = $cart;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function getConfig()
    {

        $config = [
            'title'       => $this->_gatewayConfig->getTitle(),
            'listOptions' => $this->_gatewayConfig->getKeyGateways(),
        ];
        //test
        //$config['listOptions'] = 'myfatoorah';
        if ($config['listOptions'] == 'multigateways') {
            try {
                $config = $this->fillMultigatewaysData($config);
            } catch (\Exception $ex) {
                $config['mfError'] = $ex->getMessage();
            }
        }

        return [
            'payment' => [
                Config::CODE => $config
            ]
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    private function fillMultigatewaysData($config)
    {

        $config['lang'] = $this->getCurrentLocale();

        $isApRegistered = $this->_gatewayConfig->isApplePayRegistered();

        $mfObj = $this->_gatewayConfig->getMyfatoorahObject();

        /**
         * @var \Magento\Quote\Model\Quote $quote
         */
        $quote = $this->cart->getQuote();

        $baseTotal    = $quote->getBaseGrandTotal();
        $baseCurrency = $quote->getBaseCurrencyCode();

        $config['baseGrandTotal'] = $baseTotal;
        $config['paymentMethods'] = $mfObj->getPaymentMethodsForDisplay($baseTotal, $baseCurrency, $isApRegistered);
        
        $all = $config['paymentMethods']['all'];
        if (count($all) == 1) {
            $config['title'] = ($config['lang'] == 'ar') ? $all[0]->PaymentMethodAr : $all[0]->PaymentMethodEn;
        }

        //draw form section
        if (!empty($config['paymentMethods']['form']) || !empty($config['paymentMethods']['ap'])) {
            $customerId = $this->customerSession->getCustomer()->getId();

            $config['height'] = '130';
            $userDefinedField = '';
            if ($this->_gatewayConfig->getSaveCard() && $customerId) {
                $config['height'] = '180';
                $userDefinedField = 'CK-' . $customerId;
            }

            $initSession           = $mfObj->getEmbeddedSession($userDefinedField);
            $config['countryCode'] = $initSession->CountryCode;
            $config['sessionId']   = $initSession->SessionId;
        }
        return $config;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    private function getCurrentLocale()
    {
        $currentLocaleCode = $this->localeResolver->getLocale(); // fr_CA
        $languageCode      = strstr($currentLocaleCode, '_', true);
        return $languageCode;
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
