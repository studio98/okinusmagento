<?php
namespace Okinus\Payment\Controller\Cart;

class Checkprice extends \Magento\Framework\App\Action\Action{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Pricing\Helper\Data $priceHelper
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->jsonFactory = $jsonFactory;
        $this->cookieManager = $cookieManager;
        $this->priceHelper = $priceHelper;
    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $okinus_approval_amount = $this->getCookie('okinus_approval_amount');
        
        $new_okinus_approval_amount = $okinus_approval_amount - $this->getPrice($quote);
        

        $data = [
            'value_format' => str_replace(',','',$this->priceHelper->currency($new_okinus_approval_amount,true,false)),
            'value' => $new_okinus_approval_amount
        ];
        
        return $jsonFactory->setData(['data' => $data]);
    }

  
    public function getPrice($quote){
        $price = 0;
        if(!$quote){
            return $price;
        }
        foreach($quote->getAllVisibleItems() as $item){
            $price += $item->getPrice() * $item->getQty();
        }
        return $price;
    }

    public function getCookie($name){
        return $this->cookieManager->getCookie($name);
    }
}
