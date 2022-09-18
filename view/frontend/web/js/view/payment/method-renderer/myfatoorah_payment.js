/*browser:true*/
/*global define*/
define(
    [
            'mfSessionFile', // here the session.js file is mapped
            'mfAppleFile', // here the session.js file is mapped
            'jquery',
            'Magento_Checkout/js/view/payment/default',
            'Magento_Checkout/js/model/quote',
            'mage/url'
        ],
    function (
        mfSessionFile,
        mfAppleFile,
        $,
        Component,
        quote,
        url
    ) {
        'use strict';
        var self;

        var urlCode = 'myfatoorah_payment';
        var checkoutConfig = window.checkoutConfig.payment.myfatoorah_payment;

        var mfData = 'pm=myfatoorah';

        var paymentMethods = checkoutConfig.paymentMethods;
        var listOptions = checkoutConfig.listOptions;
        var mfLang = checkoutConfig.lang;

        var mfError = checkoutConfig.mfError;

        var baseGrandTotal = checkoutConfig.baseGrandTotal;

        return Component.extend(
            {
                redirectAfterPlaceOrder: false,
                defaults: {
                    template: 'MyFatoorah_Gateway/payment/form'
                },
                initialize: function () {
                    this._super();
                    self = this;
                },
                initObservable: function () {
                    this._super()
                    .observe(
                        [
                        'gateways',
                        'transactionResult'
                            ]
                    );

                        return this;

                },
                getCode: function () {
                    return urlCode;
                },
                getData: function () {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'gateways': this.gateways(),
                            'transaction_result': this.transactionResult()
                        }
                    };
                },
                validate: function () {
                    return true;
                },
                getTitle: function () {
                    return checkoutConfig.title;
                },
                getDescription: function () {
                    return checkoutConfig.description;
                },
                afterPlaceOrder: function () {
                    window.location.replace(url.build(urlCode + '/checkout/index?' + mfData));
                },
                placeOrderCard: function (paymentMethodId) {
                    if (mfError) {
                        return false;
                    }
                    $('body').loader('show');
                    mfData = 'pm=' + paymentMethodId;
                    self.placeOrder();
                    return;
                },
                placeOrderForm: function () {
                    if (mfError) {
                        return false;
                    }

                    $('body').loader('show');
                    if (listOptions === 'myfatoorah' || paymentMethods.all.length === 0) {
                        mfData = 'pm=myfatoorah';
                        self.placeOrder();
                        return;
                    }

                    if (paymentMethods.cards.length === 1 && paymentMethods.form.length === 0) {
                        mfData = 'pm=' + paymentMethods['cards'][0]['PaymentMethodId'];
                        self.placeOrder();
                        return;
                    }

                    myFatoorah.submit()
                    .then(
                        function (response) {
                            // On success
                            mfData = 'sid=' + response.SessionId;
                            self.placeOrder();
                        }, function (error) {
                                // In case of errors
                                $('body').loader('hide');
                                self.messageContainer.addErrorMessage(
                                    {
                                        message: error
                                        }
                                );
                        }
                    );
                },
                paymentMethods: paymentMethods,
                getCardPaymentMethods: function () {
                    var totals = quote.getTotals()();
                    var baseGrandTotalNew = (totals ? totals : quote)['base_grand_total'];

                    if (baseGrandTotal == baseGrandTotalNew) {
                        return paymentMethods['cards'];
                    }

                    $.ajax(
                        {
                            showLoader: true,
                            url: url.build(urlCode + '/checkout/payment'),
                            async: false,
                            cache: false,
                            data: {
                                ajax: 1,
                                baseGrandTotal: baseGrandTotalNew
                            },
                            type: "POST",
                            dataType: 'json'
                            }
                    ).done(
                        function (data) {

                            if (data.error === null) {
                                paymentMethods['cards'] = $.parseJSON(data.cards);
                                baseGrandTotal = baseGrandTotalNew;
                            } else {
                                self.messageContainer.addErrorMessage(
                                    {
                                        message: data.error
                                        }
                                );
                                $("#mfSubmitPayment").attr("disabled", "disabled");
                            }

                            return paymentMethods['cards'];
                        }
                    );

                },
                isSectionVisible: function (section) {
                    return !jQuery.isEmptyObject(paymentMethods[section]);
                },
                isContainerVisible: function () {
                    if (mfError) {
                        self.messageContainer.addErrorMessage(
                            {
                                message: mfError
                                }
                        );
                        $("#mfSubmitPayment").attr("disabled", "disabled");
                        return false;
                    }

                    if (listOptions === 'myfatoorah' || paymentMethods.all.length === 0) {
                        return false;
                    }

                    if (paymentMethods.cards.length === 1 && paymentMethods.all.length === 1) {
                        return false;
                    }

                    return true;
                },
                getCardTitle: function (mfCard) {
                    return (mfLang === 'ar') ? mfCard.PaymentMethodAr : mfCard.PaymentMethodEn;
                },
                getForm: function () {
                    var mfConfig = {
                        countryCode: checkoutConfig.countryCode,
                        sessionId: checkoutConfig.sessionId,
                        cardViewId: "mf-card-element",
                        // The following style is optional.
                        style: {
                            cardHeight: checkoutConfig.height,
                            direction: (mfLang === 'ar') ? 'rtl' : 'ltr',
                            input: {
                                color: "black",
                                fontSize: "13px",
                                fontFamily: "sans-serif",
                                inputHeight: "32px",
                                inputMargin: "-1px",
                                borderColor: "c7c7c7",
                                borderWidth: "1px",
                                borderRadius: "0px",
                                boxShadow: "",
                                placeHolder: {
                                    holderName: $.mage.translate.add('Name On Card'),
                                    cardNumber: $.mage.translate.add('Number'),
                                    expiryDate: $.mage.translate.add('MM / YY'),
                                    securityCode: $.mage.translate.add('CVV')
                                }
                            },
                            label: {
                                display: false,
                                color: "black",
                                fontSize: "13px",
                                fontFamily: "sans-serif",
                                text: {
                                    holderName: "Card Holder Name",
                                    cardNumber: "Card Number",
                                    expiryDate: "ExpiryDate",
                                    securityCode: "Security Code"
                                }
                            },
                            error: {
                                borderColor: "red",
                                borderRadius: "8px",
                                boxShadow: "0px"
                            }
                        }
                    };
                    myFatoorah.init(mfConfig);
                    window.addEventListener("message", myFatoorah.recievedMessage, false);
                },
                getApple: function () {
                    
                    var mfApConfig = {
                        sessionId: checkoutConfig.sessionId,
                        countryCode: checkoutConfig.countryCode,
                        currencyCode: paymentMethods['ap']['GatewayData']['GatewayCurrency'],
                        amount: paymentMethods['ap']['GatewayData']['GatewayTotalAmount'],
                        cardViewId: "ap-card-element",
                        callback: mfApPayment
                    };

                    myFatoorahAP.init(mfApConfig);
                        //                    window.addEventListener("message", myFatoorahAP.recievedMessage, false);

                    function mfApPayment(response)
                    {
                        if (mfError) {
                            return false;
                        }
                        $('body').loader('show');
                        mfData = 'sid=' + response.sessionId;
                        self.placeOrder();
                    }
                }
            }
        );
    }
);