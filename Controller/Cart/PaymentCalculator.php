<?php
namespace Okinus\Payment\Controller\Cart;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Framework\App\Action\Action;

class PaymentCalculator extends Action
{
    protected $priceHelper;
    protected $jsonFactory;
    protected $curl;
    protected $scopeConfig;
    protected $checkoutSession;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param Data $priceHelper
     * @param JsonFactory $jsonFactory
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        Data $priceHelper,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $jsonFactory,
        Curl $curl,
        Session $checkoutSession
    )
    {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->curl = $curl;
        $this->priceHelper = $priceHelper;
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
        return $jsonFactory->setData(['data' => $data ]);
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
}
