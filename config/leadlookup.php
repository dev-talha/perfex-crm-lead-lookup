<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['leadlookup'] = [
    // Existing lookup API key. Kept unchanged for /leadlookup/by_phone.
    'api_key' => 'YOUR_STATIC_SECRET_KEY_HERE',
    'phone_match' => 'like',

    // Chatwoot lead sync defaults.
    'chatwoot_default_status_id'   => '',
    'chatwoot_default_source_id'   => '',
    'chatwoot_default_assigned_id' => '',

    // Admin settings override these fallback values.
    'chatwoot_allowed_domains' => '',
    'domain_ip_validation_enabled' => '1',
    'chatwoot_base_url' => '',
    'chatwoot_account_id' => '',
];
