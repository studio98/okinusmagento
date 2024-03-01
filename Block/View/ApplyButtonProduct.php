<?php
namespace Okinus\Payment\Block\View;

use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ApplyButtonProduct extends \Magento\Framework\View\Element\Template{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $session;
    protected $registry;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Registry $registry
    ){

        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
    }


    public function getProductPrice(){
        // get current product id
        $currentProduct = $this->registry->registry('current_product');
        if($currentProduct){
            return $currentProduct->getPriceInfo()->getPrice('final_price')->getValue();
        }
        return 0;
    }

    public function getDisclaimer(){
        return $this->scopeConfig->getValue('payment/okinus_payment/starting_at_disclaimer');
    }


    public function getImageUrl(){
        if($this->getBranding() == 'Okinus'){
            return $this->getViewFileUrl('Okinus_Payment::images/okinus.png');
        }else{
            return $this->getViewFileUrl('Okinus_Payment::images/breeze.png');
        }
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
