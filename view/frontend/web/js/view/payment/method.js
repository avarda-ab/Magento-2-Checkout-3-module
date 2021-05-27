/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define(['Magento_Checkout/js/view/payment/default'],
    function (Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Avarda_Checkout3/payment'
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method].instructions;
            },
            redirect: function () {
                window.location = '/avarda3/checkout?fromCheckout=1';
            }
        });
    }
);
