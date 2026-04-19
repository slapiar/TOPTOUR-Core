<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Module_Managers
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private $meta_schema = array();

    /**
     * Prepare module defaults.
     */
    public function __construct()
    {
        $this->meta_schema = $this->get_meta_schema();
    }

    /**
     * Initialize module hooks and setup.
     */
    public function init()
    {
        $this->register_hooks();
    }

    /**
     * Register profile hooks.
     */
    public function register_hooks()
    {
        add_action('show_user_profile', array($this, 'render_profile_fields'));
        add_action('edit_user_profile', array($this, 'render_profile_fields'));
        add_action('personal_options_update', array($this, 'save_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_profile_fields'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_profile_assets'));
    }

    /**
     * Enqueue media uploader assets for user profile screens.
     *
     * @param string $hook_suffix Current admin screen hook.
     */
    public function enqueue_profile_assets($hook_suffix)
    {
        if ($hook_suffix !== 'profile.php' && $hook_suffix !== 'user-edit.php') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'toptour-manager-profile-media',
            TOPTOUR_CORE_URL . 'assets/js/manager-profile-media.js',
            array(),
            TOPTOUR_CORE_VERSION,
            true
        );
    }

    /**
     * Get manager meta schema.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_meta_schema()
    {
        return array(
            'phone' => array(
                'meta_key' => 'toptour_manager_phone',
                'type' => 'text',
                'default' => '',
            ),
            'bio' => array(
                'meta_key' => 'toptour_manager_bio',
                'type' => 'textarea',
                'default' => '',
            ),
            'image_id' => array(
                'meta_key' => 'toptour_manager_image_id',
                'type' => 'int',
                'default' => 0,
            ),
        );
    }

    /**
     * Render manager profile fields in user profile screens.
     *
     * @param WP_User $user User object.
     */
    public function render_profile_fields($user)
    {
        if (! ($user instanceof WP_User)) {
            return;
        }

        $phone = $this->get_manager_field($user->ID, 'phone', '');
        $bio = $this->get_manager_field($user->ID, 'bio', '');
        $image_id = $this->get_manager_field($user->ID, 'image_id', 0);
        $image_url = $this->get_manager_image_url($user->ID, 'thumbnail');

        echo '<h2>TOPTOUR Manager Profile</h2>';
        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th><label for="toptour_manager_phone">Phone</label></th>';
        echo '<td>';
        echo '<input type="text" name="toptour_manager_phone" id="toptour_manager_phone" value="' . esc_attr((string) $phone) . '" class="regular-text" />';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="toptour_manager_bio">Bio</label></th>';
        echo '<td>';
        echo '<textarea name="toptour_manager_bio" id="toptour_manager_bio" rows="4" class="regular-text">' . esc_textarea((string) $bio) . '</textarea>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th><label for="toptour_manager_image_id">Image ID</label></th>';
        echo '<td>';
        echo '<input type="number" name="toptour_manager_image_id" id="toptour_manager_image_id" value="' . esc_attr((string) $image_id) . '" class="regular-text" />';
        echo '<p>';
        echo '<button type="button" class="button toptour-manager-select-image">Select image</button> ';
        echo '<button type="button" class="button toptour-manager-remove-image">Remove image</button>';
        echo '</p>';
        echo '<div class="toptour-manager-image-preview"' . ($image_url === '' ? ' hidden="hidden"' : '') . '>';
        echo '<img src="' . esc_url($image_url) . '" alt="Manager image" width="96" height="96" class="toptour-manager-image-preview-img" />';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
    }

    /**
     * Save manager profile fields.
     *
     * @param int $user_id User ID.
     */
    public function save_profile_fields($user_id)
    {
        if (! current_user_can('edit_user', $user_id)) {
            return;
        }

        $phone = isset($_POST['toptour_manager_phone']) ? sanitize_text_field(wp_unslash($_POST['toptour_manager_phone'])) : '';
        $bio = isset($_POST['toptour_manager_bio']) ? sanitize_textarea_field(wp_unslash($_POST['toptour_manager_bio'])) : '';
        $image_id = isset($_POST['toptour_manager_image_id']) ? intval(wp_unslash($_POST['toptour_manager_image_id'])) : 0;

        update_user_meta($user_id, 'toptour_manager_phone', $phone);
        update_user_meta($user_id, 'toptour_manager_bio', $bio);
        update_user_meta($user_id, 'toptour_manager_image_id', $image_id);
    }

    /**
     * Get one manager field value.
     *
     * @param int        $user_id User ID.
     * @param string     $field_key Field key.
     * @param mixed|null $default Explicit default value.
     * @return mixed
     */
    public function get_manager_field($user_id, $field_key, $default = null)
    {
        if (! isset($this->meta_schema[$field_key])) {
            return $default;
        }

        $field = $this->meta_schema[$field_key];
        $raw_value = get_user_meta((int) $user_id, $field['meta_key'], true);

        if ($raw_value === '' || $raw_value === null) {
            $fallback_value = func_num_args() >= 3 ? $default : $field['default'];
            return $this->normalize_field_value($fallback_value, $field['type']);
        }

        return $this->normalize_field_value($raw_value, $field['type']);
    }

    /**
     * Get normalized manager data.
     *
     * @param int $user_id User ID.
     * @return array<string, mixed>
     */
    public function get_manager_data($user_id)
    {
        return array(
            'phone' => $this->get_manager_field($user_id, 'phone', ''),
            'bio' => $this->get_manager_field($user_id, 'bio', ''),
            'image_id' => $this->get_manager_field($user_id, 'image_id', 0),
        );
    }

    /**
     * Get manager image URL with avatar fallback.
     *
     * @param int    $user_id User ID.
     * @param string $size Image size.
     * @return string
     */
    public function get_manager_image_url($user_id, $size = 'thumbnail')
    {
        $image_id = (int) $this->get_manager_field($user_id, 'image_id', 0);

        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, $size);
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        $avatar_url = get_avatar_url($user_id);
        if (is_string($avatar_url) && $avatar_url !== '') {
            return $avatar_url;
        }

        return '';
    }

    /**
     * Get normalized manager summary payload.
     *
     * @param int $user_id User ID.
     * @return array<string, mixed>
     */
    public function get_manager_summary($user_id)
    {
        $user = get_user_by('id', (int) $user_id);
        $data = $this->get_manager_data($user_id);

        return array(
            'id' => (int) $user_id,
            'name' => ($user instanceof WP_User) ? (string) $user->display_name : '',
            'email' => ($user instanceof WP_User) ? (string) $user->user_email : '',
            'phone' => (string) $data['phone'],
            'bio' => (string) $data['bio'],
            'image_id' => (int) $data['image_id'],
            'image_url' => $this->get_manager_image_url($user_id),
        );
    }

    /**
     * Normalize field value by schema type.
     *
     * @param mixed  $value Raw value.
     * @param string $type Field type.
     * @return mixed
     */
    private function normalize_field_value($value, $type)
    {
        if ($type === 'int') {
            return (int) $value;
        }

        if ($type === 'textarea' || $type === 'text') {
            return (string) $value;
        }

        return $value;
    }
}
