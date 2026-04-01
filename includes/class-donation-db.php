<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YASW_Donation_DB {

    /**
     * Create custom tables on plugin activation or first use.
     */
    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $donations_table = $wpdb->prefix . 'yasw_donations';
        $errors_table    = $wpdb->prefix . 'yasw_donation_errors';

        $sql_donations = "CREATE TABLE {$donations_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donation_type VARCHAR(100) NOT NULL DEFAULT '',
            payment_method VARCHAR(50) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            cover_fees TINYINT(1) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_schedule VARCHAR(20) NOT NULL DEFAULT '',
            installment_months INT NOT NULL DEFAULT 0,
            repeat_frequency VARCHAR(20) NOT NULL DEFAULT '',
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            street_address VARCHAR(255) NOT NULL DEFAULT '',
            zip VARCHAR(20) NOT NULL DEFAULT '',
            message TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            transaction_id VARCHAR(255) DEFAULT NULL,
            confirmation_number VARCHAR(255) DEFAULT NULL,
            masked_card VARCHAR(50) DEFAULT NULL,
            gateway_response TEXT,
            sandbox TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_email (email),
            KEY idx_created_at (created_at),
            KEY idx_payment_method (payment_method)
        ) {$charset};";

        $sql_errors = "CREATE TABLE {$errors_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            donation_id BIGINT UNSIGNED NOT NULL,
            error_code VARCHAR(50) DEFAULT NULL,
            error_message TEXT NOT NULL,
            gateway_response TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_donation_id (donation_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_donations );
        dbDelta( $sql_errors );

        update_option( 'yasw_db_version', '1.0.0' );
    }

    /**
     * Ensure tables exist (called on admin_init).
     */
    public static function maybe_create_tables() {
        if ( get_option( 'yasw_db_version' ) !== '1.0.0' ) {
            self::create_tables();
        }
    }

    /**
     * Insert a donation record. Returns the donation ID.
     */
    public static function insert_donation( $data ) {
        global $wpdb;

        $amount     = floatval( $data['amount'] ?? 0 );
        $cover_fees = ! empty( $data['cover_fees'] );
        $total      = $cover_fees ? round( $amount * 1.03, 2 ) : $amount;
        $sandbox    = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes' ? 1 : 0;

        $wpdb->insert(
            $wpdb->prefix . 'yasw_donations',
            array(
                'donation_type'      => sanitize_text_field( $data['donation_type'] ?? '' ),
                'payment_method'     => sanitize_text_field( $data['payment_method'] ?? '' ),
                'amount'             => $amount,
                'cover_fees'         => $cover_fees ? 1 : 0,
                'total'              => $total,
                'payment_schedule'   => sanitize_text_field( $data['payment_schedule'] ?? '' ),
                'installment_months' => intval( $data['installment_months'] ?? 0 ),
                'repeat_frequency'   => sanitize_text_field( $data['repeat_frequency'] ?? '' ),
                'full_name'          => sanitize_text_field( $data['full_name'] ?? '' ),
                'email'              => sanitize_email( $data['email'] ?? '' ),
                'phone'              => sanitize_text_field( $data['phone'] ?? '' ),
                'street_address'     => sanitize_text_field( $data['street_address'] ?? '' ),
                'zip'                => sanitize_text_field( $data['zip'] ?? '' ),
                'message'            => sanitize_textarea_field( $data['message'] ?? '' ),
                'status'             => 'pending',
                'sandbox'            => $sandbox,
            ),
            array( '%s', '%s', '%f', '%d', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Update a donation after processing.
     */
    public static function update_donation( $donation_id, $data ) {
        global $wpdb;

        $update = array();
        $format = array();

        if ( isset( $data['status'] ) ) {
            $update['status'] = sanitize_text_field( $data['status'] );
            $format[]         = '%s';
        }
        if ( isset( $data['transaction_id'] ) ) {
            $update['transaction_id'] = sanitize_text_field( $data['transaction_id'] );
            $format[]                 = '%s';
        }
        if ( isset( $data['confirmation_number'] ) ) {
            $update['confirmation_number'] = sanitize_text_field( $data['confirmation_number'] );
            $format[]                      = '%s';
        }
        if ( isset( $data['masked_card'] ) ) {
            $update['masked_card'] = sanitize_text_field( $data['masked_card'] );
            $format[]              = '%s';
        }
        if ( isset( $data['gateway_response'] ) ) {
            $update['gateway_response'] = is_string( $data['gateway_response'] )
                ? $data['gateway_response']
                : wp_json_encode( $data['gateway_response'] );
            $format[] = '%s';
        }

        if ( ! empty( $update ) ) {
            $wpdb->update(
                $wpdb->prefix . 'yasw_donations',
                $update,
                array( 'id' => $donation_id ),
                $format,
                array( '%d' )
            );
        }
    }

    /**
     * Log an error for a donation.
     */
    public static function log_error( $donation_id, $error_message, $error_code = null, $gateway_response = null ) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'yasw_donation_errors',
            array(
                'donation_id'      => $donation_id,
                'error_code'       => $error_code ? sanitize_text_field( $error_code ) : null,
                'error_message'    => sanitize_text_field( $error_message ),
                'gateway_response' => $gateway_response ? ( is_string( $gateway_response ) ? $gateway_response : wp_json_encode( $gateway_response ) ) : null,
            ),
            array( '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Get donations with pagination.
     */
    public static function get_donations( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $table = $wpdb->prefix . 'yasw_donations';
        $where = '1=1';
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $allowed_orderby = array( 'id', 'created_at', 'amount', 'total', 'status', 'payment_method' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                array_merge( $params, array( $args['per_page'], $offset ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Count donations.
     */
    public static function count_donations( $status = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'yasw_donations';

        if ( ! empty( $status ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s",
                $status
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Get errors for a donation.
     */
    public static function get_errors( $donation_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yasw_donation_errors WHERE donation_id = %d ORDER BY created_at DESC",
            $donation_id
        ) );
    }
}
