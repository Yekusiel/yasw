<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Pledger_Processor {

    private $api_url = 'https://api.pledgercharitable.org/api/Funds/Capture';

    /**
     * Process a Pledger grant.
     */
    public function process( $data ) {
        $bearer_token = get_option( 'yasw_pledger_bearer_token', '' );
        $sandbox      = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';
        $tax_id_raw   = $sandbox ? get_option( 'yasw_pledger_sandbox_tax_id', '' ) : get_option( 'yasw_pledger_tax_id', '' );
        $tax_id       = preg_replace( '/\D/', '', $tax_id_raw );
        $charity_name = get_option( 'yasw_pledger_charity_name', '' );

        if ( empty( $bearer_token ) || empty( $tax_id ) ) {
            return array(
                'success' => false,
                'message' => 'Pledger is not configured. Please contact the administrator.',
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

        // Card details
        $card_number = str_replace( ' ', '', sanitize_text_field( $data['pl_card_number'] ?? '' ) );
        $exp_raw     = str_replace( array( '/', ' ' ), '', sanitize_text_field( $data['pl_expiry'] ?? '' ) );

        if ( empty( $card_number ) ) {
            return array(
                'success' => false,
                'message' => 'Please enter your Pledger card number.',
            );
        }

        // Build grant request
        $donation = array(
            'TaxID'       => $tax_id,
            'CharityName' => $charity_name,
            'Command'     => 'grant:donate',
            'Cardnumber'  => $card_number,
            'Amount'      => (float) $amount,
            'ExpDate'     => $exp_raw,
            'Description' => 'YASW Donation',
        );

        // Handle installments / recurring
        $schedule = sanitize_text_field( $data['payment_schedule'] ?? '' );
        if ( 'installments' === $schedule ) {
            $months = intval( $data['installment_months'] ?? 0 );
            if ( $months > 1 ) {
                $donation['Amount']         = round( $amount / $months, 2 );
                $donation['RecurringCount'] = $months;
                $donation['BeginDate']      = gmdate( 'm/d/Y' );
                $donation['RecurringType']  = 'Monthly';
            }
        } elseif ( 'repeated' === $schedule ) {
            $frequency = sanitize_text_field( $data['repeat_frequency'] ?? 'monthly' );
            $donation['RecurringCount'] = 0; // Ongoing
            $donation['BeginDate']      = gmdate( 'm/d/Y' );
            $donation['RecurringType']  = 'monthly' === $frequency ? 'Monthly' : 'Weekly';
        }

        // Send request
        $response = wp_remote_post( $this->api_url, array(
            'timeout' => 45,
            'headers' => array(
                'Authorization' => 'Bearer ' . $bearer_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $donation ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to Pledger. Please try again.',
            );
        }

        $http_code     = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $response_body ) ) {
            error_log( 'YASW Pledger: Empty response (HTTP ' . $http_code . ')' );
            return array(
                'success' => false,
                'message' => 'Pledger returned an invalid response.',
            );
        }

        if ( 200 === $http_code ) {
            $status = $response_body['Status'] ?? '';

            if ( 'Approved' === $status ) {
                return array(
                    'success'       => true,
                    'message'       => 'Thank you! Your Pledger donation of $' . number_format( $amount, 2 ) . ' has been approved.',
                    'transactionId' => $response_body['Refnum'] ?? null,
                );
            }

            if ( 'Declined' === $status ) {
                $error = $response_body['ErrorMessage'] ?? 'Transaction declined.';
                error_log( 'YASW Pledger Declined: ' . $error );
                return array(
                    'success' => false,
                    'message' => $error,
                );
            }

            // Error status
            $error = $response_body['ErrorMessage'] ?? 'Transaction error.';
            error_log( 'YASW Pledger Error: ' . $error );
            return array(
                'success' => false,
                'message' => $error,
            );
        }

        // HTTP error codes
        error_log( 'YASW Pledger HTTP Error ' . $http_code . ': ' . wp_json_encode( $response_body ) );
        return array(
            'success' => false,
            'message' => 'Pledger transaction failed (HTTP ' . $http_code . ').',
        );
    }
}
