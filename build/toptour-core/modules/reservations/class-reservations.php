<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Module_Reservations
{
    private const NONCE_ACTION = 'toptour_submit_inquiry';
    private const NONCE_NAME = 'toptour_inquiry_nonce';

    /**
     * @var string
     */
    private $table_suffix = 'toptour_requests';

    /**
     * @var array<string, mixed>
     */
    private $inquiry_result = array(
        'success' => false,
        'error' => false,
        'message' => '',
    );

    /**
     * Initialize reservations module.
     */
    public function init()
    {
        $this->register_hooks();
    }

    /**
     * Register module hooks.
     */
    public function register_hooks()
    {
        add_action('init', array($this, 'handle_inquiry_submission'));
    }

    /**
     * Handle inquiry form submission from product page.
     */
    public function handle_inquiry_submission()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['toptour_action']) ? sanitize_text_field(wp_unslash($_POST['toptour_action'])) : '';

        if ($action !== 'submit_inquiry') {
            return;
        }

        if (! isset($_POST[self::NONCE_NAME])) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Missing security nonce.',
            );
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Invalid security nonce.',
            );
            return;
        }

        $offer_id = isset($_POST['offer_id']) ? absint(wp_unslash($_POST['offer_id'])) : 0;
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
        $adults = isset($_POST['adults']) ? absint(wp_unslash($_POST['adults'])) : 0;
        $children = isset($_POST['children']) ? absint(wp_unslash($_POST['children'])) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if ($offer_id <= 0 || $customer_name === '' || $customer_email === '') {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Missing required fields.',
            );
            return;
        }

        if (! is_email($customer_email)) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Invalid customer email.',
            );
            return;
        }

        $manager_user_id = null;
        if (class_exists('Toptour_Module_Offers')) {
            $offers_module = new Toptour_Module_Offers();
            $derived_manager_user_id = (int) $offers_module->get_offer_manager_user_id($offer_id);
            if ($derived_manager_user_id > 0) {
                $manager_user_id = $derived_manager_user_id;
            }
        }

        $date_from = $date_from !== '' ? $date_from : null;
        $date_to = $date_to !== '' ? $date_to : null;

        $data = array(
            'offer_id' => $offer_id,
            'manager_user_id' => $manager_user_id,
            'request_type' => 'inquiry',
            'status' => 'new',
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'adults' => $adults,
            'children' => $children,
            'persons_total' => $adults + $children,
            'note' => $note,
            'source' => 'product_page',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $inserted = $this->create_inquiry_request($data);

        if (! $inserted) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Unable to save inquiry request.',
            );
            return;
        }

        $this->inquiry_result = array(
            'success' => true,
            'error' => false,
            'message' => 'Inquiry request submitted successfully.',
        );
    }

    /**
     * Insert one inquiry request record.
     *
     * @param array<string, mixed> $data Inquiry payload.
     * @return bool
     */
    public function create_inquiry_request($data)
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->get_table_name(),
            array(
                'offer_id' => isset($data['offer_id']) ? absint($data['offer_id']) : 0,
                'manager_user_id' => isset($data['manager_user_id']) ? (is_null($data['manager_user_id']) ? null : absint($data['manager_user_id'])) : null,
                'request_type' => isset($data['request_type']) ? sanitize_text_field($data['request_type']) : 'inquiry',
                'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'new',
                'customer_name' => isset($data['customer_name']) ? sanitize_text_field($data['customer_name']) : '',
                'customer_email' => isset($data['customer_email']) ? sanitize_email($data['customer_email']) : '',
                'customer_phone' => isset($data['customer_phone']) ? sanitize_text_field($data['customer_phone']) : '',
                'date_from' => isset($data['date_from']) ? $data['date_from'] : null,
                'date_to' => isset($data['date_to']) ? $data['date_to'] : null,
                'adults' => isset($data['adults']) ? absint($data['adults']) : 0,
                'children' => isset($data['children']) ? absint($data['children']) : 0,
                'persons_total' => isset($data['persons_total']) ? absint($data['persons_total']) : 0,
                'note' => isset($data['note']) ? sanitize_textarea_field($data['note']) : '',
                'source' => isset($data['source']) ? sanitize_text_field($data['source']) : 'product_page',
                'created_at' => isset($data['created_at']) ? sanitize_text_field($data['created_at']) : current_time('mysql'),
                'updated_at' => isset($data['updated_at']) ? sanitize_text_field($data['updated_at']) : current_time('mysql'),
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        return $inserted !== false;
    }

    /**
     * Get latest inquiry submission result.
     *
     * @return array<string, mixed>
     */
    public function get_inquiry_result()
    {
        return $this->inquiry_result;
    }

    /**
     * Get reservations table name with WP prefix.
     *
     * @return string
     */
    public function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . $this->table_suffix;
    }

    /**
     * Get SQL schema for reservations table.
     *
     * @return string
     */
    public function get_table_schema()
    {
        $table_name = $this->get_table_name();

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            offer_id BIGINT UNSIGNED NOT NULL,
            manager_user_id BIGINT UNSIGNED NULL,
            request_type VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'new',
            customer_name VARCHAR(191) NOT NULL,
            customer_email VARCHAR(191) NOT NULL,
            customer_phone VARCHAR(64) NULL,
            date_from DATE NULL,
            date_to DATE NULL,
            adults INT UNSIGNED NULL,
            children INT UNSIGNED NULL,
            persons_total INT UNSIGNED NULL,
            note TEXT NULL,
            source VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY offer_id (offer_id),
            KEY manager_user_id (manager_user_id),
            KEY status (status),
            KEY request_type (request_type),
            KEY created_at (created_at)
        )";
    }
}
