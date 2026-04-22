/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'Magento_Checkout/js/action/set-shipping-information'
], function (
    setShippingInformationAction
) {
    'use strict';

    return function (Component) {
        return Component.extend({
            // Trigger Avarda iframe reinitialization after store-pickup component select
            selectShippingMethod: function (shippingMethod) {
                this._super(shippingMethod);
                if (shippingMethod) {
                    setShippingInformationAction();
                }
            }
        });
    };
});
