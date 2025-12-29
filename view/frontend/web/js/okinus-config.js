define([
    'jquery',
    'Okinus_Payment/js/localload'
], function ($, Okinus) {
    'use strict';

    return function (config, element) {
        var $element = $(element);
        
        try {
            console.log('Okinus Payment Config Loading - Widget Initialized');
            
            // Get configuration from data attributes
            var okinusConfig = {
                baseUri: $element.data('okinus-base-uri'),
                storeSlug: $element.data('okinus-store-slug'),
                retailerSlug: $element.data('okinus-retailer-slug')
            };
            
            var imageUrl = $element.data('okinus-image-url');
            
            console.log('Store Slug:', okinusConfig.storeSlug);
            console.log('Retailer Slug:', okinusConfig.retailerSlug);
            console.log('Base URL:', okinusConfig.baseUri);
            console.log('Image URL:', imageUrl);
            
            // Initialize Okinus
            var okinusInstance = Okinus(okinusConfig);
            
            // Set global variables
            window.okinus = okinusInstance;
            window.okinusImageUrl = imageUrl;
            
            console.log('Okinus initialized successfully:', okinusInstance);
            console.log('window.okinus set to:', window.okinus);
            console.log('window.okinusImageUrl set to:', window.okinusImageUrl);
            
        } catch (error) {
            console.error('Error initializing Okinus:', error);
        }
    };
});
