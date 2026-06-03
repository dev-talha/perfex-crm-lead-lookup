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
$showActions = $can_delete_logs || $allow_retry;
$showBulk = $can_delete_logs;
$isPhoneReport = (($filters['log_type'] ?? '') === 'phone_lookup');
$columnCount = ($showBulk ? 1 : 0) + 9 + ($showActions ? 1 : 0);

if (!function_exists('leadlookup_log_filter_url')) {
    function leadlookup_log_filter_url($page, $filters, $perPage, $base_url) {
        $query = array_filter([
            'status' => $filters['status'] ?? '',
            'search' => $filters['search'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'per_page' => $perPage,
            'page' => $page,
            'export' => $filters['export'] ?? '',
        ], static function ($v) { return $v !== '' && $v !== null; });
        return $base_url . (!empty($query) ? '?' . http_build_query($query) : '');
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
    .leadlookup-report-table-wrap { width:100%; overflow-x:auto; border:1px solid #e5e7eb; border-radius:4px; }
    .leadlookup-report-table { margin-bottom:0; min-width:1080px; background:#fff; }
    .leadlookup-report-table th, .leadlookup-report-table td { vertical-align:middle !important; }
    .leadlookup-report-table .leadlookup-error-cell { min-width:260px; max-width:560px; white-space:normal; word-break:break-word; }
    .leadlookup-empty-row td { padding:22px !important; text-align:center; }
    .leadlookup-filter-actions { padding-top:25px; }
    @media (max-width: 991px) {
        .leadlookup-filter-actions { padding-top:0; text-align:left !important; }
        .leadlookup-search-wrap { margin-top:10px; max-width:none !important; width:100%; }
    }
</style>

<div class="clearfix">
    <h4 class="pull-left"><?php echo e($table_title); ?></h4>
</div>

<?php echo form_open($base_url, ['method' => 'get', 'id' => 'leadlookup-filter-form']); ?>
<div class="row mtop15">
    <div class="col-md-2 col-sm-6">
        <label>Status</label>
        <select name="status" class="form-control selectpicker">
            <option value="" <?php echo empty($filters['status']) ? 'selected' : ''; ?>>All</option>
            <option value="pending" <?php echo ($filters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="success" <?php echo ($filters['status'] ?? '') === 'success' ? 'selected' : ''; ?>>Success</option>
            <option value="failed" <?php echo ($filters['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
            <option value="skipped" <?php echo ($filters['status'] ?? '') === 'skipped' ? 'selected' : ''; ?>>Skipped/Duplicate</option>
        </select>
    </div>
    <div class="col-md-3 col-sm-6"><?php echo render_date_input('date_from', 'Date from', $filters['date_from'] ?? ''); ?></div>
    <div class="col-md-3 col-sm-6"><?php echo render_date_input('date_to', 'Date to', $filters['date_to'] ?? ''); ?></div>
    <div class="col-md-4 col-sm-6 text-right leadlookup-filter-actions">
        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
        <a href="<?php echo $base_url; ?>" class="btn btn-default">Reset</a>
    </div>
</div>

<div class="row mtop15 mbot15">
    <div class="col-md-2 col-sm-4">
        <select name="per_page" class="form-control selectpicker" onchange="document.getElementById('leadlookup-filter-form').submit();">
            <?php foreach ([10,25,50,100] as $pp) { ?>
                <option value="<?php echo $pp; ?>" <?php echo (int) $pagination['per_page'] === $pp ? 'selected' : ''; ?>><?php echo $pp; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-5 col-sm-8">
        <a class="btn btn-default" href="<?php echo leadlookup_log_filter_url(1, array_merge($filters, ['export' => 'csv']), $pagination['per_page'], $base_url); ?>">Export</a>
        <?php if ($showBulk) { ?>
            <button type="submit" form="leadlookup-bulk-delete-form" class="btn btn-danger" onclick="return confirm('Delete selected sync logs?');"><i class="fa fa-trash"></i> Delete Selected</button>
        <?php } ?>
    </div>
    <div class="col-md-5 col-sm-12">
        <div class="input-group pull-right leadlookup-search-wrap" style="max-width:340px;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" name="search" class="form-control" value="<?php echo e($filters['search'] ?? ''); ?>" placeholder="Search..">
        </div>
    </div>
</div>
<?php echo form_close(); ?>

<?php echo form_open(admin_url('leadlookup/bulk_delete_logs'), ['id' => 'leadlookup-bulk-delete-form']); ?>
<input type="hidden" name="redirect" value="<?php echo e($delete_redirect); ?>">
<div class="leadlookup-report-table-wrap">
    <table class="table table-striped table-bordered leadlookup-report-table">
        <thead>
            <tr>
                <?php if ($showBulk) { ?><th style="width:35px;"><input type="checkbox" onclick="$('.leadlookup-log-check').prop('checked', this.checked);"></th><?php } ?>
                <th style="width:60px;">SL</th>
                <th style="width:70px;">ID</th>
                <th style="width:150px;">Time</th>
                <th style="width:110px;">Status</th>
                <?php if ($isPhoneReport) { ?>
                    <th style="width:180px;">Phone</th>
                    <th style="width:120px;">Result Count</th>
                    <th style="width:95px;">Lead</th>
                    <th style="width:90px;">Attempts</th>
                    <th class="leadlookup-error-cell">Error</th>
                <?php } else { ?>
                    <th style="width:150px;">Chatwoot Contact</th>
                    <th style="width:130px;">Conversation</th>
                    <th style="width:95px;">Lead</th>
                    <th style="width:90px;">Attempts</th>
                    <th class="leadlookup-error-cell">Error</th>
                <?php } ?>
                <?php if ($showActions) { ?><th style="width:150px;">Action</th><?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) { ?>
                <tr class="leadlookup-empty-row"><td colspan="<?php echo (int) $columnCount; ?>" class="text-muted">No logs found for the selected filters.</td></tr>
            <?php } else { ?>
                <?php $sl = ((int) $pagination['page'] - 1) * (int) $pagination['per_page']; ?>
                <?php foreach ($logs as $log) { $sl++; ?>
                    <?php $statusClass = $log['status'] === 'success' ? 'success' : ($log['status'] === 'failed' ? 'danger' : ($log['status'] === 'skipped' ? 'warning' : 'default')); ?>
                    <tr>
                        <?php if ($showBulk) { ?><td><input type="checkbox" class="leadlookup-log-check" name="ids[]" value="<?php echo (int) $log['id']; ?>"></td><?php } ?>
                        <td><?php echo (int) $sl; ?></td>
                        <td><?php echo (int) $log['id']; ?></td>
                        <td style="white-space:nowrap;"><?php echo e($log['created_at']); ?></td>
                        <td><span class="label label-<?php echo $statusClass; ?>"><?php echo e($log['status']); ?></span></td>
                        <?php if ($isPhoneReport) { ?>
                            <td><?php echo e(leadlookup_log_summary_value($log, 'phone')); ?></td>
                            <td><?php echo e(leadlookup_log_summary_value($log, 'result_count')); ?></td>
                            <td><?php echo !empty($log['crm_lead_id']) ? '<a href="' . admin_url('leads/index/' . (int) $log['crm_lead_id']) . '">#' . (int) $log['crm_lead_id'] . '</a>' : '-'; ?></td>
                            <td><?php echo (int) $log['attempts']; ?></td>
                            <td class="leadlookup-error-cell"><?php echo e($log['error_message']); ?></td>
                        <?php } else { ?>
                            <td><?php echo e($log['chatwoot_contact_id']); ?></td>
                            <td><?php echo e($log['chatwoot_conversation_id']); ?></td>
                            <td><?php echo !empty($log['crm_lead_id']) ? '<a href="' . admin_url('leads/index/' . (int) $log['crm_lead_id']) . '">#' . (int) $log['crm_lead_id'] . '</a>' : '-'; ?></td>
                            <td><?php echo (int) $log['attempts']; ?></td>
                            <td class="leadlookup-error-cell"><?php echo e($log['error_message']); ?></td>
                        <?php } ?>
                        <?php if ($showActions) { ?>
                            <td style="white-space:nowrap;">
                                <?php if ($allow_retry && $log['status'] !== 'success' && $log['status'] !== 'skipped' && ($log['log_type'] ?? 'lead_create') === 'lead_create') { ?>
                                    <a class="btn btn-default btn-xs" href="<?php echo admin_url('leadlookup/retry_log/' . (int) $log['id']); ?>">Retry</a>
                                <?php } ?>
                                <?php if ($can_delete_logs) { ?>
                                    <a class="btn btn-danger btn-xs" onclick="return confirm('Delete this sync log?');" href="<?php echo admin_url('leadlookup/delete_log/' . (int) $log['id'] . '?redirect=' . $delete_redirect); ?>">Delete</a>
                                <?php } ?>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php echo form_close(); ?>

<div class="row mtop15">
    <div class="col-md-6 text-muted" style="padding-top:8px;">Total: <?php echo (int) $pagination['total_rows']; ?></div>
    <div class="col-md-6 text-right">
        <?php if ((int) $pagination['total_pages'] > 1) { ?>
            <ul class="pagination mtop0">
                <?php $page = (int) $pagination['page']; $totalPages = (int) $pagination['total_pages']; ?>
                <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>"><a href="<?php echo $page <= 1 ? '#' : leadlookup_log_filter_url($page - 1, $filters, $pagination['per_page'], $base_url); ?>">&laquo;</a></li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) { ?>
                    <li class="<?php echo $i === $page ? 'active' : ''; ?>"><a href="<?php echo leadlookup_log_filter_url($i, $filters, $pagination['per_page'], $base_url); ?>"><?php echo $i; ?></a></li>
                <?php } ?>
                <li class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a href="<?php echo $page >= $totalPages ? '#' : leadlookup_log_filter_url($page + 1, $filters, $pagination['per_page'], $base_url); ?>">&raquo;</a></li>
            </ul>
        <?php } ?>
    </div>
</div>
