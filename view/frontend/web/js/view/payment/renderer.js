/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ], function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'avarda_checkout3_checkout',
                component: 'Avarda_Checkout3/js/view/payment/method'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
