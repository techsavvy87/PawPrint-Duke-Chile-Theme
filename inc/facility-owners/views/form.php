<?php
/**
 * Create / Edit Facility Owner form.
 *
 * @var object|null $owner  Existing record (edit) or null (create).
 * @var array       $values Field values to display.
 * @var WP_Error    $errors Validation errors.
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = (bool) $owner;
$action  = $is_edit
    ? PawPrint_Facility_Owners_Admin::page_url(['action' => 'edit', 'id' => (int) $owner->id])
    : PawPrint_Facility_Owners_Admin::page_url(['action' => 'new']);

$fields = [
    'facility_name' => ['label' => __('Facility Name', 'pawprint-duke'), 'type' => 'text', 'maxlength' => 191, 'autocomplete' => 'organization'],
    'owner_name'    => ['label' => __('Owner Name', 'pawprint-duke'), 'type' => 'text', 'maxlength' => 191, 'autocomplete' => 'name'],
    'email'         => ['label' => __('Email', 'pawprint-duke'), 'type' => 'email', 'maxlength' => 100, 'autocomplete' => 'email'],
    'phone'         => ['label' => __('Phone', 'pawprint-duke'), 'type' => 'tel', 'maxlength' => 30, 'autocomplete' => 'tel'],
];
?>
<div class="wrap pawprint-fo">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? esc_html__('Edit Facility Owner', 'pawprint-duke') : esc_html__('Create Facility Owner', 'pawprint-duke'); ?>
    </h1>
    <a href="<?php echo esc_url(PawPrint_Facility_Owners_Admin::page_url()); ?>" class="page-title-action">
        <?php esc_html_e('Back to list', 'pawprint-duke'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ($errors->has_errors()) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Please correct the following errors:', 'pawprint-duke'); ?></p>
            <ul>
                <?php foreach ($errors->get_error_messages() as $message) : ?>
                    <li><?php echo esc_html($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($action); ?>" novalidate>
        <?php wp_nonce_field('pawprint_fo_save'); ?>
        <input type="hidden" name="id" value="<?php echo $is_edit ? (int) $owner->id : 0; ?>">

        <table class="form-table" role="presentation">
            <tbody>
            <?php foreach ($fields as $name => $field) :
                $has_error = (bool) $errors->get_error_message($name);
                $field_id  = 'pawprint-fo-' . str_replace('_', '-', $name);
                ?>
                <tr class="form-required<?php echo $has_error ? ' form-invalid' : ''; ?>">
                    <th scope="row">
                        <label for="<?php echo esc_attr($field_id); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <span class="description"><?php esc_html_e('(required)', 'pawprint-duke'); ?></span>
                        </label>
                    </th>
                    <td>
                        <input
                            type="<?php echo esc_attr($field['type']); ?>"
                            id="<?php echo esc_attr($field_id); ?>"
                            name="<?php echo esc_attr($name); ?>"
                            value="<?php echo esc_attr($values[$name] ?? ''); ?>"
                            maxlength="<?php echo (int) $field['maxlength']; ?>"
                            autocomplete="<?php echo esc_attr($field['autocomplete']); ?>"
                            class="regular-text"
                            required
                            aria-required="true"
                            <?php if ($has_error) : ?>aria-invalid="true"<?php endif; ?>
                        >
                        <?php if ($has_error) : ?>
                            <p class="description pawprint-fo-field-error"><?php echo esc_html($errors->get_error_message($name)); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($is_edit) : ?>
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'pawprint-duke'); ?></th>
                    <td>
                        <?php
                        $statuses = pawprint_fo_statuses();
                        printf(
                            '<span class="pawprint-fo-status pawprint-fo-status--%s">%s</span>',
                            esc_attr($owner->status),
                            esc_html($statuses[$owner->status] ?? $owner->status)
                        );
                        ?>
                        <p class="description"><?php esc_html_e('Use Activate / Deactivate in the list to change the status.', 'pawprint-duke'); ?></p>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php
        submit_button(
            $is_edit ? __('Save Changes', 'pawprint-duke') : __('Save', 'pawprint-duke'),
            'primary',
            'pawprint_fo_save'
        );
        ?>
    </form>
</div>
