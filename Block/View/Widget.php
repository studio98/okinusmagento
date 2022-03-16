<?php
namespace Okinus\Payment\Block\View;

use Magento\Framework\Stdlib\CookieManagerInterface;

class Widget extends \Magento\Framework\View\Element\Template
{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
    }

    public function isPopupHide()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/isShow.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info($this->cookieManager->getCookie('okinus_popup_hide'));
        
        $logger->info($this->isDisplayed() && $this->cookieManager->getCookie('okinus_popup_hide') == false);


        return $this->cookieManager->getCookie('okinus_popup_hide');
    }

    public function isDisplayed()
    {
        return $this->getConfigValue('payment/okinus_payment/display_widget');
    }

    public function getWidgetPosition()
    {
        return (int)$this->getConfigValue('payment/okinus_payment/widget_position');
    }

    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
