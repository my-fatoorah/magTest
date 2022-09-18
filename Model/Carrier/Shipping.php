<?php

namespace MyFatoorah\Gateway\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\Manager;
use MyFatoorah\Library\ShippingMyfatoorahApiV2;
use MyFatoorah\Gateway\Helper\Checkout;

class Shipping extends AbstractCarrier implements CarrierInterface
{

    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'myfatoorah_shipping';

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var Result
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     *
     * @var ShippingMyfatoorahApiV2
     */
    private $sMFObj;

    /**
     *
     * @var array
     */
    private $mfShippingMethods = [1 => 'DHL', 2 => 'Aramex'];

    /**
     *
     * @var checkoutHelper
     */
    protected $checkoutHelper;

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ResultFactory         $resultFactory
     * @param MethodFactory         $methodFactory
     * @param ScopeConfigInterface  $scopeConfig
     * @param ErrorFactory          $rateErrorFactory
     * @param LoggerInterface       $logger
     * @param Checkout              $checkoutHelper
     * @param array                 $data
     */
    public function __construct(
        Manager $moduleManager,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        MethodFactory $methodFactory,
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Checkout $checkoutHelper,
        array $data = []
    ) {

        //initiate the parent constructor
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->moduleManager = $moduleManager;
        $this->storeManager  = $storeManager;

        $this->rateResultFactory = $resultFactory;
        $this->rateMethodFactory = $methodFactory;
        $this->checkoutHelper    = $checkoutHelper;
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Generates list of allowed carrier`s shipping methods
     * Displays on cart price rules page
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        return [
            $this->getCarrierCode() => __('DHL'),
            $this->getCarrierCode() => __('Aramex')
        ];
    }
    //todo ned to fix the cart page
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Collect and get rates for storefront
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param                                         RateRequest $request
     * @return                                        DataObject|bool|null
     * @api
     */
    public function collectRates(RateRequest $request)
    {

        $configData = $this->getMFPaymentConfigData();

        //return false if no MyFatoorah payment methold is not enabled
        if (!$configData) {
            return false;
        }

        $this->sMFObj = new ShippingMyfatoorahApiV2(
            $configData['apiKey'],
            $configData['countryMode'],
            $configData['isTesting'],
            MFSHIPPING_LOG_FILE
        );

        //logging
        $this->sMFObj->log("---------------------------------------------------------------------------------------");

        try {
            $currency     = $this->storeManager->getStore()->getBaseCurrency()->getCode();
            $currencyRate = $this->sMFObj->getCurrencyRate($currency);

            $curlData = [
                'Items'       => $this->getInvoiceItems($request),
                'CityName'    => $request->getDestCity(),
                'PostalCode'  => $request->getDestPostcode(),
                'CountryCode' => $request->getDestCountryId()
            ];

            $rateResult = $this->rateResultFactory->create();

            $configMethods    = $this->getConfigData('methods');
            $availableMethods = empty($configMethods) ? [] : explode(',', $configMethods);

            foreach ($availableMethods as $id) {
                $curlData['ShippingMethod'] = $id;

                $json = $this->sMFObj->calculateShippingCharge($curlData);

                $realVal = floor($json->Data->Fees * 1000) / 1000;

                $shippingAmount = $currencyRate * $realVal;
                if ($shippingAmount) {
                    $rateResult->append($this->createShippingMethod($shippingAmount, $id));
                }
            }

            return $rateResult;
        } catch (\Exception $ex) {
            $this->sMFObj->log('In Shipping exception block - ' . $ex->getMessage());
            return [];
        }
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param  RateRequest $request
     * @return type
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getInvoiceItems($request)
    {
        $items = $request->getAllItems();
        try {
            $orderItemsArr = $this->checkoutHelper->getOrderItems($items, 1, true, false);
        } catch (\Exception $exc) {
            throw new \Magento\Framework\Exception\LocalizedException(__($exc->getMessage()));
        }
        return $orderItemsArr['invoiceItemsArr'];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
    private function createShippingMethod($shippingAmount, $id)
    {

        $method = $this->rateMethodFactory->create();

        //Set carrier's data
        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData('title'));

        //Set method under Carrier
        $method->setMethod($id);
        $method->setMethodTitle(__($this->mfShippingMethods[$id]));

        //set price
        $method->setPrice($shippingAmount);
        $method->setCost($shippingAmount);

        return $method;
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Retrieve information from carrier configuration
     *
     * @return array
     */
    private function getMFPaymentConfigData()
    {
        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        $module = $this->moduleManager;
        $config = $this->_scopeConfig;

        $mgPath = 'payment/myfatoorah_payment/';
        $mmPath = 'payment/myfatoorah_gateway/';
        $mePath = 'payment/embedpay/';
        
        $mgName = 'MyFatoorah_Gateway';
        $mmName = 'MyFatoorah_MyFatoorahPaymentGateway';
        $meName = 'MyFatoorah_EmbedPay';

        if ($module->isEnabled($mgName) && $config->getValue($mgPath . 'active', $scope)) {
            $path = $mgPath;
        } elseif ($module->isEnabled($mmName) && $config->getValue($mmPath . 'active', $scope)) {
            $path = $mmPath;
        } elseif ($module->isEnabled($meName) && $config->getValue($mePath . 'active', $scope)) {
            $path = $mePath;
        } else {
            return [];
        }

        $data['apiKey']      = $config->getValue($path . 'api_key', $scope);
        $data['isTesting']   = $config->getValue($path . 'is_testing', $scope);
        $data['countryMode'] = $config->getValue($path . 'countryMode', $scope);

        return $data;
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------
}
