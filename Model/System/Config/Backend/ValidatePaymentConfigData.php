<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\Storage\WriterInterface;
use MyFatoorah\Library\PaymentMyfatoorahApiV2;

class ValidatePaymentConfigData extends Value {

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

//---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
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
            \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
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
        $this->configWriter   = $configWriter;
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
    public function beforeSave() {

        if (!$this->getValue()) {
            $this->disableShipping();
            return parent::beforeSave();
        }

        $apiKey      = $this->getFieldsetDataValue('api_key');
        $CountryCode = $this->getFieldsetDataValue('countryMode');
        $isTest      = $this->getFieldsetDataValue('is_testing');

        $mfObj = new PaymentMyfatoorahApiV2($apiKey, $CountryCode, $isTest);

        try {
            $paymentMethods = $mfObj->getVendorGateways();
        } catch (\Exception $ex) {
            return $this->disableWithError('MyFatoorah: please, fix the error to enable the payment model: ' . $ex->getMessage());
        }

        if (empty($paymentMethods)) {
            return $this->disableWithError('MyFatoorah: please, contact your account manager to activate at least one of the available payment methods in your account to enable the payment model.');
        }

        return parent::beforeSave();
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------

    private function disableWithError($err) {
        $this->disableShipping();

        $this->messageManager->addError(__($err));

        $this->setValue(0);
        return parent::beforeSave();
//        throw new LocalizedException(__($err));
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------

    private function disableShipping() {

        $path  = 'carriers/myfatoorah_shipping/active';
        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT; //$scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $code  = $this->getScopeCode();
        $this->configWriter->save($path, 0, $scope, $code);
    }

//---------------------------------------------------------------------------------------------------------------------------------------------------
}
