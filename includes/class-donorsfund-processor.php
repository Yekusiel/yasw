<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_DonorsFund_Processor {

    /**
     * Process a Donors Fund grant.
     */
    public function process( $data ) {
        $sandbox     = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';
        $environment = $sandbox ? 'sandbox' : 'production';

        $api_key          = get_option( "yasw_daf_{$environment}_api_key", '' );
        $validation_token = get_option( "yasw_daf_{$environment}_validation_token", '' );
        $account_number   = get_option( "yasw_daf_{$environment}_account_number", '' );
        $tax_id           = get_option( "yasw_daf_{$environment}_tax_id", '' );

        if ( empty( $api_key ) || empty( $validation_token ) || empty( $account_number ) ) {
            return array(
                'success' => false,
                'message' => 'Donors Fund is not configured. Please contact the administrator.',
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

        // Donor card number and CVV (authorization)
        $donor              = sanitize_text_field( $data['df_card_number'] ?? '' );
        $donor_authorization = sanitize_text_field( $data['df_cvv'] ?? '' );

        if ( empty( $donor ) ) {
            return array(
                'success' => false,
                'message' => 'Please enter your Donors Fund card number.',
            );
        }

        // Build request body
        $body = array(
            'accountNumber'      => $account_number,
            'amount'             => $amount,
            'donor'              => $donor,
            'donorAuthorization' => $donor_authorization,
            'purposeType'        => 'Other',
            'purposeNote'        => 'YASW-Donation',
        );

        if ( ! empty( $tax_id ) ) {
            $body['taxId'] = $tax_id;
        }

        // Handle recurring/installments
        $schedule = sanitize_text_field( $data['payment_schedule'] ?? '' );
        if ( 'repeated' === $schedule ) {
            $frequency = sanitize_text_field( $data['repeat_frequency'] ?? 'monthly' );
            $body['recurring'] = array(
                'scheduleType'     => 'monthly' === $frequency ? 'monthly' : 'weekly',
                'startDate'        => gmdate( 'n/j/Y', strtotime( '+1 day' ) ),
                'numberOfPayments' => 0, // Ongoing
            );
        } elseif ( 'installments' === $schedule ) {
            $months = intval( $data['installment_months'] ?? 0 );
            if ( $months > 1 ) {
                $body['recurring'] = array(
                    'scheduleType'     => 'monthly',
                    'startDate'        => gmdate( 'n/j/Y', strtotime( '+1 day' ) ),
                    'numberOfPayments' => $months,
                );
                $body['amount'] = round( $amount / $months, 2 );
            }
        }

        // API URL
        $api_urls = array(
            'sandbox'    => 'https://api.tdfcharitable.org/thedonorsfund/integration/Create',
            'production' => 'https://api.thedonorsfund.org/thedonorsfund/integration/Create',
        );
        $api_url = $api_urls[ $environment ];

        // Send request
        $response = wp_remote_post( $api_url, array(
            'timeout' => 45,
            'headers' => array(
                'Api-Key'          => $api_key,
                'Validation-Token' => $validation_token,
                'Content-Type'     => 'application/json',
                'Accept'           => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to Donors Fund. Please try again.',
            );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $response_body ) ) {
            return array(
                'success' => false,
                'message' => 'Donors Fund returned an invalid response.',
            );
        }

        // Check for API-level errors
        if ( ! empty( $response_body['error'] ) ) {
            error_log( 'YASW Donors Fund Error: Code ' . ( $response_body['errorCode'] ?? 'unknown' ) . ', Message: ' . $response_body['error'] );
            return array(
                'success' => false,
                'message' => $response_body['error'],
            );
        }

        // Check for success
        if ( isset( $response_body['data']['status'] ) && 'Approved' === $response_body['data']['status'] ) {
            return array(
                'success'            => true,
                'message'            => 'Thank you! Your Donors Fund grant of $' . number_format( $amount, 2 ) . ' has been approved.',
                'confirmationNumber' => $response_body['data']['confirmationNumber'] ?? null,
                'transactionId'      => $response_body['data']['transactionId'] ?? null,
            );
        }

        $status = $response_body['data']['status'] ?? 'Unknown';
        error_log( 'YASW Donors Fund: Grant not approved. Status: ' . $status );
        return array(
            'success' => false,
            'message' => 'Donors Fund grant status: ' . $status,
        );
    }
}
