<?php
namespace Okinus\Payment\Observer;


class OrderConfirmed implements \Magento\Framework\Event\ObserverInterface
{
    protected $quoteRepository;
    protected $curl;
    protected $encryptor;
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,

    ) {
        $this->quoteRepository = $quoteRepository;
        $this->curl = $curl;
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';
    }

	public function execute(\Magento\Framework\Event\Observer $observer)
	{
        try{

            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getId();
            $quoteId = $order->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);

            $checkoutId = $quote->getPayment()->getAdditionalInformation()['checkout_id'];

            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer ".$this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')),
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);

            $this->curl->post($this->URL . "/$checkoutId/set-order-id/$orderId", []);

            $result = json_decode($this->curl->getBody(), true);
        }catch(\Exception $e){

        }


        return $this;
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
