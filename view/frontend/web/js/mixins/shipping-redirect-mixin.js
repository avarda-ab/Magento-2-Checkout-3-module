/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'jquery',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/checkout-data-resolver',
    'Magento_Checkout/js/checkout-data',
    'uiRegistry'
], function (
    $,
    ko,
    quote,
    setShippingInformationAction,
    checkoutDataResolver,
    checkoutData,
    registry
) {
    'use strict';

    var CODE = 'avarda_checkout3_checkout';

    function useAsPaymentStep() {
        var config = window.checkoutConfig.payment[CODE];

        return !!(config && config.useAsPaymentStep);
    }

    return function (Component) {
        return Component.extend({
            refreshingTotals: false,

            initialize: function () {
                this._super();

                // The payment step skips Magento's review step where totals are recalculated,
                // so refresh totals on shipping change to keep the order summary in sync.
                if (useAsPaymentStep()) {
                    var self = this;

                    quote.shippingMethod.subscribe(function (method) {
                        if (method && !self.refreshingTotals) {
                            self.refreshingTotals = true;
                            setShippingInformationAction().always(function () {
                                self.refreshingTotals = false;
                            });
                        }
                    });
                }

                return this;
            },

            // Redirect to the Avarda checkout instead of advancing to Magento's payment step.
            setShippingInformation: function () {
                if (!useAsPaymentStep()) {
                    this._super();
                    return;
                }

                if (this.validateShippingInformation()) {
                    quote.billingAddress(null);
                    checkoutDataResolver.resolveBillingAddress();
                    registry.async('checkoutProvider')(function (checkoutProvider) {
                        var shippingAddressData = checkoutData.getShippingAddressFromData();

                        if (shippingAddressData) {
                            checkoutProvider.set(
                                'shippingAddress',
                                $.extend(true, {}, checkoutProvider.get('shippingAddress'), shippingAddressData)
                            );
                        }
                    });
                    setShippingInformationAction().done(function () {
                        window.location.href = '/avarda3/checkout?fromCheckout=1';
                    });
                }
            }
        });
    };
});
