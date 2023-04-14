<?php
namespace Okinus\Payment\Controller\Checkout;

class RequestURL extends \Magento\Framework\App\Action\Action{

    protected $scopeConfig;
    protected $checkoutSession;
    protected $encryptor;
    protected $productRepository;
    protected $jsonFactory;
    protected $curl;
    protected $urlInterface;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\UrlInterface $urlInterface
     */
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\UrlInterface $urlInterface
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->_urlInterface = $urlInterface;
        $this->encryptor = $encryptor;
        $this->productRepository = $productRepository;
        $this->curl = $curl;
        $this->jsonFactory = $jsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';

    }


    public function execute()
    {    
        $thankyou = $this->_urlInterface->getUrl('checkout/onepage/success');
        $failure = $this->_urlInterface->getUrl('*/*/cancel');
 
        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $params = [
            'store_id' => $this->getConfigValue('payment/okinus_payment/store_id'),
            'cart_id' => $quote->getId(),
            'cart' => [
                'items' => $this->getItems($quote)
            ],
            'cart_type_id' => 1,
            'return_url_thankyou' => $thankyou,
            'return_url_failure' => $failure,
            'test' => false,
            // 'test' => (bool)$this->getConfigValue('payment/okinus_payment/test_mode'),
            // 'application_id' => 1
        ];

        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer ".$this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')), 
            "Accept" => "application/json"
        ];

        $this->curl->setHeaders($headers);

        $this->curl->post($this->URL, json_encode($params));

        $result = json_decode($this->curl->getBody(), true);

        if(isset($result['status']) && !empty($result['status']))
        {
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

    /**
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path){
        return $this->scopeConfig->getValue($path,\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }


    /**
     * Get Items
     *
     * @return mixed
     */
    public function getItems($quote){
        $result = [];
        $discountTotal = 0;
        foreach($quote->getAllVisibleItems() as $item)
        {
            
            /* Add capability for Product Having a custom options with sku for custom options */
            $product = $this->productRepository->getById($item->getProductId());
            /* Add capability for Product Having a custom options with sku for custom options */

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
