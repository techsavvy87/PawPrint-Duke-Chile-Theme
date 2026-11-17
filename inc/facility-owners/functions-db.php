<?php
/**
 * Facility Owners — database layer.
 *
 * Table creation plus reusable CRUD / query helpers. All queries go through
 * $wpdb with prepared statements.
 *
 * @package PawPrint_Duke
 */

if (!defined('ABSPATH')) {
    exit;
}

const PAWPRINT_FO_DB_VERSION        = '1.1.0'; // 1.1.0: added platform_url.
const PAWPRINT_FO_DB_VERSION_OPTION = 'pawprint_fo_db_version';
const PAWPRINT_FO_STATUS_ACTIVE     = 'active';
const PAWPRINT_FO_STATUS_INACTIVE   = 'inactive';

/**
 * Full table name, e.g. wp_pawprint_facility_owners.
 */
function pawprint_fo_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'pawprint_facility_owners';
}

/**
 * Allowed statuses => labels.
 */
function pawprint_fo_statuses(): array
{
    return [
        PAWPRINT_FO_STATUS_ACTIVE   => __('Active', 'pawprint-duke'),
        PAWPRINT_FO_STATUS_INACTIVE => __('Inactive', 'pawprint-duke'),
    ];
}

/**
 * Create / upgrade the table. dbDelta() is idempotent, so running it again
 * never creates a duplicate table.
 */
function pawprint_fo_install_table(): void
{
    global $wpdb;

    $table   = pawprint_fo_table_name();
    $charset = $wpdb->get_charset_collate();

    // dbDelta formatting rules: two spaces after PRIMARY KEY, one field per line.
    $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  facility_name varchar(191) NOT NULL,
  owner_name varchar(191) NOT NULL,
  email varchar(100) NOT NULL,
  phone varchar(30) NOT NULL,
  platform_url varchar(255) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY status (status)
) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option(PAWPRINT_FO_DB_VERSION_OPTION, PAWPRINT_FO_DB_VERSION, false);
}

/**
 * Install only when the stored schema version differs (cheap option check).
 */
function pawprint_fo_maybe_install_table(): void
{
    if (get_option(PAWPRINT_FO_DB_VERSION_OPTION) !== PAWPRINT_FO_DB_VERSION) {
        pawprint_fo_install_table();
    }
}
add_action('after_switch_theme', 'pawprint_fo_install_table');
add_action('admin_init', 'pawprint_fo_maybe_install_table');

/**
 * Fetch a single facility owner.
 *
 * @return object|null
 */
function pawprint_fo_get(int $id)
{
    global $wpdb;

    if ($id <= 0) {
        return null;
    }

    $table = pawprint_fo_table_name();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
}

/**
 * Paginated / searchable list query.
 *
 * @param array $args {search, status, orderby, order, per_page, page}
 * @return array{items: array, total: int}
 */
function pawprint_fo_query(array $args = []): array
{
    global $wpdb;

    $args = wp_parse_args($args, [
        'search'   => '',
        'status'   => '',
        'orderby'  => 'created_at',
        'order'    => 'DESC',
        'per_page' => 20,
        'page'     => 1,
    ]);

    $table  = pawprint_fo_table_name();
    $where  = ['1=1'];
    $params = [];

    if ('' !== $args['search']) {
        $like     = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[]  = '(facility_name LIKE %s OR owner_name LIKE %s OR email LIKE %s OR phone LIKE %s OR platform_url LIKE %s)';
        $params   = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    if (isset(pawprint_fo_statuses()[$args['status']])) {
        $where[]  = 'status = %s';
        $params[] = $args['status'];
    }

    // Whitelist ORDER BY values — they cannot be passed as placeholders.
    $sortable = ['facility_name', 'owner_name', 'email', 'status', 'created_at'];
    $orderby  = in_array($args['orderby'], $sortable, true) ? $args['orderby'] : 'created_at';
    $order    = 'ASC' === strtoupper($args['order']) ? 'ASC' : 'DESC';

    $per_page = max(1, (int) $args['per_page']);
    $offset   = (max(1, (int) $args['page']) - 1) * $per_page;

    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

    $items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
    $items     = $wpdb->get_results($wpdb->prepare($items_sql, array_merge($params, [$per_page, $offset])));

    return [
        'items' => $items ?: [],
        'total' => $total,
    ];
}

/**
 * Count records per status (for the All / Active / Inactive views).
 */
function pawprint_fo_count_by_status(): array
{
    global $wpdb;

    $table  = pawprint_fo_table_name();
    $rows   = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status");
    $counts = array_fill_keys(array_keys(pawprint_fo_statuses()), 0);

    foreach ((array) $rows as $row) {
        if (isset($counts[$row->status])) {
            $counts[$row->status] = (int) $row->total;
        }
    }

    return $counts;
}

/**
 * Whether an email is already used by another facility owner.
 */
function pawprint_fo_email_exists(string $email, int $exclude_id = 0): bool
{
    global $wpdb;

    $table = pawprint_fo_table_name();
    return (bool) $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM {$table} WHERE email = %s AND id <> %d LIMIT 1", $email, $exclude_id)
    );
}

