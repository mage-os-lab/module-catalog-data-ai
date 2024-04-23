/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
define([
    'jquery',
    'underscore',
    'mage/translate',
    'mage/url',
    'uiRegistry',
    'tinymce',
], function ($, _, translate, urlBuilder, uiRegistry, tinyMCE) {
    'use strict';

    function generateContent(element) {
        const target = this.getTarget(element),
              productId = element.product_id;

        this.getGeneratedAiContent(
            target,
            element.url,
            this.getTargetValue(target),
            target.code,
            productId
        );
    }

    function getTargetValue(target, value) {
        let wysiwygTarget = tinyMCE.get(target.wysiwygId);
        if (target.wysiwyg && wysiwygTarget !== null) {
            return wysiwygTarget.getContent();
        } else {
            return target.value();
        }
    }

    function updateTargetValue(target, value) {
        let wysiwygTarget = tinyMCE.get(target.wysiwygId);
        if (target.wysiwyg && wysiwygTarget !== null) {
            wysiwygTarget.setContent(value);
        } else {
            target
                .value(value)
                .trigger('change');
        }
    }

    function getTarget(element) {
        let fullTargetPath = element.parentName + "." + element.targetName;
        return uiRegistry.get(fullTargetPath);
    }

    function getGeneratedAiContent(target, url, value, attributeCode, productId) {
        const that = this;
        $.ajax({
            url: url,
            showLoader: true,
            data: {
                form_key: window.FORM_KEY,
                value,
                attribute_code: attributeCode,
                product_id: productId,
            },
            type: "POST",
            dataType : 'json',
            success: (result) => {
                let content = result.response?.message?.content ?? '';
                that.updateTargetValue(target, content);
            }
        });
    }

    return {
        generateContent: generateContent,

        getTarget: getTarget,

        getTargetValue: getTargetValue,

        updateTargetValue: updateTargetValue,

        getGeneratedAiContent: getGeneratedAiContent
    };
});
