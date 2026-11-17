<?php
/**
 * Facility Owners list screen.
 *
 * @var PawPrint_Facility_Owners_List_Table $list_table
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap pawprint-fo">
    <h1 class="wp-heading-inline"><?php esc_html_e('Facility Owners', 'pawprint-duke'); ?></h1>
    <a href="<?php echo esc_url(PawPrint_Facility_Owners_Admin::page_url(['action' => 'new'])); ?>" class="page-title-action">
        <?php esc_html_e('Create Facility Owner', 'pawprint-duke'); ?>
    </a>
    <?php
    $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
    if ('' !== $search) {
        printf(
            '<span class="subtitle">%s</span>',
            /* translators: %s: search keywords */
            sprintf(esc_html__('Search results for: %s', 'pawprint-duke'), '<strong>' . esc_html($search) . '</strong>')
        );
    }
    ?>
    <hr class="wp-header-end">

    <?php PawPrint_Facility_Owners_Admin::render_notices(); ?>

    <?php $list_table->views(); ?>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr(PawPrint_Facility_Owners_Admin::PAGE_SLUG); ?>">
        <?php if (!empty($_GET['status'])) : ?>
            <input type="hidden" name="status" value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['status']))); ?>">
        <?php endif; ?>
        <?php
        $list_table->search_box(__('Search Facility Owners', 'pawprint-duke'), 'pawprint-fo');
        $list_table->display();
        ?>
    </form>
</div>
