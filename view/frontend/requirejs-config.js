/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/summary/abstract-total': {
                'Avarda_Checkout3/js/mixins/abstract-total-mixin': true
            },
            'Magento_InventoryInStorePickupFrontend/js/view/store-pickup': {
                'Avarda_Checkout3/js/mixins/store-pickup-mixin': true
            }
        }
    }
};
