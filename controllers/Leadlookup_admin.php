<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Legacy controller kept only for users who installed v1.2.3 links.
 * The real admin UI is /admin/leadlookup/settings.
 */
class Leadlookup_admin extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!function_exists('is_admin') || !is_admin()) {
            access_denied('Lead Lookup');
        }
    }

    public function settings()
    {
        redirect(admin_url('leadlookup/settings'));
    }

    public function retry_log($id = null)
    {
        redirect(admin_url('leadlookup/retry_log/' . (int) $id));
    }
}
