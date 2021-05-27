/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([
    'uiComponent'
], function (
    Component
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Avarda_Checkout3/sidebar'
        },
        visible: true,

        /**
         * @return {exports}
         */
        initialize: function () {

            var self = this;
            this._super();
        }
    });
});
