<?php
namespace Okinus\Payment\Block\View;

class Config extends \Magento\Framework\View\Element\Template{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfigValue($path){
        return $this->scopeConfig->getValue($path, 
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getRetailerSlug(){
        return $this->getConfigValue('payment/okinus_payment/retailer_slug');
    }

    public function getStoreSlug(){
        return $this->getConfigValue('payment/okinus_payment/store_slug');
    }
}
