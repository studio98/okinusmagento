<?php
namespace Okinus\Payment\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Okinus\Payment\Helper\Webhook;
use Psr\Log\LoggerInterface;

/**
 * Okinus expires webhook subscriptions (~90 days). Without renewal the
 * webhook silently stops being delivered and webhook-path orders are never
 * created, so re-subscribe automatically before the expiry date.
 */
class RenewWebhookSubscription
{
    /**
     * Renew when the subscription expires within this many days. Runs daily,
     * so a generous window gives several retry opportunities if the Okinus
     * API is unreachable on a given day.
     */
    const RENEW_WINDOW_DAYS = 7;

    protected $scopeConfig;
    protected $webhookHelper;
    protected $cacheTypeList;
    protected $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Webhook $webhookHelper,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->webhookHelper = $webhookHelper;
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    public function execute()
    {
        $hasSubscription = false;
        $needsRenewal = false;

        foreach ($this->webhookHelper->getEventsToSubscribe() as $event) {
            $suffix = strtolower($event);
            $subscriptionId = $this->getConfigValue('payment/okinus_payment/webhook_subscription_id_' . $suffix);
            if (!$subscriptionId) {
                // Never subscribed (or explicitly unsubscribed) — the merchant
                // creates the initial subscription from the admin; the cron
                // only keeps an existing one alive.
                continue;
            }

            $hasSubscription = true;
            $expiresAt = $this->getConfigValue('payment/okinus_payment/webhook_expires_at_' . $suffix);
            if (!$expiresAt || (strtotime($expiresAt) - time()) < self::RENEW_WINDOW_DAYS * 86400) {
                $needsRenewal = true;
            }
        }

        if (!$hasSubscription || !$needsRenewal) {
            return;
        }

        $storeId = $this->getConfigValue('payment/okinus_payment/store_id');
        if (!$storeId) {
            $this->logger->error('Okinus Renewal Cron: Cannot renew webhook subscription, no store_id configured');
            return;
        }

        $email = $this->getConfigValue(Webhook::XML_PATH_WEBHOOK_EMAIL);
        if (!$email) {
            $email = $this->getConfigValue('trans_email/ident_general/email');
        }

        $this->logger->info('Okinus Renewal Cron: Webhook subscription expires within ' . self::RENEW_WINDOW_DAYS . ' days, renewing');

        // Passing null for the API key makes the helper use the configured one.
        $result = $this->webhookHelper->subscribe(null, $storeId, $email);

        if (!empty($result['success'])) {
            // The new secret and expiry were written to core_config_data;
            // clean the config cache so signature verification uses the new
            // secret immediately.
            $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
            $this->logger->info('Okinus Renewal Cron: Webhook subscription renewed successfully');
        } else {
            $this->logger->error('Okinus Renewal Cron: Webhook subscription renewal failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    protected function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
