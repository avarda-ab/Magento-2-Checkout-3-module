/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'mage/translate',
], function (
    ko,
    Component,
    stepNavigator,
    quote,
    customer,
    $t
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Avarda_Checkout3/avarda'
        },
        isVisible: ko.observable(true),
        currentTotals: {},

        initialize: function () {
            // If guest user set email to empty string to avoid error message that email is required
            if (!customer.isLoggedIn() && !quote.guestEmail) {
                quote.guestEmail = '';
            }
            this._super();
        },

        getPaymentStepTitle: function () {
            if (this.isVirtual()) {
                return $t("1. Select payment");
            } else if (options.showPostcode) {
                return $t("3. Select payment");
            } else {
                return $t("2. Select payment");
            }
        },

        showLogin: function () {
            return options.offerLogin && this.isVirtual();
        },

        isVirtual: function () {
            return quote.isVirtual();
        },

        navigate: function () {

        },

        navigateToNextStep: function () {
            stepNavigator.next();
        }
    });
});
