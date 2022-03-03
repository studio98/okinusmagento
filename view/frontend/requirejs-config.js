var config = {
    paths: {
        'okinus_script': 'https://beta2.okinus.com/checkout/script'
    },
    config: {
        mixins: {
            'Magento_Catalog/js/catalog-add-to-cart': {
                'Okinus_Payment/js/mixin/catalog-add-to-cart-mixin': true
            },
            'Magento_Checkout/js/sidebar': {
                'Okinus_Payment/js/mixin/checkout-sidebar-mixin': true
            }
        }
    }
};
