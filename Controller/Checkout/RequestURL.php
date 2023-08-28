<?php
namespace Okinus\Payment\Controller\Checkout;

class RequestURL extends \Magento\Framework\App\Action\Action{

    protected $scopeConfig;
    protected $checkoutSession;
    protected $customerSession;
    protected $encryptor;
    protected $productRepository;
    protected $cartTotalRepository;
    protected $jsonFactory;
    protected $curl;
    protected $urlInterface;
    protected $addressRepository;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $$customerSession
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\UrlInterface $urlInterface
     */

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalRepository,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
    )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_urlInterface = $urlInterface;
        $this->encryptor = $encryptor;
        $this->productRepository = $productRepository;
        $this->cartTotalRepository = $cartTotalRepository;
        $this->curl = $curl;
        $this->jsonFactory = $jsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->addressRepository = $addressRepository;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';

    }


    public function execute()
    {
        $thankyou = $this->_urlInterface->getUrl('checkout/onepage/success');
        $failure = $this->_urlInterface->getUrl('*/*/cancel');

        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();

        $prefill = [];
        try{
            $prefill = $this->getPrefilInformation();
        }catch(\Exception $ex){

        }

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
            'prefill' => $prefill,
            // 'test' => (bool)$this->getConfigValue('payment/okinus_payment/test_mode'),
            // 'application_id' => 1
        ];

        // echo json_encode($params);


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

    public function getPrefilInformation(){
        if($this->customerSession->isLoggedIn()){
            $customer = $this->customerSession->getCustomer();
            $billingAddressId = $customer->getDefaultBilling();
            $telephone = '';
            $location = [];
            try {
                $billingAddress = $this->addressRepository->getById($billingAddressId);
                $street = $billingAddress->getStreet();
                $location = [
                    'line_1' => isset($street[0]) ? $street[0] : '',
                    'line_2' => isset($street[1]) ? $street[1] : '',
                    'code' => $billingAddress->getPostcode(),
                    'city' => $billingAddress->getCity(),
                    'state' => $billingAddress->getRegion()->getRegionCode(),
                ];
                $telephone = $billingAddress->getTelephone();
            } catch (\Exception $e) {
                //
            }
            return [
                'applicant' => [
                    'title' => $customer->getPrefix() ?: '',
                    'cell' => $telephone,
                    'email' => $customer->getEmail() ?: '',
                    'first_name' => $customer->getFirstname() ?: '',
                    'last_name' => $customer->getLastname() ?: '',
                    'middle_name' => $customer->getMiddlename() ?: '',
                    'date_of_birth' => $customer->getDob() ?: '',
                    'location' => $location,
                ]
            ];
        }else{
            $customer = $this->customerSession->getCustomer();
            $quote = $this->checkoutSession->getQuote();
            $shippingAddress = $quote->getShippingAddress();
            $billingAddress = $quote->getBillingAddress();
            $street = $shippingAddress->getStreet();
            $location = [
                'line_1' => isset($street[0]) ? $street[0] : '',
                'line_2' => isset($street[1]) ? $street[1] : '',
                'code' => $billingAddress->getPostcode() ?: '',
                'city' => $billingAddress->getCity() ?: '',
                'state' => $billingAddress->getRegion() ?: '',
            ];
            return [
                'applicant' => [
                    'title' => $billingAddress->getPrefix() ?: '',
                    'cell' => $billingAddress->getTelephone() ?: '',
                    'email' => $billingAddress ? $billingAddress->getEmail() : '',
                    'first_name' => $billingAddress->getFirstname() ?: '',
                    'last_name' => $billingAddress->getLastname() ?: '',
                    'middle_name' => $billingAddress->getMiddlename() ?: '',
                    'location' => $location,
                ]
                ];
        }
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

            $description = strip_tags($item->getProduct()->getName() . "<br /> " . ($product->getDescription() ?: ''));
            $isShortDescription = $this->getConfigValue('payment/okinus_payment/description') == 1;
            if($isShortDescription){
                $description = strip_tags($item->getProduct()->getName() . "<br /> " . ($product->getShortDescription() ?: '')) ;
            }

            $result[] = [
                'sku' => $item->getProduct()->getSku(),
                'quantity' => $item->getQty(),
                'unit_price' => $item->getPrice(),
                // 'description' => $item->getProduct()->getShortDescription() ?: $item->getProduct()->getDescription() ?: 'Null'
                // 'description' =>  strip_tags($item->getProduct()->getName() . "<br />" . ($product->getDescription() ?: '')),
                'description' =>  $description,
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
        /*
        // Adding Tax
        $quoteId = $quote->getId();
        $cartTotal = $this->cartTotalRepository->get($quoteId);
        $taxAmt = $cartTotal->getTaxAmount();
        if($taxAmt > 0){
            $result[] = [
                'sku' => '0000',
                'quantity' => 1,
                'unit_price' => $taxAmt,
                'description' => 'Tax',
            ];
        }
        */



        // Adding Discount Rule
        if($quote->getShippingAddress()->getDiscountAmount() && abs($quote->getShippingAddress()->getDiscountAmount()) > 0)
        {
            $discount_lable = $quote->getShippingAddress()->getDiscountDescription();
            $result[] = [
                'sku' => '0000',
                'quantity' => 1,
                'unit_price' => $quote->getShippingAddress()->getDiscountAmount(),
                'description' => "Discount $discount_lable"
            ];
        }


        // Adding total discount
        // if($discountTotal > 0){
        //     $result[] = [
        //         'sku' => '0000',
        //         'quantity' => 1,
        //         'unit_price' => -$discountTotal,
        //         'description' => 'Discount',
        //     ];
        // }

        return $result;
    }

}
