define([
    'jquery',
    'ko',
    'Avarda_Checkout3/js/view/shipping-method',
    'Magento_Checkout/js/model/checkout-data-resolver'
], function (
    $,
    ko,
    Component,
    checkoutDataResolver
) {
    'use strict';

    return Component.extend({
        defaults: {
            // Without an own template the inherited shipping-method template re-shows the shipping rates.
            template: 'Avarda_Checkout3/checkout/avarda-payment'
        },
        initialize: function () {
            // _super() initializes the uiComponent base (else this.containers is undefined) and
            // defers the iframe init until the mount DOM exists.
            this._super();
            checkoutDataResolver.resolveBillingAddress();
            checkoutDataResolver.resolveShippingAddress();
            return this;
        }
    });
});
