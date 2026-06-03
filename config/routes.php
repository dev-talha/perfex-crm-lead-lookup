<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Public API endpoints. URLs remain unchanged; they are routed to a public App_Controller.
$route['leadlookup/by_phone'] = 'leadlookup_api/by_phone';
$route['leadlookup/create_from_chatwoot'] = 'leadlookup_api/create_from_chatwoot';

// Admin UI endpoints. These use the AdminController-based Leadlookup controller.
$route['admin/leadlookup/settings'] = 'leadlookup/settings';
$route['admin/leadlookup/retry_log/(:num)'] = 'leadlookup/retry_log/$1';
$route['admin/leadlookup/debug'] = 'leadlookup/debug';
$route['admin/leadlookup/report'] = 'leadlookup/report';
$route['admin/leadlookup/phone_report'] = 'leadlookup/phone_report';
$route['admin/leadlookup/delete_log/(:num)'] = 'leadlookup/delete_log/$1';
$route['admin/leadlookup/bulk_delete_logs'] = 'leadlookup/bulk_delete_logs';

// Legacy v1.2.3 admin URLs.
$route['admin/leadlookup_admin/settings'] = 'leadlookup_admin/settings';
$route['admin/leadlookup_admin/retry_log/(:num)'] = 'leadlookup_admin/retry_log/$1';
