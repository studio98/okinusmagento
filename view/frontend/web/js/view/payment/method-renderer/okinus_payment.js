/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'jquery'
    ],
    function (Component, $) {
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

            createCheckout: function(){
                var self = this;
                $.ajax({
                    url: '/okinus/checkout/requesturl',
                    method: 'post',
                    showLoader: true
                }).done(function(response){
                    if(!response.success){
                        alert(response.data.message);
                    }else{
                        okinus.checkout(response.data.url, function(payload) {
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
