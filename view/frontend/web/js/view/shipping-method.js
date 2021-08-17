/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/shipping',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/shipping-rate-service',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    "Magento_Checkout/js/model/payment-service",
    "Magento_Checkout/js/model/error-processor",
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/shipping-service',
    'mage/translate'
], function (
    $,
    ko,
    Component,
    setShippingInformationAction,
    selectShippingMethodAction,
    checkoutData,
    shippingRateService,
    quote,
    urlBuilder,
    storage,
    paymentService,
    errorProcessor,
    customer,
    fullScreenLoader,
    shippingService
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Avarda_Checkout3/shipping-method'
        },
        initializing: false,
        initializeTimeout: false,

        initialize: function () {
            let self = this;
            this._super();

            if (quote.isVirtual()) {
                self.initializeIframe();
            }

            let initial = shippingService.isLoading.subscribe(function() {
                // If no shipping method is selected select the first one
                if (!quote.shippingMethod()) {
                    let rates = shippingService.getShippingRates()();
                    if (rates.length > 0) {
                        self.selectShippingMethod(rates[0])
                    }
                } else {
                    // This is needed when shippingMethod is already selected, but it might not be saved properly
                    setShippingInformationAction();
                }
                // remove this subscription
                initial.dispose();
            });

            /**
             * Listener for quote totals changes
             * Triggers iframe initialization when totals change
             */
            quote.totals.subscribe(function () {
                if (quote.shippingMethod() || quote.isVirtual()) {
                    // Avoid duplicate and same time initialization, which can cause
                    // problems on backend if run too simultaneously
                    clearTimeout(self.initializeTimeout);
                    self.initializeTimeout = setTimeout(function(){
                        self.initializeIframe();
                    }, 350);
                }
            });
        },

        selectShippingMethod: function (shippingMethod) {
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(shippingMethod['carrier_code'] + '_' + shippingMethod['method_code']);
            // Save selection
            setShippingInformationAction();
            return true;
        },

        /**
         * Update hook when changing different shipping address inside iframe
         *
         * @param data
         * @param avardaCheckoutInstance
         */
        updateShippingAddressHook: function (data, avardaCheckoutInstance) {
            avardaCheckoutInstance.deliveryAddressChangedContinue();
        },

        /**
         * Before redirecting away from the checkout page
         * is called right before the Check-Out is completed.
         * This function must call result.continue() or result.cancel() function.
         *
         * @param data
         * @param avardaCheckoutInstance
         */
        beforeCompleteHook: function (data, avardaCheckoutInstance) {
            if (quote.shippingMethod() || quote.isVirtual()) {
                let serviceUrl = '';
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/avarda3-payment', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/avarda3-payment', {
                        cartId: quote.getQuoteId()
                    });
                }
                fullScreenLoader.startLoader();
                storage.post(
                    serviceUrl, []
                ).done(function () {
                    avardaCheckoutInstance.beforeSubmitContinue();
                }).fail(function (response) {
                    avardaCheckoutInstance.beforeSubmitAbort(response);
                    fullScreenLoader.stopLoader();
                });
            } else {
                avardaCheckoutInstance.beforeSubmitAbort($.mage.__("Missing shipping method. Please select the shipping method and try again."));
            }
        },

        sessionTimedOutCallback: function(avardaCheckoutInstance) {
            avardaCheckoutInstance.unmount();
            options.purchaseId = undefined;
        },

        /**
         * Initializes checkout iframe
         *
         * @returns {boolean}
         */
        initializeIframe: function(renew) {
            let self = this;
            if (self.initializing) {
                return;
            }

            fullScreenLoader.startLoader();
            self.initializing = true;
            let serviceUrl = '';

            if (customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/carts/mine/avarda3-payment/:renew', {
                    renew: renew
                });
            } else {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/avarda3-payment/:renew', {
                    cartId: quote.getQuoteId(),
                    renew: renew
                });
            }

            storage.get(
                serviceUrl, true
            ).done(function (response) {
                // Initialize is needed only if purchaseId changes
                if (typeof avardaCheckout == "undefined" || options.purchaseId !== response.purchase_data[0]) {
                    if (typeof avardaCheckout != "undefined") {
                        avardaCheckout.unmount();
                    }
                    options.purchaseId = response.purchase_data[0];
                    options.purchaseJwt = response.purchase_data[1];
                    options.redirectUrl = options.redirectUrlBase + response.purchase_data[0];
                    options.deliveryAddressChangedCallback = self.updateShippingAddressHook;
                    options.beforeSubmitCallback = self.beforeCompleteHook;
                    options.sessionTimedOutCallback = function(avardaCheckoutInstance) {
                        self.sessionTimedOutCallback(avardaCheckoutInstance);
                        self.initializeIframe(1);
                    };
                    options.completedPurchaseCallback = function (avardaCheckoutInstance) {
                        avardaCheckoutInstance.unmount();
                        window.location.href = options.saveOrderUrl + options.purchaseId;
                    };

                    // Reinitialize checkout iframe
                    avardaCheckoutInit(options);
                } else {
                    // Update items to update visible price
                    avardaCheckout.refreshForm();
                }
                fullScreenLoader.stopLoader();
                self.initializing = false;
            }).fail(function (response) {
                errorProcessor.process(response);
                fullScreenLoader.stopLoader();
                self.initializing = false;
            });

            return true;
        }
    });
});
