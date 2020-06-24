/**
 * Copyright 2018 Klarna Bank AB (publ)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   Klarna
 * @package    Klarna_Kco
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/**
 * Klarna checkout JS
 */
if (typeof Klarna == 'undefined') {
    var Klarna = {};
}

Klarna.Checkout = Class.create();
Klarna.Checkout.prototype = {
    initialize: function (saveUrl, failureUrl, reloadUrl, messageId, frontEndAddress, addressUrl, frontEndShipping) {
        this.loadWaiting = false;
        this.suspended = false;
        this.saveUrl = saveUrl;
        this.failureUrl = failureUrl;
        this.reloadUrl = reloadUrl;
        this.addressUrl = addressUrl;
        this.messageId = messageId;
        this.frontEndAddress = frontEndAddress || false;
        this.frontEndShipping = frontEndShipping || false;
        this.onSave = this.saveResponse.bindAsEventListener(this);
        this.onComplete = this.resetLoadWaiting.bindAsEventListener(this);
        document.observe('dom:loaded', this.attachEvents.bind(this));
    },

    attachEvents: function () {
        if (window._klarnaCheckout) {
            window._klarnaCheckout(
                function (api) {
                    api.on(
                        {
                            'change': function (data) {
                                if (!checkout.frontEndShipping) {
                                    var request = new Ajax.Request(
                                        checkout.saveUrl,
                                        {
                                            method: 'post',
                                            parameters: data,
                                            onSuccess: function (response) {
                                                checkout.suspend();
                                                checkout.resume();
                                                checkout.reloadContainer();
                                            }
                                        }
                                    );
                                }
                            },
                            'order_total_change': function (data) {
                                checkout.reloadContainer();
                            },
                            'shipping_option_change': function (data) {
                                checkout.showLoader();

                                /**
                                 * When using the Klarna shipping gateway and a fallback happens
                                 * we need to update the klarna order before the order totals will be reloaded
                                 */
                                var request = new Ajax.Request(
                                    checkout.saveUrl,
                                    {
                                        method: 'post',
                                        parameters: data,
                                        onComplete: function (response) {
                                            checkout.suspend();
                                            checkout.resume();
                                            checkout.hideLoader();
                                            checkout.reloadContainer();
                                        }
                                    }
                                );
                            },
                            'shipping_address_change': function (data) {
                                if (checkout.frontEndAddress) {
                                    checkout.suspend();
                                    checkout.showLoader();

                                    var request = new Ajax.Request(
                                        checkout.addressUrl,
                                        {
                                            method: 'post',
                                            onComplete: checkout.onComplete,
                                            onSuccess: checkout.onSave,
                                            onFailure: checkout.ajaxFailure.bind(checkout),
                                            parameters: data
                                        }
                                    );
                                } else {
                                    checkout.reloadContainer();
                                }
                            }
                        }
                    );
                }
            );
        }
    },

    ajaxFailure: function () {
        location.href = this.failureUrl;
    },

    suspend: function () {
        if (!this.suspended && window._klarnaCheckout) {
            window._klarnaCheckout(
                function (api) {
                    api.suspend();
                }
            );

            this.suspended = true;
        }
    },

    resume: function () {
        if (this.suspended && window._klarnaCheckout) {
            window._klarnaCheckout(
                function (api) {
                    api.resume();
                }
            );

            this.suspended = false;
        }
    },

    showLoader: function () {
        this.loadWaiting = true;
        $('klarna_loader').show();
    },

    hideLoader: function () {
        this.loadWaiting = false;
        $('klarna_loader').hide();
    },

    saveResponse: function (transport) {
        var response = {};

        if (transport && transport.responseText) {
            try {
                response = eval('(' + transport.responseText + ')');
            }
            catch (e) {
            }
        }

        this.setResponse(response);
    },

    resetLoadWaiting: function () {
        this.hideLoader();
        this.resume();
    },

    setResponse: function (response) {
        var messageBox = $(this.messageId);
        var messageBoxWrapper = $(this.messageId + '_wrapper');
        var messageBoxContent = $(this.messageId + '_content');

        if (response.redirect) {
            location.href = response.redirect;
            return true;
        }

        messageBox.hide();
        if (response.update_section) {
            $(response.update_section.name).update(response.update_section.html);
        }

        if (response.update_sections) {
            response.update_sections.forEach(
                function (update_section) {
                    $(update_section.name).update(update_section.html);
                }
            );
        }

        if (response.success) {
            messageBoxWrapper.removeClassName('error-msg');
            messageBoxWrapper.addClassName('success-msg');
            messageBox.show();
            messageBoxContent.update(response.success);
        }

        if (response.error) {
            messageBoxWrapper.removeClassName('success-msg');
            messageBoxWrapper.addClassName('error-msg');
            messageBox.show();
            messageBoxContent.update(response.error);
        }

        return false;
    },

    reloadContainer: function () {
        if (this.loadWaiting !== false) return;

        this.showLoader();

        var request = new Ajax.Request(
            this.reloadUrl,
            {
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
    }
};

Klarna.Form = Class.create();
Klarna.Form.prototype = {
    initialize: function (form, saveUrl, suspend) {
        this.form = form;
        this.saveUrl = saveUrl;
        this.suspendKlarna = suspend ? true : false;
        this.onSave = checkout.saveResponse.bindAsEventListener(this);
        this.onComplete = checkout.resetLoadWaiting.bindAsEventListener(this);

        if ($(this.form)) {
            $(this.form).observe(
                'submit', function (event) {
                    this.save();
                    Event.stop(event);
                }.bind(this)
            );
        }
    },

    save: function () {
        if (checkout.loadWaiting !== false) return;

        if (this.suspendKlarna) {
            checkout.suspend();
        }

        checkout.showLoader();

        var validator = new Validation(this.form);
        if (validator.validate()) {
            var request = new Ajax.Request(
                this.saveUrl,
                {
                    method: 'post',
                    onComplete: checkout.onComplete,
                    onSuccess: checkout.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: Form.serialize(this.form)
                }
            );
        } else {
            checkout.resetLoadWaiting();
        }
    }
};
