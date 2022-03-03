<?php
namespace Okinus\Payment\Block\View;

class Widget extends \Magento\Framework\View\Element\Template{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    public function isDisplayed(){
        return $this->getConfigValue('payment/okinus_payment/display_widget');
    }

    public function getWidgetPosition(){
        return (int)$this->getConfigValue('payment/okinus_payment/widget_position');
    }

    public function getConfigValue($path){
        return $this->scopeConfig->getValue($path, 
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

}
