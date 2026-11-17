<?php
/**
 * Facility Owners — admin list table.
 *
 * @package PawPrint_Duke
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PawPrint_Facility_Owners_List_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'facility_owner',
            'plural'   => 'facility_owners',
            'ajax'     => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'facility_name' => __('Facility Name', 'pawprint-duke'),
            'owner_name'    => __('Owner Name', 'pawprint-duke'),
            'email'         => __('Email', 'pawprint-duke'),
            'phone'         => __('Phone', 'pawprint-duke'),
            'status'        => __('Status', 'pawprint-duke'),
            'actions'       => __('Actions', 'pawprint-duke'),
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'facility_name' => ['facility_name', false],
            'owner_name'    => ['owner_name', false],
            'email'         => ['email', false],
            'status'        => ['status', false],
        ];
    }

    protected function get_primary_column_name()
    {
        return 'facility_name';
    }

    /**
     * Current status filter from the request (whitelisted).
     */
    private function current_status(): string
    {
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        return isset(pawprint_fo_statuses()[$status]) ? $status : '';
    }

    protected function get_views()
    {
        $counts  = pawprint_fo_count_by_status();
        $current = $this->current_status();
        $base    = PawPrint_Facility_Owners_Admin::page_url();

        $views = [];
        $views['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url($base),
            '' === $current ? ' class="current" aria-current="page"' : '',
            esc_html__('All', 'pawprint-duke'),
            array_sum($counts)
        );

        foreach (pawprint_fo_statuses() as $key => $label) {
            $views[$key] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', $key, $base)),
                $key === $current ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                $counts[$key]
            );
        }

        return $views;
    }

    public function prepare_items()
    {
        $per_page = $this->get_items_per_page(PawPrint_Facility_Owners_Admin::PER_PAGE_OPTION, 20);

        $result = pawprint_fo_query([
            'search'   => isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '',
            'status'   => $this->current_status(),
            'orderby'  => isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'created_at',
            'order'    => isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'desc',
            'per_page' => $per_page,
            'page'     => $this->get_pagenum(),
        ]);

        $this->items = $result['items'];
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name()];

        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($result['total'] / $per_page),
        ]);
    }

    public function no_items()
    {
        esc_html_e('No facility owners found.', 'pawprint-duke');
    }

    protected function column_default($item, $column_name)
    {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }

    protected function column_facility_name($item)
    {
        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>',
            esc_url(PawPrint_Facility_Owners_Admin::page_url(['action' => 'edit', 'id' => $item->id])),
            esc_html($item->facility_name)
        );
    }

    protected function column_email($item)
    {
        return sprintf('<a href="%s">%s</a>', esc_url('mailto:' . $item->email), esc_html($item->email));
    }

    protected function column_status($item)
    {
        $labels = pawprint_fo_statuses();
        $label  = $labels[$item->status] ?? $item->status;

        return sprintf(
            '<span class="pawprint-fo-status pawprint-fo-status--%s">%s</span>',
            esc_attr($item->status),
            esc_html($label)
        );
    }

    protected function column_actions($item)
    {
        $id      = (int) $item->id;
        $links   = [];

        $links[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(PawPrint_Facility_Owners_Admin::page_url(['action' => 'edit', 'id' => $id])),
            esc_html__('Edit', 'pawprint-duke')
        );

        if (PAWPRINT_FO_STATUS_ACTIVE === $item->status) {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(PawPrint_Facility_Owners_Admin::status_url($id, 'deactivate')),
                esc_html__('Deactivate', 'pawprint-duke')
            );
        } else {
            $links[] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(PawPrint_Facility_Owners_Admin::status_url($id, 'activate')),
                esc_html__('Activate', 'pawprint-duke')
            );
        }

        // Goes to a confirmation screen; nothing is deleted from this link.
        $links[] = sprintf(
            '<a class="pawprint-fo-delete-link" href="%s">%s</a>',
            esc_url(PawPrint_Facility_Owners_Admin::page_url(['action' => 'delete', 'id' => $id])),
            esc_html__('Delete', 'pawprint-duke')
        );

        return implode(' | ', $links);
    }
}
