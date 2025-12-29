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
    protected $logger;

    const XML_PATH_WEBHOOK_SUBSCRIPTION_ID = 'payment/okinus_payment/webhook_subscription_id';
    const XML_PATH_WEBHOOK_EXPIRES_AT = 'payment/okinus_payment/webhook_expires_at';

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
        LoggerInterface $logger
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
        return ['APPLICATION_APPROVED', 'APPLICATION_STATE_ACCEPTED', 'APPLICATION_FUNDED'];
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
    public function subscribe($apiKey, $storeId)
    {
        $this->unsubscribe();
        try {
            $webhookUrl = $this->getWebhookUrl();
            $url = $this->getApiUrl() . '/webhooks/subscribe';

            $params = [
                'url' => $webhookUrl,
                'events' => $this->getEventsToSubscribe(),
                'store_id' => $storeId,
                'email_notifications' => 'sarmad@studio98.com',
                'email_errors' => 'sarmad@studio98.com',
                'http_method' => 'POST'
            ];

            $apiKey = $this->getApiKey($apiKey);
            $headers = [
                "Content-Type" => "application/json",
                "Authorization" => "Bearer " . $apiKey,
                "Accept" => "application/json"
            ];

            $this->curl->setHeaders($headers);
            $this->curl->post($url, json_encode($params));

            // First unsubscribe existing subscriptions to avoid duplicates

            $response = json_decode($this->curl->getBody(), true);
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
            // $subscriptionId = $this->getConfigValue(self::XML_PATH_WEBHOOK_SUBSCRIPTION_ID);

            // if (!$subscriptionId) {
            //     return [
            //         'subscribed' => false,
            //         'message' => 'No active subscription found'
            //     ];
            // }
            $subscriptions = $this->_getSubscriptionStatus();
            $subscribed = false;
            $expiresAt = null;
            foreach($this->getEventsToSubscribe() as $event){
                if(isset($subscriptions[$event]) && $subscriptions[$event]['isActive']){
                    $subscribed = true;
                    $expiresAt = $subscriptions[$event]['expiresAt'];
                }else{
                    $subscribed = false;
                    break;
                }
            }
            return [
                'subscribed' => $subscribed,
                'subscribedEvents' => $this->getEventsToSubscribe(),
                'webhook_url' => $this->getWebhookUrl(),
                'expires_at' => $expiresAt,
                'is_expired' => false
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
        // return $this->storeManager->getStore()->getBaseUrl() . 'okinus/webhook/receive';
        return 'https://s98.ngrok.io/okinus/webhook/receive';
    }

    /**
     * Process webhook and complete quote
     */
    public function processWebhook($webhookData)
    {
        try {
            // Extract quote/cart ID from webhook data
            $quoteId = $webhookData['cart_id'] ?? $webhookData['quote_id'] ?? null;

            if (!$quoteId) {
                $this->logger->error('Webhook: No quote ID found in webhook data');
                return [
                    'success' => false,
                    'message' => 'No quote ID provided'
                ];
            }

            // Load the quote
            $quote = $this->quoteFactory->create()->load($quoteId);

            if (!$quote->getId()) {
                $this->logger->error('Webhook: Quote not found: ' . $quoteId);
                return [
                    'success' => false,
                    'message' => 'Quote not found'
                ];
            }

            // Check if quote is already converted to order
            if (!$quote->getIsActive()) {
                $this->logger->info('Webhook: Quote already converted: ' . $quoteId);
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
            $payment->setAdditionalInformation('okinus_application_id', $webhookData['application_id'] ?? null);
            $payment->setAdditionalInformation('okinus_payment_id', $webhookData['payment_id'] ?? null);
            $payment->setAdditionalInformation('okinus_webhook_received', date('Y-m-d H:i:s'));

            // Save quote
            $this->cartRepository->save($quote);

            // Convert quote to order
            $order = $this->quoteManagement->submit($quote);

            if (!$order) {
                throw new \Exception('Failed to create order from quote');
            }

            // Send order confirmation email
            if (!$order->getEmailSent()) {
                $this->orderSender->send($order);
            }

            $this->logger->info('Webhook: Successfully created order: ' . $order->getIncrementId() . ' from quote: ' . $quoteId);

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
