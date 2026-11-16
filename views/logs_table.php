<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$base_url = isset($base_url) ? $base_url : admin_url('leadlookup/settings');
$can_delete_logs = isset($can_delete_logs) ? (bool) $can_delete_logs : false;
$delete_redirect = isset($delete_redirect) ? $delete_redirect : 'settings';
$allow_retry = isset($allow_retry) ? (bool) $allow_retry : (function_exists('is_admin') && is_admin());
$table_title = isset($table_title) ? (string) $table_title : 'Sync Logs';
$filters = isset($filters) && is_array($filters) ? $filters : [];
$logs = isset($logs) && is_array($logs) ? $logs : [];
$pagination = isset($pagination) && is_array($pagination) ? $pagination : ['page' => 1, 'per_page' => 25, 'total_rows' => 0, 'total_pages' => 1];
$form_prefix = isset($form_prefix) ? (string) $form_prefix : '';
$form_id = 'leadlookup-filter-form' . ($form_prefix !== '' ? '-' . trim($form_prefix, '_') : '');
$bulk_form_id = 'leadlookup-bulk-delete-form' . ($form_prefix !== '' ? '-' . trim($form_prefix, '_') : '');
$summary_counts = isset($summary_counts) && is_array($summary_counts) ? $summary_counts : ['total' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'pending' => 0];

$showActions = $can_delete_logs || $allow_retry;
$showBulk = $can_delete_logs;
$isPhoneReport = (($filters['log_type'] ?? '') === 'phone_lookup');
$columnCount = ($showBulk ? 1 : 0) + 6 + ($showActions ? 1 : 0);

if (!function_exists('leadlookup_log_filter_url')) {
    function leadlookup_log_filter_url($page, $filters, $perPage, $base_url, $prefix = '') {
        $hash = '';
        if (strpos($base_url, '#') !== false) {
            $parts = explode('#', $base_url, 2);
            $base_url = $parts[0];
            $hash = '#' . $parts[1];
        }
        $tabName = ltrim($hash, '#');
        if (empty($tabName)) {
            $tabName = ($prefix === 'lookup_') ? 'tab_lookup_logs' : 'tab_creator_logs';
            $hash = '#' . $tabName;
        }
        $query = array_filter([
            'tab' => $tabName,
            $prefix . 'status' => $filters['status'] ?? '',
            $prefix . 'search' => $filters['search'] ?? '',
            $prefix . 'date_from' => $filters['date_from'] ?? '',
            $prefix . 'date_to' => $filters['date_to'] ?? '',
            $prefix . 'per_page' => $perPage,
            $prefix . 'page' => $page,
            'export' => $filters['export'] ?? '',
        ], static function ($v) { return $v !== '' && $v !== null; });
        return $base_url . (!empty($query) ? '?' . http_build_query($query) : '') . $hash;
    }
}

if (!function_exists('leadlookup_log_summary_value')) {
    function leadlookup_log_summary_value($log, $key) {
        $summary = json_decode((string) ($log['payload_summary'] ?? ''), true);
        return is_array($summary) && isset($summary[$key]) ? $summary[$key] : '';
    }
}
?>

