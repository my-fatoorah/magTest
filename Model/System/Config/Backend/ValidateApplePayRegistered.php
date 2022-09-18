<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\App\Config\Value;
use MyFatoorah\Library\PaymentMyfatoorahApiV2;

class ValidateApplePayRegistered extends Value
{

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlInterface;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param \Magento\Framework\Message\ManagerInterface                  $messageManager
     * @param \Magento\Framework\UrlInterface                              $urlInterface
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param ScopeConfigInterface                                         $config
     * @param \Magento\Framework\App\Cache\TypeListInterface               $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\UrlInterface $urlInterface,
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
        $this->urlInterface   = $urlInterface;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
    public function beforeSave()
    {

        //if any don't register
        if (!$this->getValue() || !$this->getFieldsetDataValue('active')) {
            return parent::beforeSave();
        }

        //check list option
        if ($this->getFieldsetDataValue('list_options') == 'myfatoorah') {
            $msg = 'MyFatoorah: registering your domain with MyFatoorah and Apple Pay works only '
                . 'if you select "List All Enabled Gateways in Checkout Page" '
                . 'from the "List Payment Options" option.';
            
            $this->messageManager->addWarning(__($msg));

            $this->setValue(0);
            return parent::beforeSave();
        }

        //register
        $apiKey      = $this->getFieldsetDataValue('api_key');
        $CountryCode = $this->getFieldsetDataValue('countryMode');
        $isTest      = $this->getFieldsetDataValue('is_testing');

        $mfObj = new PaymentMyfatoorahApiV2($apiKey, $CountryCode, $isTest);

        $siteURL = $this->urlInterface->getCurrentUrl();
        try {
            $data = $mfObj->registerApplePayDomain($siteURL);
            if ($data->Message == 'OK') {
                return parent::beforeSave();
            }
            $err = $data->Message;
        } catch (\Exception $ex) {
            $err = 'MyFatoorah: can not register Apple Pay due to: ' . $ex->getMessage();
        }
        return $this->disableWithError($err);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    private function disableWithError($err)
    {

        $this->messageManager->addError(__($err));

        $this->setValue(0);
        return parent::beforeSave();
    }
    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
