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

        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        $page = max(1, $page);

        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $listing = $this->get_customers_listing($page, $per_page, $search);
        $customers = isset($listing['items']) && is_array($listing['items']) ? $listing['items'] : array();
        $total_items = isset($listing['total']) ? (int) $listing['total'] : 0;
        $total_pages = max(1, (int) ceil($total_items / $per_page));

        $base_args = array('page' => 'toptour-customers');
        if ($search !== '') {
            $base_args['s'] = $search;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html('TopTour - Zákazníci') . '</h1>';

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="toptour-customers" />';
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="customer-search-input">' . esc_html('Hľadať zákazníkov') . '</label>';
        echo '<input type="search" id="customer-search-input" name="s" value="' . esc_attr($search) . '" />';
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

                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html($name) . '</td>';
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
     * Return paginated customers listing for admin overview.
     *
     * @param int    $page Current page number.
     * @param int    $per_page Number of items per page.
     * @param string $search Search term (name, email, phone).
     * @return array<string, mixed>
     */
    public function get_customers_listing($page, $per_page, $search = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . $this->table_suffix;
        $page = max(1, (int) $page);
        $per_page = max(1, (int) $per_page);
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field((string) $search);

        $where_sql = '';
        $where_params = array();

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_sql = ' WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $where_params = array($like, $like, $like);
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
