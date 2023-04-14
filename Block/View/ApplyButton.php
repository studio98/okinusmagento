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
}
