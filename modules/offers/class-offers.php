<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Module_Offers
{
    private const NONCE_ACTION = 'toptour_offers_save_meta';
    private const NONCE_NAME = 'toptour_offers_meta_nonce';

    /**
     * @var array<int, string>
     */
    private $meta_keys = array();

    /**
     * Prepare module defaults.
     */
    public function __construct()
    {
        $this->meta_keys = array_keys($this->get_meta_schema());
    }

    /**
     * Central schema for offers meta fields.
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_meta_schema()
    {
        return array(
            'offer_subtitle' => array(
                'key' => 'offer_subtitle',
                'label' => 'Offer Subtitle',
                'type' => 'text',
                'section' => 'basic',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'offer_type' => array(
                'key' => 'offer_type',
                'label' => 'Offer Type',
                'type' => 'text',
                'section' => 'basic',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'location' => array(
                'key' => 'location',
                'label' => 'Location',
                'type' => 'text',
                'section' => 'basic',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'duration' => array(
                'key' => 'duration',
                'label' => 'Duration',
                'type' => 'text',
                'section' => 'basic',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'persons_min' => array(
                'key' => 'persons_min',
                'label' => 'Persons Min',
                'type' => 'number',
                'section' => 'capacity_price',
                'default' => '',
                'sanitize_callback' => array($this, 'sanitize_int_value'),
            ),
            'persons_max' => array(
                'key' => 'persons_max',
                'label' => 'Persons Max',
                'type' => 'number',
                'section' => 'capacity_price',
                'default' => '',
                'sanitize_callback' => array($this, 'sanitize_int_value'),
            ),
            'price_from' => array(
                'key' => 'price_from',
                'label' => 'Price From',
                'type' => 'text',
                'section' => 'capacity_price',
                'default' => '',
                'sanitize_callback' => array($this, 'sanitize_number_or_text_value'),
            ),
            'price_note' => array(
                'key' => 'price_note',
                'label' => 'Price Note',
                'type' => 'text',
                'section' => 'capacity_price',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'includes' => array(
                'key' => 'includes',
                'label' => 'Includes',
                'type' => 'textarea',
                'section' => 'content',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'excludes' => array(
                'key' => 'excludes',
                'label' => 'Excludes',
                'type' => 'textarea',
                'section' => 'content',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'season' => array(
                'key' => 'season',
                'label' => 'Season',
                'type' => 'text',
                'section' => 'operations',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'availability_mode' => array(
                'key' => 'availability_mode',
                'label' => 'Availability Mode',
                'type' => 'text',
                'section' => 'operations',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'assigned_manager_user_id' => array(
                'key' => 'assigned_manager_user_id',
                'label' => 'Assigned Manager',
                'type' => 'user_select',
                'section' => 'manager_notes',
                'default' => '',
                'sanitize_callback' => array($this, 'sanitize_int_value'),
            ),
            'cta_mode' => array(
                'key' => 'cta_mode',
                'label' => 'CTA Mode',
                'type' => 'text',
                'section' => 'operations',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'note' => array(
                'key' => 'note',
                'label' => 'Note',
                'type' => 'textarea',
                'section' => 'manager_notes',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'reservation_conditions' => array(
                'key' => 'reservation_conditions',
                'label' => 'Reservation Conditions',
                'type' => 'textarea',
                'section' => 'manager_notes',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
        );
    }

    /**
     * Get a single offer field value from schema-aware meta.
     *
     * @param int         $post_id  Product post ID.
     * @param string      $field_key Field key.
     * @param mixed|null  $default  Explicit fallback value.
     * @return mixed
     */
    public function get_offer_field($post_id, $field_key, $default = null)
    {
        $schema = $this->get_meta_schema();

        if (! isset($schema[$field_key])) {
            return $default;
        }

        $field = $schema[$field_key];
        $stored_value = get_post_meta((int) $post_id, $field_key, true);

        if ($stored_value === '' || $stored_value === null) {
            $fallback_value = func_num_args() >= 3 ? $default : $field['default'];
            return $this->normalize_offer_value($fallback_value, $field);
        }

        return $this->normalize_offer_value($stored_value, $field);
    }

    /**
     * Get all offer fields as a schema-based associative array.
     *
     * @param int $post_id Product post ID.
     * @return array<string, mixed>
     */
    public function get_offer_data($post_id)
    {
        $data = array();
        $schema = $this->get_meta_schema();

        foreach ($schema as $field_key => $field) {
            unset($field);
            $data[$field_key] = $this->get_offer_field($post_id, $field_key);
        }

        return $data;
    }

    /**
     * Get assigned manager user ID for an offer.
     *
     * @param int $post_id Product post ID.
     * @return int
     */
    public function get_offer_manager_user_id($post_id)
    {
        $manager_user_id = $this->get_offer_field($post_id, 'assigned_manager_user_id', 0);
        return intval($manager_user_id);
    }

    /**
     * Get assigned manager WP_User object for an offer.
     *
     * @param int $post_id Product post ID.
     * @return WP_User|null
     */
    public function get_offer_manager($post_id)
    {
        $manager_user_id = $this->get_offer_manager_user_id($post_id);

        if ($manager_user_id <= 0) {
            return null;
        }

        $user = get_user_by('id', $manager_user_id);

        if (! ($user instanceof WP_User)) {
            return null;
        }

        return $user;
    }

    /**
     * Get normalized summary payload for one offer.
     *
     * @param int $post_id Product post ID.
     * @return array<string, mixed>
     */
    public function get_offer_summary($post_id)
    {
        return array(
            'post_id' => (int) $post_id,
            'title' => (string) get_the_title($post_id),
            'subtitle' => $this->get_offer_field($post_id, 'offer_subtitle', ''),
            'location' => $this->get_offer_field($post_id, 'location', ''),
            'duration' => $this->get_offer_field($post_id, 'duration', ''),
            'price_label' => $this->get_offer_price_label($post_id),
            'persons_label' => $this->get_offer_persons_label($post_id),
            'cta' => $this->get_offer_cta_config($post_id),
            'manager' => $this->get_offer_manager_summary($post_id),
        );
    }

    /**
     * Build simple price label from offer fields.
     *
     * @param int $post_id Product post ID.
     * @return string
     */
    public function get_offer_price_label($post_id)
    {
        $price_from = $this->get_offer_field($post_id, 'price_from', '');
        $price_note = $this->get_offer_field($post_id, 'price_note', '');
        $label_from = Toptour_Core_I18n::t('label.from', 'od');
        $label_currency = Toptour_Core_I18n::t('label.currency', 'EUR');

        $price_from = trim((string) $price_from);
        $price_note = trim((string) $price_note);

        if ($price_from === '') {
            return '';
        }

        if ($price_note !== '') {
            return $label_from . ' ' . $price_from . ' ' . $label_currency . ' / ' . $price_note;
        }

        return $label_from . ' ' . $price_from . ' ' . $label_currency;
    }

    /**
     * Build simple persons label from min/max values.
     *
     * @param int $post_id Product post ID.
     * @return string
     */
    public function get_offer_persons_label($post_id)
    {
        $persons_min = (int) $this->get_offer_field($post_id, 'persons_min', 0);
        $persons_max = (int) $this->get_offer_field($post_id, 'persons_max', 0);
        $label_person = Toptour_Core_I18n::t('label.person', 'osoba');
        $label_persons = Toptour_Core_I18n::t('label.persons', 'osoby');

        if ($persons_min <= 0 && $persons_max <= 0) {
            return '';
        }

        if ($persons_min > 0 && $persons_max > 0 && $persons_min !== $persons_max) {
            return $persons_min . '-' . $persons_max . ' ' . $label_persons;
        }

        $persons = $persons_min > 0 ? $persons_min : $persons_max;

        if ($persons === 1) {
            return '1 ' . $label_person;
        }

        return $persons . ' ' . $label_persons;
    }

    /**
     * Get CTA config payload from cta_mode.
     *
     * @param int $post_id Product post ID.
     * @return array<string, string>
     */
    public function get_offer_cta_config($post_id)
    {
        $mode = (string) $this->get_offer_field($post_id, 'cta_mode', '');
        $mode = trim(strtolower($mode));
        $label_reserve = Toptour_Core_I18n::t('cta.reserve', 'Rezervovat');
        $label_check_availability = Toptour_Core_I18n::t('cta.check_availability', 'Overit dostupnost');

        if ($mode === '') {
            $mode = 'inquiry';
        }

        if ($mode === 'reservation') {
            return array(
                'mode' => 'reservation',
                'primary_label' => $label_reserve,
                'secondary_label' => '',
            );
        }

        if ($mode === 'both') {
            return array(
                'mode' => 'both',
                'primary_label' => $label_check_availability,
                'secondary_label' => $label_reserve,
            );
        }

        return array(
            'mode' => 'inquiry',
            'primary_label' => $label_check_availability,
            'secondary_label' => '',
        );
    }

    /**
     * Get normalized manager summary payload.
     *
     * @param int $post_id Product post ID.
     * @return array<string, mixed>|null
     */
    public function get_offer_manager_summary($post_id)
    {
        $manager = $this->get_offer_manager($post_id);

        if (! ($manager instanceof WP_User)) {
            return null;
        }

        return array(
            'id' => (int) $manager->ID,
            'name' => (string) $manager->display_name,
            'email' => (string) $manager->user_email,
        );
    }

    /**
     * Initialize module.
     */
    public function init()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks for the offers module.
     */
    public function register_hooks()
    {
        add_action('init', array($this, 'register_meta_fields'));
        add_action('add_meta_boxes', array($this, 'register_admin_fields'));
        add_action('save_post', array($this, 'save_meta_fields'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'render_frontend_offer_details'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'render_frontend_manager_card'), 35);
    }

    /**
     * Render offer details block on WooCommerce single product page.
     */
    public function render_frontend_offer_details()
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $summary = $this->get_offer_summary($post_id);
        $season = trim((string) $this->get_offer_field($post_id, 'season', ''));
        $includes = trim((string) $this->get_offer_field($post_id, 'includes', ''));
        $excludes = trim((string) $this->get_offer_field($post_id, 'excludes', ''));
        $note = trim((string) $this->get_offer_field($post_id, 'note', ''));

        $has_content = (
            trim((string) $summary['subtitle']) !== '' ||
            trim((string) $summary['location']) !== '' ||
            trim((string) $summary['duration']) !== '' ||
            trim((string) $summary['persons_label']) !== '' ||
            trim((string) $summary['price_label']) !== '' ||
            $season !== '' ||
            $includes !== '' ||
            $excludes !== '' ||
            $note !== ''
        );

        if (! $has_content) {
            return;
        }

        echo '<div class="toptour-offer-details">';
        echo '<h3>' . esc_html(Toptour_Core_I18n::t('offer.details', 'TOPTOUR Offer Details')) . '</h3>';

        if (trim((string) $summary['subtitle']) !== '') {
            echo '<p><strong>' . esc_html((string) $summary['subtitle']) . '</strong></p>';
        }

        echo '<ul class="toptour-offer-details-list">';

        if (trim((string) $summary['location']) !== '') {
            echo '<li><strong>' . esc_html(Toptour_Core_I18n::t('offer.location', 'Location')) . ':</strong> ' . esc_html((string) $summary['location']) . '</li>';
        }

        if (trim((string) $summary['duration']) !== '') {
            echo '<li><strong>' . esc_html(Toptour_Core_I18n::t('offer.duration', 'Duration')) . ':</strong> ' . esc_html((string) $summary['duration']) . '</li>';
        }

        if (trim((string) $summary['persons_label']) !== '') {
            echo '<li><strong>' . esc_html(Toptour_Core_I18n::t('offer.capacity', 'Capacity')) . ':</strong> ' . esc_html((string) $summary['persons_label']) . '</li>';
        }

        if (trim((string) $summary['price_label']) !== '') {
            echo '<li><strong>' . esc_html(Toptour_Core_I18n::t('offer.price', 'Price')) . ':</strong> ' . esc_html((string) $summary['price_label']) . '</li>';
        }

        if ($season !== '') {
            echo '<li><strong>' . esc_html(Toptour_Core_I18n::t('offer.season', 'Season')) . ':</strong> ' . esc_html($season) . '</li>';
        }

        echo '</ul>';

        if ($includes !== '') {
            echo '<p><strong>' . esc_html(Toptour_Core_I18n::t('offer.includes', 'Includes')) . ':</strong><br />' . nl2br(esc_html($includes)) . '</p>';
        }

        if ($excludes !== '') {
            echo '<p><strong>' . esc_html(Toptour_Core_I18n::t('offer.excludes', 'Excludes')) . ':</strong><br />' . nl2br(esc_html($excludes)) . '</p>';
        }

        if ($note !== '') {
            echo '<p><strong>' . esc_html(Toptour_Core_I18n::t('offer.note', 'Note')) . ':</strong><br />' . nl2br(esc_html($note)) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Render manager contact card on WooCommerce single product page.
     */
    public function render_frontend_manager_card()
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $manager_user_id = $this->get_offer_manager_user_id($post_id);
        if ($manager_user_id <= 0) {
            return;
        }

        if (! class_exists('Toptour_Module_Managers')) {
            return;
        }

        $managers_module = new Toptour_Module_Managers();
        $summary = $managers_module->get_manager_summary($manager_user_id);

        if (! is_array($summary) || (int) ($summary['id'] ?? 0) <= 0) {
            return;
        }

        $name = trim((string) ($summary['name'] ?? ''));
        $email = trim((string) ($summary['email'] ?? ''));
        $phone = trim((string) ($summary['phone'] ?? ''));
        $bio = trim((string) ($summary['bio'] ?? ''));
        $image_url = trim((string) ($summary['image_url'] ?? ''));

        if ($name === '' && $email === '' && $phone === '' && $bio === '' && $image_url === '') {
            return;
        }

        $heading = Toptour_Core_I18n::t('manager.contact', 'Your contact');
        $email_label = Toptour_Core_I18n::t('manager.email', 'Email');
        $phone_label = Toptour_Core_I18n::t('manager.phone', 'Phone');

        echo '<div class="toptour-manager-card">';
        echo '<h3>' . esc_html($heading) . '</h3>';

        if ($image_url !== '') {
            echo '<p><img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '" /></p>';
        }

        if ($name !== '') {
            echo '<p><strong>' . esc_html($name) . '</strong></p>';
        }

        if ($email !== '') {
            $mailto = 'mailto:' . sanitize_email($email);
            echo '<p><strong>' . esc_html($email_label) . ':</strong> <a href="' . esc_url($mailto) . '">' . esc_html($email) . '</a></p>';
        }

        if ($phone !== '') {
            $phone_href = preg_replace('/[^0-9\+]/', '', $phone);
            echo '<p><strong>' . esc_html($phone_label) . ':</strong> <a href="' . esc_url('tel:' . $phone_href) . '">' . esc_html($phone) . '</a></p>';
        }

        if ($bio !== '') {
            echo '<p>' . nl2br(esc_html($bio)) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Register post meta keys for WooCommerce products.
     */
    public function register_meta_fields()
    {
        foreach ($this->meta_keys as $meta_key) {
            register_post_meta('product', $meta_key, array(
                'single' => true,
                'type' => 'string',
                'show_in_rest' => false,
            ));
        }
    }

    /**
     * Register offers metabox for WooCommerce products.
     */
    public function register_admin_fields($post_type)
    {
        if ($post_type !== 'product') {
            return;
        }

        add_meta_box(
            'toptour_offer_details',
            'TOPTOUR Offer Details',
            array($this, 'render_admin_fields'),
            'product',
            'normal',
            'default'
        );
    }

    /**
     * Render offers metabox fields.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_admin_fields($post)
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $schema = $this->get_meta_schema();
        $values = array();

        foreach ($schema as $field_key => $field) {
            $stored_value = get_post_meta($post->ID, $field_key, true);
            $values[$field_key] = $stored_value !== '' ? $stored_value : $field['default'];
        }

        $users = get_users(array(
            'fields' => array('ID', 'display_name'),
        ));

        $section_titles = $this->get_section_titles();
        $current_section = '';

        foreach ($schema as $field_key => $field) {
            if ($field['section'] !== $current_section) {
                $current_section = $field['section'];
                echo '<h3>' . esc_html($section_titles[$current_section]) . '</h3>';
            }

            if ($field['type'] === 'textarea') {
                $this->render_textarea_input($field_key, $field['label'], $values[$field_key]);
                continue;
            }

            if ($field['type'] === 'number') {
                $this->render_number_input($field_key, $field['label'], $values[$field_key]);
                continue;
            }

            if ($field['type'] === 'user_select') {
                $this->render_user_select_input($field_key, $field['label'], $values[$field_key], $users);
                continue;
            }

            $this->render_text_input($field_key, $field['label'], $values[$field_key]);
        }

        $this->render_range_validation_script();
    }

    /**
     * Get section labels for admin metabox rendering.
     *
     * @return array<string, string>
     */
    private function get_section_titles()
    {
        return array(
            'basic' => 'Basic',
            'capacity_price' => 'Capacity & Price',
            'content' => 'Content',
            'operations' => 'Operations',
            'manager_notes' => 'Manager & Notes',
        );
    }

    /**
     * Render a text input field.
     */
    private function render_text_input($field_key, $label, $value)
    {
        printf(
            '<p><label for="%1$s">%2$s</label><br /><input type="text" name="%1$s" id="%1$s" value="%3$s" class="widefat" /></p>',
            esc_attr($field_key),
            esc_html($label),
            esc_attr((string) $value)
        );
    }

    /**
     * Render a number input field.
     */
    private function render_number_input($field_key, $label, $value)
    {
        printf(
            '<p><label for="%1$s">%2$s</label><br /><input type="number" name="%1$s" id="%1$s" value="%3$s" min="0" step="1" class="widefat" /></p>',
            esc_attr($field_key),
            esc_html($label),
            esc_attr((string) $value)
        );
    }

    /**
     * Render lightweight client-side validation for range fields.
     */
    private function render_range_validation_script()
    {
        ?>
        <script>
        (function () {
            var minInput = document.getElementById('persons_min');
            var maxInput = document.getElementById('persons_max');

            if (!minInput || !maxInput) {
                return;
            }

            function normalizeNumber(input) {
                var value = parseInt(input.value, 10);

                if (!Number.isNaN(value) && value < 0) {
                    input.value = '0';
                }
            }

            function validateRange() {
                normalizeNumber(minInput);
                normalizeNumber(maxInput);

                var minValue = parseInt(minInput.value, 10);
                var maxValue = parseInt(maxInput.value, 10);

                minInput.setCustomValidity('');
                maxInput.setCustomValidity('');

                if (!Number.isNaN(minValue) && !Number.isNaN(maxValue) && minValue > maxValue) {
                    var message = 'Persons Min cannot be greater than Persons Max.';
                    minInput.setCustomValidity(message);
                    maxInput.setCustomValidity(message);
                }
            }

            minInput.addEventListener('input', validateRange);
            maxInput.addEventListener('input', validateRange);
            validateRange();
        })();
        </script>
        <?php
    }

    /**
     * Render a textarea field.
     */
    private function render_textarea_input($field_key, $label, $value)
    {
        printf(
            '<p><label for="%1$s">%2$s</label><br /><textarea name="%1$s" id="%1$s" rows="4" class="widefat">%3$s</textarea></p>',
            esc_attr($field_key),
            esc_html($label),
            esc_textarea((string) $value)
        );
    }

    /**
     * Render a manager user select field.
     *
     * @param array<int, WP_User> $users User list.
     */
    private function render_user_select_input($field_key, $label, $value, $users)
    {
        echo '<p><label for="' . esc_attr($field_key) . '">' . esc_html($label) . '</label><br />';
        echo '<select name="' . esc_attr($field_key) . '" id="' . esc_attr($field_key) . '">';
        echo '<option value="">Select manager</option>';

        foreach ($users as $user) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr((string) $user->ID),
                selected((string) $value, (string) $user->ID, false),
                esc_html($user->display_name)
            );
        }

        echo '</select></p>';
    }

    /**
     * Placeholder for future meta persistence logic.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_fields($post_id, $post)
    {
        if (! isset($_POST[self::NONCE_NAME])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type !== 'product') {
            return;
        }

        $schema = $this->get_meta_schema();
        $sanitized_data = array();

        foreach ($schema as $meta_key => $field) {
            if (! isset($_POST[$meta_key])) {
                continue;
            }

            $raw_value = wp_unslash($_POST[$meta_key]);

            $sanitize_callback = $field['sanitize_callback'];
            if (is_callable($sanitize_callback)) {
                $sanitized_value = call_user_func($sanitize_callback, $raw_value);
            } else {
                $sanitized_value = sanitize_text_field($raw_value);
            }

            $sanitized_data[$meta_key] = $sanitized_value;
        }

        if (isset($sanitized_data['persons_min'])) {
            $sanitized_data['persons_min'] = max(0, (int) $sanitized_data['persons_min']);
        }

        if (isset($sanitized_data['persons_max'])) {
            $sanitized_data['persons_max'] = max(0, (int) $sanitized_data['persons_max']);
        }

        if (
            isset($sanitized_data['persons_min']) &&
            isset($sanitized_data['persons_max']) &&
            $sanitized_data['persons_min'] > 0 &&
            $sanitized_data['persons_max'] > 0 &&
            $sanitized_data['persons_min'] > $sanitized_data['persons_max']
        ) {
            $min = (int) $sanitized_data['persons_min'];
            $max = (int) $sanitized_data['persons_max'];
            $sanitized_data['persons_min'] = $max;
            $sanitized_data['persons_max'] = $min;
        }

        foreach ($sanitized_data as $meta_key => $sanitized_value) {
            update_post_meta($post_id, $meta_key, $sanitized_value);
        }

        // Save handling intentionally remains simple and extendable.
    }

    /**
     * Sanitize integer-like values.
     *
     * @param mixed $value Raw value.
     * @return int
     */
    private function sanitize_int_value($value)
    {
        return intval($value);
    }

    /**
     * Sanitize value that can be numeric or plain text.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function sanitize_number_or_text_value($value)
    {
        if (is_numeric($value)) {
            return (string) max(0, floatval($value));
        }

        return sanitize_text_field($value);
    }

    /**
     * Normalize offer field value according to schema type.
     *
     * @param mixed                $value Raw value.
     * @param array<string, mixed> $field Field schema.
     * @return mixed
     */
    private function normalize_offer_value($value, $field)
    {
        if (! isset($field['type'])) {
            return $value;
        }

        if ($field['type'] === 'text' || $field['type'] === 'textarea') {
            return (string) $value;
        }

        if ($field['type'] === 'user_select') {
            return intval($value);
        }

        if ($field['type'] === 'number') {
            if ($value === '' || $value === null) {
                return $value;
            }

            if (is_numeric($value) && strpos((string) $value, '.') !== false) {
                return floatval($value);
            }

            return intval($value);
        }

        return $value;
    }
}
