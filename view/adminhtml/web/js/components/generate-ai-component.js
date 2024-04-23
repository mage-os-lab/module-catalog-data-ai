/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
define([
    'jquery',
    'Magento_Ui/js/form/components/button',
    'uiComponent',
    'Magento_Ui/js/modal/alert',
    'Mijnheer_CatalogDataAi/js/actions/generate-ai-action',
], function ($, Button, Component, alert, aiAction) {
    'use strict';

    return Button.extend({
        initialize: function () {
            this._super();
        },

        getTemplate: function () {
            return 'ui/form/components/button/container';
        },

        action: function () {
            aiAction.generateContent(this);
        },
    });
});