/**
 * Sanitize and validate raw form input.
 *
 * @param array $raw        Unslashed input.
 * @param int   $exclude_id Record being edited (for the unique-email check).
 * @return array{data: array, errors: WP_Error}
 */
function pawprint_fo_prepare_input(array $raw, int $exclude_id = 0): array
{
    $data = [
        'facility_name' => sanitize_text_field($raw['facility_name'] ?? ''),
        'owner_name'    => sanitize_text_field($raw['owner_name'] ?? ''),
        'email'         => sanitize_email($raw['email'] ?? ''),
        'phone'         => sanitize_text_field($raw['phone'] ?? ''),
        'platform_url'  => esc_url_raw(trim((string) ($raw['platform_url'] ?? '')), ['http', 'https']),
    ];

    $errors = new WP_Error();

    if ('' === $data['facility_name']) {
        $errors->add('facility_name', __('Facility Name is required.', 'pawprint-duke'));
    } elseif (mb_strlen($data['facility_name']) > 191) {
        $errors->add('facility_name', __('Facility Name must be 191 characters or fewer.', 'pawprint-duke'));
    }

    if ('' === $data['owner_name']) {
        $errors->add('owner_name', __('Owner Name is required.', 'pawprint-duke'));
    } elseif (mb_strlen($data['owner_name']) > 191) {
        $errors->add('owner_name', __('Owner Name must be 191 characters or fewer.', 'pawprint-duke'));
    }

    if ('' === $data['email']) {
        $errors->add('email', __('A valid Email is required.', 'pawprint-duke'));
    } elseif (!is_email($data['email']) || strlen($data['email']) > 100) {
        $errors->add('email', __('Please enter a valid email address.', 'pawprint-duke'));
    } elseif (pawprint_fo_email_exists($data['email'], $exclude_id)) {
        $errors->add('email', __('Another facility owner already uses this email.', 'pawprint-duke'));
    }

    if ('' === $data['phone']) {
        $errors->add('phone', __('Phone is required.', 'pawprint-duke'));
    } elseif (!preg_match('/^\+?[0-9\s().\-]{7,30}$/', $data['phone'])) {
        $errors->add('phone', __('Phone may contain digits, spaces, +, -, ( ) and . (7–30 characters).', 'pawprint-duke'));
    }

    if ('' === trim((string) ($raw['platform_url'] ?? ''))) {
        $errors->add('platform_url', __('Platform URL is required.', 'pawprint-duke'));
    } elseif (
        '' === $data['platform_url']
        || !filter_var($data['platform_url'], FILTER_VALIDATE_URL)
        || !in_array(wp_parse_url($data['platform_url'], PHP_URL_SCHEME), ['http', 'https'], true)
        || strlen($data['platform_url']) > 255
    ) {
        $errors->add('platform_url', __('Please enter a valid Platform URL starting with http:// or https://.', 'pawprint-duke'));
    }

    return ['data' => $data, 'errors' => $errors];
}

