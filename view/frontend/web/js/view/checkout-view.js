/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/quote',
    'mage/translate',
], function (
    ko,
    Component,
    stepNavigator,
    quote,
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
            this._super();
        },

        getPaymentStepTitle: function () {
            if (this.isVirtual()) {
                return $t("1. Select payment");
            } else if(options.showPostcode) {
                return $t("3. Select payment");
            } else {
                return $t("2. Select payment");
            }
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
