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
use Magento\Framework\Encryption\EncryptorInterface;

class Payments extends Action
{
    protected $priceHelper;
    protected $jsonFactory;
    protected $curl;
    protected $scopeConfig;
    protected $checkoutSession;
    protected $encryptor;
    protected $logger;
    protected $cookieManager;
    protected $cookieManageURL;
    protected $URL;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Session $checkoutSession
     * @param Data $priceHelper
     * @param JsonFactory $jsonFactory
     * @param Curl $curl
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        Context $context,
        Data $priceHelper,
        ScopeConfigInterface $scopeConfig,
        JsonFactory $jsonFactory,
        Curl $curl,
        Session $checkoutSession,
        CookieManagerInterface $cookieManager,
        EncryptorInterface $encryptor,
        \Psr\Log\LoggerInterface $logger
    )
    {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->curl = $curl;
        $this->priceHelper = $priceHelper;
        $this->cookieManager = $cookieManager;
        $this->encryptor = $encryptor;

        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v1/score/payments' : 'https://www.okinushub.com/api/v1/score/payments';
        $this->logger = $logger;
    }

    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $price = $this->getRequest()->getParam('price', null);
        $isEnabled = $this->isApplyButtonEnabled();
        $minimumPrice = $this->minimumPrice();
        $maximumPrice = $this->maximumPrice();
        $withinPrice = true;
        if(!empty($minimumPrice) && $price < $minimumPrice){
            $withinPrice = false;
        }
        if(!empty($maximumPrice) && $price > $maximumPrice){
            $withinPrice = false;
        }
        if(!$price || !$isEnabled || !$withinPrice){
            return $jsonFactory->setData(['data' => ['value_format' => '0 / Monthly', 'value' => 0]]);
        }

        $isHomeImprovements = $this->isHomeImprovements();
        // $okinusApplicationId = $this->getCookie('okinus_application_id');
        $params = [
            'option' => $isHomeImprovements ? 'A' : 'B',
            'paymentFrequency' => 'monthly',
            'rating' => $isHomeImprovements ? 8 : 71,
            'incomeFrequency' => 'monthly',
            'leaseAmount' => floatval($price)
        ];


        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer ".$this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')),
            "Accept" => "application/json"
        ];

        $this->curl->setHeaders($headers);
        $this->logger->info('Okinus Request: ' . $this->URL . ':' . json_encode($params));
        $this->curl->post($this->URL, json_encode($params));

        $result = json_decode($this->curl->getBody(), true);
        $this->logger->info('Okinus Response: ' . json_encode($result));

        if(isset($result['status']) && $result['status']){
            $price = $result['data']['paymentAmount_no_tax'];
            $data = [
                'value_format' => str_replace(',','',$this->priceHelper->currency($price,true,false)).'/mo',
                'value' => $price,


            ];
        }else{
            $data = [
                'value_format' => str_replace(',','',$this->priceHelper->currency(0,true,false)).'/mo',
                'value' => 0,

            ];
        }
        return $jsonFactory->setData(['data' => $data ]);
    }

    public function isHomeImprovements()
    {
        return $this->getConfigValue('payment/okinus_payment/site_type') == 1;
    }


    public function isApplyButtonEnabled(){
        return $this->getConfigValue('payment/okinus_payment/apply_product') == 1;
    }

    public function minimumPrice(){
        return $this->getConfigValue('payment/okinus_payment/min_price');
    }

    public function maximumPrice(){
        return $this->getConfigValue('payment/okinus_payment/max_price');
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
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getSiteConfigValue($path){
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE
        );
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
