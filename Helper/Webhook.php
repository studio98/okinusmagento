<?php
namespace Okinus\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory as PaymentCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;

class Webhook extends AbstractHelper
{
    protected $curl;
    protected $scopeConfig;
    protected $configWriter;
    protected $encryptor;
    protected $storeManager;
    protected $quoteFactory;
    protected $cartRepository;
    protected $orderSender;
    protected $quoteManagement;
    protected $orderFactory;
    protected $paymentCollectionFactory;
    protected $resourceConnection;
    protected $lockManager;
    protected $logger;

    const XML_PATH_WEBHOOK_SUBSCRIPTION_ID = 'payment/okinus_payment/webhook_subscription_id';
    const XML_PATH_WEBHOOK_EXPIRES_AT = 'payment/okinus_payment/webhook_expires_at';
    const XML_PATH_WEBHOOK_EMAIL = 'payment/okinus_payment/webhook_email';

    const WEBHOOK_EVENT_TABLE = 'okinus_webhook_event';

    /**
     * Constructor
     */
    public function __construct(
        Context $context,
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        QuoteFactory $quoteFactory,
        CartRepositoryInterface $cartRepository,
        OrderSender $orderSender,
        QuoteManagement $quoteManagement,
        LoggerInterface $logger,
        PaymentCollectionFactory $paymentCollectionFactory,
        OrderFactory $orderFactory,
        ResourceConnection $resourceConnection,
        LockManagerInterface $lockManager
    )
    {
        parent::__construct($context);
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->quoteFactory = $quoteFactory;
        $this->cartRepository = $cartRepository;
        $this->orderSender = $orderSender;
        $this->quoteManagement = $quoteManagement;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->lockManager = $lockManager;
    }

    /**
     * Get API Base URL based on environment
     */
    public function getApiUrl()
    {
        $environment = $this->getConfigValue('payment/okinus_payment/environment');
        return $environment == 1
            ? 'https://beta2.okinus.com/api/v1'
            : 'https://www.okinushub.com/api/v1';
    }

    public function getEventsToSubscribe(){
        return ['APPLICATION_LOAN_ISSUED'];
    }

    /**
     * Get API Key
     */
    public function getApiKey($apiKey = null)
    {
        if ($apiKey && $apiKey !== '******') {
            return $apiKey;
        }

        $encryptedKey = $this->getConfigValue('payment/okinus_payment/api_key');
        return $this->encryptor->decrypt($encryptedKey);
    }

