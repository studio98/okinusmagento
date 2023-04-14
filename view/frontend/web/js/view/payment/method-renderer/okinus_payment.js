/**
 * Copyright Â© 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/model/payment/additional-validators'
    ],
    function (Component, $, additionalValidators) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Okinus_Payment/payment/form',
                transactionResult: ''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function() {
                return 'okinus_payment';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult()
                    }
                };
            },

            getTransactionResults: function() {
                return _.map(window.checkoutConfig.payment.okinus_payment.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            },

            isButtonActive: function () {
                return false;
            },

            createCheckout: function(){
                var self = this;
                // var okinus = Okinus({
                //     baseUri: "https://beta2.okinus.com",
                //     retailerSlug: window.checkoutConfig.payment.okinus_payment.retailerSlug,
                //     storeSlug: window.checkoutConfig.payment.okinus_payment.storeSlug
                // });
                if (!additionalValidators.validate() || !this.isPlaceOrderActionAllowed()) {
                    return false;
                }
                $.ajax({
                    url: '/okinus/checkout/requesturl',
                    method: 'post',
                    showLoader: true
                }).done(function(response){
                    if(!response.success){
                        alert(response.data.message);
                    }else{
                        okinus.checkout(response.data.url, function(payload) {
                            console.log('test' ,payload);
                            if(payload.status == 'success' && payload.step == 'CHECKOUT_COMPLETED'){
                                self.placeOrder();
                            }
                        });
                    }
                })
            }
        });
    }
);
