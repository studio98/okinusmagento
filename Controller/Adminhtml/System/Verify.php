<?php
namespace Okinus\Payment\Controller\Adminhtml\System;

class Verify extends \Magento\Framework\App\Action\Action{
    const URL = 'https://beta2.okinus.com/api/v2/checkout';
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    )
    {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->jsonFactory = $jsonFactory;
        $this->curl = $curl;
    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $api_key = $this->getRequest()->getParam('api_key', null);
        $store_id = $this->getRequest()->getParam('store_id', null);
        $params = [
            'cart_type_id' => 1,
            'cart' => [
                'items' => [
                    0 => [
                        'sku' => 'sku',
                        'quantity' => 1,
                        'unit_price' => '300',
                        'description' => 'test api'

                    ]
                ]
            ],
            'cart_id' => 1,
            'return_url_thankyou' => 'success',
            'return_url_failure' => 'fail',
            'store_id' => $store_id,
            'test' => true,
        ];

        if($api_key == '******'){
            $api_key = $this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key'));
        }

        $headers = ["Content-Type" => "application/json", "Authorization" => "Bearer ".$api_key, "Accept" => "application/json"];
        $this->curl->setHeaders($headers);

        $this->curl->post(self::URL, json_encode($params));

        $result = json_decode($this->curl->getBody(), true);

        return $jsonFactory->setData(['success' => $result['status']]);
    }

    public function getConfigValue($path){
        return $this->scopeConfig->getValue($path, 
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
