<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Module_Reservations
{
    private const NONCE_ACTION = 'toptour_submit_inquiry';
    private const NONCE_NAME = 'toptour_inquiry_nonce';
    private const RESERVATION_CONFIRM_NONCE_ACTION = 'toptour_confirm_reservation';
    private const RESERVATION_CONFIRM_NONCE_NAME = 'toptour_confirm_reservation_nonce';
    private const ADMIN_UPDATE_NONCE_ACTION = 'toptour_request_update';
    private const ADMIN_DELETE_NONCE_ACTION = 'toptour_request_delete';

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
     * @var array<string, mixed>
     */
    private $reservation_confirmation_result = array(
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
        add_action('init', array($this, 'handle_reservation_confirmation_submission'));
        add_action('woocommerce_single_product_summary', array($this, 'render_inquiry_form'), 45);
        add_filter('the_content', array($this, 'render_reservation_confirmation'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_post_toptour_request_update', array($this, 'handle_admin_request_update'));
        add_action('admin_post_toptour_request_delete', array($this, 'handle_admin_request_delete'));
    }

    /**
     * Register TOPTOUR admin menu and requests page.
     */
    public function register_admin_menu()
    {
        add_menu_page(
            'TOPTOUR',
            'TOPTOUR',
            'manage_options',
            'toptour',
            array($this, 'render_admin_requests_page')
        );

        add_submenu_page(
            'toptour',
            'Requests',
            'Requests',
            'manage_options',
            'toptour',
            array($this, 'render_admin_requests_page')
        );
    }

    /**
     * Render requests admin page (list and edit views).
     */
    public function render_admin_requests_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $list_context = $this->get_admin_list_context_from_get();
        $page = isset($list_context['paged']) ? (int) $list_context['paged'] : 1;
        $page = max(1, $page);
        $search = isset($list_context['s']) ? (string) $list_context['s'] : '';
        $status_filter = isset($list_context['status']) ? (string) $list_context['status'] : '';
        $per_page = 20;

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'list';
        $request_id = isset($_GET['request_id']) ? absint(wp_unslash($_GET['request_id'])) : 0;

        echo '<div class="wrap">';
        echo '<h1>TOPTOUR Requests</h1>';

        if ($view === 'edit' && $request_id > 0) {
            $this->render_admin_request_edit_form($request_id, $list_context);
            echo '</div>';
            return;
        }

        $listing = $this->get_admin_requests_listing($page, $per_page, $search, $status_filter);
        $requests = isset($listing['items']) && is_array($listing['items']) ? $listing['items'] : array();
        $total_items = isset($listing['total']) ? (int) $listing['total'] : 0;
        $total_pages = max(1, (int) ceil($total_items / $per_page));

        $base_args = array('page' => 'toptour');
        if ($search !== '') {
            $base_args['s'] = $search;
        }
        if ($status_filter !== '') {
            $base_args['status'] = $status_filter;
        }

        if (isset($_GET['updated']) && wp_unslash($_GET['updated']) === '1') {
            echo '<p>Request updated.</p>';
        }

        if (isset($_GET['deleted']) && wp_unslash($_GET['deleted']) === '1') {
            echo '<p>Request deleted.</p>';
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="toptour" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="request-search-input">' . esc_html('Search requests') . '</label>';
        echo '<input type="search" id="request-search-input" name="s" value="' . esc_attr($search) . '" /> ';
        echo '<select name="status" id="request-status-filter">';
        echo '<option value="">' . esc_html('All statuses') . '</option>';
        foreach ($this->get_allowed_statuses() as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . selected($status_filter, $status, false) . '>' . esc_html($status) . '</option>';
        }
        echo '</select> ';
        echo '<input type="submit" class="button" value="' . esc_attr('Filter') . '" />';
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Created At</th>';
        echo '<th>Offer ID</th>';
        echo '<th>Manager User ID</th>';
        echo '<th>Customer Name</th>';
        echo '<th>Customer Email</th>';
        echo '<th>Customer Phone</th>';
        echo '<th>Date From</th>';
        echo '<th>Date To</th>';
        echo '<th>Status</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($requests)) {
            echo '<tr><td colspan="11">No requests found.</td></tr>';
        } else {
            foreach ($requests as $request) {
                $edit_url = add_query_arg(
                    array_merge(
                        array(
                            'page' => 'toptour',
                            'view' => 'edit',
                            'request_id' => (int) $request->id,
                        ),
                        $list_context
                    ),
                    admin_url('admin.php')
                );

                $delete_url = wp_nonce_url(
                    add_query_arg(
                        array_merge(
                            array(
                                'action' => 'toptour_request_delete',
                                'request_id' => (int) $request->id,
                            ),
                            $list_context
                        ),
                        admin_url('admin-post.php')
                    ),
                    self::ADMIN_DELETE_NONCE_ACTION . '_' . (int) $request->id
                );

                echo '<tr>';
                echo '<td>' . esc_html((string) $request->id) . '</td>';
                echo '<td>' . esc_html((string) $request->created_at) . '</td>';
                echo '<td>' . esc_html((string) $request->offer_id) . '</td>';
                echo '<td>' . esc_html((string) $request->manager_user_id) . '</td>';
                echo '<td>' . esc_html((string) $request->customer_name) . '</td>';
                echo '<td>' . esc_html((string) $request->customer_email) . '</td>';
                echo '<td>' . esc_html((string) $request->customer_phone) . '</td>';
                echo '<td>' . esc_html((string) $request->date_from) . '</td>';
                echo '<td>' . esc_html((string) $request->date_to) . '</td>';
                echo '<td>' . esc_html((string) $request->status) . '</td>';
                echo '<td><a href="' . esc_url($edit_url) . '">Edit</a> | <a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js('Are you sure you want to delete this request?') . '\');">Delete</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        if ($total_pages > 1) {
            $pagination_links = paginate_links(
                array(
                    'base' => add_query_arg('paged', '%#%', admin_url('admin.php?' . http_build_query($base_args))),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                    'type' => 'array',
                )
            );

            if (is_array($pagination_links) && ! empty($pagination_links)) {
                echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
                foreach ($pagination_links as $link) {
                    echo wp_kses_post($link) . ' ';
                }
                echo '</span></div></div>';
            }
        }

        echo '</div>';
    }

    /**
     * Render admin edit form for one request.
     *
     * @param int $request_id Request ID.
     */
    private function render_admin_request_edit_form($request_id, $list_context = array())
    {
        $request = $this->get_admin_request((int) $request_id);

        if (! $request) {
            echo '<p>Request not found.</p>';
            return;
        }

        $back_url = add_query_arg(array_merge(array('page' => 'toptour'), $list_context), admin_url('admin.php'));

        echo '<p><a href="' . esc_url($back_url) . '">Back to requests</a></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="toptour_request_update" />';
        echo '<input type="hidden" name="request_id" value="' . esc_attr((string) $request->id) . '" />';
        if (isset($list_context['paged'])) {
            echo '<input type="hidden" name="paged" value="' . esc_attr((string) $list_context['paged']) . '" />';
        }
        if (isset($list_context['s'])) {
            echo '<input type="hidden" name="s" value="' . esc_attr((string) $list_context['s']) . '" />';
        }
        if (isset($list_context['status'])) {
            echo '<input type="hidden" name="list_status" value="' . esc_attr((string) $list_context['status']) . '" />';
        }
        wp_nonce_field(self::ADMIN_UPDATE_NONCE_ACTION . '_' . (int) $request->id);

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th><label for="customer_name">Customer Name</label></th><td><input type="text" name="customer_name" id="customer_name" value="' . esc_attr((string) $request->customer_name) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="customer_email">Customer Email</label></th><td><input type="email" name="customer_email" id="customer_email" value="' . esc_attr((string) $request->customer_email) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="customer_phone">Customer Phone</label></th><td><input type="text" name="customer_phone" id="customer_phone" value="' . esc_attr((string) $request->customer_phone) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="date_from">Date From</label></th><td><input type="date" name="date_from" id="date_from" value="' . esc_attr((string) $request->date_from) . '" /></td></tr>';
        echo '<tr><th><label for="date_to">Date To</label></th><td><input type="date" name="date_to" id="date_to" value="' . esc_attr((string) $request->date_to) . '" /></td></tr>';
        echo '<tr><th><label for="adults">Adults</label></th><td><input type="number" min="0" name="adults" id="adults" value="' . esc_attr((string) $request->adults) . '" /></td></tr>';
        echo '<tr><th><label for="children">Children</label></th><td><input type="number" min="0" name="children" id="children" value="' . esc_attr((string) $request->children) . '" /></td></tr>';
        echo '<tr><th><label for="note">Note</label></th><td><textarea name="note" id="note" rows="6" class="large-text">' . esc_textarea((string) $request->note) . '</textarea></td></tr>';

        echo '<tr><th><label for="status">Status</label></th><td>';
        echo '<select name="status" id="status">';
        foreach ($this->get_allowed_statuses() as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . selected((string) $request->status, $status, false) . '>' . esc_html($status) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '</table>';
        submit_button('Save Request');
        echo '</form>';
    }

    /**
     * Handle secure admin update action.
     */
    public function handle_admin_request_update()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $list_context = $this->get_admin_list_context_from_post();

        $request_id = isset($_POST['request_id']) ? absint(wp_unslash($_POST['request_id'])) : 0;
        if ($request_id <= 0) {
            wp_die('Invalid request ID.');
        }

        check_admin_referer(self::ADMIN_UPDATE_NONCE_ACTION . '_' . $request_id);

        $existing_request = $this->get_admin_request($request_id);
        $old_status = is_object($existing_request) && isset($existing_request->status) ? sanitize_text_field((string) $existing_request->status) : '';

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
        $adults = isset($_POST['adults']) ? absint(wp_unslash($_POST['adults'])) : 0;
        $children = isset($_POST['children']) ? absint(wp_unslash($_POST['children'])) : 0;
        $note_input = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        $existing_note = is_object($existing_request) && isset($existing_request->note) ? sanitize_textarea_field((string) $existing_request->note) : '';
        $note_to_save = $existing_note;
        $trimmed_note_input = trim($note_input);

        if ($trimmed_note_input !== '') {
            if (trim($existing_note) !== '' && trim($existing_note) === $trimmed_note_input) {
                $note_to_save = $existing_note;
            } else {
                $new_note_message = $note_input;

                if ($existing_note !== '' && strpos($note_input, $existing_note) === 0) {
                    $new_note_message = trim(substr($note_input, strlen($existing_note)));
                }

                $note_to_save = $this->append_note_log($existing_note, $new_note_message, $this->get_backend_note_author_label());
            }
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'new';

        if (! in_array($status, $this->get_allowed_statuses(), true)) {
            $status = 'new';
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->get_table_name(),
            array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'date_from' => $date_from !== '' ? $date_from : null,
                'date_to' => $date_to !== '' ? $date_to : null,
                'adults' => $adults,
                'children' => $children,
                'persons_total' => $adults + $children,
                'note' => $note_to_save,
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $request_id),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated !== false) {
            if ($customer_email !== '' && class_exists('Toptour_Module_Customers')) {
                $customers_module = new Toptour_Module_Customers();
                $customers_module->upsert_customer(
                    array(
                        'name' => $customer_name,
                        'email' => $customer_email,
                        'phone' => $customer_phone,
                    )
                );
            }

            $request_data = array(
                'offer_id' => is_object($existing_request) && isset($existing_request->offer_id) ? (int) $existing_request->offer_id : 0,
                'manager_user_id' => is_object($existing_request) && isset($existing_request->manager_user_id) ? (int) $existing_request->manager_user_id : 0,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'date_from' => $date_from !== '' ? $date_from : null,
                'date_to' => $date_to !== '' ? $date_to : null,
                'adults' => $adults,
                'children' => $children,
                'note' => $note_to_save,
                'status' => $status,
            );

            $this->maybe_send_customer_status_email($request_id, $old_status, $status, $request_data);
        }

        wp_safe_redirect(add_query_arg(array_merge(array('page' => 'toptour', 'updated' => '1'), $list_context), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle secure admin delete action.
     */
    public function handle_admin_request_delete()
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $list_context = $this->get_admin_list_context_from_get();

        $request_id = isset($_GET['request_id']) ? absint(wp_unslash($_GET['request_id'])) : 0;
        if ($request_id <= 0) {
            wp_die('Invalid request ID.');
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, self::ADMIN_DELETE_NONCE_ACTION . '_' . $request_id)) {
            wp_die('Invalid delete nonce.');
        }

        global $wpdb;
        $wpdb->delete($this->get_table_name(), array('id' => $request_id), array('%d'));

        wp_safe_redirect(add_query_arg(array_merge(array('page' => 'toptour', 'deleted' => '1'), $list_context), admin_url('admin.php')));
        exit;
    }

    /**
     * Get request rows for admin listing.
     *
     * @return array<int, object>
     */
    private function get_admin_requests()
    {
        global $wpdb;

        $table = $this->get_table_name();
        $sql = "SELECT id, created_at, offer_id, manager_user_id, customer_name, customer_email, customer_phone, date_from, date_to, status FROM {$table} ORDER BY created_at DESC";

        $rows = $wpdb->get_results($sql);

        return is_array($rows) ? $rows : array();
    }

    /**
     * Get paginated request rows for admin listing with filters.
     *
     * @param int    $page Current page.
     * @param int    $per_page Items per page.
     * @param string $search Search term.
     * @param string $status_filter Status filter.
     * @return array<string, mixed>
     */
    private function get_admin_requests_listing($page, $per_page, $search = '', $status_filter = '')
    {
        global $wpdb;

        $table = $this->get_table_name();
        $page = max(1, (int) $page);
        $per_page = max(1, (int) $per_page);
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field((string) $search);
        $status_filter = sanitize_text_field((string) $status_filter);

        $where_sql = '';
        $where_clauses = array();
        $where_params = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR request_type LIKE %s OR note LIKE %s OR CAST(offer_id AS CHAR) LIKE %s)';
            $where_params = array_merge($where_params, array($like, $like, $like, $like, $like, $like));
        }

        if ($status_filter !== '' && in_array($status_filter, $this->get_allowed_statuses(), true)) {
            $where_clauses[] = 'status = %s';
            $where_params[] = $status_filter;
        }

        if (! empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }

        $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
        if (! empty($where_params)) {
            $count_sql = $wpdb->prepare($count_sql, $where_params);
        }
        $total = (int) $wpdb->get_var($count_sql);

        $items_sql = "SELECT id, created_at, offer_id, manager_user_id, customer_name, customer_email, customer_phone, date_from, date_to, status FROM {$table}{$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $items_params = array_merge($where_params, array($per_page, $offset));
        $prepared_items_sql = $wpdb->prepare($items_sql, $items_params);
        $items = $wpdb->get_results($prepared_items_sql);

        return array(
            'items' => is_array($items) ? $items : array(),
            'total' => $total,
        );
    }

    /**
     * Return sanitized admin list context from GET.
     *
     * @return array<string, string|int>
     */
    private function get_admin_list_context_from_get()
    {
        return $this->sanitize_admin_list_context($_GET, true);
    }

    /**
     * Return sanitized admin list context from POST.
     *
     * @return array<string, string|int>
     */
    private function get_admin_list_context_from_post()
    {
        return $this->sanitize_admin_list_context($_POST, false);
    }

    /**
     * Sanitize and whitelist admin requests list context.
     *
     * @param array<mixed> $raw Raw input.
     * @param bool         $is_get True for GET source.
     * @return array<string, string|int>
     */
    private function sanitize_admin_list_context($raw, $is_get)
    {
        $context = array();

        if (isset($raw['paged'])) {
            $paged_raw = wp_unslash($raw['paged']);
            if (is_scalar($paged_raw)) {
                $paged = absint((string) $paged_raw);
                if ($paged >= 1) {
                    $context['paged'] = $paged;
                }
            }
        }

        if (isset($raw['s'])) {
            $search_raw = wp_unslash($raw['s']);
            if (is_scalar($search_raw)) {
                $search = sanitize_text_field((string) $search_raw);
                if ($search !== '') {
                    $context['s'] = $search;
                }
            }
        }

        $status_key = $is_get ? 'status' : 'list_status';
        if (isset($raw[$status_key])) {
            $status_raw = wp_unslash($raw[$status_key]);
            if (is_scalar($status_raw)) {
                $status = sanitize_text_field((string) $status_raw);
                if ($status !== '' && in_array($status, $this->get_allowed_statuses(), true)) {
                    $context['status'] = $status;
                }
            }
        }

        return $context;
    }

    /**
     * Get single request row for admin edit.
     *
     * @param int $request_id Request ID.
     * @return object|null
     */
    private function get_admin_request($request_id)
    {
        global $wpdb;

        $table = $this->get_table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $request_id);
        $row = $wpdb->get_row($sql);

        return is_object($row) ? $row : null;
    }

    /**
     * Allowed request statuses.
     *
     * @return array<int, string>
     */
    private function get_allowed_statuses()
    {
        return array('new', 'approved', 'rejected', 'reserved');
    }

    /**
     * Get single request row by ID.
     *
     * @param int $request_id Request ID.
     * @return object|null
     */
    public function get_request_by_id($request_id)
    {
        global $wpdb;

        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return null;
        }

        $table = $this->get_table_name();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id);
        $row = $wpdb->get_row($sql);

        return is_object($row) ? $row : null;
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
        $required_label_suffix = ' *';
        $form_started_at = current_time('timestamp');

        echo '<div class="toptour-inquiry-form">';
        echo '<p><button type="button" id="toptour-inquiry-open">' . esc_html($toggle_label) . '</button></p>';
        echo '<dialog id="toptour-inquiry-dialog">';
        echo '<h3>' . esc_html(Toptour_Core_I18n::t('form.inquiry_heading', 'Check availability')) . '</h3>';
        echo '<form method="post">';

        echo '<p><label for="toptour_customer_name">' . esc_html(Toptour_Core_I18n::t('form.customer_name', 'Name') . $required_label_suffix) . '</label><br />';
        echo '<input type="text" id="toptour_customer_name" name="customer_name" value="' . esc_attr($customer_name) . '" required /></p>';

        echo '<p><label for="toptour_customer_email">' . esc_html(Toptour_Core_I18n::t('form.customer_email', 'Email') . $required_label_suffix) . '</label><br />';
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

        // Honeypot field for basic bot protection.
        echo '<p style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">';
        echo '<label for="toptour_website">Website</label>';
        echo '<input type="text" id="toptour_website" name="toptour_website" value="" tabindex="-1" autocomplete="off" />';
        echo '</p>';

        echo '<input type="hidden" name="offer_id" value="' . esc_attr((string) $offer_id) . '" />';
        echo '<input type="hidden" name="toptour_action" value="submit_inquiry" />';
        echo '<input type="hidden" name="toptour_form_started_at" value="' . esc_attr((string) $form_started_at) . '" />';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        echo '<p><button type="submit">' . esc_html(Toptour_Core_I18n::t('form.submit_inquiry', 'Send inquiry')) . '</button></p>';
        echo '</form>';
        echo '<p><button type="button" id="toptour-inquiry-close">' . esc_html(Toptour_Core_I18n::t('form.close', 'Close')) . '</button></p>';
        echo '</dialog>';
        echo '</div>';
        ?>
        <script>
        (function () {
            var openButton = document.getElementById('toptour-inquiry-open');
            var closeButton = document.getElementById('toptour-inquiry-close');
            var dialog = document.getElementById('toptour-inquiry-dialog');
            var dateFromInput = document.getElementById('toptour_date_from');
            var dateToInput = document.getElementById('toptour_date_to');
            var autoOpen = <?php echo $should_show_form ? 'true' : 'false'; ?>;

            function openDialog() {
                if (!dialog) {
                    return;
                }

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                    return;
                }

                dialog.setAttribute('open', 'open');
            }

            function closeDialog() {
                if (!dialog) {
                    return;
                }

                if (typeof dialog.close === 'function') {
                    dialog.close();
                    return;
                }

                dialog.removeAttribute('open');
            }

            if (openButton && dialog) {
                openButton.addEventListener('click', function () {
                    openDialog();
                });
            }

            if (closeButton && dialog) {
                closeButton.addEventListener('click', function () {
                    closeDialog();
                });
            }

            if (autoOpen && dialog) {
                openDialog();
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

        $honeypot = isset($_POST['toptour_website']) ? trim((string) wp_unslash($_POST['toptour_website'])) : '';
        if ($honeypot !== '') {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Spam check failed.',
            );
            return;
        }

        $started_at_raw = isset($_POST['toptour_form_started_at']) ? absint(wp_unslash($_POST['toptour_form_started_at'])) : 0;
        $current_ts = (int) current_time('timestamp');
        $elapsed = $started_at_raw > 0 ? ($current_ts - $started_at_raw) : -1;

        if ($elapsed < 3 || $elapsed > DAY_IN_SECONDS) {
            $this->inquiry_result = array(
                'success' => false,
                'error' => true,
                'message' => 'Spam check failed.',
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
        $note_log = $this->append_note_log('', $note, $this->get_frontend_note_author_label());

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
            'note' => $note_log,
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

        global $wpdb;
        $request_id = (int) $wpdb->insert_id;

        if ($request_id > 0) {
            if (class_exists('Toptour_Module_Customers')) {
                $customers_module = new Toptour_Module_Customers();
                $customers_module->upsert_customer(
                    array(
                        'name' => $customer_name,
                        'email' => $customer_email,
                        'phone' => $customer_phone,
                    )
                );
            }

            $data['id'] = $request_id;
            $this->send_new_inquiry_notifications($request_id, $data);
            $this->send_customer_autoresponder($request_id, $data);
        }

        $this->inquiry_result = array(
            'success' => true,
            'error' => false,
            'message' => 'Inquiry request submitted successfully.',
        );
    }

    /**
     * Send notifications for newly created inquiry.
     *
     * @param int                 $request_id Request ID.
     * @param array<string, mixed> $request_data Request data.
     */
    public function send_new_inquiry_notifications($request_id, $request_data)
    {
        $request_id = (int) $request_id;

        if ($request_id <= 0) {
            return;
        }

        $admin_email = sanitize_email((string) get_option('admin_email', ''));
        $manager_email = '';

        $manager_user_id = isset($request_data['manager_user_id']) ? (int) $request_data['manager_user_id'] : 0;
        if ($manager_user_id > 0) {
            $manager = get_user_by('id', $manager_user_id);

            if ($manager instanceof WP_User) {
                $manager_email = sanitize_email((string) $manager->user_email);
            }
        }

        $recipients_map = array();

        if ($manager_email !== '') {
            $recipients_map[strtolower($manager_email)] = $manager_email;
        }

        if ($admin_email !== '') {
            $recipients_map[strtolower($admin_email)] = $admin_email;
        }

        $recipients = array_values($recipients_map);

        if (empty($recipients)) {
            return;
        }

        $subject = $this->get_new_inquiry_email_subject($request_data);
        $message = $this->get_new_inquiry_email_message($request_id, $request_data);

        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $message);
        }
    }

    /**
     * Send plain-text autoresponder to customer after successful inquiry creation.
     *
     * @param int                  $request_id Request ID.
     * @param array<string, mixed> $request_data Request data.
     */
    public function send_customer_autoresponder($request_id, $request_data)
    {
        $request_id = (int) $request_id;

        if ($request_id <= 0) {
            return;
        }

        $customer_email = isset($request_data['customer_email']) ? sanitize_email((string) $request_data['customer_email']) : '';
        if ($customer_email === '' || ! is_email($customer_email)) {
            return;
        }

        $offer_id = isset($request_data['offer_id']) ? (int) $request_data['offer_id'] : 0;
        $offer_title = $offer_id > 0 ? sanitize_text_field((string) get_the_title($offer_id)) : '';
        if ($offer_title === '' && $offer_id > 0) {
            $offer_title = '#' . $offer_id;
        }

        $date_from = isset($request_data['date_from']) && $request_data['date_from'] !== null ? sanitize_text_field((string) $request_data['date_from']) : '';
        $date_to = isset($request_data['date_to']) && $request_data['date_to'] !== null ? sanitize_text_field((string) $request_data['date_to']) : '';
        $adults = isset($request_data['adults']) ? absint($request_data['adults']) : 0;
        $children = isset($request_data['children']) ? absint($request_data['children']) : 0;
        $note = isset($request_data['note']) ? trim(sanitize_textarea_field((string) $request_data['note'])) : '';
        $persons_total = $adults + $children;

        $manager_name = '';
        $manager_email = '';
        $manager_phone = '';
        $manager_user_id = isset($request_data['manager_user_id']) ? (int) $request_data['manager_user_id'] : 0;

        if ($manager_user_id > 0 && class_exists('Toptour_Module_Managers')) {
            $managers_module = new Toptour_Module_Managers();
            $manager_summary = $managers_module->get_manager_summary($manager_user_id);

            if (is_array($manager_summary)) {
                $manager_name = sanitize_text_field((string) ($manager_summary['name'] ?? ''));
                $manager_email = sanitize_email((string) ($manager_summary['email'] ?? ''));
                $manager_phone = sanitize_text_field((string) ($manager_summary['phone'] ?? ''));
            }
        }

        $subject = Toptour_Core_I18n::t('mail.customer_autoresponder_subject', 'Your inquiry has been received');
        $summary_heading = Toptour_Core_I18n::t('mail.customer_autoresponder_summary', 'Inquiry summary');
        $contact_heading = Toptour_Core_I18n::t('mail.customer_autoresponder_contact', 'Your contact person');

        $lines = array(
            Toptour_Core_I18n::t('mail.customer_autoresponder_greeting', 'Hello,'),
            '',
            Toptour_Core_I18n::t('mail.customer_autoresponder_message', 'Thank you for your inquiry. We will contact you shortly.'),
            '',
            $summary_heading . ':',
            Toptour_Core_I18n::t('mail.offer', 'Offer') . ': ' . ($offer_title !== '' ? $offer_title : '-'),
            Toptour_Core_I18n::t('mail.date_from', 'Date from') . ': ' . ($date_from !== '' ? $date_from : '-'),
            Toptour_Core_I18n::t('mail.date_to', 'Date to') . ': ' . ($date_to !== '' ? $date_to : '-'),
            Toptour_Core_I18n::t('label.persons', 'persons') . ': ' . $persons_total . ' (' . $adults . ' + ' . $children . ')',
        );

        if ($note !== '') {
            $lines[] = Toptour_Core_I18n::t('mail.note', 'Note') . ': ' . $note;
        }

        if ($manager_name !== '' || $manager_email !== '' || $manager_phone !== '') {
            $lines[] = '';
            $lines[] = $contact_heading . ':';

            if ($manager_name !== '') {
                $lines[] = 'Name: ' . $manager_name;
            }

            if ($manager_email !== '' && is_email($manager_email)) {
                $lines[] = Toptour_Core_I18n::t('manager.email', 'Email') . ': ' . $manager_email;
            }

            if ($manager_phone !== '') {
                $lines[] = Toptour_Core_I18n::t('manager.phone', 'Phone') . ': ' . $manager_phone;
            }
        }

        $lines[] = '';
        $lines[] = Toptour_Core_I18n::t('mail.customer_autoresponder_closing', 'Best regards');

        wp_mail($customer_email, $subject, implode("\n", $lines));
    }

    /**
     * Build email subject for newly created inquiry.
     *
     * @param array<string, mixed> $request_data Request data.
     * @return string
     */
    private function get_new_inquiry_email_subject($request_data)
    {
        $prefix = Toptour_Core_I18n::t('mail.new_inquiry_subject', 'New inquiry for');
        $offer_id = isset($request_data['offer_id']) ? (int) $request_data['offer_id'] : 0;

        if ($offer_id > 0) {
            $offer_title = trim((string) get_the_title($offer_id));

            if ($offer_title !== '') {
                return $prefix . ' ' . $offer_title;
            }
        }

        $request_id = isset($request_data['id']) ? (int) $request_data['id'] : 0;

        if ($request_id > 0) {
            return $prefix . ' #' . $request_id;
        }

        return $prefix;
    }

    /**
     * Build plain-text email message body for newly created inquiry.
     *
     * @param int                  $request_id Request ID.
     * @param array<string, mixed> $request_data Request data.
     * @return string
     */
    private function get_new_inquiry_email_message($request_id, $request_data)
    {
        $request_id = (int) $request_id;
        $offer_id = isset($request_data['offer_id']) ? (int) $request_data['offer_id'] : 0;
        $offer_title = $offer_id > 0 ? trim((string) get_the_title($offer_id)) : '';

        $offer_label = $offer_title !== '' ? $offer_title : ($offer_id > 0 ? '#' . $offer_id : '-');
        $admin_edit_url = $this->get_request_admin_edit_url($request_id);

        $lines = array(
            Toptour_Core_I18n::t('mail.request_id', 'Request ID') . ': ' . $request_id,
            Toptour_Core_I18n::t('mail.offer', 'Offer') . ': ' . $offer_label,
            Toptour_Core_I18n::t('mail.customer_name', 'Customer name') . ': ' . (isset($request_data['customer_name']) ? (string) $request_data['customer_name'] : ''),
            Toptour_Core_I18n::t('mail.customer_email', 'Customer email') . ': ' . (isset($request_data['customer_email']) ? (string) $request_data['customer_email'] : ''),
            Toptour_Core_I18n::t('mail.customer_phone', 'Customer phone') . ': ' . (isset($request_data['customer_phone']) ? (string) $request_data['customer_phone'] : ''),
            Toptour_Core_I18n::t('mail.date_from', 'Date from') . ': ' . (isset($request_data['date_from']) && $request_data['date_from'] !== null ? (string) $request_data['date_from'] : ''),
            Toptour_Core_I18n::t('mail.date_to', 'Date to') . ': ' . (isset($request_data['date_to']) && $request_data['date_to'] !== null ? (string) $request_data['date_to'] : ''),
            Toptour_Core_I18n::t('mail.adults', 'Adults') . ': ' . (isset($request_data['adults']) ? (string) $request_data['adults'] : '0'),
            Toptour_Core_I18n::t('mail.children', 'Children') . ': ' . (isset($request_data['children']) ? (string) $request_data['children'] : '0'),
            Toptour_Core_I18n::t('mail.note', 'Note') . ': ' . (isset($request_data['note']) ? (string) $request_data['note'] : ''),
            Toptour_Core_I18n::t('mail.status', 'Status') . ': ' . (isset($request_data['status']) ? (string) $request_data['status'] : 'new'),
            Toptour_Core_I18n::t('mail.admin_link', 'Admin link') . ': ' . $admin_edit_url,
        );

        return implode("\n", $lines);
    }

    /**
     * Build admin edit URL for request detail page.
     *
     * @param int $request_id Request ID.
     * @return string
     */
    private function get_request_admin_edit_url($request_id)
    {
        $url = add_query_arg(
            array(
                'page' => 'toptour',
                'view' => 'edit',
                'request_id' => (int) $request_id,
            ),
            admin_url('admin.php')
        );

        return (string) $url;
    }

    /**
     * Send plain-text customer status email after admin update when eligible.
     *
     * @param int                  $request_id Request ID.
     * @param string               $old_status Previous status.
     * @param string               $new_status New status.
     * @param array<string, mixed> $request_data Request data.
     */
    private function maybe_send_customer_status_email($request_id, $old_status, $new_status, $request_data)
    {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        $old_status = sanitize_text_field((string) $old_status);
        $new_status = sanitize_text_field((string) $new_status);

        if ($old_status === $new_status) {
            return;
        }

        if (! in_array($new_status, array('approved', 'rejected'), true)) {
            return;
        }

        $customer_email = isset($request_data['customer_email']) ? sanitize_email((string) $request_data['customer_email']) : '';
        if ($customer_email === '' || ! is_email($customer_email)) {
            return;
        }

        $subject = $this->get_customer_status_email_subject($new_status, $request_data);
        $message = $this->get_customer_status_email_message($request_id, $new_status, $request_data);

        if ($subject === '' || $message === '') {
            return;
        }

        wp_mail($customer_email, $subject, $message);
    }

    /**
     * Ensure a reservation token exists for request and return it.
     *
     * @param int $request_id Request ID.
     * @return string
     */
    private function ensure_reservation_token($request_id)
    {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return '';
        }

        $existing_token = sanitize_text_field((string) $this->get_request_meta($request_id, 'reservation_token', ''));
        if ($existing_token !== '') {
            return $existing_token;
        }

        $token = wp_generate_password(48, false, false);
        if ($token === '') {
            return '';
        }

        $token_saved = $this->upsert_request_meta($request_id, 'reservation_token', $token);
        $created_at_saved = $this->upsert_request_meta($request_id, 'reservation_token_created_at', current_time('mysql'));

        if (! $token_saved || ! $created_at_saved) {
            return '';
        }

        return $token;
    }

    /**
     * Get request meta value from custom request_meta table.
     *
     * @param int    $request_id Request ID.
     * @param string $meta_key Meta key.
     * @param string $default Default value.
     * @return string
     */
    private function get_request_meta($request_id, $meta_key, $default = '')
    {
        global $wpdb;

        $request_id = (int) $request_id;
        $meta_key = sanitize_text_field((string) $meta_key);

        if ($request_id <= 0 || $meta_key === '') {
            return (string) $default;
        }

        $table = $wpdb->prefix . 'toptour_request_meta';
        $sql = $wpdb->prepare(
            "SELECT meta_value FROM {$table} WHERE request_id = %d AND meta_key = %s ORDER BY id DESC LIMIT 1",
            $request_id,
            $meta_key
        );
        $meta_value = $wpdb->get_var($sql);

        if ($meta_value === null) {
            return (string) $default;
        }

        return (string) $meta_value;
    }

    /**
     * Insert or update one request meta key in custom request_meta table.
     *
     * @param int    $request_id Request ID.
     * @param string $meta_key Meta key.
     * @param string $meta_value Meta value.
     * @return bool
     */
    private function upsert_request_meta($request_id, $meta_key, $meta_value)
    {
        global $wpdb;

        $request_id = (int) $request_id;
        $meta_key = sanitize_text_field((string) $meta_key);
        $meta_value = sanitize_text_field((string) $meta_value);

        if ($request_id <= 0 || $meta_key === '') {
            return false;
        }

        $table = $wpdb->prefix . 'toptour_request_meta';
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE request_id = %d AND meta_key = %s ORDER BY id DESC LIMIT 1",
                $request_id,
                $meta_key
            )
        );

        if ($existing_id > 0) {
            $updated = $wpdb->update(
                $table,
                array('meta_value' => $meta_value),
                array('id' => $existing_id),
                array('%s'),
                array('%d')
            );

            return $updated !== false;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'request_id' => $request_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
            ),
            array('%d', '%s', '%s')
        );

        return $inserted !== false;
    }

    /**
     * Build customer status-change email subject.
     *
     * @param string               $new_status New status.
     * @param array<string, mixed> $request_data Request data.
     * @return string
     */
    private function get_customer_status_email_subject($new_status, $request_data)
    {
        if ($new_status === 'approved') {
            return Toptour_Core_I18n::t('mail.status_approved_subject', 'Your availability request was approved');
        }

        if ($new_status === 'rejected') {
            return Toptour_Core_I18n::t('mail.status_rejected_subject', 'Your availability request could not be approved');
        }

        return '';
    }

    /**
     * Build plain-text customer status-change email body.
     *
     * @param int                  $request_id Request ID.
     * @param string               $new_status New status.
     * @param array<string, mixed> $request_data Request data.
     * @return string
     */
    private function get_customer_status_email_message($request_id, $new_status, $request_data)
    {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return '';
        }

        $offer_id = isset($request_data['offer_id']) ? (int) $request_data['offer_id'] : 0;
        $offer_title = $offer_id > 0 ? sanitize_text_field((string) get_the_title($offer_id)) : '';
        if ($offer_title === '' && $offer_id > 0) {
            $offer_title = '#' . $offer_id;
        }

        $date_from = isset($request_data['date_from']) && $request_data['date_from'] !== null ? sanitize_text_field((string) $request_data['date_from']) : '';
        $date_to = isset($request_data['date_to']) && $request_data['date_to'] !== null ? sanitize_text_field((string) $request_data['date_to']) : '';
        $adults = isset($request_data['adults']) ? absint($request_data['adults']) : 0;
        $children = isset($request_data['children']) ? absint($request_data['children']) : 0;
        $note = isset($request_data['note']) ? trim(sanitize_textarea_field((string) $request_data['note'])) : '';

        $manager_name = '';
        $manager_email = '';
        $manager_phone = '';
        $manager_user_id = isset($request_data['manager_user_id']) ? (int) $request_data['manager_user_id'] : 0;

        if ($manager_user_id > 0 && class_exists('Toptour_Module_Managers')) {
            $managers_module = new Toptour_Module_Managers();
            $manager_summary = $managers_module->get_manager_summary($manager_user_id);

            if (is_array($manager_summary)) {
                $manager_name = sanitize_text_field((string) ($manager_summary['name'] ?? ''));
                $manager_email = sanitize_email((string) ($manager_summary['email'] ?? ''));
                $manager_phone = sanitize_text_field((string) ($manager_summary['phone'] ?? ''));
            }
        }

        $lines = array();

        if ($new_status === 'approved') {
            $token = $this->ensure_reservation_token($request_id);
            if ($token === '') {
                return '';
            }

            $reservation_link = $this->get_reservation_link($request_id, $token);
            $lines[] = Toptour_Core_I18n::t('mail.status_approved_message', 'Your requested availability has been confirmed. You can continue with reservation using the link below.');
            $lines[] = '';
            $lines[] = Toptour_Core_I18n::t('mail.reservation_link', 'Reservation link') . ': ' . $reservation_link;
        } elseif ($new_status === 'rejected') {
            $lines[] = Toptour_Core_I18n::t('mail.status_rejected_message', 'Unfortunately, your requested availability could not be confirmed.');
        } else {
            return '';
        }

        $lines[] = '';
        $lines[] = Toptour_Core_I18n::t('mail.request_summary', 'Request summary') . ':';
        $lines[] = Toptour_Core_I18n::t('mail.request_id', 'Request ID') . ': ' . $request_id;
        $lines[] = Toptour_Core_I18n::t('mail.offer', 'Offer') . ': ' . ($offer_title !== '' ? $offer_title : '-');
        $lines[] = Toptour_Core_I18n::t('mail.date_from', 'Date from') . ': ' . ($date_from !== '' ? $date_from : '-');
        $lines[] = Toptour_Core_I18n::t('mail.date_to', 'Date to') . ': ' . ($date_to !== '' ? $date_to : '-');
        $lines[] = Toptour_Core_I18n::t('mail.adults', 'Adults') . ': ' . $adults;
        $lines[] = Toptour_Core_I18n::t('mail.children', 'Children') . ': ' . $children;

        if ($note !== '') {
            $lines[] = Toptour_Core_I18n::t('mail.note', 'Note') . ': ' . $note;
        }

        if ($manager_name !== '' || ($manager_email !== '' && is_email($manager_email)) || $manager_phone !== '') {
            $lines[] = '';
            $lines[] = Toptour_Core_I18n::t('mail.manager_contact', 'Contact person') . ':';

            if ($manager_name !== '') {
                $lines[] = Toptour_Core_I18n::t('form.customer_name', 'Name') . ': ' . $manager_name;
            }

            if ($manager_email !== '' && is_email($manager_email)) {
                $lines[] = Toptour_Core_I18n::t('manager.email', 'Email') . ': ' . $manager_email;
            }

            if ($manager_phone !== '') {
                $lines[] = Toptour_Core_I18n::t('manager.phone', 'Phone') . ': ' . $manager_phone;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build reservation confirmation URL for approved requests.
     *
     * @param int    $request_id Request ID.
     * @param string $token Reservation token.
     * @return string
     */
    private function get_reservation_link($request_id, $token)
    {
        return (string) add_query_arg(
            array(
                'request' => (int) $request_id,
                'token' => sanitize_text_field((string) $token),
            ),
            home_url('/reservation-confirmation/')
        );
    }

    /**
     * Format one communication log note entry.
     *
     * @param string $author_label Author label.
     * @param string $message Note message.
     * @param string $timestamp MySQL timestamp.
     * @return string
     */
    private function format_note_log_entry($author_label, $message, $timestamp = '')
    {
        $author_label = trim(sanitize_text_field((string) $author_label));
        $message = trim(sanitize_textarea_field((string) $message));
        $timestamp = $timestamp !== '' ? sanitize_text_field((string) $timestamp) : current_time('mysql');

        if ($author_label === '' || $message === '') {
            return '';
        }

        $single_line_message = preg_replace('/\s+/', ' ', $message);

        return $author_label . ' | ' . $timestamp . ': ' . (string) $single_line_message;
    }

    /**
     * Append one formatted note entry to an existing note log.
     *
     * @param string $existing_note Existing note log.
     * @param string $new_message New note message.
     * @param string $author_label Author label.
     * @return string
     */
    private function append_note_log($existing_note, $new_message, $author_label)
    {
        $existing_note = trim(sanitize_textarea_field((string) $existing_note));
        $entry = $this->format_note_log_entry($author_label, $new_message);

        if ($entry === '') {
            return $existing_note;
        }

        if ($existing_note === '') {
            return $entry;
        }

        return $existing_note . "\n" . $entry;
    }

    /**
     * Resolve note author label for frontend inquiry submissions.
     *
     * @return string
     */
    private function get_frontend_note_author_label()
    {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();

            if ($current_user instanceof WP_User && $current_user->exists()) {
                $display_name = trim((string) $current_user->display_name);
                if ($display_name !== '') {
                    return $display_name;
                }

                $user_login = trim((string) $current_user->user_login);
                if ($user_login !== '') {
                    return $user_login;
                }
            }
        }

        return 'Customer';
    }

    /**
     * Resolve note author label for backend/admin updates.
     *
     * @return string
     */
    private function get_backend_note_author_label()
    {
        $current_user = wp_get_current_user();

        if ($current_user instanceof WP_User && $current_user->exists()) {
            $display_name = trim((string) $current_user->display_name);
            if ($display_name !== '') {
                return $display_name;
            }

            $user_login = trim((string) $current_user->user_login);
            if ($user_login !== '') {
                return $user_login;
            }
        }

        return 'Admin';
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
     * Validate reservation token against approved request.
     *
     * @param int    $request_id Request ID.
     * @param string $token Reservation token.
     * @return bool
     */
    public function validate_reservation_token($request_id, $token)
    {
        $request = $this->get_request_by_id((int) $request_id);
        if (! $request) {
            return false;
        }

        if (! isset($request->status) || (string) $request->status !== 'approved') {
            return false;
        }

        $provided_token = sanitize_text_field((string) $token);
        $stored_token = sanitize_text_field((string) $this->get_request_meta((int) $request_id, 'reservation_token', ''));

        if ($provided_token === '' || $stored_token === '') {
            return false;
        }

        return hash_equals($stored_token, $provided_token);
    }

    /**
     * Render reservation confirmation form/output on confirmation URL.
     *
     * @param string $content Current post content.
     * @return string
     */
    public function render_reservation_confirmation($content = '')
    {
        if (is_admin()) {
            return $content;
        }

        $is_confirmation_page = function_exists('is_page') && is_page('reservation-confirmation');
        $has_query_args = isset($_GET['request']) || isset($_GET['token']) || isset($_POST['request']) || isset($_POST['token']);

        if (! $is_confirmation_page && ! $has_query_args) {
            return $content;
        }

        $request_id = isset($_GET['request']) ? absint(wp_unslash($_GET['request'])) : (isset($_POST['request']) ? absint(wp_unslash($_POST['request'])) : 0);
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : (isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '');

        $invalid_message = '<p>' . esc_html(Toptour_Core_I18n::t('reservation.invalid_link', 'This reservation link is invalid or expired.')) . '</p>';

        if ($request_id <= 0 || $token === '') {
            return $invalid_message;
        }

        $request = $this->get_request_by_id($request_id);
        if (! $request) {
            return $invalid_message;
        }

        $stored_token = sanitize_text_field((string) $this->get_request_meta($request_id, 'reservation_token', ''));
        if ($stored_token === '' || ! hash_equals($stored_token, $token)) {
            return $invalid_message;
        }

        if (isset($request->status) && (string) $request->status === 'reserved') {
            return '<p>' . esc_html(Toptour_Core_I18n::t('reservation.already_reserved', 'This reservation has already been confirmed.')) . '</p>';
        }

        $result = $this->get_reservation_confirmation_result();
        if (is_array($result) && (bool) ($result['success'] ?? false) === true) {
            return '<p>' . esc_html((string) ($result['message'] ?? Toptour_Core_I18n::t('reservation.success', 'Your reservation has been confirmed successfully.'))) . '</p>';
        }

        if (! $this->validate_reservation_token($request_id, $token)) {
            return $invalid_message;
        }

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : (isset($request->customer_name) ? sanitize_text_field((string) $request->customer_name) : '');
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : (isset($request->customer_email) ? sanitize_email((string) $request->customer_email) : '');
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : (isset($request->customer_phone) ? sanitize_text_field((string) $request->customer_phone) : '');
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        $output = '';

        if (is_array($result) && (bool) ($result['error'] ?? false) === true && (string) ($result['message'] ?? '') !== '') {
            $output .= '<p>' . esc_html((string) $result['message']) . '</p>';
        }

        $output .= '<h3>' . esc_html(Toptour_Core_I18n::t('reservation.heading', 'Reservation confirmation')) . '</h3>';
        $output .= '<form method="post">';
        $output .= '<p><label for="toptour_reservation_customer_name">' . esc_html(Toptour_Core_I18n::t('reservation.customer_name', 'Name')) . '</label><br />';
        $output .= '<input type="text" id="toptour_reservation_customer_name" name="customer_name" value="' . esc_attr($customer_name) . '" required /></p>';

        $output .= '<p><label for="toptour_reservation_customer_email">' . esc_html(Toptour_Core_I18n::t('reservation.customer_email', 'Email')) . '</label><br />';
        $output .= '<input type="email" id="toptour_reservation_customer_email" name="customer_email" value="' . esc_attr($customer_email) . '" required /></p>';

        $output .= '<p><label for="toptour_reservation_customer_phone">' . esc_html(Toptour_Core_I18n::t('reservation.customer_phone', 'Phone')) . '</label><br />';
        $output .= '<input type="text" id="toptour_reservation_customer_phone" name="customer_phone" value="' . esc_attr($customer_phone) . '" /></p>';

        $output .= '<p><label for="toptour_reservation_note">' . esc_html(Toptour_Core_I18n::t('reservation.note', 'Note')) . '</label><br />';
        $output .= '<textarea id="toptour_reservation_note" name="note" rows="4">' . esc_textarea($note) . '</textarea></p>';

        $output .= '<input type="hidden" name="request" value="' . esc_attr((string) $request_id) . '" />';
        $output .= '<input type="hidden" name="token" value="' . esc_attr($token) . '" />';
        $output .= '<input type="hidden" name="toptour_action" value="confirm_reservation" />';
        $output .= wp_nonce_field(self::RESERVATION_CONFIRM_NONCE_ACTION, self::RESERVATION_CONFIRM_NONCE_NAME, true, false);
        $output .= '<p><button type="submit">' . esc_html(Toptour_Core_I18n::t('reservation.submit', 'Confirm reservation')) . '</button></p>';
        $output .= '</form>';

        return $output;
    }

    /**
     * Handle reservation confirmation submit from token-protected form.
     */
    public function handle_reservation_confirmation_submission()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = isset($_POST['toptour_action']) ? sanitize_text_field(wp_unslash($_POST['toptour_action'])) : '';
        if ($action !== 'confirm_reservation') {
            return;
        }

        $request_id = isset($_POST['request']) ? absint(wp_unslash($_POST['request'])) : 0;
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

        $invalid_message = Toptour_Core_I18n::t('reservation.invalid_link', 'This reservation link is invalid or expired.');

        if ($request_id <= 0 || $token === '' || ! isset($_POST[self::RESERVATION_CONFIRM_NONCE_NAME])) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => $invalid_message,
            );
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::RESERVATION_CONFIRM_NONCE_NAME]));
        if (! wp_verify_nonce($nonce, self::RESERVATION_CONFIRM_NONCE_ACTION)) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => $invalid_message,
            );
            return;
        }

        $request = $this->get_request_by_id($request_id);
        if (! $request) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => $invalid_message,
            );
            return;
        }

        $stored_token = sanitize_text_field((string) $this->get_request_meta($request_id, 'reservation_token', ''));
        if ($stored_token === '' || ! hash_equals($stored_token, $token)) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => $invalid_message,
            );
            return;
        }

        if (isset($request->status) && (string) $request->status === 'reserved') {
            $this->reservation_confirmation_result = array(
                'success' => true,
                'error' => false,
                'message' => Toptour_Core_I18n::t('reservation.already_reserved', 'This reservation has already been confirmed.'),
            );
            return;
        }

        if (! $this->validate_reservation_token($request_id, $token)) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => $invalid_message,
            );
            return;
        }

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $note_input = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if ($customer_name === '' || $customer_email === '' || ! is_email($customer_email)) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => Toptour_Core_I18n::t('form.error', 'Please check the form and try again.'),
            );
            return;
        }

        $existing_note = isset($request->note) ? sanitize_textarea_field((string) $request->note) : '';
        $note_to_save = $existing_note;

        if (trim($note_input) !== '') {
            $note_to_save = $this->append_note_log($existing_note, $note_input, $this->get_frontend_note_author_label());
        }

        global $wpdb;
        $updated = $wpdb->update(
            $this->get_table_name(),
            array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'note' => $note_to_save,
                'status' => 'reserved',
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $request_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            $this->reservation_confirmation_result = array(
                'success' => false,
                'error' => true,
                'message' => Toptour_Core_I18n::t('form.error', 'Please check the form and try again.'),
            );
            return;
        }

        $this->reservation_confirmation_result = array(
            'success' => true,
            'error' => false,
            'message' => Toptour_Core_I18n::t('reservation.success', 'Your reservation has been confirmed successfully.'),
        );
    }

    /**
     * Get latest reservation confirmation flow result.
     *
     * @return array<string, mixed>
     */
    private function get_reservation_confirmation_result()
    {
        return $this->reservation_confirmation_result;
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
