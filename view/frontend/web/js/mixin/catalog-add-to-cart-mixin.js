define(['jquery', 'js-cookie/cookie-wrapper'], function ($) {
    'use strict';

    var addToCartMixin = {
        ajaxSubmit: function (form) {
            this._super(form);

            $(document).on('ajax:addToCart', function () {
                
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

            })
            return this;
        }
    };

    return function (targetWidget) {
        return $.widget('mage.catalogAddToCart', targetWidget, addToCartMixin);

        // return $.mage.catalogAddToCart;
    };
});
