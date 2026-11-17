<?php
/**
 * Facility Owners module loader.
 *
 * WordPress Admin → Facility Owners (Super Admin only).
 *
 * @package PawPrint_Duke
 */

if (!defined('ABSPATH')) {
    exit;
}

// Data layer is always loaded so table setup (after_switch_theme) and the
// pawprint_fo_* helpers are available to other theme code.
require_once __DIR__ . '/functions-db.php';

if (is_admin()) {
    require_once __DIR__ . '/class-pawprint-facility-owners-list-table.php';
    require_once __DIR__ . '/class-pawprint-facility-owners-admin.php';

    PawPrint_Facility_Owners_Admin::init();
}