/**
 * Insert a facility owner. Expects already-validated data.
 *
 * @return int|WP_Error New ID.
 */
function pawprint_fo_insert(array $data)
{
    global $wpdb;

    $now = current_time('mysql', true);
    $ok  = $wpdb->insert(
        pawprint_fo_table_name(),
        [
            'facility_name' => $data['facility_name'],
            'owner_name'    => $data['owner_name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'platform_url'  => $data['platform_url'],
            'status'        => PAWPRINT_FO_STATUS_ACTIVE,
            'created_at'    => $now,
            'updated_at'    => $now,
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if (false === $ok) {
        return new WP_Error('db_insert', __('Could not save the facility owner.', 'pawprint-duke'));
    }

    $id = (int) $wpdb->insert_id;
    do_action('pawprint_fo_created', $id, $data);

    return $id;
}

/**
 * Update the editable fields of a facility owner.
 *
 * @return true|WP_Error
 */
function pawprint_fo_update(int $id, array $data)
{
    global $wpdb;

    $ok = $wpdb->update(
        pawprint_fo_table_name(),
        [
            'facility_name' => $data['facility_name'],
            'owner_name'    => $data['owner_name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'platform_url'  => $data['platform_url'],
            'updated_at'    => current_time('mysql', true),
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if (false === $ok) {
        return new WP_Error('db_update', __('Could not update the facility owner.', 'pawprint-duke'));
    }

    do_action('pawprint_fo_updated', $id, $data);

    return true;
}

/**
 * Change status (active / inactive) without touching anything else.
 *
 * @return true|WP_Error
 */
function pawprint_fo_set_status(int $id, string $status)
{
    global $wpdb;

    if (!isset(pawprint_fo_statuses()[$status])) {
        return new WP_Error('invalid_status', __('Invalid status.', 'pawprint-duke'));
    }

    $ok = $wpdb->update(
        pawprint_fo_table_name(),
        ['status' => $status, 'updated_at' => current_time('mysql', true)],
        ['id' => $id],
        ['%s', '%s'],
        ['%d']
    );

    if (false === $ok) {
        return new WP_Error('db_update', __('Could not change the status.', 'pawprint-duke'));
    }

    do_action('pawprint_fo_status_changed', $id, $status);

    return true;
}

/**
 * Count invoice / payment rows linked to a facility owner.
 *
 * Checks every table listed in the `pawprint_fo_related_tables` filter
 * (table name without prefix => foreign-key column). Tables or columns that
 * don't exist yet are skipped, so this is safe before billing is built.
 * The final count can be overridden with `pawprint_fo_related_records_count`.
 */
function pawprint_fo_count_related_records(int $id): int
{
    global $wpdb;

    $tables = apply_filters('pawprint_fo_related_tables', [
        'pawprint_invoices' => 'facility_owner_id',
        'pawprint_payments' => 'facility_owner_id',
    ]);

    $count = 0;

    foreach ($tables as $table => $column) {
        $table  = $wpdb->prefix . sanitize_key($table);
        $column = sanitize_key($column);

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($table_exists !== $table) {
            continue;
        }

        $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $wpdb->esc_like($column)));
        if (!$column_exists) {
            continue;
        }

        $count += (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $id));
    }

    return (int) apply_filters('pawprint_fo_related_records_count', $count, $id);
}

/**
 * Permanently delete a facility owner. Refuses when invoice / payment
 * history references the record — deactivate those owners instead.
 *
 * @return true|WP_Error
 */
function pawprint_fo_delete(int $id)
{
    global $wpdb;

    if (pawprint_fo_count_related_records($id) > 0) {
        return new WP_Error(
            'has_related_records',
            __('This facility owner has invoice or payment history and cannot be deleted. Deactivate it instead.', 'pawprint-duke')
        );
    }

    $ok = $wpdb->delete(pawprint_fo_table_name(), ['id' => $id], ['%d']);

    if (!$ok) {
        return new WP_Error('db_delete', __('Could not delete the facility owner.', 'pawprint-duke'));
    }

    do_action('pawprint_fo_deleted', $id);

    return true;
}
