<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Donation_Form {

    public function render() {
        $plugin_url = YASW_DONATIONS_URL;
        ob_start();
        ?>
        <div class="yasw-donate">
            <h2 class="yasw-donate-title">Make a Donation</h2>

            <form id="yasw-donation-form" class="yasw-donate-form" novalidate>
                <?php wp_nonce_field( 'yasw_donation_nonce', 'yasw_nonce_field' ); ?>

                <!-- Donation Type & Amount -->
                <div class="yasw-donate-section">
                    <div class="yasw-donate-field-group">
                        <label class="yasw-donate-label" for="yasw-donation-type">Donation Type</label>
                        <div class="yasw-donate-select-wrap">
                            <select id="yasw-donation-type" name="donation_type" class="yasw-donate-select">
                                <option value="" disabled selected>Select </option>
                                <?php
                                $donation_types = YASW_Admin_Settings::get_donation_types();
                                foreach ( $donation_types as $type ) :
                                ?>
                                <option value="<?php echo esc_attr( $type['slug'] ); ?>"><?php echo esc_html( $type['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="yasw-donate-field-group">
                        <label class="yasw-donate-label" for="yasw-amount">Amount</label>
                        <div class="yasw-donate-amount-wrap">
                            <input type="text" id="yasw-amount" name="amount" class="yasw-donate-amount-input" placeholder="$" inputmode="decimal">
                        </div>
                    </div>

                    <div class="yasw-donate-checkbox-row">
                        <label class="yasw-donate-checkbox-label">
                            <input type="checkbox" id="yasw-cover-fees" name="cover_fees" class="yasw-donate-checkbox" checked>
                            <span class="yasw-donate-checkbox-custom"></span>
                            <span>Yes, I'd like to cover the processing fees.</span>
                        </label>
                    </div>

                    <div class="yasw-donate-total">
                        Total: <span id="yasw-total-amount">$0.00</span>
                    </div>
                </div>

                <!-- Divider -->
                <hr class="yasw-donate-divider">

                <!-- Payment Schedule -->
                <div class="yasw-donate-section yasw-donate-schedule">
                    <p class="yasw-donate-schedule-info">
                        There is an option to pay the above amount in installments, or as a repeating payment.
                    </p>

                    <div class="yasw-donate-radio-group">
                        <label class="yasw-donate-radio-label">
                            <input type="radio" name="payment_schedule" value="one_time" class="yasw-donate-radio" checked>
                            <span class="yasw-donate-radio-custom"></span>
                            <span>One time</span>
                        </label>
                        <label class="yasw-donate-radio-label">
                            <input type="radio" name="payment_schedule" value="installments" class="yasw-donate-radio">
                            <span class="yasw-donate-radio-custom"></span>
                            <span>Pay in installments</span>
                        </label>
                        <label class="yasw-donate-radio-label">
                            <input type="radio" name="payment_schedule" value="repeated" class="yasw-donate-radio">
                            <span class="yasw-donate-radio-custom"></span>
                            <span>Repeated Payments</span>
                        </label>
                    </div>

                    <!-- Installments options -->
                    <div id="yasw-installments-options" class="yasw-donate-schedule-options" style="display:none;">
                        <div class="yasw-donate-installments-row">
                            <label class="yasw-donate-label-inline" for="yasw-months">Months:</label>
                            <input type="number" id="yasw-months" name="installment_months" class="yasw-donate-months-input" min="2" max="36" placeholder="2">
                            <div class="yasw-donate-monthly-display">
                                <span class="yasw-donate-monthly-label">Monthly payment:</span>
                                <span id="yasw-monthly-amount" class="yasw-donate-monthly-amount"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Repeated options -->
                    <div id="yasw-repeated-options" class="yasw-donate-schedule-options" style="display:none;">
                        <div class="yasw-donate-repeated-row">
                            <span class="yasw-donate-label-inline">Repeat:</span>
                            <div class="yasw-donate-frequency-buttons">
                                <button type="button" class="yasw-donate-freq-btn active" data-frequency="monthly">Monthly</button>
                                <button type="button" class="yasw-donate-freq-btn" data-frequency="weekly">Weekly</button>
                            </div>
                            <input type="hidden" name="repeat_frequency" id="yasw-repeat-frequency" value="monthly">
                        </div>
                        <div id="yasw-ojc-weekly-notice" class="yasw-donate-ojc-weekly-notice" style="display:none;">
                            <p class="yasw-donate-ojc-weekly-text">
                                OJC Fund does not support weekly payments.
                                Would you like to switch to <strong>monthly</strong> payments of <strong><span id="yasw-ojc-monthly-equivalent"></span></strong> instead?
                            </p>
                            <button type="button" id="yasw-ojc-switch-monthly" class="yasw-donate-ojc-switch-btn">Switch to Monthly</button>
                        </div>
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="yasw-donate-section">
                    <h3 class="yasw-donate-section-title">Personal Details</h3>
                    <div class="yasw-donate-personal-fields">
                        <div class="yasw-donate-field-row">
                            <input type="text" name="full_name" class="yasw-donate-input" placeholder="Full Name" required>
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="text" name="street_address" class="yasw-donate-input" placeholder="Street Address">
                        </div>
                        <div class="yasw-donate-field-row yasw-donate-field-row--split">
                            <input type="tel" name="phone" class="yasw-donate-input yasw-donate-input--phone" placeholder="Phone Number">
                            <input type="text" name="zip" class="yasw-donate-input yasw-donate-input--zip" placeholder="ZIP">
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="email" name="email" class="yasw-donate-input" placeholder="Email Address" required>
                        </div>
                        <div class="yasw-donate-field-row">
                            <textarea name="message" class="yasw-donate-input yasw-donate-textarea" placeholder="Your Message" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="yasw-donate-section">
                    <h3 class="yasw-donate-section-title">Select a Payment Method</h3>
                    <div class="yasw-donate-payment-methods">
                        <button type="button" class="yasw-donate-method-btn active" data-method="credit_card">
                            <svg class="yasw-donate-method-icon" width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="6" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <line x1="3" y1="12" x2="27" y2="12" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="6" y="16" width="6" height="3" rx="0.5" fill="currentColor" opacity="0.5"/>
                            </svg>
                            <span>Credit Card</span>
                        </button>
                        <button type="button" class="yasw-donate-method-btn" data-method="donors_fund">
                            <img src="<?php echo esc_url( $plugin_url . 'assets/images/donors-fund-logo.svg' ); ?>" alt="Donors Fund" class="yasw-donate-method-icon">
                            <span>The Donors Fund</span>
                        </button>
                        <button type="button" class="yasw-donate-method-btn" data-method="ojc_fund">
                            <img src="<?php echo esc_url( $plugin_url . 'assets/images/ojc-icon.svg' ); ?>" alt="OJC" class="yasw-donate-method-icon">
                            <span>OJC Fund</span>
                        </button>
                        <button type="button" class="yasw-donate-method-btn" data-method="pledger">
                            <img src="<?php echo esc_url( $plugin_url . 'assets/images/pledger-logo.png' ); ?>" alt="Pledger" class="yasw-donate-method-icon">
                            <span>Pledger</span>
                        </button>
                    </div>
                    <input type="hidden" name="payment_method" id="yasw-payment-method" value="credit_card">
                </div>

                <!-- Credit Card Fields (Sola iFields) -->
                <div class="yasw-donate-payment-fields" id="yasw-fields-credit_card">
                    <h3 class="yasw-donate-section-title">Credit Card Details</h3>
                    <div class="yasw-donate-cc-fields">
                        <div class="yasw-donate-field-row">
                            <iframe data-ifields-id="card-number" data-ifields-placeholder="Card Number"
                                src="https://cdn.cardknox.com/ifields/<?php echo esc_attr( YASW_IFIELDS_VERSION ); ?>/ifield.htm"
                                class="yasw-donate-iframe"></iframe>
                            <input data-ifields-id="card-number-token" name="xCardNum" type="hidden">
                        </div>
                        <div class="yasw-donate-field-row yasw-donate-field-row--three">
                            <input type="text" name="cc_month" class="yasw-donate-input" placeholder="Month" inputmode="numeric" autocomplete="cc-exp-month">
                            <input type="text" name="cc_year" class="yasw-donate-input" placeholder="Year" inputmode="numeric" autocomplete="cc-exp-year">
                            <div class="yasw-donate-cvv-wrap">
                                <iframe data-ifields-id="cvv" data-ifields-placeholder="CVV"
                                    src="https://cdn.cardknox.com/ifields/<?php echo esc_attr( YASW_IFIELDS_VERSION ); ?>/ifield.htm"
                                    class="yasw-donate-iframe yasw-donate-iframe--cvv"></iframe>
                                <input data-ifields-id="cvv-token" name="xCVV" type="hidden">
                            </div>
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="text" name="cc_name" class="yasw-donate-input" placeholder="Name on Card" autocomplete="cc-name">
                        </div>
                        <label data-ifields-id="card-data-error" class="yasw-donate-ifields-error"></label>
                    </div>
                </div>

                <!-- Donors Fund Fields -->
                <div class="yasw-donate-payment-fields" id="yasw-fields-donors_fund" style="display:none;">
                    <h3 class="yasw-donate-section-title">The Donors Fund Details</h3>
                    <div class="yasw-donate-cc-fields">
                        <div class="yasw-donate-field-row">
                            <input type="text" name="df_card_number" class="yasw-donate-input" placeholder="Card Number" inputmode="numeric">
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="text" name="df_cvv" class="yasw-donate-input yasw-donate-input--short" placeholder="CVV" inputmode="numeric">
                        </div>
                    </div>
                </div>

                <!-- OJC Fund Fields -->
                <div class="yasw-donate-payment-fields" id="yasw-fields-ojc_fund" style="display:none;">
                    <h3 class="yasw-donate-section-title">OJC Fund Details</h3>
                    <div class="yasw-donate-cc-fields">
                        <div class="yasw-donate-field-row">
                            <input type="text" name="ojc_card_number" class="yasw-donate-input" placeholder="Card Number" inputmode="numeric">
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="text" name="ojc_expiry" class="yasw-donate-input yasw-donate-input--short" placeholder="Expiry (MM/YY)" inputmode="numeric">
                        </div>
                    </div>
                </div>

                <!-- Pledger Fields -->
                <div class="yasw-donate-payment-fields" id="yasw-fields-pledger" style="display:none;">
                    <h3 class="yasw-donate-section-title">Pledger Details</h3>
                    <div class="yasw-donate-cc-fields">
                        <div class="yasw-donate-field-row">
                            <input type="text" name="pl_card_number" class="yasw-donate-input" placeholder="Card Number" inputmode="numeric">
                        </div>
                        <div class="yasw-donate-field-row">
                            <input type="text" name="pl_expiry" class="yasw-donate-input yasw-donate-input--short" placeholder="Expiry (MM/YY)" inputmode="numeric">
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="yasw-donate-submit-row">
                    <button type="submit" class="yasw-donate-submit-btn">Process Payment</button>
                </div>

                <!-- Status messages -->
                <div id="yasw-form-message" class="yasw-donate-message" style="display:none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
