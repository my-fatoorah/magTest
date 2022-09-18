<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\App\Config\ScopeConfigInterface;

class MethodListPlugin
{

    /**
     * Core store config
     *
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    //-----------------------------------------------------------------------------------------------------------------------------------------
    public function __construct(
        Manager $moduleManager,
        ScopeConfigInterface $scopeConfig
    ) {

        $this->moduleManager = $moduleManager;
        $this->_scopeConfig  = $scopeConfig;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
    public function afterGetAvailableMethods(MethodList $subject, $availableMethods, CartInterface $quote = null)
    {

        $shippingMethod = $quote ? $quote->getShippingAddress()->getShippingMethod() : '';

        $mfPaymentCode = $this->getMFPaymentCode();
        if ($shippingMethod == 'myfatoorah_shipping_1' || $shippingMethod == 'myfatoorah_shipping_2') {

            foreach ($availableMethods as $key => $method) {
                if ($method->getCode() != $mfPaymentCode) {
                    unset($availableMethods[$key]);
                }
            }
        }
        return $availableMethods;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
    private function getMFPaymentCode()
    {

        //        $store = $this->getStore();
        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        $module = $this->moduleManager;
        $config = $this->_scopeConfig;
        
        $mgCode = 'myfatoorah_payment';
        $mmCode = 'myfatoorah_gateway';
        $meCode = 'embedpay';
        
        $mgName = 'MyFatoorah_Gateway';
        $mmName = 'MyFatoorah_MyFatoorahPaymentGateway';
        $meName = 'MyFatoorah_EmbedPay';

        if ($module->isEnabled($mgName) && $config->getValue("payment/$mgCode/active", $scope)) {
            return $mgCode;
        } elseif ($module->isEnabled($mmName) && $config->getValue("payment/$mmCode/active", $scope)) {
            return $mmCode;
        } elseif ($module->isEnabled($meName) && $config->getValue("payment/$meCode/active", $scope)) {
            return $meCode;
        } else {
            return false;
        }
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------
}
