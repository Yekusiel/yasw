<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Sola_Processor {

    private $api_endpoint = 'https://x1.cardknox.com/gatewayjson';

    /**
     * Get the API key for a transaction based on donation type and sandbox mode.
     */
    private function get_api_key( $donation_type ) {
        $sandbox = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';

        if ( $sandbox ) {
            return get_option( 'yasw_sola_sandbox_api_key', '' );
        }

        $sola_keys = get_option( 'yasw_sola_keys', array() );
        if ( isset( $sola_keys[ $donation_type ]['api_key'] ) && ! empty( $sola_keys[ $donation_type ]['api_key'] ) ) {
            return $sola_keys[ $donation_type ]['api_key'];
        }

        // Fallback: try first available production key
        foreach ( $sola_keys as $keys ) {
            if ( ! empty( $keys['api_key'] ) ) {
                return $keys['api_key'];
            }
        }

        return '';
    }

    /**
     * Process a credit card donation via Sola gateway.
     *
     * Expects SUTs (single-use tokens) from iFields, not raw card data.
     */
    public function process( $data ) {
        $donation_type = sanitize_text_field( $data['donation_type'] ?? '' );
        $api_key       = $this->get_api_key( $donation_type );

        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => 'Payment configuration error. Please contact the administrator.',
            );
        }

        // Amount
        $amount = floatval( $data['amount'] ?? 0 );
        if ( $amount <= 0 ) {
            return array(
                'success' => false,
                'message' => 'Invalid donation amount.',
            );
        }

        // Processing fees
        $cover_fees = isset( $data['cover_fees'] ) && $data['cover_fees'] === 'on';
        if ( $cover_fees ) {
            $amount = round( $amount * 1.03, 2 );
        }

        // SUTs from iFields (card number and CVV tokens)
        $card_token = sanitize_text_field( $data['xCardNum'] ?? '' );
        $cvv_token  = sanitize_text_field( $data['xCVV'] ?? '' );

        if ( empty( $card_token ) ) {
            return array(
                'success' => false,
                'message' => 'Card information is missing. Please enter your card details.',
            );
        }

        // Expiry
        $exp_month = sanitize_text_field( $data['cc_month'] ?? '' );
        $exp_year  = sanitize_text_field( $data['cc_year'] ?? '' );
        $exp       = str_pad( $exp_month, 2, '0', STR_PAD_LEFT ) . substr( str_pad( $exp_year, 2, '0', STR_PAD_LEFT ), -2 );

        // Donor info
        $full_name = sanitize_text_field( $data['full_name'] ?? '' );
        $email     = sanitize_email( $data['email'] ?? '' );
        $street    = sanitize_text_field( $data['street_address'] ?? '' );
        $zip       = sanitize_text_field( $data['zip'] ?? '' );
        $phone     = sanitize_text_field( $data['phone'] ?? '' );

        // Split name into first/last
        $name_parts = explode( ' ', $full_name, 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // Build transaction request
        $transaction = array(
            'xCommand'         => 'cc:sale',
            'xKey'             => $api_key,
            'xVersion'         => '5.0.0',
            'xSoftwareName'    => 'YASW Donations',
            'xSoftwareVersion' => YASW_DONATIONS_VERSION,
            'xAmount'          => number_format( $amount, 2, '.', '' ),
            'xCardNum'         => $card_token,
            'xCVV'             => $cvv_token,
            'xExp'             => $exp,
            'xName'            => sanitize_text_field( $data['cc_name'] ?? $full_name ),
            'xBillFirstName'   => $first_name,
            'xBillLastName'    => $last_name,
            'xBillStreet'      => $street,
            'xBillZip'         => $zip,
            'xBillPhone'       => $phone,
            'xEmail'           => $email,
            'xInvoice'         => 'YASW-' . time(),
            'xDescription'     => 'Donation: ' . $donation_type,
        );

        // Send to Sola gateway
        $response = wp_remote_post( $this->api_endpoint, array(
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $transaction ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to the payment gateway. Please try again.',
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid response from payment gateway.',
            );
        }

        // Sola returns xResult: A (Approved), D (Declined), E (Error), V (3DS Verification needed)
        $result_code = $body['xResult'] ?? '';

        if ( 'A' === $result_code ) {
            return array(
                'success'  => true,
                'message'  => 'Thank you for your generous donation of $' . number_format( $amount, 2 ) . '!',
                'refNum'   => $body['xRefNum'] ?? '',
                'token'    => $body['xToken'] ?? '',
                'maskedCard' => $body['xMaskedCardNumber'] ?? '',
            );
        }

        if ( 'V' === $result_code ) {
            // 3DS verification required — return data for client-side handling
            return array(
                'success'     => false,
                'requires3ds' => true,
                'message'     => '3D Secure verification required.',
                'gatewayResponse' => $body,
            );
        }

        // Declined or error
        $error_msg = $body['xError'] ?? 'Transaction was not approved.';
        return array(
            'success' => false,
            'message' => $error_msg,
        );
    }
}
