<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Lead Lookup
Description: Internal API to lookup leads by phone and create leads from Chatwoot webhooks with domain/IP validation and role-based lead create reports.
Version: 1.3.6
Author: Internal
*/

define('LEADLOOKUP_MODULE_NAME', 'leadlookup');

register_activation_hook(LEADLOOKUP_MODULE_NAME, 'leadlookup_activation_hook');
function leadlookup_activation_hook()
{
    $CI = &get_instance();
    $CI->config->load(LEADLOOKUP_MODULE_NAME . '/leadlookup');
    $cfg = (array) $CI->config->item('leadlookup');

    $defaults = [
        'leadlookup_chatwoot_allowed_domains'      => (string) ($cfg['chatwoot_allowed_domains'] ?? ''),
        'leadlookup_domain_ip_validation_enabled' => (string) ($cfg['domain_ip_validation_enabled'] ?? '1'),
        'leadlookup_chatwoot_default_status_id'    => '',
        'leadlookup_chatwoot_default_source_id'    => '',
        'leadlookup_chatwoot_default_assigned_id'  => '',
        'leadlookup_chatwoot_base_url'             => (string) ($cfg['chatwoot_base_url'] ?? ''),
        'leadlookup_chatwoot_account_id'           => (string) ($cfg['chatwoot_account_id'] ?? ''),
        'leadlookup_chatwoot_save_payload'         => '1',
    ];

    foreach ($defaults as $name => $value) {
        if (function_exists('add_option')) {
            add_option($name, $value);
        }
    }

    // v1.3.0 migration: previous module builds shipped 2/3/11 as automatic
    // defaults. The professional build leaves these fields empty unless the
    // admin explicitly enters values.
    if (function_exists('get_option') && function_exists('update_option') && get_option('leadlookup_v130_empty_defaults_migrated') !== '1') {
        if ((string) get_option('leadlookup_chatwoot_default_status_id') === '2') {
            update_option('leadlookup_chatwoot_default_status_id', '');
        }
        if ((string) get_option('leadlookup_chatwoot_default_source_id') === '3') {
            update_option('leadlookup_chatwoot_default_source_id', '');
        }
        if ((string) get_option('leadlookup_chatwoot_default_assigned_id') === '11') {
            update_option('leadlookup_chatwoot_default_assigned_id', '');
        }
        update_option('leadlookup_v130_empty_defaults_migrated', '1');
    }

    leadlookup_create_sync_logs_table();
}

function leadlookup_create_sync_logs_table()
{
    $CI = &get_instance();
    $table = db_prefix() . 'leadlookup_sync_logs';

    // Use raw SQL instead of dbforge so the log table is created reliably during
    // module activation/admin_init even on older Perfex/CodeIgniter installs.
    $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `log_type` VARCHAR(30) NOT NULL DEFAULT 'lead_create',
        `chatwoot_contact_id` VARCHAR(64) NULL,
        `chatwoot_conversation_id` VARCHAR(64) NULL,
        `chatwoot_inbox_id` VARCHAR(64) NULL,
        `chatwoot_account_id` VARCHAR(64) NULL,
        `crm_lead_id` INT(11) NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `matched_by` VARCHAR(50) NULL,
        `error_message` TEXT NULL,
        `payload_summary` TEXT NULL,
        `payload` LONGTEXT NULL,
        `attempts` INT(11) NOT NULL DEFAULT 0,
        `last_synced_at` DATETIME NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `idx_contact` (`chatwoot_contact_id`),
        KEY `idx_conversation` (`chatwoot_conversation_id`),
        KEY `idx_status` (`status`),
        KEY `idx_log_type` (`log_type`),
        KEY `idx_lead` (`crm_lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    $CI->db->query($sql);

    // Lightweight migration for installs upgraded from older module versions.
    if ($CI->db->table_exists($table) && !$CI->db->field_exists('log_type', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `log_type` VARCHAR(30) NOT NULL DEFAULT 'lead_create' AFTER `id`");
        $CI->db->query("ALTER TABLE `{$table}` ADD KEY `idx_log_type` (`log_type`)");
    }


    // v1.3.3 migration: normalize old rows with blank log_type so every
    // report table can render historical logs after upgrade.
    if ($CI->db->table_exists($table) && $CI->db->field_exists('log_type', $table)) {
        $CI->db->query("UPDATE `{$table}` SET `log_type` = 'lead_create' WHERE `log_type` IS NULL OR `log_type` = ''");
    }

    return $CI->db->table_exists($table);
}

function leadlookup_sync_logs_table_exists()
{
    $CI = &get_instance();
    return $CI->db->table_exists(db_prefix() . 'leadlookup_sync_logs');
}

hooks()->add_action('admin_init', 'leadlookup_admin_init');
function leadlookup_admin_init()
{
    $CI = &get_instance();
    $CI->config->load(LEADLOOKUP_MODULE_NAME . '/leadlookup');

    leadlookup_create_sync_logs_table();

    if (function_exists('register_staff_capabilities')) {
        register_staff_capabilities('leadlookup_reports', [
            'capabilities' => [
                'view'   => 'View Lead Create Report',
                'delete' => 'Delete Lead Create Report Logs',
            ],
        ], 'Lead Lookup Reports');
    }


    if (function_exists('register_staff_capabilities')) {
        register_staff_capabilities('leadlookup_phone_reports', [
            'capabilities' => [
                'view'   => 'View Phone Lookup Report',
                'delete' => 'Delete Phone Lookup Report Logs',
            ],
        ], 'Lead Lookup Phone Reports');
    }

    // Settings UI must be administrator-only.
    if (function_exists('is_admin') && is_admin() && isset($CI->app_menu)) {
        $CI->app_menu->add_setup_menu_item('leadlookup-chatwoot-sync', [
            'slug'     => 'leadlookup-chatwoot-sync',
            'name'     => 'Lead Lookup',
            'href'     => admin_url('leadlookup/settings'),
            'position' => 66,
        ]);
    }

    // Role-based report page for non-admin staff with permission.
    if (isset($CI->app_menu) && (is_admin() || has_permission('leadlookup_reports', '', 'view'))) {
        $CI->app_menu->add_sidebar_children_item('reports', [
            'slug'     => 'leadlookup-create-report',
            'name'     => 'Lead Create Report',
            'href'     => admin_url('leadlookup/report'),
            'position' => 66,
        ]);
    }

    if (isset($CI->app_menu) && (is_admin() || has_permission('leadlookup_phone_reports', '', 'view'))) {
        $CI->app_menu->add_sidebar_children_item('reports', [
            'slug'     => 'leadlookup-phone-report',
            'name'     => 'Phone Lookup Report',
            'href'     => admin_url('leadlookup/phone_report'),
            'position' => 67,
        ]);
    }

    // Do not register this as a core Settings tab. The settings/log screen uses
    // a dedicated AdminController route so Perfex admin CSS/JS assets are
    // initialized correctly on all supported versions.
}
