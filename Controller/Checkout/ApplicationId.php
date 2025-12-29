<?php
namespace Okinus\Payment\Controller\Checkout;

class ApplicationId extends \Magento\Framework\App\Action\Action{

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
    protected $logger;
    protected $_urlInterface;
    protected $URL;


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
        \Psr\Log\LoggerInterface $logger,
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
        $this->logger = $logger;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';

    }


    public function execute()
    {

        // Get input data from the request in post method
        $applicationId = $this->getRequest()->getParam('applicationId', null);


        $jsonFactory = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();


        if(!empty($applicationId)){
            $additionalData = $quote->getPayment()->getAdditionalInformation();
            if($additionalData){
                $additionalData['applicationId'] = $applicationId;
            }
            $quote->getPayment()->setAdditionalInformation($additionalData);
            $quote->save();

        }

        $data = [
            'status' => true,
            'applicationId' => $applicationId,
        ];

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


}
