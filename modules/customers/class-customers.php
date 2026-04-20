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
        // Reserved for future hooks.
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
