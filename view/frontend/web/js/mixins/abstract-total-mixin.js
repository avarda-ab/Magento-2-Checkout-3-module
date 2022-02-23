/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
define([], function () {
    'use strict';

    let mixin = {
        isFullMode: function () {
            return this.getTotals();
        }
    };
    return function (target) {
      return target.extend(mixin);
    };
});
