<?php
namespace Okinus\Payment\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Action\Action;

class Success extends Action{

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\HTTP\Client\Curl $curl 
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        Curl $curl,
        EncryptorInterface $encryptor,
        ScopeConfigInterface $scopeConfig
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
