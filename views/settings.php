<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$CI = &get_instance();
$CI->load->model('leadlookup/leadlookup_model');
$staffList = $CI->leadlookup_model->get_staff_list();
$statusList = $CI->leadlookup_model->get_status_list();
$sourceList = $CI->leadlookup_model->get_source_list();

$assigneeMapRaw = get_option('leadlookup_assignee_staff_mapping');
$assigneeMap = !empty($assigneeMapRaw) ? json_decode($assigneeMapRaw, true) : [];
if (!is_array($assigneeMap)) {
    $assigneeMap = [];
}

$channelMapRaw = get_option('leadlookup_channel_source_mapping');
$channelMap = !empty($channelMapRaw) ? json_decode($channelMapRaw, true) : [];
if (!is_array($channelMap)) {
    $channelMap = [];
}

$availableChannels = [
    'Channel::WebWidget'       => 'Website Widget (Channel::WebWidget)',
    'Channel::FacebookPage'    => 'Facebook Page (Channel::FacebookPage)',
    'Channel::Instagram'       => 'Instagram Direct (Channel::Instagram)',
    'Channel::Whatsapp'        => 'WhatsApp (Channel::Whatsapp)',
    'Channel::Email'           => 'Email (Channel::Email)',
    'Channel::Telegram'        => 'Telegram (Channel::Telegram)',
    'Channel::TwilioSms'       => 'Twilio SMS / WhatsApp (Channel::TwilioSms)',
    'Channel::Sms'             => 'SMS (Channel::Sms)',
    'Channel::Tiktok'          => 'TikTok (Channel::Tiktok)',
    'Channel::TwitterProfile'  => 'Twitter / X (Channel::TwitterProfile)',
    'Channel::Api'             => 'API Channel (Channel::Api)',
];
?>

<style>
.leadlookup-nav-pills > li > a {
    color: #475569 !important;
    border-radius: 6px !important;
    padding: 9px 14px !important;
    font-size: 13px !important;
    transition: all 0.15s ease !important;
}
.leadlookup-nav-pills > li.active > a,
.leadlookup-nav-pills > li.active > a:focus,
.leadlookup-nav-pills > li.active > a:hover {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
    font-weight: 600 !important;
}
.leadlookup-nav-pills > li > a:hover {
    background-color: #f8fafc !important;
    color: #1e293b !important;
}
</style>

