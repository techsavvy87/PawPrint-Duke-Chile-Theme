<?php
/**
 * Facility Owners — admin menu, access control and request handling.
 *
 * All state-changing requests are handled on the page's `load-*` hook (before
 * any output) so we can redirect after success (Post/Redirect/Get).
 *
 * @package PawPrint_Duke
 */

if (!defined('ABSPATH')) {
    exit;
}

class PawPrint_Facility_Owners_Admin
{
    const PAGE_SLUG       = 'pawprint-facility-owners';
    const PER_PAGE_OPTION = 'pawprint_fo_per_page';

    /** @var string Admin page hook suffix. */
    private static $hook = '';

    /** @var WP_Error|null Validation errors for the current form submission. */
    private static $errors = null;

    /** @var array Submitted values to re-populate the form after a failed save. */
    private static $form_data = [];

    /** @var PawPrint_Facility_Owners_List_Table|null */
    private static $list_table = null;

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('set_screen_option_' . self::PER_PAGE_OPTION, [__CLASS__, 'save_per_page'], 10, 3);
        add_filter('removable_query_args', [__CLASS__, 'removable_query_args']);
    }

    /* ---------------------------------------------------------------------
     * Access control
     * ------------------------------------------------------------------ */

    /**
     * Capability used for the menu. Super Admins hold every capability; on
     * multisite this hides the menu from regular site administrators.
     */
    public static function capability(): string
    {
        return is_multisite() ? 'manage_network_options' : 'manage_options';
    }

    /**
     * Super Admin check used on every request and action.
     */
    public static function current_user_can_manage(): bool
    {
        return is_super_admin() && current_user_can(self::capability());
    }

    private static function authorize(): void
    {
        if (!self::current_user_can_manage()) {
            wp_die(
                esc_html__('Sorry, you are not allowed to manage facility owners.', 'pawprint-duke'),
                esc_html__('Access denied', 'pawprint-duke'),
                ['response' => 403]
            );
        }
    }

    /* ---------------------------------------------------------------------
     * URL helpers
     * ------------------------------------------------------------------ */

    public static function page_url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::PAGE_SLUG], $args), admin_url('admin.php'));
    }

    /**
     * Nonced URL for activate / deactivate.
     */
    public static function status_url(int $id, string $action): string
    {
        return wp_nonce_url(
            self::page_url(['action' => $action, 'id' => $id]),
            'pawprint_fo_status_' . $id
        );
    }

    private static function redirect(array $args = []): void
    {
        wp_safe_redirect(self::page_url($args));
        exit;
    }

    /* ---------------------------------------------------------------------
     * Menu, assets, screen options
     * ------------------------------------------------------------------ */

    public static function register_menu(): void
    {
        if (!self::current_user_can_manage()) {
            return;
        }

        self::$hook = add_menu_page(
            __('Facility Owners', 'pawprint-duke'),
            __('Facility Owners', 'pawprint-duke'),
            self::capability(),
            self::PAGE_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-pets',
            26
        );

        add_action('load-' . self::$hook, [__CLASS__, 'handle_request']);
    }

    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== self::$hook) {
            return;
        }

        wp_enqueue_style(
            'pawprint-fo-admin',
            get_stylesheet_directory_uri() . '/assets/css/admin-facility-owners.css',
            [],
            // Cache-bust whenever the file changes.
            (string) filemtime(get_stylesheet_directory() . '/assets/css/admin-facility-owners.css')
        );
    }

    public static function save_per_page($status, $option, $value)
    {
        return self::PER_PAGE_OPTION === $option ? max(1, min(200, (int) $value)) : $status;
    }

    /**
     * Strip one-time notice args from the URL after the page loads.
     */
    public static function removable_query_args(array $args): array
    {
        $args[] = 'pawprint_fo_message';
        $args[] = 'pawprint_fo_error';
        return $args;
    }

    /* ---------------------------------------------------------------------
     * Request handling (runs before output)
     * ------------------------------------------------------------------ */

    private static function current_action(): string
    {
        return isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    }

    private static function current_id(): int
    {
        return isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
    }

    public static function handle_request(): void
    {
        self::authorize();

        $action = self::current_action();

        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? '')) {
            if (isset($_POST['pawprint_fo_save'])) {
                self::handle_save();
            } elseif (isset($_POST['pawprint_fo_confirm_delete'])) {
                self::handle_delete();
            }
        } elseif (in_array($action, ['activate', 'deactivate'], true)) {
            self::handle_status_change($action);
        }

        if (in_array($action, ['edit', 'delete'], true) && !pawprint_fo_get(self::current_id())) {
            self::redirect(['pawprint_fo_error' => 'not_found']);
        }

        if ('' === $action || '-1' === $action) {
            add_screen_option('per_page', [
                'label'   => __('Facility owners per page', 'pawprint-duke'),
                'default' => 20,
                'option'  => self::PER_PAGE_OPTION,
            ]);
            self::$list_table = new PawPrint_Facility_Owners_List_Table();
        }
    }

    private static function handle_save(): void
    {
        check_admin_referer('pawprint_fo_save');

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if ($id && !pawprint_fo_get($id)) {
            self::redirect(['pawprint_fo_error' => 'not_found']);
        }

        $input    = pawprint_fo_prepare_input(wp_unslash($_POST), $id);
        $data     = $input['data'];
        $errors   = $input['errors'];

        if (!$errors->has_errors()) {
            $result = $id ? pawprint_fo_update($id, $data) : pawprint_fo_insert($data);

            if (!is_wp_error($result)) {
                self::redirect(['pawprint_fo_message' => $id ? 'updated' : 'created']);
            }

            $errors = $result;
        }

        // Re-render the form with errors and the user's (sanitized) input.
        self::$errors    = $errors;
        self::$form_data = $data;
    }

    private static function handle_status_change(string $action): void
    {
        $id = self::current_id();
        check_admin_referer('pawprint_fo_status_' . $id);

        if (!pawprint_fo_get($id)) {
            self::redirect(['pawprint_fo_error' => 'not_found']);
        }

        $status = 'activate' === $action ? PAWPRINT_FO_STATUS_ACTIVE : PAWPRINT_FO_STATUS_INACTIVE;
        $result = pawprint_fo_set_status($id, $status);

        if (is_wp_error($result)) {
            self::redirect(['pawprint_fo_error' => 'generic']);
        }

        self::redirect(['pawprint_fo_message' => 'activate' === $action ? 'activated' : 'deactivated']);
    }

    private static function handle_delete(): void
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        check_admin_referer('pawprint_fo_delete_' . $id);

        if (!pawprint_fo_get($id)) {
            self::redirect(['pawprint_fo_error' => 'not_found']);
        }

        $result = pawprint_fo_delete($id);

        if (is_wp_error($result)) {
            $code = 'has_related_records' === $result->get_error_code() ? 'has_related_records' : 'generic';
            self::redirect(['pawprint_fo_error' => $code]);
        }

        self::redirect(['pawprint_fo_message' => 'deleted']);
    }

    /* ---------------------------------------------------------------------
     * Rendering
     * ------------------------------------------------------------------ */

    public static function render_page(): void
    {
        self::authorize();

        switch (self::current_action()) {
            case 'new':
                self::render_form(null);
                break;

            case 'edit':
                self::render_form(pawprint_fo_get(self::current_id()));
                break;

            case 'delete':
                self::render_view('delete-confirm', [
                    'owner'         => pawprint_fo_get(self::current_id()),
                    'related_count' => pawprint_fo_count_related_records(self::current_id()),
                ]);
                break;

            default:
                if (!self::$list_table) {
                    self::$list_table = new PawPrint_Facility_Owners_List_Table();
                }
                self::$list_table->prepare_items();
                self::render_view('list', ['list_table' => self::$list_table]);
        }
    }

    /**
     * @param object|null $owner Existing record when editing.
     */
    private static function render_form($owner): void
    {
        $defaults = ['facility_name' => '', 'owner_name' => '', 'email' => '', 'phone' => '', 'platform_url' => ''];
        $values   = $owner ? array_intersect_key((array) $owner, $defaults) : $defaults;

        // After a failed submission, show what the user typed.
        if (self::$form_data) {
            $values = array_merge($values, self::$form_data);
        }

        self::render_view('form', [
            'owner'  => $owner,
            'values' => $values,
            'errors' => self::$errors ?: new WP_Error(),
        ]);
    }

    /**
     * Include a view file from ./views with the given variables in scope.
     */
    private static function render_view(string $view, array $vars = []): void
    {
        $file = __DIR__ . '/views/' . $view . '.php';
        if (!is_readable($file)) {
            return;
        }

        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract
        include $file;
    }

    /**
     * Success / error notice based on the redirect query args.
     */
    public static function render_notices(): void
    {
        $messages = [
            'created'     => __('Facility owner created successfully.', 'pawprint-duke'),
            'updated'     => __('Facility owner updated successfully.', 'pawprint-duke'),
            'activated'   => __('Facility owner activated.', 'pawprint-duke'),
            'deactivated' => __('Facility owner deactivated.', 'pawprint-duke'),
            'deleted'     => __('Facility owner deleted.', 'pawprint-duke'),
        ];

        $errors = [
            'not_found'           => __('Facility owner not found.', 'pawprint-duke'),
            'has_related_records' => __('This facility owner has invoice or payment history and cannot be deleted. Deactivate it instead.', 'pawprint-duke'),
            'generic'             => __('Something went wrong. Please try again.', 'pawprint-duke'),
        ];

        $message = isset($_GET['pawprint_fo_message']) ? sanitize_key(wp_unslash($_GET['pawprint_fo_message'])) : '';
        $error   = isset($_GET['pawprint_fo_error']) ? sanitize_key(wp_unslash($_GET['pawprint_fo_error'])) : '';

        if (isset($messages[$message])) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($messages[$message]));
        }

        if (isset($errors[$error])) {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($errors[$error]));
        }
    }
}
