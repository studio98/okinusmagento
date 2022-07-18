<?php
namespace Okinus\Payment\Controller\Checkout;

class RequestURL extends \Magento\Framework\App\Action\Action{
    // const URL = 'https://beta2.okinus.com/api/v2/checkout';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->jsonFactory = $jsonFactory;
        $this->productRepository = $productRepository;
        $this->curl = $curl;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';

    }


    public function execute(){
        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $params = [
            'cart_type_id' => 1,
            'cart' => [
                'items' => $this->getItems($quote)
            ],
            'cart_id' => $quote->getId(),
            'return_url_thankyou' => '',
            'return_url_failure' => '',
            'store_id' => $this->getConfigValue('payment/okinus_payment/store_id'),
            'test' => false,
            // 'test' => (bool)$this->getConfigValue('payment/okinus_payment/test_mode'),
            // 'application_id' => 1
        ];

        $headers = ["Content-Type" => "application/json", "Authorization" => "Bearer ".$this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')), "Accept" => "application/json"];
        $this->curl->setHeaders($headers);

        $this->curl->post($this->URL, json_encode($params));

        $result = json_decode($this->curl->getBody(), true);

        if($result['status']){
            $data = [
                'success' => $result['status'],
                'data' => [
                    'checkout_id' => $result['data']['checkout_id'],
                    'url' => $result['data']['url'],
                ]
            ];
        }else{
            $data = [
                'success' => $result['status'],
                'data' => [
                    'message' => $result['data']['message']
                ]
            ];
        }
        return $jsonFactory->setData($data);
    }

    public function getConfigValue($path){
        return $this->scopeConfig->getValue($path,
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getItems($quote){
        $result = [];
        $discountTotal = 0;
        foreach($quote->getAllVisibleItems() as $item){
            $product = $this->productRepository->get($item->getProduct()->getSku());

            $result[] = [
                'sku' => $item->getProduct()->getSku(),
                'quantity' => $item->getQty(),
                'unit_price' => $item->getPrice(),
                // 'description' => $item->getProduct()->getShortDescription() ?: $item->getProduct()->getDescription() ?: 'Null'
                'description' =>  strip_tags($item->getProduct()->getName() . "<br />" . ($product->getDescription() ?: '')),
            ];

            $discountTotal += $item->getDiscountAmount();

        }

        // Adding Shipping Cost
        if($quote->getShippingAddress()->getShippingAmount() > 0){
            $result[] = [
                'sku' => '0000',
                'quantity' => 1,
                'unit_price' => $quote->getShippingAddress()->getShippingAmount(),
                'description' => 'Shipping',
            ];
        }

        // Adding total discount
        if($discountTotal > 0){
            $result[] = [
                'sku' => '0000',
                'quantity' => 1,
                'unit_price' => -$discountTotal,
                'description' => 'Discount',
            ];
        }


        return $result;
    }

}
