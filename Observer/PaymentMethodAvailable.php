<?php

namespace Okinus\Payment\Observer;


class PaymentMethodAvailable implements \Magento\Framework\Event\ObserverInterface
{
    protected $quoteRepository;
    protected $scopeConfig;
    protected $logger;
    protected $URL;
    protected $deliveryEnable;
    protected $modelNumberAtribute;
    protected $serialNumberAttribute;
    protected $checkoutCartBlock;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Checkout\Block\Cart $checkoutCartBlock,
        \Psr\Log\LoggerInterface $logger

    ) {
        $this->quoteRepository = $quoteRepository;
        $this->scopeConfig = $scopeConfig;
        $this->checkoutCartBlock = $checkoutCartBlock;
        // $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';
        // $this->deliveryEnable = $this->getConfigValue('payment/okinus_payment/delivery') == 1 ? true : false;
        // $this->modelNumberAtribute = $this->getConfigValue('payment/okinus_payment/delivery_model');
        // $this->serialNumberAttribute = $this->getConfigValue('payment/okinus_payment/delivery_serial');
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($observer->getEvent()->getMethodInstance()->getCode() == "okinus_payment") {
            $checkResult = $observer->getEvent()->getResult();
            $quote = $this->checkoutCartBlock->getQuote();
            $total = $quote->getGrandTotal();


            $minPrice = $this->minimumPrice();
            $maxPrice = $this->maximumPrice();
            $shouldVisible = true;
            if(!empty($minPrice) && $total < $minPrice){
                $shouldVisible = false;
            }
            if(!empty($maxPrice) && $total > $maxPrice){
                $shouldVisible = false;
            }


            $checkResult->setData('is_available', $shouldVisible);
        }


        return $this;
    }

    /**
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function minimumPrice(){
        return $this->getConfigValue('payment/okinus_payment/min_price');
    }

    public function maximumPrice(){
        return $this->getConfigValue('payment/okinus_payment/max_price');
    }

}