    /**
     * Subscribe to webhook
     */
    public function subscribe($apiKey, $storeId, $email)
    {
        $this->unsubscribe();
        try {
            $webhookUrl = $this->getWebhookUrl();
            $url = $this->getApiUrl() . '/webhooks/subscribe';

            $params = [
                'url' => $webhookUrl,
                'events' => $this->getEventsToSubscribe(),
                'store_id' => $storeId,
                'email_notifications' => $email,
                'email_errors' => $email,
                'http_method' => 'POST'
            ];

            $apiKey = $this->getApiKey($apiKey);
            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $apiKey,
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);
            $this->logger->info('Okinus Request: ' . $url . ':' . json_encode($params));
            $this->curl->post($url, json_encode($params));

            // First unsubscribe existing subscriptions to avoid duplicates

            $response = json_decode($this->curl->getBody(), true);
            $this->logger->info('Okinus Response: ' . $url . ':' . json_encode($response));
            foreach($response as $event => $value){
                // Save each subscription with its $eventId in the config
                $subscriptionId = $value['subscription']['subscription_id'] ?? null;
                $secret = $value['subscription']['secret'] ?? null;
                $expiresAt = $value['subscription']['expires_at'] ?? null;

                $this->configWriter->save(
                    'payment/okinus_payment/webhook_subscription_id_' . strtolower($event),
                    $subscriptionId
                );
                $this->configWriter->save(
                    'payment/okinus_payment/webhook_secret_' . strtolower($event),
                    $secret
                );
                $this->configWriter->save(
                    'payment/okinus_payment/webhook_expires_at_' . strtolower($event),
                    $expiresAt
                );
            }

            if(count($response) == count($this->getEventsToSubscribe())){
                return [
                    'success' => true,
                    'subscription_id' => 0,
                    'webhook_url' => $webhookUrl,
                    'expires_at' => '',
                    'email' => $email,
                    'message' => 'Successfully subscribed to webhook'
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? 'Failed to subscribe to webhook'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Webhook subscription error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check webhook subscription status
     */
    public function checkStatus()
    {
        try {
            $subscriptions = $this->_getSubscriptionStatus();
            $subscribed = false;
            $expiresAt = null;
            $url = $this->getWebhookUrl();
            foreach($this->getEventsToSubscribe() as $event){
                if(isset($subscriptions[$event]) && $subscriptions[$event]['isActive']){
                    $subscribed = true;
                    $expiresAt = @$subscriptions[$event]['expiresAt'];
                    $url = @$subscriptions[$event]['url'] ?? $url;
                }else{
                    $subscribed = false;
                    break;
                }
            }
            $savedEmail = $this->getConfigValue(self::XML_PATH_WEBHOOK_EMAIL);
            return [
                'subscribed' => $subscribed,
                'subscribedEvents' => $this->getEventsToSubscribe(),
                'webhook_url' => $url,
                'expires_at' => $expiresAt,
                'is_expired' => false,
                'email' => $savedEmail
            ];

            return [
                'subscribed' => false,
                'message' => 'Subscription not active'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Webhook status check error: ' . $e->getMessage());
            return [
                'subscribed' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function _getSubscriptionStatus(){
        $url = $this->getApiUrl() . '/webhooks/status';
        $apiKey = $this->getApiKey();
        // echo $apiKey . "\n";

        $headers = [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $apiKey,
            "Accept" => "application/json"
        ];

        $this->curl->setHeaders($headers);
        $store_id = $this->getConfigValue('payment/okinus_payment/store_id');
        $params = [
            'store_id' => strval($store_id),
            'events' => $this->getEventsToSubscribe()
        ];
        $this->curl->post($url, json_encode($params));

        $response = json_decode($this->curl->getBody(), true);
        $output = [];
        foreach($response as $key => $value){
            $isActive = false;
            if(isset($value['subscription']['expires_at'])){
                $isActive = strtotime($value['subscription']['expires_at']) > time();
            }
            $output[$key] = [
                'event' => $key,
                'subscriptionId' => $value['subscription']['subscription_id'] ?? null,
                'webhookUrl' => $value['subscription']['url'] ?? null,
                'expiresAt' => $value['subscription']['expires_at'] ?? null,
                'inactiveAt' => $value['subscription']['inactive_at'] ?? null,
                'isActive' => $isActive,
            ];
        }
        return $output;
    }


    private function _checkApplicationStatus($applicationId){
        try{

            $url = $this->getApiUrl() . '/applications/' . $applicationId;
            $apiKey = $this->getApiKey();

            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $apiKey,
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);
            $this->logger->info('Okinus Request: ' . $url);
            $this->curl->get($url);

            $response = json_decode($this->curl->getBody(), true);

            $applicationStatus = $response['data']['status_code'];
            return $applicationStatus == 'Y';
        }catch(\Exception $e){
            $this->logger->error('Application status check error: ' . $e->getMessage());
            return false;
        }
    }

    private function _setOrderId($checkoutId, $orderId){
        try{

            $url = $this->getConfigValue('payment/okinus_payment/environment') == 1 ? 'https://beta2.okinus.com/api/v2/checkout' : 'https://www.okinushub.com/api/v2/checkout';
            $url = $url . "/$checkoutId/set-order-id/$orderId";
            $apiKey = $this->getApiKey();

            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $apiKey,
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);
            $this->logger->info('Okinus Request: ' . $url);
            $this->curl->post($url, []);

            $response = json_decode($this->curl->getBody(), true);

        }catch(\Exception $e){
            return false;
        }
    }



    /**
     * Unsubscribe from webhook
     */
    public function unsubscribe()
    {
        try {

            $url = $this->getApiUrl() . '/webhooks/unsubscribe';
            $apiKey = $this->getApiKey();

            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $apiKey,
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);
            $store_id = $this->getConfigValue('payment/okinus_payment/store_id');
            $params = [
                'store_id' => strval($store_id),
                'events' => $this->getEventsToSubscribe()
            ];

            $this->logger->info('Okinus Request: ' . $url . ':' . json_encode($params));
            $this->curl->post($url, json_encode($params));

            $response = json_decode($this->curl->getBody(), true);

            return [
                'success' => true,
                'message' => 'Successfully unsubscribed'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Webhook unsubscribe error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get webhook receiver URL
     */
    public function getWebhookUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl() . 'okinus/webhook/receive';
        // return 'https://s98.ngrok.io/okinus/webhook/receive';
    }

    /**
     * Verify webhook signature
     */
    public function verifySignature($payload, $incomingHash, $eventName)
    {
        try {
            if (!$eventName) {
                $this->logger->error('Webhook: Event name not provided');
                return false;
            }

            // Get the saved secret for this event
            $secret = $this->getConfigValue('payment/okinus_payment/webhook_secret_' . strtolower($eventName));
            $this->logger->info('payment/okinus_payment/webhook_secret_' . strtolower($eventName) . ' - ' . 'Verifying signature for event: ' . $eventName . ' with secret: ' . $secret);

            if (!$secret) {
                $this->logger->error('Webhook: No secret found for event ' . $eventName);
                return false;
            }

            // Remove newlines and extra whitespace from payload to match the stringified version
            $cleanPayload = preg_replace('/\s+/', '', $payload);

            // Generate hash using HMAC SHA256
            $generatedHash = hash_hmac('sha256', $cleanPayload, $secret);

            $this->logger->info('Webhook signature verification - Event: ' . $eventName . ', Generated: ' . $generatedHash . ', Received: ' . $incomingHash);

            // Compare the hashes
            return hash_equals($generatedHash, $incomingHash);

        } catch (\Exception $e) {
            $this->logger->error('Webhook signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate and queue a webhook event for deferred processing.
     *
     * The webhook fires the moment the loan is issued — while the customer is
     * usually still inside the Okinus iframe. Creating the order inline races
     * the frontend placeOrder() call (duplicate orders) and takes several
     * seconds, which makes Okinus redeliver the event. So we only store the
     * event here and ack immediately; the cron job creates the order after a
     * grace period if the frontend hasn't already done it.
     */
    public function queueWebhook($webhookData)
    {
        $eventName = $webhookData['event_name'] ?? null;
        $dataString = $webhookData['data'] ?? null;

        if (!$eventName || !$dataString) {
            $this->logger->error('Webhook: Missing event_name or data field');
            return [
                'success' => false,
                'message' => 'Invalid webhook data structure'
            ];
        }

        $eventData = json_decode($dataString, true);
        $applicationId = $eventData['application_id'] ?? null;
        if (!$applicationId) {
            $this->logger->error('Webhook: No application_id found in data');
            return [
                'success' => false,
                'message' => 'No application_id provided'
            ];
        }

        $trackedEventId = $webhookData['tracked_event_id'] ?? null;

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::WEBHOOK_EVENT_TABLE);

        if ($trackedEventId) {
            $existing = $connection->fetchOne(
                $connection->select()->from($table, 'entity_id')->where('tracked_event_id = ?', $trackedEventId)
            );
            if ($existing) {
                $this->logger->info('Webhook: Duplicate delivery of tracked_event_id ' . $trackedEventId . ' ignored');
                return [
                    'success' => true,
                    'message' => 'Event already received'
                ];
            }
        }

        try {
            $connection->insert($table, [
                'tracked_event_id' => $trackedEventId,
                'event_name' => $eventName,
                'application_id' => $applicationId,
                'payload' => json_encode($webhookData),
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            // A concurrent delivery of the same tracked_event_id hits the
            // unique index — treat it as the duplicate it is.
            if ($trackedEventId) {
                $existing = $connection->fetchOne(
                    $connection->select()->from($table, 'entity_id')->where('tracked_event_id = ?', $trackedEventId)
                );
                if ($existing) {
                    return [
                        'success' => true,
                        'message' => 'Event already received'
                    ];
                }
            }
            $this->logger->error('Webhook: Failed to queue event: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to queue event'
            ];
        }

        $this->logger->info('Webhook: Queued event ' . $eventName . ' for application_id ' . $applicationId);
        return [
            'success' => true,
            'message' => 'Event queued for processing'
        ];
    }

    /**
     * Process webhook and complete quote
     */
    public function processWebhook($webhookData)
    {
        try {
            // Parse the webhook data structure
            $eventName = $webhookData['event_name'] ?? null;
            $dataString = $webhookData['data'] ?? null;

            if (!$eventName || !$dataString) {
                $this->logger->error('Webhook: Missing event_name or data field');
                return [
                    'success' => false,
                    'message' => 'Invalid webhook data structure'
                ];
            }

            // Parse the data field (it's a JSON string)
            $eventData = json_decode($dataString, true);
            if (!$eventData) {
                $this->logger->error('Webhook: Failed to parse data field: ' . $dataString);
                return [
                    'success' => false,
                    'message' => 'Invalid data format'
                ];
            }

            // Extract application_id
            $applicationId = $eventData['application_id'] ?? null;
            if (!$applicationId) {
                $this->logger->error('Webhook: No application_id found in data');
                return [
                    'success' => false,
                    'message' => 'No application_id provided'
                ];
            }

            $this->logger->info('Webhook: Processing event ' . $eventName . ' for application_id: ' . $applicationId);

            if(!$this->_checkApplicationStatus($applicationId)){
                return [
                    'success' => true,
                    'message' => 'Application is not the correct status for order creation'
                ];
            }


            // Find quote by searching payment additional_information for checkout_id matching application_id
            $quote = $this->findQuoteByApplicationId($applicationId);

            if (!$quote) {
                $this->logger->error('Webhook: Quote not found for application_id: ' . $applicationId);
                return [
                    'success' => false,
                    'message' => 'Quote not found'
                ];
            }

            $this->logger->info('Webhook: Found quote ID: ' . $quote->getId());

            $checkoutId = $quote->getPayment()->getAdditionalInformation()['checkout_id'];

            // Serialize order creation per quote so this path can never race the
            // frontend placeOrder() (or a concurrent webhook delivery) into
            // creating a second order for the same quote.
            $lockName = 'okinus_order_quote_' . $quote->getId();
            if (!$this->lockManager->lock($lockName, 10)) {
                $this->logger->error('Webhook: Could not acquire lock for quote ' . $quote->getId());
                return [
                    'success' => false,
                    'message' => 'Could not acquire order creation lock'
                ];
            }

            try {
                // Reload the quote inside the lock so the checks below see the
                // latest state, not what it was before we waited for the lock.
                $quote = $this->quoteFactory->create()->load($quote->getId());

                // Check if order already exists for this quote
                $existingOrder = $this->orderFactory->create()->loadByAttribute('quote_id', $quote->getId());
                if ($existingOrder && $existingOrder->getId()) {
                    $this->logger->info('Webhook: Order already exists: ' . $existingOrder->getIncrementId());
                    return [
                        'success' => true,
                        'message' => 'Order already exists',
                        'order_id' => $existingOrder->getIncrementId()
                    ];
                }

                // Check if quote is already converted to order
                if (!$quote->getIsActive()) {
                    $this->logger->info('Webhook: Quote already converted: ' . $quote->getId());
                    return [
                        'success' => true,
                        'message' => 'Quote already processed',
                        'order_id' => $quote->getReservedOrderId()
                    ];
                }

                // Set payment method and additional information
                $payment = $quote->getPayment();
                $payment->setMethod('okinus_payment');

                // Add webhook data to payment for reference
                $payment->setAdditionalInformation('okinus_application_id', $applicationId);
                $payment->setAdditionalInformation('okinus_webhook_received', date('Y-m-d H:i:s'));
                $payment->setAdditionalInformation('okinus_event_name', $eventName);

                // Save quote
                $this->cartRepository->save($quote);

                // Normalize guest quote before submit. QuoteManagement::submit() skips the
                // guest preparation placeOrder() normally performs, so copy the customer
                // email and name from the billing address onto the quote ourselves —
                // otherwise the order is created with a blank customer name in the grid.
                $billingAddress = $quote->getBillingAddress();

                $customerEmail = $quote->getCustomerEmail();
                if (!$customerEmail) {
                    $customerEmail = $billingAddress->getEmail();
                    if (!$customerEmail && !$quote->isVirtual()) {
                        $customerEmail = $quote->getShippingAddress()->getEmail();
                    }
                    if (!$customerEmail) {
                        $customerEmail = 'customer_' . $quote->getId() . '@example.com';
                    }
                    $quote->setCustomerEmail($customerEmail);
                    $billingAddress->setEmail($customerEmail);
                    if (!$quote->isVirtual()) {
                        $quote->getShippingAddress()->setEmail($customerEmail);
                    }
                }

                if (!$quote->getCustomerId()) {
                    $quote->setCustomerIsGuest(true);
                    $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
                    if ($quote->getCustomerFirstname() === null && $quote->getCustomerLastname() === null) {
                        $quote->setCustomerFirstname($billingAddress->getFirstname());
                        $quote->setCustomerLastname($billingAddress->getLastname());
                        if ($billingAddress->getMiddlename() !== null) {
                            $quote->setCustomerMiddlename($billingAddress->getMiddlename());
                        }
                    }
                }

                $this->cartRepository->save($quote);

                // Convert quote to order
                $order = $this->quoteManagement->submit($quote);

                if (!$order) {
                    throw new \Exception('Failed to create order from quote');
                }
            } finally {
                $this->lockManager->unlock($lockName);
            }

            $this->_setOrderId($checkoutId, $order->getIncrementId());

            $this->logger->info('Webhook: Successfully created order: ' . $order->getIncrementId() . ' from quote: ' . $quote->getId());

            return [
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => $order->getIncrementId(),
                'order_entity_id' => $order->getId()
            ];

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Find quote by application_id stored in payment additional_information
     */
    protected function findQuoteByApplicationId($applicationId)
    {
        try {
            // Search through quote_payment table for matching checkout_id in additional_information
            $paymentCollection = $this->paymentCollectionFactory->create();
            $paymentCollection->addFieldToFilter('additional_information', ['like' => '%' . $applicationId . '%']);

            foreach ($paymentCollection as $payment) {
                $this->logger->info('Webhook: Checking payment ID: ' . $payment->getId());
                $additionalInfo = $payment->getAdditionalInformation();
                if (isset($additionalInfo['applicationId']) && $additionalInfo['applicationId'] == $applicationId) {
                    $quote = $this->quoteFactory->create()->load($payment->getQuoteId());
                    if ($quote->getId()) {
                        return $quote;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Error finding quote by application_id: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get configuration value
     */
    protected function getConfigValue($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
