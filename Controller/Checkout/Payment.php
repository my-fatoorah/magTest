<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Cart;
use MyFatoorah\Gateway\Gateway\Config\Config;

class Payment extends Action
{

    /**
     * @var \Tutorial\SimpleNews\Model\NewsFactory
     */
    protected $_modelNewsFactory;

    /**
     * @var Config
     */
    private $_gatewayConfig;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * @param Context     $context
     * @param NewsFactory $modelNewsFactory
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Config $gatewayConfig,
        Cart $cart
    ) {
        $this->_gatewayConfig    = $gatewayConfig;
        $this->cart              = $cart;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->getRequest()->isAjax()) {
            return;
        }
        try {
            /**
             * @var \Magento\Quote\Model\Quote $quote
             */
            $quote        = $this->cart->getQuote();
            $baseCurrency = $quote->getBaseCurrencyCode();

            $baseGrandTotal = $this->getRequest()->getParam('baseGrandTotal');

            $mfObj          = $this->_gatewayConfig->getMyfatoorahObject();
            $paymentMethods = $mfObj->getPaymentMethodsForDisplay($baseGrandTotal, $baseCurrency);
        } catch (Exception $exc) {
            $error = $exc->getMessage();
        }

        return $result->setData(
            [
                'cards' => json_encode(isset($paymentMethods['cards']) ? $paymentMethods['cards'] : []),
                'error' => !empty($error) ? $error : null
            ]
        );
    }
}
