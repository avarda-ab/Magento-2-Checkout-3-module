/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'underscore',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Checkout/js/model/quote'
], function (
    _,
    setShippingInformationAction,
    stepNavigator,
    quote
) {
    'use strict';

    return function (Component) {
        return Component.extend({
            // Trigger Avarda iframe reinitialization after store-pickup component select
            initialize: function () {
                this._super();
                quote.shippingAddress.subscribe(function (address) {
                    if (this.isStorePickupSelected() && this.isStorePickupAddress(address)) {
                        setShippingInformationAction();
                    }
                }, this);
            },
            selectShippingMethod: function (shippingMethod) {
                this._super(shippingMethod);
                if (shippingMethod && !this.isStorePickupSelected()) {
                    setShippingInformationAction();
                }
            },

            // Fix bug in Magento_InventoryInStorePickupFrontend/js/view/store-pickup.js syncWithShipping isAvailable used without ()
            syncWithShipping: function () {
                var shippingStep = _.findWhere(stepNavigator.steps(), {
                    code: 'shipping'
                });

                shippingStep.isVisible.subscribe(function (isShippingVisible) {
                    this.isVisible(this.isAvailable() && isShippingVisible);
                }, this);
                this.isVisible(this.isAvailable() && shippingStep.isVisible());

                this.isAvailable.subscribe(function (isAvailable) {
                    this.isVisible(isAvailable && shippingStep.isVisible());
                }, this);

                // In the Avarda single-page checkout shippingStep.isVisible never changes,
                // so restore it explicitly when switching back from store pickup to shipping
                this.isStorePickupSelected.subscribe(function (isPickup) {
                    if (!isPickup) {
                        shippingStep.isVisible(true);
                    }
                });
            }
        });
    };
});
