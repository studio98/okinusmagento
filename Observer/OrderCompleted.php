<?php

namespace Okinus\Payment\Observer;


class OrderCompleted implements \Magento\Framework\Event\ObserverInterface
{
    protected $quoteRepository;
    protected $curl;
    protected $encryptor;
    protected $scopeConfig;
    protected $logger;
    protected $URL;
    protected $deliveryEnable;
    protected $modelNumberAtribute;
    protected $serialNumberAttribute;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Psr\Log\LoggerInterface $logger

    ) {
        $this->quoteRepository = $quoteRepository;
        $this->curl = $curl;
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
        $this->URL = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v1/' : 'https://www.okinushub.com/api/v1/';
        $this->deliveryEnable = $this->getConfigValue('payment/okinus_payment/delivery') == 1 ? true : false;
        $this->modelNumberAtribute = $this->getConfigValue('payment/okinus_payment/delivery_model');
        $this->serialNumberAttribute = $this->getConfigValue('payment/okinus_payment/delivery_serial');
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->deliveryEnable) {
            return $this;
        }

        $order = $observer->getOrder();


        if ($order->getState() == 'complete') {
            $this->logger->info('Okinus Request: Order Completed');
            try {

                // $items = $order->getAllItems();
                // $parts = [];
                // foreach ($items as $item) {
                //     $parts[] = [
                //         'model' => $item->getProduct()->getData($this->modelNumberAtribute),
                //         'serial' => $item->getProduct()->getData($this->serialNumberAttribute),
                //     ];
                // }


                $payload = [
                    'date' => date('Y-m-d H:i:s'),
                    'parts' => [],
                    'comments' => []
                ];

                // Get all shipments from order
                $shipments = $order->getShipmentsCollection();
                // Iterate through shipments and get their comments
                foreach ($shipments as $shipment) {
                    $comments = $shipment->getCommentsCollection();
                    foreach ($comments as $comment) {
                        $payload['comments'][] = $comment->getComment();
                    }
                }

                // Filter all emptry comments and join then with a comma
                $payload['comments'] = implode(', ', array_filter($payload['comments']));

                $this->logger->info('Okinus Request: ' . json_encode($payload));

                $quoteId = $order->getQuoteId();
                $quote = $this->quoteRepository->get($quoteId);

                $additionalInformation = $quote->getPayment()->getAdditionalInformation();
                // Check if it has applicationId
                if (!isset($additionalInformation['applicationId'])) {
                    return $this;
                }

                $applicationId = $quote->getPayment()->getAdditionalInformation()['applicationId'];

                $headers = [
                    "Content-Type" => "application/json",
                    "Authorization" => "Bearer " . $this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')),
                    "Accept" => "application/json"
                ];

                $this->logger->info('Key ' . $this->encryptor->decrypt($this->getConfigValue('payment/okinus_payment/api_key')));

                $this->curl->setHeaders($headers);
                $this->logger->info('Okinus Request: ' . $this->URL . "/commerce/leases/$applicationId/delivered" . ': ' . json_encode($payload));
                $this->curl->post($this->URL . "commerce/leases/$applicationId/delivered", json_encode($payload));


                $result = json_decode($this->curl->getBody(), true);
                $this->logger->info('Okinus Response: ' . json_encode($result));
            } catch (\Exception $e) {
            }
        }


        return $this;
    }

    /**
     * Get configuration value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
