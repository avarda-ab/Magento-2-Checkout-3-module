/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'ko',
    'Magento_Checkout/js/view/form/element/email',
    'jquery'
], function (ko, defaultEmail) {
    'use strict';

    let mixin = {
        defaults: {
            template: 'Avarda_Checkout3/form/element/email',
            imports: {
                postalCode: '${ $.parentName }:postalCode',
                email: '${ $.parentName }:email',
                showPassword: '${ $.parentName }:showNext',
                offerLogin: '${ $.parentName }:offerLogin',
            },
            exports: {
                postalCode: '${ $.parentName }:postalCode',
                email: '${ $.parentName }:email',
                showPassword: '${ $.parentName }:showNext',
            }
        },

        postalCode: ko.observable(),
        email: ko.observable(),
        showPassword: ko.observable(),
        offerLogin: ko.observable(),

        validateEmail: function () {
            if (this.offerLogin()) {
                return this._super();
            }
        }
    };

    return defaultEmail.extend(mixin);
});
