<?php
namespace Okinus\Payment\Block\View;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ApplyButton extends \Magento\Framework\View\Element\Template{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig
    ){

        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;

    }

    public function getBranding()
    {
        return $this->getConfigValue('payment/okinus_payment/branding') == 0 ? 'Okinus' : 'Breeze Leasing';
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

}