<div class="row" id="leadlookup_settings_container">
    <!-- Left Navigation Column -->
    <div class="col-md-4 col-lg-3">
        <div class="panel_s">
            <div class="panel-body">
                <ul class="nav nav-pills nav-stacked leadlookup-nav-pills tw-space-y-1" role="tablist" id="leadlookup_settings_tabs">
                    <!-- Section 1: Lead Creator -->
                    <li class="tw-px-3 tw-py-1.5 tw-text-xs tw-text-neutral-400 tw-font-bold tw-uppercase tw-tracking-wider">
                        Lead Creator (UniChat)
                    </li>
                    <li role="presentation" class="active">
                        <a href="#tab_creator_general" aria-controls="tab_creator_general" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-sliders fa-fw tw-text-neutral-500"></i>
                            <span>General & Webhook</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_security" aria-controls="tab_creator_security" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-shield-halved fa-fw tw-text-neutral-500"></i>
                            <span>Security & IP List</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_rules" aria-controls="tab_creator_rules" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-user-check fa-fw tw-text-neutral-500"></i>
                            <span>Lead & Duplicate Rules</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_mappings" aria-controls="tab_creator_mappings" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-user-gear fa-fw tw-text-neutral-500"></i>
                            <span>Assignee Staff Mapping</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_channel_mapping" aria-controls="tab_creator_channel_mapping" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-comments fa-fw tw-text-neutral-500"></i>
                            <span>Chat Source Mapping</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_private_notes" aria-controls="tab_creator_private_notes" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-comment-dots fa-fw tw-text-neutral-500"></i>
                            <span>UniChat Private Notes</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_creator_mapping" aria-controls="tab_creator_mapping" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-sitemap fa-fw tw-text-neutral-500"></i>
                            <span>Field Mapping Reference</span>
                        </a>
                    </li>

                    <!-- Section 2: Phone Lookup API -->
                    <li class="tw-px-3 tw-py-1.5 tw-mt-4 tw-pt-4 tw-border-t tw-border-solid tw-border-neutral-200/80 tw-text-xs tw-text-neutral-400 tw-font-bold tw-uppercase tw-tracking-wider">
                        Phone Lookup API
                    </li>
                    <li role="presentation">
                        <a href="#tab_lookup_config" aria-controls="tab_lookup_config" role="tab" data-toggle="tab" class="tw-flex tw-items-center tw-gap-2.5">
                            <i class="fa-solid fa-key fa-fw tw-text-neutral-500"></i>
                            <span>API Key & Config</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Content Area -->
    <div class="col-md-8 col-lg-9">
        <div class="tab-content">

            <!-- TAB 1: Creator General & Webhook -->
            <div role="tabpanel" class="tab-pane active" id="tab_creator_general">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-general-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-4">UniChat Webhook & URL Settings</h4>
                        <hr class="hr-panel-heading" />

                        <div class="alert alert-info tw-mb-6">
                            <h5 class="bold tw-mt-0 tw-mb-2"><i class="fa-solid fa-circle-info tw-mr-1"></i> UniChat Webhook Endpoint Information</h5>
                            <div class="row">
                                <div class="col-md-6 tw-mb-1">
                                    <span class="text-muted">Webhook Endpoint URL:</span><br>
                                    <code><?php echo site_url('leadlookup/create_from_chatwoot'); ?></code>
                                </div>
                                <div class="col-md-6 tw-mb-1">
                                    <span class="text-muted">Required Payload:</span><br>
                                    <span class="bold">name + phone (or email)</span>
                                </div>
                            </div>
                        </div>

                        <h5 class="bold tw-mb-3">UniChat Application URL</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Example: https://app.unichat.com.bd"></i>
                                <?php echo render_input('settings[leadlookup_chatwoot_base_url]', 'UniChat base URL', get_option('leadlookup_chatwoot_base_url'), 'text', [], [], 'mbot15'); ?>
                            </div>
                            <div class="col-md-6">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Used for conversation URL when account ID is not available in payload."></i>
                                <?php echo render_input('settings[leadlookup_chatwoot_account_id]', 'Fallback UniChat account ID', get_option('leadlookup_chatwoot_account_id'), 'number', [], [], 'mbot15'); ?>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />
                        <h5 class="bold tw-mb-3">Log Maintenance & Retention</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Both Lead Create and Phone Lookup logs older than this number of days will be automatically purged by cron and report views. Default is 30 days."></i>
                                <?php echo render_input('settings[leadlookup_log_retention_days]', 'Auto purge logs older than (days)', get_option('leadlookup_log_retention_days') ? get_option('leadlookup_log_retention_days') : '30', 'number', ['min' => 1, 'max' => 365], [], 'mbot15'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB 2: Creator Security -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_security">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-security-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-1">Security & Allowed Domains/IPs</h4>
                        <p class="text-muted">Restrict incoming UniChat webhook requests based on source domain or IP address.</p>
                        <hr class="hr-panel-heading" />
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="When enabled, webhook requests must match the allowed domain/IP list."></i>
                                    <label for="leadlookup_domain_ip_validation_enabled">Enable domain/IP validation</label>
                                    <select name="settings[leadlookup_domain_ip_validation_enabled]" id="leadlookup_domain_ip_validation_enabled" class="selectpicker" data-width="100%">
                                        <option value="1" <?php echo get_option('leadlookup_domain_ip_validation_enabled') !== '0' ? 'selected' : ''; ?>>Yes (Recommended)</option>
                                        <option value="0" <?php echo get_option('leadlookup_domain_ip_validation_enabled') === '0' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Validation checks incoming request metadata only: Origin, Referer, X-UniChat-Source-Domain, REMOTE_ADDR, X-Real-IP, CF-Connecting-IP, and X-Forwarded-For. Domain entries are also resolved to IPs."></i>
                                <?php echo render_textarea('settings[leadlookup_chatwoot_allowed_domains]', 'Allowed source domains / IP addresses', get_option('leadlookup_chatwoot_allowed_domains'), ['rows' => 4, 'placeholder' => 'app.unichat.com.bd, 103.125.253.126'], [], 'mbot15'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB 3: Creator Rules -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_rules">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-rules-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-4">Default Lead Values & Duplicate Rules</h4>
                        <hr class="hr-panel-heading" />

                        <h5 class="bold tw-mb-3">Default Lead Assignment Values</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mbot15">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Select system default status for new leads."></i>
                                    <label for="leadlookup_chatwoot_default_status_id" class="control-label">Default lead status</label>
                                    <select name="settings[leadlookup_chatwoot_default_status_id]" id="leadlookup_chatwoot_default_status_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value="">-- None / System Default --</option>
                                        <?php foreach ($statusList as $st): ?>
                                            <option value="<?php echo $st['id']; ?>" <?php echo ((string)get_option('leadlookup_chatwoot_default_status_id') === (string)$st['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($st['name']); ?> (ID: <?php echo $st['id']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mbot15">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Select system default source for new leads."></i>
                                    <label for="leadlookup_chatwoot_default_source_id" class="control-label">Default lead source</label>
                                    <select name="settings[leadlookup_chatwoot_default_source_id]" id="leadlookup_chatwoot_default_source_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value="">-- None / System Default --</option>
                                        <?php foreach ($sourceList as $so): ?>
                                            <option value="<?php echo $so['id']; ?>" <?php echo ((string)get_option('leadlookup_chatwoot_default_source_id') === (string)$so['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($so['name']); ?> (ID: <?php echo $so['id']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mbot15">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Fallback staff if UniChat Assignee is not mapped in Assignee Staff Mapping."></i>
                                    <label for="leadlookup_chatwoot_default_assigned_id" class="control-label">Default assigned staff</label>
                                    <select name="settings[leadlookup_chatwoot_default_assigned_id]" id="leadlookup_chatwoot_default_assigned_id" class="selectpicker" data-width="100%" data-live-search="true">
                                        <option value="">-- None / Unassigned --</option>
                                        <?php foreach ($staffList as $st): ?>
                                            <option value="<?php echo $st['staffid']; ?>" <?php echo ((string)get_option('leadlookup_chatwoot_default_assigned_id') === (string)$st['staffid']) ? 'selected' : ''; ?>>
                                                <?php echo e(trim($st['firstname'] . ' ' . $st['lastname'])); ?><?php echo !empty($st['email']) ? ' (' . e($st['email']) . ')' : ''; ?> (ID: <?php echo $st['staffid']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Choose whether to store full JSON payload in logs or summary only."></i>
                                    <label for="leadlookup_chatwoot_save_payload">Save raw webhook payload in logs</label>
                                    <select name="settings[leadlookup_chatwoot_save_payload]" id="leadlookup_chatwoot_save_payload" class="selectpicker" data-width="100%">
                                        <option value="1" <?php echo get_option('leadlookup_chatwoot_save_payload') != '0' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="0" <?php echo get_option('leadlookup_chatwoot_save_payload') == '0' ? 'selected' : ''; ?>>No, summary only</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="hr-panel-heading" />
                        <h5 class="bold tw-mb-3">Lead Duplicate Check Rules</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="When enabled, the lead create API skips new lead creation if a non-Customer lead already exists with the same email."></i>
                                    <label for="leadlookup_duplicate_check_email_enabled">Check duplicate by email</label>
                                    <select name="settings[leadlookup_duplicate_check_email_enabled]" id="leadlookup_duplicate_check_email_enabled" class="selectpicker" data-width="100%">
                                        <option value="1" <?php echo get_option('leadlookup_duplicate_check_email_enabled') !== '0' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="0" <?php echo get_option('leadlookup_duplicate_check_email_enabled') === '0' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="When enabled, the lead create API skips new lead creation if a non-Customer lead already exists with the same phone number."></i>
                                    <label for="leadlookup_duplicate_check_phone_enabled">Check duplicate by phone</label>
                                    <select name="settings[leadlookup_duplicate_check_phone_enabled]" id="leadlookup_duplicate_check_phone_enabled" class="selectpicker" data-width="100%">
                                        <option value="1" <?php echo get_option('leadlookup_duplicate_check_phone_enabled') !== '0' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="0" <?php echo get_option('leadlookup_duplicate_check_phone_enabled') === '0' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning" style="margin-top:0;">
                                    <strong>Note:</strong> If both duplicate checks are disabled, every valid UniChat webhook will create a new lead.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB 4: Assignee Staff Mapping -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_mappings">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-mappings-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-1">UniChat Assignee ➔ Staff Mapping</h4>
                        <p class="text-muted">Map UniChat Agent/Assignee IDs to Perfex CRM Staff Members.</p>
                        <hr class="hr-panel-heading" />

                        <h5 class="bold tw-mb-3"><i class="fa-solid fa-user-gear tw-mr-1.5"></i> UniChat Assignee ID ➔ Perfex CRM Staff Mapping</h5>
                        <p class="text-muted tw-text-xs tw-mb-3">When a webhook arrives with a UniChat <code>assignee.id</code> (e.g. 112), the created lead will be automatically assigned to the corresponding Perfex CRM staff member.</p>
                        <div class="table-responsive tw-mb-4">
                            <table class="table table-bordered table-striped" id="assignee_mapping_table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">UniChat Assignee / Agent ID</th>
                                        <th style="width: 50%;">Perfex CRM Staff Member</th>
                                        <th style="width: 10%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($assigneeMap)) {
                                        foreach ($assigneeMap as $uId => $sId) { ?>
                                            <tr>
                                                <td>
                                                    <input type="text" name="settings[leadlookup_assignee_staff_mapping][unichat_id][]" value="<?php echo e($uId); ?>" class="form-group form-control" placeholder="e.g. 112" />
                                                </td>
                                                <td>
                                                    <select name="settings[leadlookup_assignee_staff_mapping][staff_id][]" class="form-control">
                                                        <option value="">-- Select Staff --</option>
                                                        <?php foreach ($staffList as $st) { ?>
                                                            <option value="<?php echo $st['staffid']; ?>" <?php echo ((string)$st['staffid'] === (string)$sId) ? 'selected' : ''; ?>>
                                                                <?php echo e($st['firstname'] . ' ' . $st['lastname'] . ' (' . $st['email'] . ')'); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="settings[leadlookup_assignee_staff_mapping][unichat_id][]" class="form-control" placeholder="e.g. 112" />
                                            </td>
                                            <td>
                                                <select name="settings[leadlookup_assignee_staff_mapping][staff_id][]" class="form-control">
                                                    <option value="">-- Select Staff --</option>
                                                    <?php foreach ($staffList as $st) { ?>
                                                        <option value="<?php echo $st['staffid']; ?>">
                                                            <?php echo e($st['firstname'] . ' ' . $st['lastname'] . ' (' . $st['email'] . ')'); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-default btn-sm" id="add_assignee_row_btn" onclick="addAssigneeRow()"><i class="fa fa-plus tw-mr-1"></i> Add Assignee Mapping</button>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB: Chat Source / Channel Mapping -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_channel_mapping">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-channel-mapping-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-1">UniChat Chat Source ➔ Perfex Lead Source Mapping</h4>
                        <p class="text-muted">Map UniChat chat channel types (e.g. <code>Channel::WebWidget</code>, <code>Channel::FacebookPage</code>, <code>Channel::Whatsapp</code>) to Perfex CRM Lead Sources.</p>
                        <hr class="hr-panel-heading" />

                        <div class="alert alert-info tw-mb-4">
                            <i class="fa-solid fa-circle-info tw-mr-1"></i> When a lead is created from a UniChat webhook, the module reads the <code>channel</code> parameter from the payload and automatically sets the corresponding <strong>Lead Source</strong> in Perfex CRM. If unmapped or missing, the <strong>Default Lead Source</strong> configured in <a href="#tab_creator_rules" onclick="$('a[href=\'#tab_creator_rules\']').tab('show');">Lead & Duplicate Rules</a> will be used.
                        </div>

                        <div class="table-responsive tw-mb-4">
                            <table class="table table-bordered table-striped" id="channel_mapping_table">
                                <thead>
                                    <tr>
                                        <th style="width: 45%;">UniChat Channel / Chat Source</th>
                                        <th style="width: 45%;">Perfex CRM Lead Source</th>
                                        <th style="width: 10%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($channelMap)) {
                                        foreach ($channelMap as $chan => $srcId) { ?>
                                            <tr>
                                                <td>
                                                    <select name="settings[leadlookup_channel_source_mapping][channel][]" class="form-control">
                                                        <option value="">-- Select Channel --</option>
                                                        <?php foreach ($availableChannels as $cVal => $cLabel) { ?>
                                                            <option value="<?php echo e($cVal); ?>" <?php echo ($cVal === $chan) ? 'selected' : ''; ?>>
                                                                <?php echo e($cLabel); ?>
                                                            </option>
                                                        <?php } ?>
                                                        <?php if (!isset($availableChannels[$chan]) && !empty($chan)) { ?>
                                                            <option value="<?php echo e($chan); ?>" selected><?php echo e($chan); ?> (Custom)</option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="settings[leadlookup_channel_source_mapping][source_id][]" class="form-control">
                                                        <option value="">-- Select Lead Source --</option>
                                                        <?php foreach ($sourceList as $src) { ?>
                                                            <option value="<?php echo $src['id']; ?>" <?php echo ((string)$src['id'] === (string)$srcId) ? 'selected' : ''; ?>>
                                                                <?php echo e($src['name']); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr>
                                            <td>
                                                <select name="settings[leadlookup_channel_source_mapping][channel][]" class="form-control">
                                                    <option value="">-- Select Channel --</option>
                                                    <?php foreach ($availableChannels as $cVal => $cLabel) { ?>
                                                        <option value="<?php echo e($cVal); ?>"><?php echo e($cLabel); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="settings[leadlookup_channel_source_mapping][source_id][]" class="form-control">
                                                    <option value="">-- Select Lead Source --</option>
                                                    <?php foreach ($sourceList as $src) { ?>
                                                        <option value="<?php echo $src['id']; ?>"><?php echo e($src['name']); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest('tr').remove()"><i class="fa fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-default btn-sm" id="add_channel_row_btn" onclick="addChannelRow()"><i class="fa fa-plus tw-mr-1"></i> Add Channel Mapping</button>
                            <button type="button" class="btn btn-info btn-sm tw-ml-2" onclick="addCustomChannelPrompt()"><i class="fa fa-keyboard tw-mr-1"></i> Add Custom Channel String</button>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB 5: UniChat Private Notes -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_private_notes">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-private-notes-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-1">UniChat Private Note Callback</h4>
                        <p class="text-muted">Automatically send a private internal note back to the UniChat conversation when a lead is created, skipped, or fails.</p>
                        <hr class="hr-panel-heading" />

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="leadlookup_chatwoot_private_note_enabled">Enable Private Note Callback</label>
                                    <select name="settings[leadlookup_chatwoot_private_note_enabled]" id="leadlookup_chatwoot_private_note_enabled" class="selectpicker" data-width="100%">
                                        <option value="1" <?php echo get_option('leadlookup_chatwoot_private_note_enabled') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="0" <?php echo get_option('leadlookup_chatwoot_private_note_enabled') !== '1' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <?php echo render_input('settings[leadlookup_chatwoot_api_token]', 'UniChat API Access Token', get_option('leadlookup_chatwoot_api_token'), 'password', ['placeholder' => 'Enter UniChat Profile API Access Token'], [], 'mbot15'); ?>
                            </div>
                        </div>

                        <div class="alert alert-info tw-mt-4">
                            <h5 class="bold tw-mt-0 tw-mb-2"><i class="fa-solid fa-circle-info tw-mr-1"></i> How to Get your UniChat API Access Token</h5>
                            <ol class="tw-pl-4 tw-space-y-1 tw-mb-2">
                                <li>Log in to your <strong>UniChat / Chatwoot</strong> dashboard.</li>
                                <li>Click on your <strong>Profile Avatar</strong> (bottom left corner) ➔ select <strong>Profile Settings</strong>.</li>
                                <li>Scroll down to the <strong>API Access Token</strong> section.</li>
                                <li>Copy your User / Agent API Token and paste it into the field above.</li>
                            </ol>
                            <p class="tw-mb-0 text-muted tw-text-xs">
                                <strong>What happens when enabled:</strong> Whenever a webhook is processed, Perfex CRM will call UniChat API to add a private note inside the conversation with Lead ID, Staff Assignment, and Direct CRM Lead Link!
                            </p>
                        </div>

                        <div class="alert alert-warning tw-mt-3">
                            <h5 class="bold tw-mt-0 tw-mb-1 text-warning"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i> Inbox Access Requirement</h5>
                            <p class="tw-mb-0 tw-text-xs">
                                <strong>Important:</strong> If the API Access Token belongs to a non-admin agent, ensure that agent is added to all target inboxes where leads will be created.
                            </p>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

            <!-- TAB 6: Creator Field Mapping Reference -->
            <div role="tabpanel" class="tab-pane" id="tab_creator_mapping">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-1">UniChat Sender Field Mapping Reference</h4>
                        <p class="text-muted">Automatic mapping rules between UniChat sender attributes and Perfex CRM Lead fields.</p>
                        <hr class="hr-panel-heading" />
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>UniChat Field</th>
                                        <th>CRM Field</th>
                                        <th>Mapping Rule</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><code>sender.name</code></td><td>Name</td><td><span class="label label-danger">Required</span></td></tr>
                                    <tr><td><code>sender.phone_number</code></td><td>Phone</td><td><span class="label label-default">Required if email missing</span></td></tr>
                                    <tr><td><code>sender.email</code></td><td>Email</td><td><span class="label label-default">Required if phone missing</span></td></tr>
                                    <tr><td><code>meta.assignee.id</code></td><td>Assigned Staff</td><td><span class="label label-success">Mapped via Assignee Staff Mapping</span></td></tr>
                                    <tr><td><code>channel</code></td><td>Lead Source</td><td><span class="label label-success">Mapped via Chat Source Mapping</span></td></tr>
                                    <tr><td><code>additional_attributes.city</code></td><td>City</td><td><span class="label label-default">Auto mapped</span></td></tr>
                                    <tr><td><code>additional_attributes.description</code></td><td>Position / Title</td><td><span class="label label-default">Auto mapped</span></td></tr>
                                    <tr><td><code>additional_attributes.company_name</code></td><td>Company</td><td><span class="label label-default">Auto mapped</span></td></tr>
                                    <tr><td><code>additional_attributes.country_code / country</code></td><td>Country</td><td><span class="label label-default">Resolves to Perfex Country ID</span></td></tr>
                                    <tr><td><code>additional_attributes.social_profiles</code></td><td>Website</td><td><span class="label label-default">Priority: LinkedIn, GitHub, X, Facebook, TikTok, Instagram</span></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 7: Lookup Config -->
            <div role="tabpanel" class="tab-pane" id="tab_lookup_config">
                <?php echo form_open(admin_url('leadlookup/settings'), ['id' => 'leadlookup-lookup-config-form']); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="bold tw-mt-0 tw-mb-4">Phone Lookup API Configuration</h4>
                        <hr class="hr-panel-heading" />
                        <div class="alert alert-info tw-mb-6">
                            <h5 class="bold tw-mt-0 tw-mb-2"><i class="fa-solid fa-circle-info tw-mr-1"></i> Phone Lookup Endpoint Information</h5>
                            <div>
                                <span class="text-muted">Endpoint URL:</span><br>
                                <code><?php echo site_url('leadlookup/by_phone'); ?>?apikey=YOUR_API_KEY&phone=017...</code>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip" data-title="Used for /leadlookup/by_phone?apikey=. Keep it secret and use a strong random value."></i>
                                <?php echo render_input('settings[leadlookup_api_key]', 'Phone lookup API key', get_option('leadlookup_api_key'), 'text', [], [], 'mbot15'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('save'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>

        </div>
    </div>
</div>

<script>
var leadlookup_staffList = <?php echo json_encode(array_map(function($st) {
    return [
        'id' => (string) $st['staffid'],
        'name' => trim($st['firstname'] . ' ' . $st['lastname']) . ' (' . trim($st['email']) . ')'
    ];
}, $staffList), JSON_UNESCAPED_UNICODE); ?>;

function addAssigneeRow() {
    var staffSelectHtml = '<option value="">-- Select Staff --</option>';
    $.each(leadlookup_staffList, function(i, st) {
        staffSelectHtml += '<option value="' + st.id + '">' + $('<div>').text(st.name).html() + '</option>';
    });

    var html = '<tr>' +
        '<td><input type="text" name="settings[leadlookup_assignee_staff_mapping][unichat_id][]" class="form-control" placeholder="e.g. 112" /></td>' +
        '<td><select name="settings[leadlookup_assignee_staff_mapping][staff_id][]" class="form-control">' + staffSelectHtml + '</select></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
        '</tr>';
    $('#assignee_mapping_table tbody').append(html);
}

var leadlookup_channelsList = <?php echo json_encode($availableChannels, JSON_UNESCAPED_UNICODE); ?>;
var leadlookup_sourcesList = <?php echo json_encode(array_map(function($src) {
    return [
        'id' => (int) $src['id'],
        'name' => trim($src['name'])
    ];
}, $sourceList), JSON_UNESCAPED_UNICODE); ?>;

function addChannelRow(customValue) {
    var channelSelectHtml = '<option value="">-- Select Channel --</option>';
    var hasCustom = false;
    $.each(leadlookup_channelsList, function(val, label) {
        var sel = (customValue && customValue === val) ? ' selected' : '';
        channelSelectHtml += '<option value="' + val + '"' + sel + '>' + $('<div>').text(label).html() + '</option>';
        if (customValue && customValue === val) hasCustom = true;
    });
    if (customValue && !hasCustom) {
        channelSelectHtml += '<option value="' + customValue + '" selected>' + $('<div>').text(customValue + ' (Custom)').html() + '</option>';
    }

    var sourceSelectHtml = '<option value="">-- Select Lead Source --</option>';
    $.each(leadlookup_sourcesList, function(i, src) {
        sourceSelectHtml += '<option value="' + src.id + '">' + $('<div>').text(src.name).html() + '</option>';
    });

    var html = '<tr>' +
        '<td><select name="settings[leadlookup_channel_source_mapping][channel][]" class="form-control">' + channelSelectHtml + '</select></td>' +
        '<td><select name="settings[leadlookup_channel_source_mapping][source_id][]" class="form-control">' + sourceSelectHtml + '</select></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-xs remove-row-btn" onclick="$(this).closest(\'tr\').remove()"><i class="fa fa-trash"></i></button></td>' +
        '</tr>';
    $('#channel_mapping_table tbody').append(html);
}

function addCustomChannelPrompt() {
    var customChan = prompt('Enter custom channel string (e.g. Channel::Api or custom_channel):');
    if (customChan && customChan.trim() !== '') {
        addChannelRow(customChan.trim());
    }
}

$(function() {
    function getUrlParam(name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        return results ? results[1] : null;
    }

    function activateTabFromUrl() {
        var hash = window.location.hash;
        var tabParam = getUrlParam('tab');
        var targetTab = hash || (tabParam ? '#' + tabParam : '');
        if (targetTab && $('#leadlookup_settings_tabs a[href="' + targetTab + '"]').length) {
            $('#leadlookup_settings_container #leadlookup_settings_tabs li').removeClass('active');
            $('#leadlookup_settings_container .tab-pane').removeClass('active');
            var $link = $('#leadlookup_settings_tabs a[href="' + targetTab + '"]');
            $link.closest('li').addClass('active');
            $('#leadlookup_settings_container ' + targetTab).addClass('active');
        }
    }

    activateTabFromUrl();

    $(window).on('hashchange', function() {
        activateTabFromUrl();
    });

    $('#leadlookup_settings_tabs a[data-toggle="tab"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('#leadlookup_settings_container #leadlookup_settings_tabs li').removeClass('active');
        $(this).closest('li').addClass('active');
        
        $('#leadlookup_settings_container .tab-pane').removeClass('active');
        $('#leadlookup_settings_container ' + target).addClass('active');
        
        if (history.pushState) {
            history.pushState(null, null, target);
        } else {
            location.hash = target;
        }
    });
});
</script>
