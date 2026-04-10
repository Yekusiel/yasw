(function($) {
    'use strict';

    var PROCESSING_FEE_RATE = 0.03; // 3%

    function getAmount() {
        var val = $('#yasw-amount').val().replace(/[^0-9.]/g, '');
        return parseFloat(val) || 0;
    }

    function getTotal() {
        var amount = getAmount();
        var coverFees = $('#yasw-cover-fees').is(':checked');
        return coverFees ? amount * (1 + PROCESSING_FEE_RATE) : amount;
    }

    function updateTotal() {
        var amount = getAmount();
        var total = getTotal();

        if (amount > 0) {
            $('#yasw-total-amount').text('$' + total.toFixed(2));
        } else {
            $('#yasw-total-amount').text('');
        }

        updateMonthlyPayment(total);
    }

    function updateMonthlyPayment(total) {
        var months = parseInt($('#yasw-months').val()) || 0;
        if (months > 0 && total > 0) {
            var monthly = total / months;
            $('#yasw-monthly-amount').text('$' + monthly.toFixed(2));
        } else {
            $('#yasw-monthly-amount').text('');
        }
    }

    // =========================================================================
    // Sola iFields initialization
    // =========================================================================
    function initIFields() {
        if (typeof setAccount !== 'function') {
            console.warn('YASW: iFields JS not loaded');
            return;
        }

        if (!yaswDonations.iFieldsKey) {
            console.warn('YASW: No iFields key configured');
            return;
        }

        // Initialize iFields account
        setAccount(
            yaswDonations.iFieldsKey,
            yaswDonations.softwareName,
            yaswDonations.softwareVersion
        );

        // Style the content INSIDE the iframes to match .yasw-donate-input:
        // font: 20px Inter, color: #15283D, placeholder opacity: 0.3,
        // no border (the outer iframe element has the bottom border),
        // transparent background, 32px line-height
        var iframeStyle = {
            'width': '100%',
            'height': '32px',
            'font-size': '20px',
            'line-height': '32px',
            'font-family': "'Inter', sans-serif",
            'font-weight': '400',
            'color': '#15283D',
            'border': 'none',
            'outline': 'none',
            'background': 'transparent',
            'padding': '0',
            'margin': '0',
            'box-shadow': 'none',
            '-webkit-appearance': 'none'
        };

        if (typeof setIfieldStyle === 'function') {
            setIfieldStyle('card-number', iframeStyle);
            setIfieldStyle('cvv', iframeStyle);
        }

        // Auto-format card number with spaces
        if (typeof enableAutoFormatting === 'function') {
            enableAutoFormatting(' ');
        }

        // Visual feedback: change the outer iframe border color on validity
        if (typeof addIfieldKeyPressCallback === 'function') {
            addIfieldKeyPressCallback(function(data) {
                var $cardFrame = $('iframe[data-ifields-id="card-number"]');
                var $cvvFrame = $('iframe[data-ifields-id="cvv"]');

                // Card number border
                if (data.cardNumberFormattedLength <= 0) {
                    $cardFrame.css('border-bottom-color', '#15283D');
                } else if (data.cardNumberIsValid) {
                    $cardFrame.css('border-bottom-color', '#4D6A7D');
                } else {
                    $cardFrame.css('border-bottom-color', '#C62828');
                }

                // CVV border
                if (data.lastIfieldChanged === 'cvv') {
                    if (data.cvvLength <= 0) {
                        $cvvFrame.css('border-bottom-color', '#15283D');
                    } else if (data.cvvIsValid) {
                        $cvvFrame.css('border-bottom-color', '#4D6A7D');
                    } else {
                        $cvvFrame.css('border-bottom-color', '#C62828');
                    }
                }
            });
        }
    }

    // =========================================================================
    // Form event handlers
    // =========================================================================
    $(function() {

        // Initialize iFields
        initIFields();

        // Amount input — update total on change
        $('#yasw-amount').on('input', function() { updateTotal(); checkOjcWeekly(); });
        $('#yasw-cover-fees').on('change', function() { updateTotal(); checkOjcWeekly(); });

        // Payment schedule radios
        $('input[name="payment_schedule"]').on('change', function() {
            var val = $(this).val();
            $('#yasw-installments-options, #yasw-repeated-options').hide();
            if (val === 'installments') {
                $('#yasw-installments-options').show();
            } else if (val === 'repeated') {
                $('#yasw-repeated-options').show();
            }
            checkOjcWeekly();
        });

        // Installment months
        $('#yasw-months').on('input', updateTotal);

        // Frequency toggle buttons
        $('.yasw-donate-freq-btn').on('click', function() {
            $('.yasw-donate-freq-btn').removeClass('active');
            $(this).addClass('active');
            $('#yasw-repeat-frequency').val($(this).data('frequency'));
            checkOjcWeekly();
        });

        // Payment method selection
        $('.yasw-donate-method-btn').on('click', function() {
            var method = $(this).data('method');

            // Toggle active button
            $('.yasw-donate-method-btn').removeClass('active');
            $(this).addClass('active');
            $('#yasw-payment-method').val(method);

            // Show/hide payment fields
            $('.yasw-donate-payment-fields').hide();
            $('#yasw-fields-' + method).show();
            checkOjcWeekly();
        });

        // OJC weekly notice — switch to monthly
        $('#yasw-ojc-switch-monthly').on('click', function() {
            $('.yasw-donate-freq-btn').removeClass('active');
            $('.yasw-donate-freq-btn[data-frequency="monthly"]').addClass('active');
            $('#yasw-repeat-frequency').val('monthly');

            // Quadruple the amount (4 weeks ≈ 1 month)
            var amount = getAmount();
            if (amount > 0) {
                $('#yasw-amount').val((amount * 4).toFixed(2));
                updateTotal();
            }

            checkOjcWeekly();
        });

        // Form submission
        $('#yasw-donation-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('.yasw-donate-submit-btn');
            var $msg = $('#yasw-form-message');
            var method = $('#yasw-payment-method').val();

            // Basic validation
            var amount = getAmount();
            if (amount <= 0) {
                showMessage($msg, 'Please enter a valid donation amount.', 'error');
                return;
            }

            var donationType = $('#yasw-donation-type').val();
            if (!donationType) {
                showMessage($msg, 'Please select a donation type.', 'error');
                return;
            }

            var name = $form.find('input[name="full_name"]').val().trim();
            if (!name) {
                showMessage($msg, 'Please enter your full name.', 'error');
                return;
            }

            var email = $form.find('input[name="email"]').val().trim();
            if (!email || !isValidEmail(email)) {
                showMessage($msg, 'Please enter a valid email address.', 'error');
                return;
            }

            // Disable button
            $btn.prop('disabled', true).text('Processing...');
            $msg.hide();

            if (method === 'credit_card') {
                // Use iFields to get SUTs, then submit
                submitWithIFields($form, $btn, $msg);
            } else {
                // Other payment methods — submit directly
                submitFormData($form, $btn, $msg);
            }
        });
    });

    /**
     * Get SUTs from iFields, then submit to server.
     */
    function submitWithIFields($form, $btn, $msg) {
        if (typeof getTokens !== 'function') {
            showMessage($msg, 'Payment system not loaded. Please refresh and try again.', 'error');
            $btn.prop('disabled', false).text('Process Payment');
            return;
        }

        getTokens(
            function() {
                // Success — SUTs are now in the hidden fields
                // Verify we got the card token
                var cardToken = $('input[data-ifields-id="card-number-token"]').val();
                if (!cardToken) {
                    showMessage($msg, 'Could not tokenize card data. Please check your card number.', 'error');
                    $btn.prop('disabled', false).text('Process Payment');
                    return;
                }

                submitFormData($form, $btn, $msg);
            },
            function() {
                // Error getting tokens
                showMessage($msg, 'Could not process card information. Please check your details and try again.', 'error');
                $btn.prop('disabled', false).text('Process Payment');
            },
            30000 // 30 second timeout
        );
    }

    /**
     * Submit form data to server via AJAX.
     */
    function submitFormData($form, $btn, $msg) {
        $.ajax({
            url: yaswDonations.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=yasw_process_donation&nonce=' + yaswDonations.nonce,
            success: function(response) {
                if (response.success) {
                    showMessage($msg, response.data.message, 'success');
                    resetForm($form);
                } else {
                    showMessage($msg, response.data || 'An error occurred.', 'error');
                }
            },
            error: function() {
                showMessage($msg, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Process Payment');
            }
        });
    }

    /**
     * Reset form to initial state.
     */
    function resetForm($form) {
        $form[0].reset();
        $('.yasw-donate-method-btn').removeClass('active').first().addClass('active');
        $('#yasw-payment-method').val('credit_card');
        $('.yasw-donate-payment-fields').hide();
        $('#yasw-fields-credit_card').show();
        $('#yasw-installments-options, #yasw-repeated-options').hide();
        $('#yasw-total-amount, #yasw-monthly-amount').text('');

        // Clear iFields
        if (typeof clearIfield === 'function') {
            clearIfield('card-number');
            clearIfield('cvv');
        }

        // Clear hidden SUT tokens
        $('input[data-ifields-id="card-number-token"]').val('');
        $('input[data-ifields-id="cvv-token"]').val('');
    }

    function showMessage($el, text, type) {
        $el.text(text)
           .removeClass('yasw-donate-message--success yasw-donate-message--error')
           .addClass('yasw-donate-message--' + type)
           .show();

        // Scroll to message
        $('html, body').animate({
            scrollTop: $el.offset().top - 100
        }, 300);
    }

    function checkOjcWeekly() {
        var method = $('#yasw-payment-method').val();
        var schedule = $('input[name="payment_schedule"]:checked').val();
        var frequency = $('#yasw-repeat-frequency').val();
        var $notice = $('#yasw-ojc-weekly-notice');

        if (method === 'ojc_fund' && schedule === 'repeated' && frequency === 'weekly') {
            var total = getTotal();
            var monthlyAmount = total * 4;
            $('#yasw-ojc-monthly-equivalent').text(monthlyAmount > 0 ? '$' + monthlyAmount.toFixed(2) + '/month' : '');
            $notice.show();
        } else {
            $notice.hide();
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

})(jQuery);
