<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'search' => '', 'date_from' => '', 'date_to' => ''];
$logs = isset($logs) && is_array($logs) ? $logs : [];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : ['page' => 1, 'per_page' => 25, 'total_rows' => 0, 'total_pages' => 1];

?>

<div class="clearfix">
    <h4 class="tw-font-semibold tw-mt-0 pull-left"><?php echo _l('Lead Lookup - Chatwoot Lead Sync API'); ?></h4>
    <div class="pull-right">
        <a href="<?php echo admin_url('leadlookup/debug'); ?>" class="btn btn-info">
            <i class="fa fa-bug"></i> Debug Page
        </a>
        <a href="<?php echo admin_url('leadlookup/phone_report'); ?>" class="btn btn-default">
            <i class="fa fa-phone"></i> Phone Lookup Report
        </a>
    </div>
</div>
<p class="text-muted">Configure Chatwoot webhook lead create sync. Existing phone lookup API remains unchanged.</p>
<hr />

<div class="alert alert-info">
    <strong>Webhook endpoint:</strong> <code><?php echo site_url('leadlookup/create_from_chatwoot'); ?></code><br>
    <strong>Method:</strong> POST JSON<br>
    <strong>Required:</strong> name + phone or email<br>
    <strong>Security:</strong> allowed domain/IP validation only
</div>

<?php echo form_open(admin_url('leadlookup/settings')); ?>
<div class="row">
    <div class="col-md-12">
        <h4>Phone Lookup API</h4>
        <p class="text-muted">The phone lookup endpoint uses a static API key from <code>modules/leadlookup/config/leadlookup.php</code>.</p>
        <p class="text-muted">Endpoint: <code><?php echo site_url('leadlookup/by_phone'); ?>?apikey=YOUR_STATIC_KEY&phone=017...</code></p>
    </div>

    <div class="col-md-12"><hr /><h4>Security</h4></div>
    <div class="col-md-4">
        <label>Enable domain/IP validation</label>
        <select name="settings[leadlookup_domain_ip_validation_enabled]" class="form-control selectpicker">
            <option value="1" <?php echo get_option('leadlookup_domain_ip_validation_enabled') !== '0' ? 'selected' : ''; ?>>Yes</option>
            <option value="0" <?php echo get_option('leadlookup_domain_ip_validation_enabled') === '0' ? 'selected' : ''; ?>>No</option>
        </select>
        <p class="text-muted">When enabled, both public endpoints must match the allowed domain/IP list.</p>
    </div>
    <div class="col-md-8">
        <?php echo render_textarea('settings[leadlookup_chatwoot_allowed_domains]', 'Allowed source domains / IP addresses', get_option('leadlookup_chatwoot_allowed_domains'), ['rows' => 4], [], 'mbot15', 'comma separated or one per line, for example: app.unichat.com.bd, 103.125.253.126'); ?>
        <p class="text-muted">Validation checks incoming request metadata only: Origin, Referer, X-Chatwoot-Source-Domain, REMOTE_ADDR, X-Real-IP, CF-Connecting-IP, and X-Forwarded-For. Chatwoot payload referer is ignored. Domain entries are also resolved to IPs for self-hosted Chatwoot webhooks.</p>
    </div>

    <div class="col-md-12"><hr /><h4>Default Lead Values</h4></div>
    <div class="col-md-4">
        <?php echo render_input('settings[leadlookup_chatwoot_default_status_id]', 'Default lead status ID', get_option('leadlookup_chatwoot_default_status_id'), 'number', [], [], 'mbot15'); ?>
        <p class="text-muted">Leave empty if no default status should be forced.</p>
    </div>
    <div class="col-md-4">
        <?php echo render_input('settings[leadlookup_chatwoot_default_source_id]', 'Default lead source ID', get_option('leadlookup_chatwoot_default_source_id'), 'number', [], [], 'mbot15'); ?>
        <p class="text-muted">Leave empty if no default source should be forced.</p>
    </div>
    <div class="col-md-4">
        <?php echo render_input('settings[leadlookup_chatwoot_default_assigned_id]', 'Default assigned staff ID', get_option('leadlookup_chatwoot_default_assigned_id'), 'number', [], [], 'mbot15'); ?>
        <p class="text-muted">Leave empty if no staff should be assigned automatically.</p>
    </div>

    <div class="col-md-4">
        <label>Save raw webhook payload in logs</label>
        <select name="settings[leadlookup_chatwoot_save_payload]" class="form-control selectpicker">
            <option value="1" <?php echo get_option('leadlookup_chatwoot_save_payload') != '0' ? 'selected' : ''; ?>>Yes</option>
            <option value="0" <?php echo get_option('leadlookup_chatwoot_save_payload') == '0' ? 'selected' : ''; ?>>No, summary only</option>
        </select>
    </div>

    <div class="col-md-12"><hr /><h4>Chatwoot URL</h4></div>
    <div class="col-md-6">
        <?php echo render_input('settings[leadlookup_chatwoot_base_url]', 'Chatwoot base URL', get_option('leadlookup_chatwoot_base_url'), 'text', [], [], 'mbot15'); ?>
        <p class="text-muted">Example: https://app.unichat.com.bd</p>
    </div>
    <div class="col-md-6">
        <?php echo render_input('settings[leadlookup_chatwoot_account_id]', 'Fallback Chatwoot account ID', get_option('leadlookup_chatwoot_account_id'), 'number', [], [], 'mbot15'); ?>
        <p class="text-muted">Used for conversation URL when account ID is not available in payload.</p>
    </div>

    <div class="col-md-12"><hr /><h4>Default Sender Field Mapping</h4></div>
    <div class="col-md-12">
        <table class="table table-bordered">
            <thead><tr><th>Chatwoot field</th><th>CRM field</th><th>Rule</th></tr></thead>
            <tbody>
                <tr><td>sender.name</td><td>Name</td><td>Required</td></tr>
                <tr><td>sender.phone_number</td><td>Phone</td><td>Required if email missing</td></tr>
                <tr><td>sender.email</td><td>Email</td><td>Required if phone missing</td></tr>
                <tr><td>additional_attributes.city</td><td>City</td><td>Auto mapped</td></tr>
                <tr><td>additional_attributes.description</td><td>Position / Title</td><td>Auto mapped</td></tr>
                <tr><td>additional_attributes.company_name</td><td>Company</td><td>Auto mapped</td></tr>
                <tr><td>additional_attributes.country_code or country</td><td>Country</td><td>Resolves to Perfex country ID when possible</td></tr>
                <tr><td>additional_attributes.social_profiles</td><td>Website</td><td>Priority: LinkedIn, GitHub, X/Twitter, Facebook, TikTok, Instagram</td></tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-12">
        <hr />
        <button type="submit" class="btn btn-primary pull-right"><?php echo _l('save'); ?></button>
    </div>
</div>
<?php echo form_close(); ?>

<div class="clearfix"></div>
<hr />

<?php $CI = &get_instance(); $CI->load->view('leadlookup/logs_table', [
    'filters' => $filters,
    'logs' => $logs,
    'pagination' => $pagination,
    'can_delete_logs' => $can_delete_logs,
    'base_url' => $base_url,
    'delete_redirect' => $delete_redirect,
]); ?>
