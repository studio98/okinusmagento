<?php

namespace Okinus\Payment\Model\Config\Source;

/**
 * @api
 * @since 100.0.2
 */
class OkinusProductAttributes implements \Magento\Framework\Option\ArrayInterface
{

    protected $_attributeFactory;
    protected $logger;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_attributeFactory = $attributeFactory;
        $this->logger = $logger;
    }

    public function getAllAttributes()
    {
        $attribute_data = [];
        $attributeInfo = $this->_attributeFactory->create();
        foreach ($attributeInfo as $item) {
            $item = $item->getData();
            // If that is user define
            if ($item['is_user_defined'] == 1) {
                $attribute_data[] = $item;
            }
        }
        return $attribute_data;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $allAttributes = $this->getAllAttributes();
        $options = [];
        foreach ($allAttributes as $attribute) {
            $options[] = ['value' => $attribute['attribute_code'], 'label' => $attribute['frontend_label']];
        }
        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $allAttributes = $this->getAllAttributes();
        $options = [];
        foreach ($allAttributes as $attribute) {
            $options[$attribute['attribute_code']] = $attribute['frontend_label'];
        }
        return $options;
    }
}
