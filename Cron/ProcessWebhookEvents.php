<?php
namespace Okinus\Payment\Cron;

use Magento\Framework\App\ResourceConnection;
use Okinus\Payment\Helper\Webhook;
use Psr\Log\LoggerInterface;

class ProcessWebhookEvents
{
    /**
     * How long to wait after receiving a webhook before creating the order.
     * Gives the customer's browser (the primary order-creation path) time to
     * place the order normally; the webhook only acts as a safety net for
     * sessions that never return to the site.
     */
    const GRACE_PERIOD_SECONDS = 300;

    const MAX_ATTEMPTS = 5;
    const BATCH_SIZE = 20;

    protected $resourceConnection;
    protected $webhookHelper;
    protected $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        Webhook $webhookHelper,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->webhookHelper = $webhookHelper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(Webhook::WEBHOOK_EVENT_TABLE);

        $select = $connection->select()
            ->from($table)
            ->where('status IN (?)', ['pending', 'processing'])
            ->where('attempts < ?', self::MAX_ATTEMPTS)
            ->where('created_at <= NOW() - INTERVAL ' . (int)self::GRACE_PERIOD_SECONDS . ' SECOND')
            ->order('entity_id ASC')
            ->limit(self::BATCH_SIZE);

        $events = $connection->fetchAll($select);

        foreach ($events as $event) {
            $connection->update(
                $table,
                ['status' => 'processing', 'attempts' => new \Zend_Db_Expr('attempts + 1')],
                ['entity_id = ?' => $event['entity_id']]
            );

            $webhookData = json_decode($event['payload'], true);
            if (!$webhookData) {
                $connection->update(
                    $table,
                    ['status' => 'failed', 'message' => 'Invalid stored payload', 'processed_at' => new \Zend_Db_Expr('NOW()')],
                    ['entity_id = ?' => $event['entity_id']]
                );
                continue;
            }

            $this->logger->info('Okinus Cron: Processing webhook event ' . $event['entity_id'] . ' (application_id: ' . $event['application_id'] . ')');

            try {
                $result = $this->webhookHelper->processWebhook($webhookData);
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }

            if (!empty($result['success'])) {
                $update = [
                    'status' => 'complete',
                    'message' => $result['message'] ?? '',
                    'processed_at' => new \Zend_Db_Expr('NOW()'),
                ];
            } else {
                $exhausted = ((int)$event['attempts'] + 1) >= self::MAX_ATTEMPTS;
                $update = [
                    'status' => $exhausted ? 'failed' : 'pending',
                    'message' => $result['message'] ?? 'Unknown error',
                ];
                if ($exhausted) {
                    $update['processed_at'] = new \Zend_Db_Expr('NOW()');
                    $this->logger->error('Okinus Cron: Webhook event ' . $event['entity_id'] . ' failed after ' . self::MAX_ATTEMPTS . ' attempts: ' . ($result['message'] ?? ''));
                }
            }

            $connection->update($table, $update, ['entity_id = ?' => $event['entity_id']]);
        }
    }
}
