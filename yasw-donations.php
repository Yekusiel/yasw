<?php
/**
 * Plugin Name: YASW Donations
 * Description: Donation form shortcode for Yeshiva Ateres Shmuel of Waterbury
 * Version: 1.0.0
 * Author: YASW
 * Text Domain: yasw-donations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'YASW_DONATIONS_VERSION', '1.0.0' );
define( 'YASW_DONATIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'YASW_DONATIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'YASW_IFIELDS_VERSION', '3.4.2602.2001' );

require_once YASW_DONATIONS_PATH . 'includes/class-donation-form.php';
require_once YASW_DONATIONS_PATH . 'includes/class-admin-settings.php';
require_once YASW_DONATIONS_PATH . 'includes/class-sola-processor.php';
require_once YASW_DONATIONS_PATH . 'includes/class-donorsfund-processor.php';
require_once YASW_DONATIONS_PATH . 'includes/class-ojc-processor.php';
require_once YASW_DONATIONS_PATH . 'includes/class-pledger-processor.php';
require_once YASW_DONATIONS_PATH . 'includes/class-donation-db.php';
require_once YASW_DONATIONS_PATH . 'includes/class-donation-emails.php';

class YASW_Donations {

    private static $instance = null;
    private $has_shortcode = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'yasw_donations', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_footer', array( $this, 'maybe_enqueue_assets' ) );
        add_action( 'wp_ajax_yasw_process_donation', array( $this, 'process_donation' ) );
        add_action( 'wp_ajax_nopriv_yasw_process_donation', array( $this, 'process_donation' ) );

        if ( is_admin() ) {
            YASW_Admin_Settings::instance();
            add_action( 'admin_init', array( 'YASW_Donation_DB', 'maybe_create_tables' ) );
        }
    }

    /**
     * Get the iFields key to use (sandbox or per-type production).
     */
    private function get_ifields_key() {
        $sandbox = get_option( 'yasw_sandbox_mode', 'yes' ) === 'yes';
        if ( $sandbox ) {
            return get_option( 'yasw_sola_sandbox_ifields', '' );
        }
        // For production, we return the first available key as default.
        // The actual per-type key is resolved server-side during transaction.
        // The iFields key on the frontend just needs to match the account.
        $sola_keys = get_option( 'yasw_sola_keys', array() );
        foreach ( $sola_keys as $keys ) {
            if ( ! empty( $keys['ifields'] ) ) {
                return $keys['ifields'];
            }
        }
        return '';
    }

    public function register_assets() {
        wp_register_style(
            'yasw-donations-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather&display=swap',
            array(),
            null
        );

        wp_register_style(
            'yasw-donations',
            YASW_DONATIONS_URL . 'assets/css/donation-form.css',
            array( 'yasw-donations-fonts' ),
            YASW_DONATIONS_VERSION
        );

        wp_register_script(
            'yasw-ifields',
            'https://cdn.cardknox.com/ifields/' . YASW_IFIELDS_VERSION . '/ifields.min.js',
            array(),
            YASW_IFIELDS_VERSION,
            false // Load in head
        );

        wp_register_script(
            'yasw-donations',
            YASW_DONATIONS_URL . 'assets/js/donation-form.js',
            array( 'jquery', 'yasw-ifields' ),
            YASW_DONATIONS_VERSION,
            true
        );

        wp_localize_script( 'yasw-donations', 'yaswDonations', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'yasw_donation_nonce' ),
            'iFieldsKey'     => $this->get_ifields_key(),
            'iFieldsVersion' => YASW_IFIELDS_VERSION,
            'softwareName'   => 'YASW Donations',
            'softwareVersion'=> YASW_DONATIONS_VERSION,
        ) );
    }

    public function render_shortcode( $atts ) {
        $this->has_shortcode = true;
        $form = new YASW_Donation_Form();
        return $form->render();
    }

    public function maybe_enqueue_assets() {
        if ( $this->has_shortcode ) {
            wp_enqueue_style( 'yasw-donations' );
            wp_enqueue_script( 'yasw-donations' );
        }
    }

    public function process_donation() {
        check_ajax_referer( 'yasw_donation_nonce', 'nonce' );

        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );

        // Insert donation record as pending
        $donation_id = YASW_Donation_DB::insert_donation( $_POST );

        if ( ! $donation_id ) {
            wp_send_json_error( 'Failed to record donation. Please try again.' );
            return;
        }

        $result = null;

        switch ( $payment_method ) {
            case 'credit_card':
                $processor = new YASW_Sola_Processor();
                $result    = $processor->process( $_POST );
                break;

            case 'donors_fund':
                $processor = new YASW_DonorsFund_Processor();
                $result    = $processor->process( $_POST );
                break;

            case 'ojc_fund':
                $processor = new YASW_OJC_Processor();
                $result    = $processor->process( $_POST );
                break;

            case 'pledger':
                $processor = new YASW_Pledger_Processor();
                $result    = $processor->process( $_POST );
                break;

            default:
                YASW_Donation_DB::update_donation( $donation_id, array( 'status' => 'error' ) );
                YASW_Donation_DB::log_error( $donation_id, 'Invalid payment method: ' . $payment_method );
                wp_send_json_error( 'Invalid payment method.' );
                return;
        }

        if ( $result && $result['success'] ) {
            YASW_Donation_DB::update_donation( $donation_id, array(
                'status'              => 'approved',
                'transaction_id'      => $result['transactionId'] ?? $result['refNum'] ?? null,
                'confirmation_number' => $result['confirmationNumber'] ?? null,
                'masked_card'         => $result['maskedCard'] ?? null,
                'gateway_response'    => $result,
            ) );

            // Send email notifications
            $donation = YASW_Donation_DB::get_donation( $donation_id );
            if ( $donation ) {
                YASW_Donation_Emails::send_all( $donation_id, $donation );
            }

            wp_send_json_success( $result );
        } elseif ( $result ) {
            $status = 'declined';
            if ( ! empty( $result['requires3ds'] ) ) {
                $status = '3ds_pending';
            }

            YASW_Donation_DB::update_donation( $donation_id, array(
                'status'           => $status,
                'gateway_response' => $result['gatewayResponse'] ?? $result,
            ) );
            YASW_Donation_DB::log_error(
                $donation_id,
                $result['message'] ?? 'Unknown error',
                $result['errorCode'] ?? null,
                $result['gatewayResponse'] ?? null
            );
            wp_send_json_error( $result['message'] );
        }
    }
}

YASW_Donations::instance();