<style>
    .leadlookup-summary-overview { margin-bottom: 20px; }
    .leadlookup-summary-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-size: 13px; font-weight: 500; text-decoration: none !important; transition: all 0.15s ease; cursor: pointer; }
    .leadlookup-summary-btn:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; }
    .leadlookup-summary-btn.active { font-weight: 700; box-shadow: inset 0 2px 4px rgba(0,0,0,0.06); }
    .leadlookup-summary-btn .badge-count { font-weight: 700; font-size: 13px; }
    
    .leadlookup-report-table-wrap { width:100%; overflow-x:auto; border:1px solid #e5e7eb; border-radius:6px; margin-top:12px; }
    .leadlookup-report-table { margin-bottom:0; min-width:1080px; background:#fff; }
    .leadlookup-report-table th, .leadlookup-report-table td { vertical-align:middle !important; }
    .leadlookup-report-table .leadlookup-error-cell { min-width:240px; max-width:480px; white-space:normal; word-break:break-word; }
    .leadlookup-empty-row td { padding:28px !important; text-align:center; }
    .leadlookup-filter-actions { padding-top:25px; }
    @media (max-width: 991px) {
        .leadlookup-filter-actions { padding-top:0; text-align:left !important; }
        .leadlookup-search-wrap { margin-top:10px; max-width:none !important; width:100%; }
    }
</style>

<!-- Top Status Summary Overview Bar -->
<div class="leadlookup-summary-overview">
    <div class="tw-grid tw-gap-2 sm:tw-grid-flow-col sm:tw-auto-cols-max tw-overflow-x-auto">
        <?php if ($isPhoneReport) { ?>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => '']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo empty($filters['status']) ? 'active tw-bg-neutral-100 tw-border-neutral-400' : ''; ?>">
                <span class="badge-count text-neutral-800"><?php echo (int) ($summary_counts['total'] ?? 0); ?></span> Total Lookups
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'success']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'success' ? 'active tw-bg-emerald-50 tw-border-emerald-400' : ''; ?>">
                <span class="badge-count text-success"><?php echo (int) ($summary_counts['success'] ?? 0); ?></span> Found / Success
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'failed']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'failed' ? 'active tw-bg-rose-50 tw-border-rose-400' : ''; ?>">
                <span class="badge-count text-danger"><?php echo (int) ($summary_counts['failed'] ?? 0); ?></span> Not Found / Failed
            </a>
        <?php } else { ?>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => '']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo empty($filters['status']) ? 'active tw-bg-neutral-100 tw-border-neutral-400' : ''; ?>">
                <span class="badge-count text-neutral-800"><?php echo (int) ($summary_counts['total'] ?? 0); ?></span> Total Webhook Logs
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'success']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'success' ? 'active tw-bg-emerald-50 tw-border-emerald-400' : ''; ?>">
                <span class="badge-count text-success"><?php echo (int) ($summary_counts['success'] ?? 0); ?></span> Lead Created (Success)
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'skipped']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'skipped' ? 'active tw-bg-sky-50 tw-border-sky-400' : ''; ?>">
                <span class="badge-count text-info"><?php echo (int) ($summary_counts['skipped'] ?? 0); ?></span> Skipped (Duplicate Lead)
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'failed']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'failed' ? 'active tw-bg-rose-50 tw-border-rose-400' : ''; ?>">
                <span class="badge-count text-danger"><?php echo (int) ($summary_counts['failed'] ?? 0); ?></span> Creation Failed
            </a>
            <a href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['status' => 'pending']), $pagination['per_page'], $base_url, $form_prefix); ?>"
               class="leadlookup-summary-btn <?php echo ($filters['status'] ?? '') === 'pending' ? 'active tw-bg-amber-50 tw-border-amber-400' : ''; ?>">
                <span class="badge-count text-warning"><?php echo (int) ($summary_counts['pending'] ?? 0); ?></span> Pending
            </a>
        <?php } ?>
    </div>
</div>

<div class="clearfix tw-mb-2">
    <h4 class="pull-left bold tw-my-0"><?php echo e($table_title); ?></h4>
    <?php if ($showBulk) { ?>
        <div class="pull-right">
            <button type="submit" form="<?php echo $bulk_form_id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected sync logs?');">
                <i class="fa-solid fa-trash tw-mr-1"></i> Delete Selected
            </button>
        </div>
    <?php } ?>
</div>

