/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/step-navigator'
], function (Component, quote, priceUtils, totals, stepNavigator) {
    'use strict';

    var mixin = {
        isFullMode: function () {
            return this.getTotals();
        }
    };

    return function (target) {
      return target.extend(mixin);
    };
});
