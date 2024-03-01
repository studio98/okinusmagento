<?php
namespace Okinus\Payment\Controller\Cart;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\App\Action\Action;

class Checkprice extends Action
{

    /**
     * @param Context $context
     * @param Session $checkoutSession
     * @param Data $priceHelper
     * @param JsonFactory $jsonFactory
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        Data $priceHelper,
        CookieManagerInterface $cookieManager,
        JsonFactory $jsonFactory
    )
    {
        parent::__construct($context);
        $this->cookieManager = $cookieManager;
        $this->priceHelper = $priceHelper;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $okinus_approval_amount = $this->getCookie('okinus_approval_amount');

        $new_okinus_approval_amount = $okinus_approval_amount - $this->getPrice($quote);



        $data = [
            'value_format' => str_replace(',','',$this->priceHelper->currency($new_okinus_approval_amount,true,false)),
            'value' => $new_okinus_approval_amount,
        ];

        return $jsonFactory->setData(['data' => $data]);
    }

     /**
     * Get Price value
     *
     * @return mixed
     */
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

    /**
     * Get Price value
     *
     * @param string $name
     * @return mixed
     */
    public function getCookie($name){
        return $this->cookieManager->getCookie($name);
    }



}
