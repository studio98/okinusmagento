<?php
namespace Okinus\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Okinus\Payment\Gateway\Http\Client\ClientMock;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }
    const CODE = 'okinus_payment';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Fraud')
                    ],
                    'storeSlug' => $this->scopeConfig->getValue('payment/okinus_payment/store_slug', 
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'retailerSlug' => $this->scopeConfig->getValue('payment/okinus_payment/retailer_slug', 
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
                ]
            ]
        ];
    }
}
