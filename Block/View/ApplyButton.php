<?php
namespace Okinus\Payment\Block\View;

class ApplyButton extends \Magento\Framework\View\Element\Template{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }
}
