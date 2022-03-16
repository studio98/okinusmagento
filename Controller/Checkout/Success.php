<?php
namespace Okinus\Payment\Controller\Checkout;

class Success extends \Magento\Framework\App\Action\Action{
    const URL = 'https://beta2.okinus.com/api/v2/checkout';
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->curl = $curl;
    }


    public function execute(){
        print_r($this->getRequest()->getParams());
    }

}
