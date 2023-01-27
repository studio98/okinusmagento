<?php
namespace Okinus\Payment\Controller\Cart;

class PaymentCalculator extends \Magento\Framework\App\Action\Action{
    // const URL = 'https://www.okinushub.com/api/v1/score/payments';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\Pricing\Helper\Data $priceHelper
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->jsonFactory = $jsonFactory;
        $this->priceHelper = $priceHelper;
        $this->curl = $curl;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';
    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $params = [
            'option' => 'A',
            'paymentFrequency' => '14-day',
            'rating' => '4',
            'incomeFrequency' => 'bi-weekly',
            'return_url_failure' => 'GA',
            'zip_code' => '30024',
            'modelVersion' => null,
            'score' => '1000',
            'leaseAmount' => $this->getPrice($quote)
        ];

        $headers = ["Content-Type" => "application/json"];
        $this->curl->setHeaders($headers);

        $this->curl->post($this->URL, json_encode($params));

        $result = json_decode($this->curl->getBody(), true);

        if(isset($result['status']) && $result['status']){
            $price = $result['data']['paymentAmount_no_tax'];
            $data = [
                'value_format' => str_replace(',','',$this->priceHelper->currency($price,true,false)).' / Every Two Weeks',
                'value' => $price
            ];
        }else{
            $data = [
                'value_format' => str_replace(',','',$this->priceHelper->currency(0,true,false)).' / Every Two Weeks',
                'value' => 0
            ];
        }
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
}