<!-- Filters Bar -->
<?php echo form_open($base_url, ['method' => 'get', 'id' => $form_id]); ?>
<input type="hidden" name="tab" value="<?php echo $isPhoneReport ? 'tab_lookup_logs' : 'tab_creator_logs'; ?>">
<div class="row mtop15">
    <div class="col-md-3 col-sm-6">
        <label>Status Filter</label>
        <select name="<?php echo $form_prefix; ?>status" class="form-control selectpicker" data-width="100%">
            <option value="" <?php echo empty($filters['status']) ? 'selected' : ''; ?>>All Statuses</option>
            <?php if ($isPhoneReport) { ?>
                <option value="success" <?php echo ($filters['status'] ?? '') === 'success' ? 'selected' : ''; ?>>Found / Success</option>
                <option value="failed" <?php echo ($filters['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Not Found / Failed</option>
            <?php } else { ?>
                <option value="success" <?php echo ($filters['status'] ?? '') === 'success' ? 'selected' : ''; ?>>Lead Created (Success)</option>
                <option value="skipped" <?php echo ($filters['status'] ?? '') === 'skipped' ? 'selected' : ''; ?>>Skipped / Duplicate Lead</option>
                <option value="failed" <?php echo ($filters['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Creation Failed</option>
                <option value="pending" <?php echo ($filters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-3 col-sm-6"><?php echo render_date_input($form_prefix . 'date_from', 'Date from', $filters['date_from'] ?? ''); ?></div>
    <div class="col-md-3 col-sm-6"><?php echo render_date_input($form_prefix . 'date_to', 'Date to', $filters['date_to'] ?? ''); ?></div>
    <div class="col-md-3 col-sm-6 text-right leadlookup-filter-actions">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter tw-mr-1"></i> Filter</button>
        <a href="<?php echo $base_url; ?>" class="btn btn-default"><i class="fa-solid fa-rotate-left tw-mr-1"></i> Reset</a>
    </div>
</div>

<div class="row mtop15 mbot15">
    <div class="col-md-2 col-sm-4">
        <label class="tw-text-xs tw-text-neutral-500">Per page</label>
        <select name="<?php echo $form_prefix; ?>per_page" class="form-control selectpicker" data-width="100%" onchange="document.getElementById('<?php echo $form_id; ?>').submit();">
            <?php foreach ([10,25,50,100] as $pp) { ?>
                <option value="<?php echo $pp; ?>" <?php echo (int) $pagination['per_page'] === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-5 col-sm-8 tw-pt-5">
        <a class="btn btn-default" href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['export' => 'csv']), $pagination['per_page'], $base_url, $form_prefix); ?>">
            <i class="fa-solid fa-file-csv tw-mr-1 text-success"></i> Export CSV
        </a>
    </div>
    <div class="col-md-5 col-sm-12 tw-pt-5">
        <div class="input-group pull-right leadlookup-search-wrap" style="max-width:340px;">
            <span class="input-group-addon"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="<?php echo $form_prefix; ?>search" class="form-control" value="<?php echo e($filters['search'] ?? ''); ?>" placeholder="Search logs...">
        </div>
    </div>
</div>
<?php echo form_close(); ?>

<!-- Table Area -->
<?php echo form_open(admin_url('leadlookup/bulk_delete_logs'), ['id' => $bulk_form_id]); ?>
<input type="hidden" name="redirect" value="<?php echo e($delete_redirect); ?>">
<div class="table-responsive mtop15">
    <table class="table table-striped table-bordered text-left">
        <thead>
            <tr>
                <?php if ($showBulk) { ?>
                    <th style="width:38px; text-align:center;">
                        <div class="checkbox mass_select_all_wrap">
                            <input type="checkbox" id="leadlookup-select-all-<?php echo $form_prefix; ?>" onclick="var c = this.checked; var els = document.getElementsByClassName('leadlookup-cb-<?php echo $form_prefix; ?>'); for(var i=0; i<els.length; i++){ els[i].checked = c; }">
                            <label></label>
                        </div>
                    </th>
                <?php } ?>
                <th style="width:75px;">ID</th>
                <th style="width:130px;">Status</th>
                <?php if ($isPhoneReport) { ?>
                    <th>Phone</th>
                    <th style="width:100px;">Results</th>
                    <th style="width:130px;">IP Address</th>
                <?php } else { ?>
                    <th>Conversation ID</th>
                    <th>Lead ID</th>
                    <th>Matched By</th>
                <?php } ?>
                <th style="width:170px;">Created At</th>
                <?php if ($showActions) { ?>
                    <th style="width:130px; text-align:right; white-space:nowrap;">Actions</th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) { ?>
                <tr class="leadlookup-empty-row">
                    <td colspan="<?php echo (int) $columnCount; ?>" class="text-muted text-center">No logs found matching the selected filter.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($logs as $log) { ?>
                    <?php
                    $status = (string) ($log['status'] ?? 'pending');
                    $statusClass = $status === 'success' ? 'success' : ($status === 'skipped' ? 'info' : ($status === 'pending' ? 'warning' : 'danger'));
                    $statusLabel = $status === 'skipped' ? 'Skipped / Duplicate' : ucfirst($status);
                    $crmLeadId = !empty($log['crm_lead_id']) ? (int) $log['crm_lead_id'] : 0;
                    $modalId = 'logModal_' . (int) $log['id'] . ($form_prefix !== '' ? '_' . trim($form_prefix, '_') : '');
                    ?>
                    <tr>
                        <?php if ($showBulk) { ?>
                            <td style="text-align:center;">
                                <div class="checkbox">
                                    <input type="checkbox" name="ids[]" value="<?php echo (int) $log['id']; ?>" class="leadlookup-cb-<?php echo $form_prefix; ?>">
                                    <label></label>
                                </div>
                            </td>
                        <?php } ?>
                        <td class="bold">#<?php echo (int) $log['id']; ?></td>
                        <td><span class="label label-<?php echo $statusClass; ?>"><?php echo e($statusLabel); ?></span></td>
                        <?php if ($isPhoneReport) { ?>
                            <td><code><?php echo e(leadlookup_log_summary_value($log, 'phone')); ?></code></td>
                            <td><span class="badge badge-default"><?php echo (int) leadlookup_log_summary_value($log, 'result_count'); ?></span></td>
                            <td><code><?php echo e(leadlookup_log_summary_value($log, 'ip')); ?></code></td>
                        <?php } else { ?>
                            <td><code><?php echo e($log['chatwoot_conversation_id'] ?? ''); ?></code></td>
                            <td>
                                <?php if ($crmLeadId > 0) { ?>
                                    <a href="<?php echo admin_url('leads/index/' . $crmLeadId); ?>" target="_blank" class="bold text-primary">
                                        <i class="fa-solid fa-user-tie tw-mr-1"></i>#<?php echo $crmLeadId; ?>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">-</span>
                                <?php } ?>
                            </td>
                            <td><?php echo !empty($log['matched_by']) ? '<span class="label label-default">' . e($log['matched_by']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                        <?php } ?>
                        <td class="text-muted"><?php echo e($log['created_at'] ?? ''); ?></td>
                        <?php if ($showActions) { ?>
                            <td style="text-align:right; white-space:nowrap;">
                                <div class="tw-inline-flex tw-items-center tw-gap-1 tw-justify-end" style="white-space:nowrap;">
                                    <button type="button" class="btn btn-default btn-icon" data-toggle="modal" data-target="#<?php echo $modalId; ?>" title="View Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <?php if ($allow_retry && !$isPhoneReport) { ?>
                                        <a href="<?php echo admin_url('leadlookup/retry_log/' . (int) $log['id']); ?>" class="btn btn-default btn-icon" data-toggle="tooltip" title="Retry webhook lead creation"><i class="fa-solid fa-rotate"></i></a>
                                    <?php } ?>
                                    <?php if ($can_delete_logs) { ?>
                                        <a href="<?php echo admin_url('leadlookup/delete_log/' . (int) $log['id'] . '?redirect=' . urlencode($delete_redirect)); ?>" class="btn btn-danger btn-icon" onclick="return confirm('Delete this sync log?');" title="Delete Log"><i class="fa-solid fa-trash"></i></a>
                                    <?php } ?>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php echo form_close(); ?>

<!-- Render Modals OUTSIDE Table and Form -->
<?php if (!empty($logs)) { ?>
    <?php foreach ($logs as $log) { ?>
        <?php
        $status = (string) ($log['status'] ?? 'pending');
        $statusClass = $status === 'success' ? 'success' : ($status === 'skipped' ? 'info' : ($status === 'pending' ? 'warning' : 'danger'));
        $statusLabel = $status === 'skipped' ? 'Skipped / Duplicate' : ucfirst($status);
        $crmLeadId = !empty($log['crm_lead_id']) ? (int) $log['crm_lead_id'] : 0;
        $modalId = 'logModal_' . (int) $log['id'] . ($form_prefix !== '' ? '_' . trim($form_prefix, '_') : '');
        ?>
        <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title bold">
                            Log Details #<?php echo (int) $log['id']; ?> 
                            <span class="label label-<?php echo $statusClass; ?> tw-ml-2"><?php echo e($statusLabel); ?></span>
                        </h4>
                    </div>
                    <div class="modal-body text-left">
                        <div class="row tw-mb-4">
                            <div class="col-md-3 col-xs-6">
                                <span class="text-muted">Log Type:</span><br>
                                <span class="bold"><?php echo e(ucwords(str_replace('_', ' ', $log['log_type'] ?? 'lead_create'))); ?></span>
                            </div>
                            <div class="col-md-3 col-xs-6">
                                <span class="text-muted">CRM Lead ID:</span><br>
                                <?php if ($crmLeadId > 0) { ?>
                                    <a href="<?php echo admin_url('leads/index/' . $crmLeadId); ?>" target="_blank" class="bold">#<?php echo $crmLeadId; ?></a>
                                <?php } else { ?>
                                    <span class="text-muted">None</span>
                                <?php } ?>
                            </div>
                            <div class="col-md-3 col-xs-6">
                                <span class="text-muted">Matched By:</span><br>
                                <span class="bold"><?php echo e($log['matched_by'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="col-md-3 col-xs-6">
                                <span class="text-muted">Created At:</span><br>
                                <span class="bold"><?php echo e($log['created_at'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($log['error_message'])) { ?>
                            <div class="alert alert-danger">
                                <h5 class="bold tw-mt-0 tw-mb-1"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i> Error Message</h5>
                                <div><?php echo e($log['error_message']); ?></div>
                            </div>
                        <?php } ?>

                        <?php if (!$isPhoneReport) { ?>
                            <div class="panel panel-default">
                                <div class="panel-heading bold">UniChat Webhook Metadata</div>
                                <div class="panel-body">
                                    <div class="row">
                                        <div class="col-md-3"><strong>Contact ID:</strong> <code><?php echo e($log['chatwoot_contact_id'] ?? 'N/A'); ?></code></div>
                                        <div class="col-md-3"><strong>Conversation ID:</strong> <code><?php echo e($log['chatwoot_conversation_id'] ?? 'N/A'); ?></code></div>
                                        <div class="col-md-3"><strong>Inbox ID:</strong> <code><?php echo e($log['chatwoot_inbox_id'] ?? 'N/A'); ?></code></div>
                                        <div class="col-md-3"><strong>Account ID:</strong> <code><?php echo e($log['chatwoot_account_id'] ?? 'N/A'); ?></code></div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($log['payload_summary'])) { ?>
                            <div class="form-group">
                                <label class="bold">Payload Summary:</label>
                                <pre class="tw-bg-neutral-900 tw-text-emerald-400 tw-p-3 tw-rounded tw-text-xs" style="max-height:180px; overflow-y:auto;"><?php
                                    $pSum = json_decode((string) $log['payload_summary'], true);
                                    echo e(json_encode($pSum, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                ?></pre>
                            </div>
                        <?php } ?>

                        <?php if (!empty($log['payload'])) { ?>
                            <div class="form-group">
                                <label class="bold">Full Raw Payload JSON:</label>
                                <pre class="tw-bg-neutral-900 tw-text-sky-300 tw-p-3 tw-rounded tw-text-xs" style="max-height:260px; overflow-y:auto;"><?php
                                    $pRaw = json_decode((string) $log['payload'], true);
                                    echo e($pRaw ? json_encode($pRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $log['payload']);
                                ?></pre>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="modal-footer">
                        <?php if ($allow_retry && !$isPhoneReport) { ?>
                            <a href="<?php echo admin_url('leadlookup/retry_log/' . (int) $log['id']); ?>" class="btn btn-primary pull-left"><i class="fa-solid fa-rotate tw-mr-1"></i> Retry Creation</a>
                        <?php } ?>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<!-- Pagination -->
<?php if ((int) $pagination['total_pages'] > 1) { ?>
    <div class="row mtop15">
        <div class="col-md-6">
            <span class="text-muted">Showing page <?php echo (int) $pagination['page']; ?> of <?php echo (int) $pagination['total_pages']; ?> (Total <?php echo (int) $pagination['total_rows']; ?> records)</span>
        </div>
        <div class="col-md-6 text-right">
            <ul class="pagination mtop0 mbot0">
                <?php for ($p = 1; $p <= (int) $pagination['total_pages']; $p++) { ?>
                    <li class="<?php echo $p === (int) $pagination['page'] ? 'active' : ''; ?>">
                        <a href="<?php echo leadlookup_log_filter_url($p, $filters, $pagination['per_page'], $base_url, $form_prefix); ?>"><?php echo $p; ?></a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
<?php } ?>
