<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Donation_Emails {

    /**
     * Send all enabled emails for a completed donation.
     *
     * @param int   $donation_id The donation row ID.
     * @param array $donation    The full donation row (object cast to array).
     */
    public static function send_all( $donation_id, $donation ) {
        $placeholders = self::build_placeholders( $donation );

        if ( get_option( 'yasw_email_admin_notification_enabled', 'yes' ) === 'yes' ) {
            self::send_email( 'admin_notification', $placeholders );
        }

        if ( get_option( 'yasw_email_donor_receipt_enabled', 'yes' ) === 'yes' ) {
            self::send_email( 'donor_receipt', $placeholders );
        }
    }

    /**
     * Build the placeholder => value map from a donation record.
     */
    private static function build_placeholders( $donation ) {
        $donation = (array) $donation;

        // Split full_name into first/last
        $name_parts = explode( ' ', trim( $donation['full_name'] ?? '' ), 2 );
        $fname      = $name_parts[0] ?? '';
        $lname      = $name_parts[1] ?? '';

        // Resolve donation type slug to label
        $type_label = $donation['donation_type'] ?? '';
        $types      = YASW_Admin_Settings::get_donation_types();
        foreach ( $types as $type ) {
            if ( $type['slug'] === $type_label ) {
                $type_label = $type['label'];
                break;
            }
        }

        // Payment method display name
        $method_labels = array(
            'credit_card' => 'Credit Card',
            'donors_fund' => 'The Donors Fund',
            'ojc_fund'    => 'OJC Fund',
            'pledger'     => 'Pledger',
        );
        $method_label = $method_labels[ $donation['payment_method'] ?? '' ] ?? $donation['payment_method'] ?? '';

        // Installment per-payment amount
        $total              = floatval( $donation['total'] ?? 0 );
        $installment_months = intval( $donation['installment_months'] ?? 0 );
        $installment_amount = ( $installment_months > 1 && $total > 0 )
            ? '$' . number_format( $total / $installment_months, 2 )
            : '$' . number_format( $total, 2 );

        // Transaction / confirmation ID
        $txn_id = $donation['transaction_id'] ?? $donation['confirmation_number'] ?? 'N/A';

        // Build {all_fields} summary
        $all_fields = self::build_all_fields_summary( $donation, $type_label, $method_label, $fname, $lname, $installment_amount );

        return array(
            '{donor_fname}'        => $fname,
            '{donor_lname}'        => $lname,
            '{donor_email}'        => $donation['email'] ?? '',
            '{donor_phone}'        => $donation['phone'] ?? '',
            '{donor_address}'      => $donation['street_address'] ?? '',
            '{donor_zip}'          => $donation['zip'] ?? '',
            '{donation_amount}'    => '$' . number_format( $total, 2 ),
            '{donation_type}'      => $type_label,
            '{payment_method}'     => $method_label,
            '{transaction_id}'     => $txn_id,
            '{donation_date}'      => wp_date( 'F j, Y \a\t g:i A', strtotime( $donation['created_at'] ?? 'now' ) ),
            '{donation_message}'   => $donation['message'] ?? '',
            '{admin_email}'        => get_option( 'admin_email' ),
            '{installment_amount}' => $installment_amount,
            '{installment_months}' => $installment_months > 1 ? (string) $installment_months : 'N/A',
            '{all_fields}'         => $all_fields,
        );
    }

    /**
     * Build an HTML summary table of all donation fields.
     */
    private static function build_all_fields_summary( $donation, $type_label, $method_label, $fname, $lname, $installment_amount ) {
        $total              = floatval( $donation['total'] ?? 0 );
        $installment_months = intval( $donation['installment_months'] ?? 0 );
        $txn_id             = $donation['transaction_id'] ?? $donation['confirmation_number'] ?? 'N/A';

        $rows = array(
            'Donor Name'      => trim( $fname . ' ' . $lname ),
            'Email'           => $donation['email'] ?? '',
            'Phone'           => $donation['phone'] ?? '',
            'Address'         => $donation['street_address'] ?? '',
            'ZIP'             => $donation['zip'] ?? '',
            'Donation Type'   => $type_label,
            'Payment Method'  => $method_label,
            'Amount'          => '$' . number_format( floatval( $donation['amount'] ?? 0 ), 2 ),
            'Processing Fees' => ! empty( $donation['cover_fees'] ) ? 'Covered by donor' : 'Not covered',
            'Total Charged'   => '$' . number_format( $total, 2 ),
        );

        if ( ( $donation['payment_schedule'] ?? '' ) === 'installments' && $installment_months > 1 ) {
            $rows['Installments'] = $installment_months . ' months at ' . $installment_amount . '/month';
        } elseif ( ( $donation['payment_schedule'] ?? '' ) === 'repeated' ) {
            $freq = $donation['repeat_frequency'] ?? 'monthly';
            $rows['Recurring'] = ucfirst( $freq ) . ' at $' . number_format( $total, 2 );
        }

        $rows['Transaction ID'] = $txn_id;
        $rows['Date']           = wp_date( 'F j, Y \a\t g:i A', strtotime( $donation['created_at'] ?? 'now' ) );

        if ( ! empty( $donation['message'] ) ) {
            $rows['Message'] = $donation['message'];
        }

        $html = '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;max-width:600px;">';
        foreach ( $rows as $label => $value ) {
            if ( '' === $value ) {
                continue;
            }
            $html .= '<tr>';
            $html .= '<td style="border-bottom:1px solid #eee;font-weight:600;color:#15283D;vertical-align:top;padding:8px 12px 8px 0;white-space:nowrap;">' . esc_html( $label ) . '</td>';
            $html .= '<td style="border-bottom:1px solid #eee;color:#4D6A7D;padding:8px 0;">' . esc_html( $value ) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Send a single email type (admin_notification or donor_receipt).
     */
    private static function send_email( $type, $placeholders ) {
        $prefix = "yasw_email_{$type}";

        // Defaults
        $defaults = array(
            'admin_notification' => array(
                'send_to'    => '{admin_email}',
                'from_name'  => 'YASW Donations',
                'from_email' => '{admin_email}',
                'reply_to'   => '{donor_email}',
                'cc'         => '',
                'bcc'        => '',
                'subject'    => 'New Donation from {donor_fname} {donor_lname}',
                'message'    => '<p>A new donation has been received.</p>{all_fields}',
            ),
            'donor_receipt' => array(
                'send_to'    => '{donor_email}',
                'from_name'  => 'YASW Donations',
                'from_email' => '{admin_email}',
                'reply_to'   => '{admin_email}',
                'cc'         => '',
                'bcc'        => '',
                'subject'    => 'Thank you for your donation, {donor_fname}!',
                'message'    => '<p>Dear {donor_fname},</p><p>Thank you for your generous donation of {donation_amount}.</p>{all_fields}',
            ),
        );
        $d = $defaults[ $type ] ?? $defaults['admin_notification'];

        $send_to    = get_option( "{$prefix}_send_to", $d['send_to'] );
        $from_name  = get_option( "{$prefix}_from_name", $d['from_name'] );
        $from_email = get_option( "{$prefix}_from_email", $d['from_email'] );
        $reply_to   = get_option( "{$prefix}_reply_to", $d['reply_to'] );
        $cc         = get_option( "{$prefix}_cc", $d['cc'] );
        $bcc        = get_option( "{$prefix}_bcc", $d['bcc'] );
        $subject    = get_option( "{$prefix}_subject", $d['subject'] );
        $message    = get_option( "{$prefix}_message", $d['message'] );

        // Replace placeholders — subject gets text values, body gets HTML-safe values
        $subject  = self::replace_placeholders( $subject, $placeholders );
        $message  = self::replace_placeholders( $message, $placeholders );
        $send_to  = self::replace_placeholders( $send_to, $placeholders );
        $from_name  = self::replace_placeholders( $from_name, $placeholders );
        $from_email = self::replace_placeholders( $from_email, $placeholders );
        $reply_to   = self::replace_placeholders( $reply_to, $placeholders );
        $cc         = self::replace_placeholders( $cc, $placeholders );
        $bcc        = self::replace_placeholders( $bcc, $placeholders );

        // Build headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        if ( $from_name && $from_email ) {
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }
        if ( $reply_to ) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        if ( $cc ) {
            foreach ( array_map( 'trim', explode( ',', $cc ) ) as $addr ) {
                if ( $addr ) {
                    $headers[] = 'Cc: ' . $addr;
                }
            }
        }
        if ( $bcc ) {
            foreach ( array_map( 'trim', explode( ',', $bcc ) ) as $addr ) {
                if ( $addr ) {
                    $headers[] = 'Bcc: ' . $addr;
                }
            }
        }

        // Wrap message in basic HTML template
        $html = self::wrap_html( $message );

        // Parse multiple recipients
        $to = array_filter( array_map( 'trim', explode( ',', $send_to ) ) );

        if ( empty( $to ) ) {
            error_log( 'YASW Email: No recipients for ' . $type );
            return false;
        }

        // Sandbox override — redirect all emails to the sandbox address
        $sandbox_mode  = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';
        $sandbox_email = get_option( 'yasw_sandbox_email', '' );
        if ( $sandbox_mode && $sandbox_email ) {
            $to      = array( $sandbox_email );
            $subject = '[SANDBOX] ' . $subject;
            // Strip CC/BCC so only the sandbox address receives the email
            $headers = array_filter( $headers, function( $h ) {
                return stripos( $h, 'Cc:' ) !== 0 && stripos( $h, 'Bcc:' ) !== 0;
            } );
        }

        $result = wp_mail( $to, $subject, $html, $headers );

        if ( ! $result ) {
            error_log( 'YASW Email: Failed to send ' . $type . ' to ' . implode( ', ', $to ) );
        }

        return $result;
    }

    /**
     * Replace placeholders in a string.
     */
    private static function replace_placeholders( $string, $placeholders ) {
        return str_replace(
            array_keys( $placeholders ),
            array_values( $placeholders ),
            $string
        );
    }

    /**
     * Wrap email body in a simple, clean HTML template.
     */
    private static function wrap_html( $body ) {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:4px;overflow:hidden;max-width:600px;width:100%;">
<tr><td style="background:#15283D;padding:24px 32px;text-align:center;">
<span style="color:#FEFCF5;font-size:20px;font-weight:600;">YASW Donations</span>
</td></tr>
<tr><td style="padding:32px;color:#15283D;font-size:15px;line-height:1.6;">
' . $body . '
</td></tr>
<tr><td style="padding:20px 32px;background:#f9f9f9;text-align:center;color:#999;font-size:12px;">
Yeshiva Ateres Shmuel of Waterbury
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }
}
