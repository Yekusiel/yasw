<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Sola_Processor {

    private $gateway_endpoint = 'https://x1.cardknox.com/gatewayjson';
    private $schedule_endpoint = 'https://api.cardknox.com/v2/CreateSchedule';

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

        foreach ( $sola_keys as $keys ) {
            if ( ! empty( $keys['api_key'] ) ) {
                return $keys['api_key'];
            }
        }

        return '';
    }

    /**
     * Parse common fields from the form data.
     */
    private function parse_common( $data ) {
        $full_name  = sanitize_text_field( $data['full_name'] ?? '' );
        $name_parts = explode( ' ', $full_name, 2 );

        return array(
            'amount'      => floatval( $data['amount'] ?? 0 ),
            'cover_fees'  => isset( $data['cover_fees'] ) && $data['cover_fees'] === 'on',
            'card_token'  => sanitize_text_field( $data['xCardNum'] ?? '' ),
            'cvv_token'   => sanitize_text_field( $data['xCVV'] ?? '' ),
            'exp_month'   => sanitize_text_field( $data['cc_month'] ?? '' ),
            'exp_year'    => sanitize_text_field( $data['cc_year'] ?? '' ),
            'cc_name'     => sanitize_text_field( $data['cc_name'] ?? $full_name ),
            'full_name'   => $full_name,
            'first_name'  => $name_parts[0] ?? '',
            'last_name'   => $name_parts[1] ?? '',
            'email'       => sanitize_email( $data['email'] ?? '' ),
            'street'      => sanitize_text_field( $data['street_address'] ?? '' ),
            'zip'         => sanitize_text_field( $data['zip'] ?? '' ),
            'phone'       => sanitize_text_field( $data['phone'] ?? '' ),
            'schedule'    => sanitize_text_field( $data['payment_schedule'] ?? 'one_time' ),
            'months'      => intval( $data['installment_months'] ?? 0 ),
            'frequency'   => sanitize_text_field( $data['repeat_frequency'] ?? 'monthly' ),
        );
    }

    /**
     * Build the expiry string (MMYY).
     */
    private function build_exp( $month, $year ) {
        return str_pad( $month, 2, '0', STR_PAD_LEFT ) . substr( str_pad( $year, 2, '0', STR_PAD_LEFT ), -2 );
    }


    /**
     * Process a credit card donation via Sola gateway.
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

        $fields = $this->parse_common( $data );

        if ( $fields['amount'] <= 0 ) {
            return array(
                'success' => false,
                'message' => 'Invalid donation amount.',
            );
        }

        if ( empty( $fields['card_token'] ) ) {
            return array(
                'success' => false,
                'message' => 'Card information is missing. Please enter your card details.',
            );
        }

        // Calculate total with fees
        $total = $fields['cover_fees'] ? round( $fields['amount'] * 1.03, 2 ) : $fields['amount'];
        $exp   = $this->build_exp( $fields['exp_month'], $fields['exp_year'] );

        // Determine flow based on payment schedule
        if ( 'installments' === $fields['schedule'] || 'repeated' === $fields['schedule'] ) {
            return $this->process_recurring( $api_key, $fields, $total, $exp, $donation_type );
        }

        return $this->process_one_time( $api_key, $fields, $total, $exp, $donation_type );
    }

    /**
     * One-time payment: cc:sale
     */
    private function process_one_time( $api_key, $fields, $total, $exp, $donation_type ) {
        $transaction = array(
            'xCommand'         => 'cc:sale',
            'xKey'             => $api_key,
            'xVersion'         => '5.0.0',
            'xSoftwareName'    => 'YASW Donations',
            'xSoftwareVersion' => YASW_DONATIONS_VERSION,
            'xAmount'          => number_format( $total, 2, '.', '' ),
            'xCardNum'         => $fields['card_token'],
            'xCVV'             => $fields['cvv_token'],
            'xExp'             => $exp,
            'xName'            => $fields['cc_name'],
            'xBillFirstName'   => $fields['first_name'],
            'xBillLastName'    => $fields['last_name'],
            'xBillStreet'      => $fields['street'],
            'xBillZip'         => $fields['zip'],
            'xBillPhone'       => $fields['phone'],
            'xEmail'           => $fields['email'],
            'xIP'              => YASW_Donation_DB::get_client_ip(),
            'xInvoice'         => 'YASW-' . time(),
            'xDescription'     => 'Donation: ' . $donation_type,
            'xAllowDuplicate'  => 'TRUE',
        );

        $result = $this->gateway_request( $transaction );

        if ( is_wp_error( $result ) ) {
            return array( 'success' => false, 'message' => 'Unable to connect to the payment gateway. Please try again.' );
        }

        return $this->parse_gateway_response( $result, $total );
    }

    /**
     * Recurring/installments: cc:save → get xToken → CreateSchedule
     */
    private function process_recurring( $api_key, $fields, $total, $exp, $donation_type ) {
        // Step 1: Save card to get a reusable token
        $save_request = array(
            'xCommand'         => 'cc:save',
            'xKey'             => $api_key,
            'xVersion'         => '5.0.0',
            'xSoftwareName'    => 'YASW Donations',
            'xSoftwareVersion' => YASW_DONATIONS_VERSION,
            'xCardNum'         => $fields['card_token'],
            'xExp'             => $exp,
            'xName'            => $fields['cc_name'],
            'xBillFirstName'   => $fields['first_name'],
            'xBillLastName'    => $fields['last_name'],
            'xBillStreet'      => $fields['street'],
            'xBillZip'         => $fields['zip'],
            'xEmail'           => $fields['email'],
            'xIP'              => YASW_Donation_DB::get_client_ip(),
        );

        $save_result = $this->gateway_request( $save_request );

        if ( is_wp_error( $save_result ) ) {
            return array( 'success' => false, 'message' => 'Unable to connect to the payment gateway. Please try again.' );
        }

        $save_code = $save_result['xResult'] ?? '';

        if ( 'A' !== $save_code ) {
            $error = $save_result['xError'] ?? 'Could not save card for recurring payments.';
            return array( 'success' => false, 'message' => $error );
        }

        $token = $save_result['xToken'] ?? '';
        if ( empty( $token ) ) {
            return array( 'success' => false, 'message' => 'Failed to obtain payment token for recurring schedule.' );
        }

        // Step 2: Create schedule
        $is_installments = 'installments' === $fields['schedule'];

        if ( $is_installments && $fields['months'] > 1 ) {
            $per_payment    = round( $total / $fields['months'], 2 );
            $total_payments = $fields['months'];
            $interval_type  = 'month';
        } else {
            // Repeated/ongoing
            $per_payment    = $total;
            $total_payments = 0; // Ongoing
            $interval_type  = 'monthly' === $fields['frequency'] ? 'month' : 'week';
        }

        $schedule = array(
            'SoftwareName'    => 'YASW Donations',
            'SoftwareVersion' => YASW_DONATIONS_VERSION,
            'NewCustomer'     => array(
                'BillFirstName' => $fields['first_name'],
                'BillLastName'  => $fields['last_name'],
                'Email'         => $fields['email'],
                'BillPhone'     => $fields['phone'],
            ),
            'NewPaymentMethod' => array(
                'Token'     => $token,
                'TokenType' => 'cc',
                'Exp'       => $exp,
            ),
            'IntervalType'  => $interval_type,
            'Cvv'           => $fields['cvv_token'],
            'Amount'        => number_format( $per_payment, 2, '.', '' ),
            'TotalPayments' => $total_payments,
        );

        $schedule_response = wp_remote_post( $this->schedule_endpoint, array(
            'timeout' => 45,
            'headers' => array(
                'Authorization'          => $api_key,
                'X-Recurring-Api-Version' => '2.1',
                'Content-Type'           => 'application/json',
            ),
            'body' => wp_json_encode( $schedule ),
        ) );

        if ( is_wp_error( $schedule_response ) ) {
            return array( 'success' => false, 'message' => 'Unable to create payment schedule. Please try again.' );
        }

        $schedule_body = json_decode( wp_remote_retrieve_body( $schedule_response ), true );

        if ( empty( $schedule_body ) ) {
            return array( 'success' => false, 'message' => 'Invalid response from payment scheduler.' );
        }

        $schedule_result = $schedule_body['Result'] ?? '';

        if ( 'S' === $schedule_result ) {
            $schedule_label = $is_installments
                ? $fields['months'] . ' monthly payments of $' . number_format( $per_payment, 2 )
                : ucfirst( $interval_type ) . 'ly payments of $' . number_format( $per_payment, 2 );

            return array(
                'success'       => true,
                'message'       => 'Thank you! Your donation has been set up: ' . $schedule_label . '.',
                'transactionId' => $schedule_body['ScheduleId'] ?? '',
                'token'         => $token,
                'maskedCard'    => $save_result['xMaskedCardNumber'] ?? '',
            );
        }

        $error = $schedule_body['Error'] ?? 'Failed to create payment schedule.';
        error_log( 'YASW Sola Schedule Error: ' . $error );
        return array(
            'success'         => false,
            'message'         => $error,
            'gatewayResponse' => $schedule_body,
        );
    }

    /**
     * Send a request to the Sola gateway.
     */
    private function gateway_request( $payload ) {
        $response = wp_remote_post( $this->gateway_endpoint, array(
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) ) {
            return new \WP_Error( 'empty_response', 'Empty response from gateway' );
        }

        return $body;
    }

    /**
     * Parse a standard gateway response (for cc:sale).
     */
    private function parse_gateway_response( $body, $amount ) {
        $result_code = $body['xResult'] ?? '';

        if ( 'A' === $result_code ) {
            return array(
                'success'    => true,
                'message'    => 'Thank you for your generous donation of $' . number_format( $amount, 2 ) . '!',
                'refNum'     => $body['xRefNum'] ?? '',
                'token'      => $body['xToken'] ?? '',
                'maskedCard' => $body['xMaskedCardNumber'] ?? '',
            );
        }

        if ( 'V' === $result_code ) {
            return array(
                'success'         => false,
                'requires3ds'     => true,
                'message'         => '3D Secure verification required.',
                'gatewayResponse' => $body,
            );
        }

        $error_msg = $body['xError'] ?? 'Transaction was not approved.';
        return array(
            'success'         => false,
            'message'         => $error_msg,
            'gatewayResponse' => $body,
        );
    }
}
