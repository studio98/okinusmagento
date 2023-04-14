<?php
namespace Okinus\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Action\Action;

class Fail extends Action{

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\HTTP\Client\Curl $curl 
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        Curl $curl,
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    )
    {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->curl = $curl;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(){
        print_r($this->getRequest()->getParams());
    }

}
