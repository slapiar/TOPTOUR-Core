<?php

if (! defined('ABSPATH')) {
    exit;
}

class Toptour_Module_Customers
{
    /**
     * @var string
     */
    private $table_suffix = 'toptour_customers';

    /**
     * @var string
     */
    private $admin_action_field = 'toptour_customer_action';

    /**
     * @var array<int, string>
     */
    private $allowed_statuses = array('lead', 'customer', 'inactive');

    /**
     * Initialize customers module.
     */
    public function init()
    {
        add_action('admin_menu', array($this, 'register_admin_menu'), 20);
    }

    /**
     * Register customers admin submenu.
     */
    public function register_admin_menu()
    {
        add_submenu_page(
            'toptour',
            'TopTour - Zákazníci',
            'Zákazníci',
            'manage_options',
            'toptour-customers',
            array($this, 'render_admin_customers_page')
        );
    }

    /**
     * Render customers overview admin page.
     */
    public function render_admin_customers_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_admin_customer_update_submission();
        }

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
        if ($action === 'delete') {
            $this->handle_admin_customer_delete_action();
        }

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'list';
        $customer_id = isset($_GET['customer_id']) ? absint(wp_unslash($_GET['customer_id'])) : 0;

        if ($view === 'edit' && $customer_id > 0) {
            $this->render_admin_customer_edit_screen($customer_id);
            return;
        }

        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        $page = max(1, $page);

        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if (! in_array($status_filter, $this->allowed_statuses, true)) {
            $status_filter = '';
        }

        $listing = $this->get_customers_listing($page, $per_page, $search, $status_filter);
        $customers = isset($listing['items']) && is_array($listing['items']) ? $listing['items'] : array();
        $total_items = isset($listing['total']) ? (int) $listing['total'] : 0;
        $total_pages = max(1, (int) ceil($total_items / $per_page));

        $base_args = array('page' => 'toptour-customers');
        if ($search !== '') {
            $base_args['s'] = $search;
        }
        if ($status_filter !== '') {
            $base_args['status'] = $status_filter;
        }

        $list_context = array(
            'paged' => $page,
        );
        if ($search !== '') {
            $list_context['s'] = $search;
        }
        if ($status_filter !== '') {
            $list_context['status'] = $status_filter;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html('TopTour - Zákazníci') . '</h1>';

        if (isset($_GET['updated']) && wp_unslash($_GET['updated']) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html('Záznam bol uložený.') . '</p></div>';
        }

        if (isset($_GET['deleted']) && wp_unslash($_GET['deleted']) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html('Záznam bol odstránený.') . '</p></div>';
        }

        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            $error_messages = array(
                'not-found' => 'Zákazník neexistuje.',
                'invalid-request' => 'Neplatná požiadavka.',
                'invalid-email' => 'Neplatný email.',
                'invalid-status' => 'Neplatný status.',
                'email-exists' => 'Tento email už používa iný zákazník.',
                'save-failed' => 'Záznam sa nepodarilo uložiť.',
                'delete-failed' => 'Záznam sa nepodarilo odstrániť.',
            );

            if (isset($error_messages[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_messages[$error]) . '</p></div>';
            }
        }

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="toptour-customers" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="customer-search-input">' . esc_html('Hľadať zákazníkov') . '</label>';
        echo '<input type="search" id="customer-search-input" name="s" value="' . esc_attr($search) . '" />';
        echo '<select name="status" id="customer-status-filter">';
        echo '<option value="">' . esc_html('All statuses') . '</option>';
        foreach ($this->allowed_statuses as $allowed_status) {
            echo '<option value="' . esc_attr($allowed_status) . '" ' . selected($status_filter, $allowed_status, false) . '>' . esc_html($allowed_status) . '</option>';
        }
        echo '</select>';
        echo '<input type="submit" class="button" value="' . esc_attr('Hľadať') . '" />';
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html('ID') . '</th>';
        echo '<th>' . esc_html('Name') . '</th>';
        echo '<th>' . esc_html('Email') . '</th>';
        echo '<th>' . esc_html('Phone') . '</th>';
        echo '<th>' . esc_html('Inquiry count') . '</th>';
        echo '<th>' . esc_html('Status') . '</th>';
        echo '<th>' . esc_html('First seen') . '</th>';
        echo '<th>' . esc_html('Last seen') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($customers)) {
            echo '<tr><td colspan="8">' . esc_html('No customers found.') . '</td></tr>';
        } else {
            foreach ($customers as $customer) {
                $id = isset($customer->id) ? (int) $customer->id : 0;
                $name = isset($customer->name) && $customer->name !== '' ? (string) $customer->name : '-';
                $email = isset($customer->email) && $customer->email !== '' ? (string) $customer->email : '-';
                $phone = isset($customer->phone) && $customer->phone !== '' ? (string) $customer->phone : '-';
                $inquiry_count = isset($customer->inquiry_count) ? (int) $customer->inquiry_count : 0;
                $status = isset($customer->status) && $customer->status !== '' ? (string) $customer->status : '-';
                $first_seen_at = isset($customer->first_seen_at) && $customer->first_seen_at !== '' ? (string) $customer->first_seen_at : '-';
                $last_seen_at = isset($customer->last_seen_at) && $customer->last_seen_at !== '' ? (string) $customer->last_seen_at : '-';

                $edit_url = add_query_arg(
                    array_merge(
                        array(
                            'page' => 'toptour-customers',
                            'view' => 'edit',
                            'customer_id' => $id,
                        ),
                        $list_context
                    ),
                    admin_url('admin.php')
                );

                $delete_url = wp_nonce_url(
                    add_query_arg(
                        array_merge(
                            array(
                                'page' => 'toptour-customers',
                                'action' => 'delete',
                                'customer_id' => $id,
                            ),
                            $list_context
                        ),
                        admin_url('admin.php')
                    ),
                    'toptour_customer_delete_' . $id
                );

                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>';
                echo esc_html($name);
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url($edit_url) . '">' . esc_html('Upraviť') . '</a> | </span>';
                echo '<span class="delete"><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js('Naozaj chcete zmazať tohto zákazníka?') . '\');">' . esc_html('Zmazať') . '</a></span>';
                echo '</div>';
                echo '</td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td>' . esc_html($phone) . '</td>';
                echo '<td>' . esc_html((string) $inquiry_count) . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>' . esc_html($first_seen_at) . '</td>';
                echo '<td>' . esc_html($last_seen_at) . '</td>';
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
     * Render customer edit screen.
     *
     * @param int $customer_id Customer ID.
     */
    private function render_admin_customer_edit_screen($customer_id)
    {
        $customer = $this->get_customer_by_id($customer_id);
        $list_context = $this->get_list_context_from_get();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html('TopTour - Zákazníci') . '</h1>';

        if (isset($_GET['error'])) {
            $error = sanitize_text_field(wp_unslash($_GET['error']));
            $error_messages = array(
                'not-found' => 'Zákazník neexistuje.',
                'invalid-request' => 'Neplatná požiadavka.',
                'invalid-email' => 'Neplatný email.',
                'invalid-status' => 'Neplatný status.',
                'email-exists' => 'Tento email už používa iný zákazník.',
                'save-failed' => 'Záznam sa nepodarilo uložiť.',
            );

            if (isset($error_messages[$error])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_messages[$error]) . '</p></div>';
            }
        }

        if (! is_object($customer)) {
            echo '<div class="notice notice-error"><p>' . esc_html('Zákazník neexistuje.') . '</p></div>';
            echo '<p><a href="' . esc_url($this->get_admin_customers_url()) . '">' . esc_html('Späť na zoznam') . '</a></p>';
            echo '</div>';
            return;
        }

        $name = isset($customer->name) ? (string) $customer->name : '';
        $email = isset($customer->email) ? (string) $customer->email : '';
        $phone = isset($customer->phone) ? (string) $customer->phone : '';
        $status = isset($customer->status) ? (string) $customer->status : 'lead';
        if (! in_array($status, $this->allowed_statuses, true)) {
            $status = 'lead';
        }

        echo '<h2>' . esc_html('Upraviť zákazníka') . '</h2>';
        echo '<form method="post" action="' . esc_url($this->get_admin_customers_url()) . '">';
        wp_nonce_field('toptour_customer_update_' . $customer_id, '_wpnonce_toptour_customer_update');
        echo '<input type="hidden" name="' . esc_attr($this->admin_action_field) . '" value="save_customer" />';
        echo '<input type="hidden" name="customer_id" value="' . esc_attr((string) $customer_id) . '" />';
        if (isset($list_context['paged'])) {
            echo '<input type="hidden" name="paged" value="' . esc_attr((string) $list_context['paged']) . '" />';
        }
        if (isset($list_context['s'])) {
            echo '<input type="hidden" name="s" value="' . esc_attr((string) $list_context['s']) . '" />';
        }
        if (isset($list_context['status'])) {
            echo '<input type="hidden" name="status" value="' . esc_attr((string) $list_context['status']) . '" />';
        }

        echo '<table class="form-table" role="presentation">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="toptour_customer_name">' . esc_html('Name') . '</label></th>';
        echo '<td><input name="name" type="text" id="toptour_customer_name" value="' . esc_attr($name) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="toptour_customer_email">' . esc_html('Email') . '</label></th>';
        echo '<td><input name="email" type="email" id="toptour_customer_email" value="' . esc_attr($email) . '" class="regular-text" required /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="toptour_customer_phone">' . esc_html('Phone') . '</label></th>';
        echo '<td><input name="phone" type="text" id="toptour_customer_phone" value="' . esc_attr($phone) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="toptour_customer_status">' . esc_html('Status') . '</label></th>';
        echo '<td><select name="status" id="toptour_customer_status">';
        foreach ($this->allowed_statuses as $allowed_status) {
            echo '<option value="' . esc_attr($allowed_status) . '" ' . selected($status, $allowed_status, false) . '>' . esc_html($allowed_status) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . esc_html('Uložiť') . '</button> ';
        echo '<a href="' . esc_url($this->get_admin_customers_url($list_context)) . '" class="button button-secondary">' . esc_html('Späť na zoznam') . '</a>';
        echo '</p>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Handle admin customer update submit.
     */
    private function handle_admin_customer_update_submission()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $list_context = $this->get_list_context_from_post();

        $action = isset($_POST[$this->admin_action_field]) ? sanitize_text_field(wp_unslash($_POST[$this->admin_action_field])) : '';
        if ($action !== 'save_customer') {
            return;
        }

        $customer_id = isset($_POST['customer_id']) ? absint(wp_unslash($_POST['customer_id'])) : 0;
        if ($customer_id <= 0) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'invalid-request')));
        }

        $nonce = isset($_POST['_wpnonce_toptour_customer_update']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce_toptour_customer_update'])) : '';
        if (! wp_verify_nonce($nonce, 'toptour_customer_update_' . $customer_id)) {
            $this->redirect_to_edit_with_args($customer_id, array_merge($list_context, array('error' => 'invalid-request')));
        }

        $customer = $this->get_customer_by_id($customer_id);
        if (! is_object($customer)) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'not-found')));
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if ($email === '' || ! is_email($email)) {
            $this->redirect_to_edit_with_args($customer_id, array_merge($list_context, array('error' => 'invalid-email')));
        }

        if (! in_array($status, $this->allowed_statuses, true)) {
            $this->redirect_to_edit_with_args($customer_id, array_merge($list_context, array('error' => 'invalid-status')));
        }

        $existing_customer = $this->find_customer_by_email($email);
        if (is_object($existing_customer) && isset($existing_customer->id) && (int) $existing_customer->id !== $customer_id) {
            $this->redirect_to_edit_with_args($customer_id, array_merge($list_context, array('error' => 'email-exists')));
        }

        $updated = $this->update_customer_admin(
            $customer_id,
            array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
            )
        );

        if (! $updated) {
            $this->redirect_to_edit_with_args($customer_id, array_merge($list_context, array('error' => 'save-failed')));
        }

        $this->redirect_to_list_with_args(array_merge($list_context, array('updated' => '1')));
    }

    /**
     * Handle admin customer delete action.
     */
    private function handle_admin_customer_delete_action()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $list_context = $this->get_list_context_from_get();

        $customer_id = isset($_GET['customer_id']) ? absint(wp_unslash($_GET['customer_id'])) : 0;
        if ($customer_id <= 0) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'invalid-request')));
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'toptour_customer_delete_' . $customer_id)) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'invalid-request')));
        }

        $customer = $this->get_customer_by_id($customer_id);
        if (! is_object($customer)) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'not-found')));
        }

        $deleted = $this->delete_customer($customer_id);
        if (! $deleted) {
            $this->redirect_to_list_with_args(array_merge($list_context, array('error' => 'delete-failed')));
        }

        $this->redirect_to_list_with_args(array_merge($list_context, array('deleted' => '1')));
    }

    /**
     * Return sanitized list context from query params.
     *
     * @return array<string, string|int>
     */
    private function get_list_context_from_get()
    {
        return $this->sanitize_list_context($_GET);
    }

    /**
     * Return sanitized list context from POST payload.
     *
     * @return array<string, string|int>
     */
    private function get_list_context_from_post()
    {
        return $this->sanitize_list_context($_POST);
    }

    /**
     * Sanitize and whitelist list context values.
     *
     * @param array<mixed> $raw
     * @return array<string, string|int>
     */
    private function sanitize_list_context($raw)
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

        if (isset($raw['status'])) {
            $status_raw = wp_unslash($raw['status']);
            if (is_scalar($status_raw)) {
                $status = sanitize_text_field((string) $status_raw);
                if ($status !== '' && in_array($status, $this->allowed_statuses, true)) {
                    $context['status'] = $status;
                }
            }
        }

        return $context;
    }

    /**
     * Redirect to customers list with query args.
     *
     * @param array<string, string> $args
     */
    private function redirect_to_list_with_args($args = array())
    {
        wp_safe_redirect($this->get_admin_customers_url($args));
        exit;
    }

    /**
     * Redirect to customer edit with query args.
     *
     * @param int                   $customer_id
     * @param array<string, string> $args
     */
    private function redirect_to_edit_with_args($customer_id, $args = array())
    {
        $base_args = array(
            'view' => 'edit',
            'customer_id' => (string) $customer_id,
        );

        wp_safe_redirect($this->get_admin_customers_url(array_merge($base_args, $args)));
        exit;
    }

    /**
     * Build customers admin URL.
     *
     * @param array<string, string|int> $args
     * @return string
     */
    private function get_admin_customers_url($args = array())
    {
        $base_args = array('page' => 'toptour-customers');
        return add_query_arg(array_merge($base_args, $args), admin_url('admin.php'));
    }

    /**
     * Return paginated customers listing for admin overview.
     *
     * @param int    $page Current page number.
     * @param int    $per_page Number of items per page.
     * @param string $search Search term (name, email, phone).
     * @param string $status_filter Status filter.
     * @return array<string, mixed>
     */
    public function get_customers_listing($page, $per_page, $search = '', $status_filter = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_suffix;
        $page = max(1, (int) $page);
        $per_page = max(1, (int) $per_page);
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field((string) $search);
        $status_filter = sanitize_text_field((string) $status_filter);
        if (! in_array($status_filter, $this->allowed_statuses, true)) {
            $status_filter = '';
        }

        $where_sql = '';
        $where_clauses = array();
        $where_params = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $where_params = array_merge($where_params, array($like, $like, $like));
        }

        if ($status_filter !== '') {
            $where_clauses[] = 'status = %s';
            $where_params[] = $status_filter;
        }

        if (! empty($where_clauses)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }

        $count_sql = "SELECT COUNT(*) FROM {$table_name}{$where_sql}";
        if (! empty($where_params)) {
            $count_sql = $wpdb->prepare($count_sql, $where_params);
        }

        $total = (int) $wpdb->get_var($count_sql);

        $items_sql = "SELECT id, name, email, phone, inquiry_count, status, first_seen_at, last_seen_at FROM {$table_name}{$where_sql} ORDER BY last_seen_at DESC LIMIT %d OFFSET %d";
        $items_params = array_merge($where_params, array($per_page, $offset));
        $prepared_items_sql = $wpdb->prepare($items_sql, $items_params);
        $items = $wpdb->get_results($prepared_items_sql);

        return array(
            'items' => is_array($items) ? $items : array(),
            'total' => $total,
        );
    }

    /**
     * Return single customer row by ID.
     *
     * @param int $customer_id Customer ID.
     * @return object|null
     */
    public function get_customer_by_id($customer_id)
    {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return null;
        }

        $table_name = $wpdb->prefix . $this->table_suffix;
        $sql = $wpdb->prepare(
            "SELECT id, name, email, phone, inquiry_count, status, first_seen_at, last_seen_at FROM {$table_name} WHERE id = %d LIMIT 1",
            $customer_id
        );

        $customer = $wpdb->get_row($sql);
        return is_object($customer) ? $customer : null;
    }

    /**
     * Update customer row from admin edit screen.
     *
     * @param int                  $customer_id Customer ID.
     * @param array<string, mixed> $data Input values.
     * @return bool
     */
    public function update_customer_admin($customer_id, $data)
    {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return false;
        }

        $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';
        $status = isset($data['status']) ? sanitize_text_field((string) $data['status']) : '';

        if ($email === '' || ! is_email($email)) {
            return false;
        }

        if (! in_array($status, $this->allowed_statuses, true)) {
            return false;
        }

        $table_name = $wpdb->prefix . $this->table_suffix;
        $updated = $wpdb->update(
            $table_name,
            array(
                'name' => isset($data['name']) ? sanitize_text_field((string) $data['name']) : '',
                'email' => $email,
                'phone' => isset($data['phone']) ? sanitize_text_field((string) $data['phone']) : '',
                'status' => $status,
            ),
            array('id' => $customer_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        return $updated !== false;
    }

    /**
     * Delete customer row by ID.
     *
     * @param int $customer_id Customer ID.
     * @return bool
     */
    public function delete_customer($customer_id)
    {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return false;
        }

        $table_name = $wpdb->prefix . $this->table_suffix;
        $deleted = $wpdb->delete($table_name, array('id' => $customer_id), array('%d'));

        return $deleted !== false;
    }

    /**
     * Create or update customer by email.
     *
     * @param array<string, mixed> $data Input customer data.
     * @return bool
     */
    public function upsert_customer($data)
    {
        $email = isset($data['email']) ? sanitize_email((string) $data['email']) : '';

        if ($email === '' || ! is_email($email)) {
            return false;
        }

        $normalized_data = array(
            'email' => $email,
            'name' => isset($data['name']) ? sanitize_text_field((string) $data['name']) : '',
            'phone' => isset($data['phone']) ? sanitize_text_field((string) $data['phone']) : '',
        );

        $existing_customer = $this->find_customer_by_email($email);

        if (! is_object($existing_customer) || ! isset($existing_customer->id)) {
            return $this->create_customer($normalized_data);
        }

        return $this->update_customer((int) $existing_customer->id, $normalized_data);
    }

    /**
     * Find customer row by email.
     *
     * @param string $email Customer email.
     * @return object|null
     */
    private function find_customer_by_email($email)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_suffix;
        $sql = $wpdb->prepare("SELECT * FROM {$table_name} WHERE email = %s LIMIT 1", $email);
        $row = $wpdb->get_row($sql);

        return is_object($row) ? $row : null;
    }

    /**
     * Insert new customer row.
     *
     * @param array<string, string> $data Normalized customer data.
     * @return bool
     */
    private function create_customer($data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_suffix;
        $now = current_time('mysql');

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'email' => $data['email'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'inquiry_count' => 1,
                'status' => 'lead',
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return $inserted !== false;
    }

    /**
     * Update existing customer row.
     *
     * @param int                  $customer_id Customer ID.
     * @param array<string, mixed> $data Normalized customer data.
     * @return bool
     */
    private function update_customer($customer_id, $data)
    {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return false;
        }

        $table_name = $wpdb->prefix . $this->table_suffix;

        $update_data = array(
            'last_seen_at' => current_time('mysql'),
        );
        $update_formats = array('%s');

        if (isset($data['name']) && $data['name'] !== '') {
            $update_data['name'] = sanitize_text_field((string) $data['name']);
            $update_formats[] = '%s';
        }

        if (isset($data['phone']) && $data['phone'] !== '') {
            $update_data['phone'] = sanitize_text_field((string) $data['phone']);
            $update_formats[] = '%s';
        }

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $customer_id),
            $update_formats,
            array('%d')
        );

        if ($updated === false) {
            return false;
        }

        $incremented = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET inquiry_count = inquiry_count + 1 WHERE id = %d",
                $customer_id
            )
        );

        return $incremented !== false;
    }
}
