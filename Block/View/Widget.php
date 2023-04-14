<?php
namespace Okinus\Payment\Block\View;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Widget extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    public $cookieManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param CookieManagerInterface $cookieManager
     */

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager
    ) {
        parent::__construct($context);
        $this->cookieManager = $cookieManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function isPopupHide()
    {
        // Get Cookie Data related to okinus popup
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/isShow.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $logger->info($this->cookieManager->getCookie('okinus_popup_hide'));
        
        $logger->info($this->isDisplayed() && $this->cookieManager->getCookie('okinus_popup_hide') == false);

        return $this->cookieManager->getCookie('okinus_popup_hide');
    }

    /**
     * Get Display Widget Position
     *
     * @return int
     */
    public function getWidgetPosition()
    {
        return (int)$this->getConfigValue('payment/okinus_payment/widget_position');
    }

    /**
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Check Display Widget value
     *
     * @return mixed
     */
    public function isDisplayed()
    {
        $isCheckDisplayWidget = $this->getConfigValue('payment/okinus_payment/display_widget');
        return $isCheckDisplayWidget;
    }
}
