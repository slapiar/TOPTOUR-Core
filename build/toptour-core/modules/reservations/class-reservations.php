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
        add_action('woocommerce_single_product_summary', array($this, 'render_inquiry_form'), 45);
    }

    /**
     * Render minimal inquiry form on WooCommerce single product page.
     */
    public function render_inquiry_form()
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $offer_id = get_the_ID();
        if (! $offer_id) {
            return;
        }

        $result = $this->get_inquiry_result();

        if (is_array($result) && (bool) ($result['success'] ?? false) === true) {
            echo '<p>' . esc_html(Toptour_Core_I18n::t('form.success', 'Your inquiry has been sent successfully.')) . '</p>';
        } elseif (is_array($result) && (bool) ($result['error'] ?? false) === true) {
            echo '<p>' . esc_html(Toptour_Core_I18n::t('form.error', 'Please check the form and try again.')) . '</p>';
        }

        $posted_action = isset($_POST['toptour_action']) ? sanitize_text_field(wp_unslash($_POST['toptour_action'])) : '';
        $posted_offer_id = isset($_POST['offer_id']) ? absint(wp_unslash($_POST['offer_id'])) : 0;
        $can_prefill = ($posted_action === 'submit_inquiry' && $posted_offer_id === (int) $offer_id);
        $has_error = is_array($result) && (bool) ($result['error'] ?? false) === true;
        $should_show_form = $has_error || $can_prefill;

        $customer_name = $can_prefill && isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = $can_prefill && isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customer_phone = $can_prefill && isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $date_from = $can_prefill && isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = $can_prefill && isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
        $adults = $can_prefill && isset($_POST['adults']) ? absint(wp_unslash($_POST['adults'])) : 0;
        $children = $can_prefill && isset($_POST['children']) ? absint(wp_unslash($_POST['children'])) : 0;
        $note = $can_prefill && isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        $today = wp_date('Y-m-d');
        $toggle_label = Toptour_Core_I18n::t('cta.check_availability', 'Check availability');

        echo '<div class="toptour-inquiry-form">';
        echo '<p><button type="button" id="toptour-inquiry-toggle">' . esc_html($toggle_label) . '</button></p>';
        echo '<div id="toptour-inquiry-form-panel"' . ($should_show_form ? '' : ' hidden="hidden"') . '>';
        echo '<h3>' . esc_html(Toptour_Core_I18n::t('form.inquiry_heading', 'Check availability')) . '</h3>';
        echo '<form method="post">';

        echo '<p><label for="toptour_customer_name">' . esc_html(Toptour_Core_I18n::t('form.customer_name', 'Name')) . '</label><br />';
        echo '<input type="text" id="toptour_customer_name" name="customer_name" value="' . esc_attr($customer_name) . '" required /></p>';

        echo '<p><label for="toptour_customer_email">' . esc_html(Toptour_Core_I18n::t('form.customer_email', 'Email')) . '</label><br />';
        echo '<input type="email" id="toptour_customer_email" name="customer_email" value="' . esc_attr($customer_email) . '" required /></p>';

        echo '<p><label for="toptour_customer_phone">' . esc_html(Toptour_Core_I18n::t('form.customer_phone', 'Phone')) . '</label><br />';
        echo '<input type="text" id="toptour_customer_phone" name="customer_phone" value="' . esc_attr($customer_phone) . '" /></p>';

        echo '<p><label for="toptour_date_from">' . esc_html(Toptour_Core_I18n::t('form.date_from', 'Date from')) . '</label><br />';
        echo '<input type="date" id="toptour_date_from" name="date_from" min="' . esc_attr($today) . '" value="' . esc_attr($date_from) . '" /></p>';

        echo '<p><label for="toptour_date_to">' . esc_html(Toptour_Core_I18n::t('form.date_to', 'Date to')) . '</label><br />';
        echo '<input type="date" id="toptour_date_to" name="date_to" min="' . esc_attr($today) . '" value="' . esc_attr($date_to) . '" /></p>';

        echo '<p><label for="toptour_adults">' . esc_html(Toptour_Core_I18n::t('form.adults', 'Adults')) . '</label><br />';
        echo '<input type="number" id="toptour_adults" name="adults" min="0" value="' . esc_attr((string) $adults) . '" /></p>';

        echo '<p><label for="toptour_children">' . esc_html(Toptour_Core_I18n::t('form.children', 'Children')) . '</label><br />';
        echo '<input type="number" id="toptour_children" name="children" min="0" value="' . esc_attr((string) $children) . '" /></p>';

        echo '<p><label for="toptour_note">' . esc_html(Toptour_Core_I18n::t('form.note', 'Note')) . '</label><br />';
        echo '<textarea id="toptour_note" name="note" rows="4">' . esc_textarea($note) . '</textarea></p>';

        echo '<input type="hidden" name="offer_id" value="' . esc_attr((string) $offer_id) . '" />';
        echo '<input type="hidden" name="toptour_action" value="submit_inquiry" />';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<p><button type="submit">' . esc_html(Toptour_Core_I18n::t('form.submit_inquiry', 'Send inquiry')) . '</button></p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        ?>
        <script>
        (function () {
            var toggleButton = document.getElementById('toptour-inquiry-toggle');
            var formPanel = document.getElementById('toptour-inquiry-form-panel');
            var dateFromInput = document.getElementById('toptour_date_from');
            var dateToInput = document.getElementById('toptour_date_to');

            if (toggleButton && formPanel) {
                toggleButton.addEventListener('click', function () {
                    formPanel.hidden = !formPanel.hidden;
                });
            }

            if (dateFromInput && dateToInput) {
                function syncDateRange() {
                    var fromValue = dateFromInput.value;

                    if (fromValue !== '') {
                        dateToInput.setAttribute('min', fromValue);

                        if (dateToInput.value !== '' && dateToInput.value < fromValue) {
                            dateToInput.value = '';
                        }
                        return;
                    }

                    dateToInput.setAttribute('min', dateFromInput.getAttribute('min') || '');
                }

                dateFromInput.addEventListener('change', syncDateRange);
                syncDateRange();
            }
        })();
        </script>
        <?php
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

        $today = wp_date('Y-m-d');

        if ($date_from !== '' && ! $this->is_valid_date_ymd($date_from)) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Invalid date_from value.',
            );
            return;
        }

        if ($date_to !== '' && ! $this->is_valid_date_ymd($date_to)) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Invalid date_to value.',
            );
            return;
        }

        if (($date_from !== '' && $date_from < $today) || ($date_to !== '' && $date_to < $today)) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Dates cannot be in the past.',
            );
            return;
        }

        if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Date from cannot be after date to.',
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

    /**
     * Validate YYYY-MM-DD date format.
     *
     * @param string $value Raw date string.
     * @return bool
     */
    private function is_valid_date_ymd($value)
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }
}
