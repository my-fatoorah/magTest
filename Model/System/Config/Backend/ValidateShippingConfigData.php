<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\App\Config\Value;
//use Magento\Framework\Exception\LocalizedException;
use MyFatoorah\Library\ShippingMyfatoorahApiV2;

class ValidateShippingConfigData extends Value {

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

//---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data

     */
    public function __construct(
            \Magento\Framework\Message\ManagerInterface $messageManager,
            \Magento\Framework\Model\Context $context,
            \Magento\Framework\Registry $registry,
            \Magento\Framework\App\Config\ScopeConfigInterface $config,
            \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
            \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
            \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
            array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->messageManager = $messageManager;
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
    public function beforeSave() {

        if (!$this->getValue()) {
            return parent::beforeSave();
        }

        //check if payment is enabled
        $path  = 'payment/myfatoorah_payment/';
        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT; //$scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $code  = $this->getScopeCode();

        if (!$this->_config->getValue($path . 'active', $scope, $code)) {
            return $this->disableWithError('MyFatoorah: please, activate the MyFatoorah Payment to enable the shipping model.');
        }


        //check if carriers are selected
        $methods = $this->getFieldsetDataValue('methods');

        if (empty($methods)) {
            return $this->disableWithError('MyFatoorah: please, select at least one of the carrier methods to enable the shipping model.');
        }


        //check if carriers are correctly configured
        $apiKey      = (string) $this->_config->getValue($path . 'api_key', $scope, $code);
        $CountryCode = (string) $this->_config->getValue($path . 'countryMode', $scope, $code);
        $isTest      = $this->_config->getValue($path . 'is_testing', $scope, $code);

        $mfObj = new ShippingMyfatoorahApiV2($apiKey, $CountryCode, $isTest);

        foreach ($methods as $m) {
            try {
                $shippingData = [
                    'ShippingMethod' => $m,
                    'Items'          => [['ProductName' => 'product', 'Description' => 'product', 'Weight' => 10, 'Width' => 10, 'Height' => 10, 'Depth' => 10, 'Quantity' => 1, 'UnitPrice' => '17.234']],
                    'CountryCode'    => 'KW',
                    'CityName'       => 'adan',
                    'PostalCode'     => '12345',
                ];

                $mfObj->calculateShippingCharge($shippingData);
            } catch (\Exception $ex) {
                $error = str_replace("\n", '', $ex->getMessage()); // \n must be in "
                $type  = ($m == 1) ? 'DHL' : 'ARAMEX';

                return $this->disableWithError('MyFatoorah: please, fix the ' . $type . ' error to enable the shipping model: ' . $error);
            }
        }
        return parent::beforeSave();
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------

    private function disableWithError($err) {
        $this->messageManager->addError(__($err));

        $this->setValue(0);
        return parent::beforeSave();
//        throw new LocalizedException(__($err));
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
}
