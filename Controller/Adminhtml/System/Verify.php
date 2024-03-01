<?php
namespace Okinus\Payment\Controller\Adminhtml\System;

use Magento\Framework\App\Action\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Action;

class Verify extends Action{

    protected $curl;
    protected $scopeConfig;
    protected $encryptor;
    protected $jsonFactory;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param JsonFactory $jsonFactory
     * @param Curl $curl
     */

    public function __construct(
        Context $context,
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        JsonFactory $jsonFactory
    )
    {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->jsonFactory = $jsonFactory;
        $this->curl = $curl;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';
    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $api_key = $this->getRequest()->getParam('api_key', null);
        $store_id = $this->getRequest()->getParam('store_id', null);
        $retailer_slug = $this->getRequest()->getParam('retailer_slug', null);
        $store_slug = $this->getRequest()->getParam('store_slug', null);
        if($api_key != "" && $store_id != '' && $retailer_slug != '' && $store_slug != '')
        {
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
                'test' => false,
            ];

            if($api_key == '******'){
                $api_key = $this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key'));
            }

            $headers = ["Content-Type" => "application/json", "Authorization" => "Bearer ".$api_key, "Accept" => "application/json"];
            $this->curl->setHeaders($headers);

            $this->curl->post($this->URL, json_encode($params));

            $result = json_decode($this->curl->getBody(), true);
        }else{
            $result['status'] = false;
        }

        return $jsonFactory->setData(['success' => $result['status']]);
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
