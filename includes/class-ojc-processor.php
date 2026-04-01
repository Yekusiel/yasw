<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_OJC_Processor {

    private $api_url = 'https://api.ojcfund.org:3391/api/vouchers/processcharitycardtransaction';

    private $error_messages = array(
        461 => 'Organization was not found in the OJC Fund system.',
        462 => 'Card is not valid or not active.',
        451 => 'The amount entered is more than the max allowed by the donor.',
        452 => 'The donor has reached the daily limit.',
        406 => 'Wrong info — Card Number or expiration date is not correct.',
    );

    /**
     * Process an OJC Fund donation.
     */
    public function process( $data ) {
        $sandbox     = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';
        $environment = $sandbox ? 'sandbox' : 'production';

        $auth_token = get_option( 'yasw_ojc_auth_token', '' );
        $org_id     = get_option( "yasw_ojc_{$environment}_org_id", '' );

        if ( empty( $auth_token ) || empty( $org_id ) ) {
            return array(
                'success' => false,
                'message' => 'OJC Fund is not configured. Please contact the administrator.',
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
        $card_no  = sanitize_text_field( $data['ojc_card_number'] ?? '' );
        $exp_date = str_replace( '/', '', sanitize_text_field( $data['ojc_expiry'] ?? '' ) );

        if ( empty( $card_no ) ) {
            return array(
                'success' => false,
                'message' => 'Please enter your OJC Fund card number.',
            );
        }

        // Handle installments
        $split_months = 0;
        $schedule     = sanitize_text_field( $data['payment_schedule'] ?? '' );
        if ( 'installments' === $schedule ) {
            $months = intval( $data['installment_months'] ?? 0 );
            if ( $months > 1 ) {
                $split_months = $months;
            }
        }

        // Build request
        $grant = array(
            'CardNo'              => $card_no,
            'ExpDate'             => $exp_date,
            'OrgId'               => $org_id,
            'Amount'              => (float) $amount,
            'ExternalreferenceId' => time() . 'yasw',
            'SplitByMonths'       => $split_months,
        );

        // Send request
        $response = wp_remote_post( $this->api_url, array(
            'timeout' => 45,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $grant ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to OJC Fund. Please try again.',
            );
        }

        $http_code     = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( 200 === $http_code ) {
            return array(
                'success'       => true,
                'message'       => 'Thank you! Your OJC Fund donation of $' . number_format( $amount, 2 ) . ' has been approved.',
                'transactionId' => $response_body,
            );
        }

        // Known error codes
        if ( isset( $this->error_messages[ $http_code ] ) ) {
            $error_msg = $this->error_messages[ $http_code ];
            error_log( 'YASW OJC Error (HTTP ' . $http_code . '): ' . $error_msg );
            return array(
                'success' => false,
                'message' => $error_msg,
            );
        }

        // Unknown error
        error_log( 'YASW OJC Error (HTTP ' . $http_code . '): ' . $response_body );
        return array(
            'success' => false,
            'message' => 'OJC Fund transaction failed (HTTP ' . $http_code . ').',
        );
    }
}
