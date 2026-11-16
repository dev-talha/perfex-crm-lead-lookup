<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$summary = json_decode((string) ($log['payload_summary'] ?? ''), true);
$phone = is_array($summary) && !empty($summary['phone']) ? $summary['phone'] : 'N/A';
$ip = is_array($summary) && !empty($summary['ip']) ? $summary['ip'] : 'N/A';
$userAgent = is_array($summary) && !empty($summary['user_agent']) ? $summary['user_agent'] : 'N/A';
$resultCount = is_array($summary) && isset($summary['result_count']) ? (int) $summary['result_count'] : 0;
$status = (string) ($log['status'] ?? 'pending');
$statusClass = $status === 'success' ? 'success' : ($status === 'skipped' ? 'warning' : ($status === 'pending' ? 'info' : 'danger'));
$crmLeadId = !empty($log['crm_lead_id']) ? (int) $log['crm_lead_id'] : 0;
?>
<div class="row">
    <div class="col-md-6">
        <table class="table table-bordered table-striped">
            <tbody>
                <tr>
                    <td class="bold" style="width: 35%;">Log ID</td>
                    <td>#<?php echo (int) $log['id']; ?></td>
                </tr>
                <tr>
                    <td class="bold">Status</td>
                    <td><span class="label label-<?php echo $statusClass; ?>"><?php echo ucfirst(html_escape($status)); ?></span></td>
                </tr>
                <tr>
                    <td class="bold">Phone Queried</td>
                    <td><a href="tel:<?php echo html_escape($phone); ?>" class="bold"><?php echo html_escape($phone); ?></a></td>
                </tr>
                <tr>
                    <td class="bold">Results Found</td>
                    <td><span class="badge"><?php echo $resultCount; ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered table-striped">
            <tbody>
                <tr>
                    <td class="bold" style="width: 35%;">Matched Lead</td>
                    <td>
                        <?php if ($crmLeadId > 0) { ?>
                            <a href="<?php echo admin_url('leads/index/' . $crmLeadId); ?>" onclick="init_lead(<?php echo $crmLeadId; ?>); return false;" class="bold">
                                <i class="fa-regular fa-user tw-mr-1"></i> Lead #<?php echo $crmLeadId; ?>
                            </a>
                        <?php } else { ?>
                            <span class="text-muted">Not Found</span>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <td class="bold">Client IP</td>
                    <td><code><?php echo html_escape($ip); ?></code></td>
                </tr>
                <tr>
                    <td class="bold">Date & Time</td>
                    <td><?php echo _dt($log['created_at']); ?></td>
                </tr>
                <tr>
                    <td class="bold">User Agent</td>
                    <td class="tw-text-xs tw-break-all text-muted"><?php echo html_escape($userAgent); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($log['error_message'])) { ?>
    <div class="alert alert-danger tw-mt-2 tw-mb-2">
        <h5 class="bold tw-mt-0 tw-mb-1"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i> Error / Note:</h5>
        <div><?php echo html_escape($log['error_message']); ?></div>
    </div>
<?php } ?>

<?php if (!empty($log['payload_summary'])) { ?>
    <div class="form-group tw-mt-3">
        <label class="bold text-muted">Raw Summary JSON:</label>
        <pre class="tw-bg-neutral-900 tw-text-emerald-400 tw-p-3 tw-rounded tw-text-xs" style="max-height:160px; overflow-y:auto;"><?php
            echo html_escape(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        ?></pre>
    </div>
<?php } ?>
