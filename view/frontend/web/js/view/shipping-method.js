/**
 * @copyright Copyright © Avarda. All rights reserved.
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
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/model/checkout-data-resolver',
    'mage/translate',
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
    shippingService,
    customerData,
    placeOrderAction,
    checkoutDataResolver,
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Avarda_Checkout3/shipping-method'
        },
        initializing: ko.observable(false),
        initializeTimeout: false,
        forceRenew: ko.observable(false),
        purchaseId: ko.observable(''),
        isCustomerLoggedIn: customer.isLoggedIn,

        cartLocked: ko.observable(false),
        email: ko.observable(),
        postalCode: ko.observable(),
        showNext: ko.observable(0),
        offerLogin: ko.observable(null),
        currentStep: ko.observable(0),
        shippingSelecting: ko.observable(false),

        initialize: function () {
            let self = this;
            this._super();

            if (quote.isVirtual()) {
                self.initializeIframe();
            }

            let initial = shippingService.isLoading.subscribe(function () {
                checkoutDataResolver.resolveBillingAddress();
                checkoutDataResolver.resolveShippingAddress();

                if (!quote.isVirtual() && self.getShowPostcode()) {
                    $("#checkout-step-shipping_method").hide();
                    $("#checkout-step-iframe").hide();
                    if (customer.isLoggedIn()) {
                        self.email(customer.customerData.email);
                        self.postalCode(quote.shippingAddress().postcode);
                        self.postCodeNext()
                    } else {
                        self.email(quote.guestEmail || quote.shippingAddress().email);
                        self.postalCode(quote.shippingAddress().postcode);
                    }
                    self.email.subscribe(function (latest) {
                        if (quote.shippingAddress().email != latest && self.currentStep() === 0) {
                            self.forceRenew(true);
                        }
                        quote.guestEmail = latest;
                        quote.shippingAddress().email = latest;
                        if (quote.billingAddress()) {
                            quote.billingAddress().email = latest;
                        }
                    });
                    self.postalCode.subscribe(function (latest) {
                        // Force renew only if changed from postcode step
                        if (quote.shippingAddress().postcode != latest && self.currentStep() === 0) {
                            self.forceRenew(true);
                        }
                        quote.shippingAddress().postcode = latest;
                        if (quote.billingAddress()) {
                            quote.billingAddress().postcode = latest;
                        }
                    });
                }

                if (!self.getShowPostcode()) {
                    // If no shipping method is selected, select the first one
                    if (!quote.shippingMethod()) {
                        let rates = shippingService.getShippingRates()();
                        if (rates.length > 0) {
                            if (self.getSelectShippingMethod()) {
                                self.selectShippingMethod(rates[0])
                            }
                        }
                    } else {
                        // This is needed when shippingMethod is already selected, but it might not be saved properly
                        setShippingInformationAction();
                    }
                }
                // remove this subscription
                initial.dispose();

                // make sure isloading is false because getShippingRates also starts the loader
                // it will disable input fields in magento versions <2.4.3
                shippingService.isLoading(false);
            });

            /**
             * Listener for quote totals changes
             * Triggers iframe initialization when totals change
             */
            quote.totals.subscribe(function () {
                if (typeof avardaCheckout != "undefined" || quote.shippingMethod() || quote.isVirtual()) {
                    // Avoid duplicate and same time initialization, which can cause
                    // problems on backend if run too simultaneously
                    clearTimeout(self.initializeTimeout);
                    self.initializeTimeout = setTimeout(function () {
                        self.initializeIframe();
                    }, 350);
                }
            });
            this.offerLogin(!!this.showLoginOffer());
        },

        getShowPostcode: function () {
            return !!options.showPostcode;
        },

        getSubscribeAddressChangeCallback: function () {
            return !!options.addressChangeCallback;
        },

        getSelectShippingMethod: function () {
            return options.selectShippingMethod;
        },

        getPostCodeTitle: function () {
            if (this.getShowPostcode()) {
                return $.mage.__("1. Zip/Postal Code and Email");
            }
        },

        getShippingMethodTitle: function () {
            if (this.getShowPostcode()) {
                return $.mage.__("2. Shipping Methods");
            } else {
                return $.mage.__("1. Shipping Methods");
            }
        },

        showLoginOffer: function () {
            return options.offerLogin && !this.isCustomerLoggedIn();
        },

        showLoginInfo: function () {
            return options.offerLogin && this.isCustomerLoggedIn();
        },

        postCodeStep: function () {
            this.currentStep(0);
            $("#checkout-step-postalcode").show();
            $("#checkout-step-shipping_method").hide();
            $("#checkout-step-iframe").hide();
        },

        postCodeNext: function () {
            this.currentStep(1);
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
                    if (this.getSelectShippingMethod()) {
                        this.selectShippingMethod(rates[0])
                    }
                } else {
                    setShippingInformationAction();
                }
            }
        },

        /**
         * Reloads shipping methods from backend
         */
        reloadShippingMethods: function () {
            let address = quote.shippingAddress();
            shippingRateRegistry.set(address.getKey(), null);
            shippingRateRegistry.set(address.getCacheKey(), null);
            quote.shippingAddress(address);
        },

        selectShippingMethod: function (shippingMethod) {
            let self = requirejs('uiRegistry').get('checkout.steps.avarda-shipping');

            // Set selected shipping method
            selectShippingMethodAction(shippingMethod);
            checkoutData.setSelectedShippingRate(shippingMethod['carrier_code'] + '_' + shippingMethod['method_code']);

            // Depending on the shipping method, this might be called many times which would cause multiple saves at
            // the same time so we make sure no duplicate save happening
            if (self.shippingSelecting()) {
                return true;
            }
            self.shippingSelecting(true);

            // Save selection
            setShippingInformationAction().always(function () {
                self.shippingSelecting(false);
            });
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
         * This function must call avardaCheckoutInstance.beforeSubmitContinue() or avardaCheckoutInstance.beforeSubmitAbort() function.
         *
         * @param data
         * @param avardaCheckoutInstance
         */
        beforeCompleteHook: function (data, avardaCheckoutInstance) {
            let self = this;

            // if cart is locked don't try to lock it again
            if (self.cartLocked()) {
                avardaCheckoutInstance.beforeSubmitContinue();
                setTimeout(function () {
                    // Remove loader, if avarda validation fails user will not be forwarded
                    fullScreenLoader.stopLoader();
                }, 1000);
            } else if (quote.shippingMethod() || quote.isVirtual()) {
                self.cartLocked(true);

                // if postcode not showing then email is not set yet
                if (!self.getShowPostcode() || !quote.guestEmail || !quote.billingAddress().email) {
                    quote.guestEmail = data.email;
                    quote.shippingAddress().email = data.email;
                    if (quote.billingAddress()) {
                        quote.billingAddress().email = data.email;
                    }
                }

                placeOrderAction({
                    'method': 'avarda_checkout3_checkout',
                    'additional_data': {'avarda': JSON.stringify(data)}
                }).fail(function (response) {
                    self.cartLocked(false);
                    let error = '';
                    try {
                        let result = JSON.parse(response.responseText);
                        error = result.message;
                        $.each(result.parameters, function (key, val) {
                            error = error.replace('%' + key, val);
                        });
                    } catch (exception) {
                        error = $.mage.__('Something went wrong with your request. Please try again later.');
                    }
                    $('<div class="messages"><div class="message error"><div>' +
                        error +
                        '</div></div></div>')
                        .modal({
                            title: $.mage.__('Something went wrong with your request.'),
                            buttons: [{
                                text: 'OK',
                                class: 'action primary',
                                click: function () {
                                    this.closeModal();
                                }
                            }]
                        }).modal('openModal');
                    avardaCheckoutInstance.beforeSubmitAbort();
                    fullScreenLoader.stopLoader();
                }).done(function () {
                    history.pushState(null, document.title, options.redirectUrl);
                    fullScreenLoader.startLoader();
                    avardaCheckoutInstance.beforeSubmitContinue();
                    setTimeout(function() {
                        // Remove loader, if avarda validation fails user will not be forwarded
                        fullScreenLoader.stopLoader();
                    }, 1000);
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

        sessionTimedOutCallback: function (avardaCheckoutInstance) {
            avardaCheckoutInstance.unmount();
            options.purchaseId = undefined;
        },

        /**
         * This is called initially when iframe is initialized
         * Purpose is to allow modifying via plugin the options before initializing iframe
         *
         * @param options
         */
        avardaCheckoutInitOptions: function (options) {
            return options;
        },

        /**
         * Initializes checkout iframe
         *
         * @returns {boolean}
         */
        initializeIframe: function (renew) {
            let self = this;
            if (self.initializing()) {
                return true;
            }
            let renewParam = (self.forceRenew() || renew) ? 1 : 0;
            self.forceRenew(false);

            fullScreenLoader.startLoader();
            self.initializing(true);
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
                    self.purchaseId(options.purchaseId);
                    options.purchaseJwt = response.purchase_data[1];
                    options.redirectUrl = options.redirectUrlBase + response.purchase_data[0];
                    if (self.getSubscribeAddressChangeCallback()) {
                        options.deliveryAddressChangedCallback = function (data, avardaCheckoutInstance) {
                            self.updateShippingAddressHook(data, avardaCheckoutInstance);
                        };
                    }
                    options.beforeSubmitCallback = function (data, avardaCheckoutInstance) {
                        self.beforeCompleteHook(data, avardaCheckoutInstance);
                    };
                    options.sessionTimedOutCallback = function (avardaCheckoutInstance) {
                        self.sessionTimedOutCallback(avardaCheckoutInstance);
                        self.initializeIframe(1);
                    };
                    options.completedPurchaseCallback = function (avardaCheckoutInstance) {
                        avardaCheckoutInstance.unmount();
                        customerData.reload(['cart']);
                        window.location.href = options.saveOrderUrl + options.purchaseId;
                    };

                    // Allow other modules modify options before initializing iframe
                    self.avardaCheckoutInitOptions(options);

                    // (Re)Initialize checkout iframe
                    avardaCheckoutInit(options);
                } else {
                    // Update items to update visible price
                    avardaCheckout.refreshForm();
                }
                fullScreenLoader.stopLoader();
                self.initializing(false);
            }).fail(function (response) {
                errorProcessor.process(response);
                fullScreenLoader.stopLoader();
                self.initializing(false);
            });

            return true;
        }
    });
});
