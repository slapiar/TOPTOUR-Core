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

        $price_from = trim((string) $price_from);
        $price_note = trim((string) $price_note);

        if ($price_from === '') {
            return '';
        }

        if ($price_note !== '') {
            return 'od ' . $price_from . ' EUR / ' . $price_note;
        }

        return 'od ' . $price_from . ' EUR';
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

        if ($persons_min <= 0 && $persons_max <= 0) {
            return '';
        }

        if ($persons_min > 0 && $persons_max > 0 && $persons_min !== $persons_max) {
            return $persons_min . '-' . $persons_max . ' osoby';
        }

        $persons = $persons_min > 0 ? $persons_min : $persons_max;

        if ($persons === 1) {
            return '1 osoba';
        }

        return $persons . ' osoby';
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

        if ($mode === '') {
            $mode = 'inquiry';
        }

        if ($mode === 'reservation') {
            return array(
                'mode' => 'reservation',
                'primary_label' => 'Rezervovat',
                'secondary_label' => '',
            );
        }

        if ($mode === 'both') {
            return array(
                'mode' => 'both',
                'primary_label' => 'Overit dostupnost',
                'secondary_label' => 'Rezervovat',
            );
        }

        return array(
            'mode' => 'inquiry',
            'primary_label' => 'Overit dostupnost',
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
            '<p><label for="%1$s">%2$s</label><br /><input type="number" name="%1$s" id="%1$s" value="%3$s" class="widefat" /></p>',
            esc_attr($field_key),
            esc_html($label),
            esc_attr((string) $value)
        );
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
            return (string) floatval($value);
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
