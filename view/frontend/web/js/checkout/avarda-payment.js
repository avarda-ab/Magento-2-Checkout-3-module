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
        initialize: function () {
            let self = this;
            checkoutDataResolver.resolveBillingAddress();
            checkoutDataResolver.resolveShippingAddress();
            self.initializeIframe();
        }
    });
});
