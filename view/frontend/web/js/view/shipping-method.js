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
    'Magento_Checkout/js/model/shipping-rate-registry',
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
    shippingRateRegistry,
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
        forceRenew: false,
        isCustomerLoggedIn: customer.isLoggedIn,

        cartLocked: ko.observable(false),
        email: ko.observable(),
        postalCode: ko.observable(),
        showNext: ko.observable(0),
        offerLogin: ko.observable(null),

        initialize: function () {
            let self = this;
            this._super();

            if (quote.isVirtual()) {
                self.initializeIframe();
            }

            let initial = shippingService.isLoading.subscribe(function() {
                if (!quote.isVirtual() && self.getShowPostcode()) {
                    $("#checkout-step-shipping_method").hide();
                    $("#checkout-step-iframe").hide();
                    if (customer.isLoggedIn()) {
                        self.email(customer.customerData.email);
                        self.postalCode(quote.shippingAddress().postcode);
                    } else {
                        self.email(quote.guestEmail || quote.shippingAddress().email);
                        self.postalCode(quote.shippingAddress().postcode);
                    }
                    self.email.subscribe(function (latest) {
                        if (quote.shippingAddress().email != latest) {
                            self.forceRenew = true;
                        }
                        quote.guestEmail = latest;
                        quote.shippingAddress().email = latest;
                        if (quote.billingAddress()) {
                            quote.billingAddress().email = latest;
                        }
                    });
                    self.postalCode.subscribe(function (latest) {
                        if (quote.shippingAddress().postcode != latest) {
                            self.forceRenew = true;
                        }
                        quote.shippingAddress().postcode = latest;
                        if (quote.billingAddress()) {
                            quote.billingAddress().postcode = latest;
                        }
                    });
                }

                if (!self.getShowPostcode()) {
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
            this.offerLogin(!!this.showLoginOffer());
        },

        getShowPostcode: function ()
        {
            return !!options.showPostcode;
        },

        getPostCodeTitle: function ()
        {
            if (this.getShowPostcode()) {
                return $.mage.__("1. Zip/Postal Code and Email");
            }
        },

        getShippingMethodTitle: function ()
        {
            if (this.getShowPostcode()) {
                return $.mage.__("2. Shipping Methods");
            } else {
                return $.mage.__("1. Shipping Methods");
            }
        },

        showLoginOffer: function()
        {
            return options.offerLogin && !this.isCustomerLoggedIn();
        },

        showLoginInfo: function ()
        {
            return options.offerLogin && this.isCustomerLoggedIn();
        },

        postCodeStep: function ()
        {
            $("#checkout-step-postalcode").show();
            $("#checkout-step-shipping_method").hide();
            $("#checkout-step-iframe").hide();
        },

        postCodeNext: function ()
        {
            if ($('#customer-password').val() == '') {
                this.showNext(0);
            }
            let form = $("#postal_code_form");
            if ($('.avarda.form.form-login').length) {
                form = $('.form.form-login');
            }

            if (form.valid()) {
                checkoutData.setShippingAddressFromData(quote.shippingAddress());
                $("#checkout-step-postalcode").hide();
                $("#checkout-step-shipping_method").show();
                $("#checkout-step-iframe").show();

                this.reloadShippingMethods();
                let rates = shippingService.getShippingRates()();
                if (rates.length > 0 && !quote.shippingMethod()) {
                    this.selectShippingMethod(rates[0])
                } else {
                    setShippingInformationAction();
                }
            }
        },

        /**
         * Reloads shipping methods from backend
         */
        reloadShippingMethods: function()
        {
            let address = quote.shippingAddress();
            shippingRateRegistry.set(address.getKey(), null);
            shippingRateRegistry.set(address.getCacheKey(), null);
            quote.shippingAddress(address);
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
            if (this.getShowPostcode() && !this.cartLocked()) {
                this.postalCode(data.zip);
                this.reloadShippingMethods();
            }
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
            let self = this;
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
                    self.cartLocked(true);
                    avardaCheckoutInstance.beforeSubmitContinue();
                    setTimeout(function() {
                        // Remove loader, if avarda validation fails user will not be forwarded
                        fullScreenLoader.stopLoader();
                    }, 1000);
                }).fail(function (response) {
                    avardaCheckoutInstance.beforeSubmitAbort();
                    fullScreenLoader.stopLoader();
                });
            } else {
                avardaCheckoutInstance.beforeSubmitAbort();
                $('<div><p>' +
                    $.mage.__("Missing shipping method. Please select the shipping method and try again.") +
                    '</p>')
                    .modal({
                        title: $.mage.__('Missing shipping method!'),
                        buttons: [{
                            text: 'OK',
                            class: 'action primary',
                            click: function () {
                                this.closeModal();
                            }
                        }]
                    }).modal('openModal');
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
            let renewParam = (self.forceRenew || renew) ? 1 : 0;
            self.forceRenew = false;

            fullScreenLoader.startLoader();
            self.initializing = true;
            let serviceUrl = '';

            if (customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/carts/mine/avarda3-payment/:renew', {
                    renew: renewParam
                });
            } else {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/avarda3-payment/:renew', {
                    cartId: quote.getQuoteId(),
                    renew: renewParam
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
                    options.deliveryAddressChangedCallback = function(data, avardaCheckoutInstance) {
                        self.updateShippingAddressHook(data, avardaCheckoutInstance);
                    };
                    options.beforeSubmitCallback = function(data, avardaCheckoutInstance) {
                        self.beforeCompleteHook(data, avardaCheckoutInstance);
                    };
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
