<?php
/**
 * Delete confirmation screen.
 *
 * @var object $owner         Record to delete.
 * @var int    $related_count Linked invoice / payment rows.
 */

if (!defined('ABSPATH')) {
    exit;
}

$id = (int) $owner->id;
?>
<div class="wrap pawprint-fo">
    <h1><?php esc_html_e('Delete Facility Owner', 'pawprint-duke'); ?></h1>

    <table class="widefat striped pawprint-fo-summary">
        <tbody>
        <tr><th scope="row"><?php esc_html_e('Facility Name', 'pawprint-duke'); ?></th><td><?php echo esc_html($owner->facility_name); ?></td></tr>
        <tr><th scope="row"><?php esc_html_e('Owner Name', 'pawprint-duke'); ?></th><td><?php echo esc_html($owner->owner_name); ?></td></tr>
        <tr><th scope="row"><?php esc_html_e('Email', 'pawprint-duke'); ?></th><td><?php echo esc_html($owner->email); ?></td></tr>
        <tr><th scope="row"><?php esc_html_e('Phone', 'pawprint-duke'); ?></th><td><?php echo esc_html($owner->phone); ?></td></tr>
        </tbody>
    </table>

    <?php if ($related_count > 0) : ?>
        <div class="notice notice-warning inline">
            <p>
                <?php
                printf(
                    /* translators: %d: number of invoice/payment records */
                    esc_html(_n(
                        'This facility owner has %d related invoice/payment record. To protect billing history it cannot be deleted.',
                        'This facility owner has %d related invoice/payment records. To protect billing history it cannot be deleted.',
                        $related_count,
                        'pawprint-duke'
                    )),
                    (int) $related_count
                );
                ?>
            </p>
        </div>
        <p>
            <?php if (PAWPRINT_FO_STATUS_ACTIVE === $owner->status) : ?>
                <a class="button button-primary" href="<?php echo esc_url(PawPrint_Facility_Owners_Admin::status_url($id, 'deactivate')); ?>">
                    <?php esc_html_e('Deactivate Instead', 'pawprint-duke'); ?>
                </a>
            <?php endif; ?>
            <a class="button" href="<?php echo esc_url(PawPrint_Facility_Owners_Admin::page_url()); ?>"><?php esc_html_e('Back to list', 'pawprint-duke'); ?></a>
        </p>
    <?php else : ?>
        <div class="notice notice-warning inline">
            <p><strong><?php esc_html_e('Are you sure you want to permanently delete this facility owner? This cannot be undone.', 'pawprint-duke'); ?></strong></p>
            <p><?php esc_html_e('If you only want to stop this owner from being used, deactivate it instead.', 'pawprint-duke'); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url(PawPrint_Facility_Owners_Admin::page_url(['action' => 'delete', 'id' => $id])); ?>">
            <?php wp_nonce_field('pawprint_fo_delete_' . $id); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <p>
                <button type="submit" name="pawprint_fo_confirm_delete" value="1" class="button button-primary pawprint-fo-button-danger">
                    <?php esc_html_e('Yes, Delete Permanently', 'pawprint-duke'); ?>
                </button>
                <a class="button" href="<?php echo esc_url(PawPrint_Facility_Owners_Admin::page_url()); ?>"><?php esc_html_e('Cancel', 'pawprint-duke'); ?></a>
            </p>
        </form>
    <?php endif; ?>
</div>
