define(['jquery', 'jquery/jquery.cookie'], function ($) {
    'use strict';

    var checkoutSidebar = {
        _updateItemQtyAfter: function(elem){
            this._super(elem);
            this.checkPrice();

            return this;
        },

        _removeItemAfter: function (elem) {
            this._super(elem);
            this.checkPrice();

            return this;
        },

        checkPrice(){
            if ($.cookie('okinus_popup_status') != null && $.cookie('okinus_popup_status') == 'open') {
                $.ajax({
                    url: '/okinus/cart/checkprice',
                    method: 'post'
                }).done(function (response) {
                    $('.okinus-widget .amount-remain .value').html(response.data.value_format);
                    $.cookie('okinus_approval_amount_format', response.data.value_format, { expires: 86400 });
                });

                $.ajax({
                    url: '/okinus/cart/paymentcalculator',
                    method: 'post'
                }).done(function(response) {
                    $('.okinus-widget .estimated-payment .value').html(response.data.value_format);
                    $.cookie('okinus_subtraction_amount_format', response.data.value_format, {expires: 86400});
                });
            }
        }
    };

    return function (targetWidget) {
        $.widget('mage.sidebar', targetWidget, checkoutSidebar);

        return $.mage.catalogAddToCart;
    };
});
