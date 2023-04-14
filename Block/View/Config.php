<?php
namespace Okinus\Payment\Block\View;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config extends \Magento\Framework\View\Element\Template
{

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

    /**
     * Get RetailerSlug
     *
     * @return string
     */
    public function getRetailerSlug(){
        return $this->getConfigValue('payment/okinus_payment/retailer_slug');
    }
     
    /**
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path){
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Get StoreSlug
     *
     * @return string
     */
    public function getStoreSlug(){
        return $this->getConfigValue('payment/okinus_payment/store_slug');
    }

    /**
     * Get base url
     *
     * @return string
     */
    public function getBaseURL(){
        return $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com' : 'https://www.okinushub.com';
    }
}
