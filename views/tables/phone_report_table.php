<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

$aColumns = [
    db_prefix() . 'leadlookup_sync_logs.id as id',
    db_prefix() . 'leadlookup_sync_logs.status as status',
    db_prefix() . 'leadlookup_sync_logs.payload_summary as payload_summary',
    db_prefix() . 'leadlookup_sync_logs.crm_lead_id as crm_lead_id',
    db_prefix() . 'leadlookup_sync_logs.created_at as created_at',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'leadlookup_sync_logs';

$where = [
    "AND log_type = 'phone_lookup'",
];

// Support Date Filter from front-end
if ($CI->input->post('report_date')) {
    $date = to_sql_date($CI->input->post('report_date'));
    array_push($where, 'AND ' . db_prefix() . 'leadlookup_sync_logs.created_at LIKE "' . $CI->db->escape_like_str($date) . '%" ESCAPE \'!\'');
}

$join = [
    'LEFT JOIN ' . db_prefix() . 'leads ON ' . db_prefix() . 'leads.id = ' . db_prefix() . 'leadlookup_sync_logs.crm_lead_id',
];

$additionalSelect = [
    db_prefix() . 'leadlookup_sync_logs.error_message as error_message',
    db_prefix() . 'leadlookup_sync_logs.matched_by as matched_by',
    db_prefix() . 'leads.name as lead_name',
    db_prefix() . 'leads.phonenumber as lead_phonenumber',
    db_prefix() . 'leads.company as lead_company',
];

$result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);
$output  = $result['output'];
$rResult = $result['rResult'];

$canDelete = function_exists('has_permission') && has_permission('leadlookup_phone_reports', '', 'delete');

foreach ($rResult as $aRow) {
    $row = [];

    // 1. # ID
    $row[] = $aRow['id'];

    // 2. Status
    $status = $aRow['status'];
    $statusBadge = 'default';
    if ($status === 'success') {
        $statusBadge = 'success';
    } elseif ($status === 'failed') {
        $statusBadge = 'danger';
    } elseif ($status === 'skipped') {
        $statusBadge = 'warning';
    } elseif ($status === 'pending') {
        $statusBadge = 'info';
    }
    $row[] = '<span class="label label-' . $statusBadge . '">' . ucfirst(e($status)) . '</span>';

    // 3. Phone Number
    $summary = json_decode((string) $aRow['payload_summary'], true);
    $phone = is_array($summary) && !empty($summary['phone']) ? $summary['phone'] : 'N/A';
    $row[] = '<a href="tel:' . e($phone) . '" class="tw-font-medium">' . e($phone) . '</a>';

    // 4. Matched Lead
    $crmLeadId = (int) $aRow['crm_lead_id'];
    if ($crmLeadId > 0) {
        $leadName = !empty($aRow['lead_name']) ? $aRow['lead_name'] : ('#' . $crmLeadId);
        $leadUrl = admin_url('leads/index/' . $crmLeadId);
        $matchedHtml = '<a href="' . $leadUrl . '" onclick="init_lead(' . $crmLeadId . '); return false;" class="tw-font-semibold text-primary">'
            . '<i class="fa-regular fa-user tw-mr-1"></i>' . e($leadName) 
            . '</a>';
        if (!empty($aRow['lead_company'])) {
            $matchedHtml .= '<br><small class="text-muted">' . e($aRow['lead_company']) . '</small>';
        }
    } else {
        $matchedHtml = '<span class="text-muted">' . _l('not_found') . '</span>';
    }
    $row[] = $matchedHtml;

    // 5. IP Address
    $ip = is_array($summary) && !empty($summary['ip']) ? $summary['ip'] : '-';
    $row[] = '<code>' . e($ip) . '</code>';

    // 6. Error / Note
    $error = !empty($aRow['error_message']) ? $aRow['error_message'] : '-';
    if ($error !== '-') {
        $row[] = '<span class="text-danger tw-text-xs"><i class="fa-solid fa-triangle-exclamation tw-mr-1"></i>' . e($error) . '</span>';
    } else {
        $row[] = '<span class="text-muted">-</span>';
    }

    // 7. Created At
    $row[] = _dt($aRow['created_at']);

    // 8. Options
    $options = '<div class="tw-flex tw-items-center tw-space-x-2">';
    $options .= '<button type="button" class="btn btn-default btn-icon view-phone-log-details" data-id="' . $aRow['id'] . '" title="View Details"><i class="fa-regular fa-eye"></i></button>';
    if ($canDelete) {
        $options .= '<a href="' . admin_url('leadlookup/delete_log/' . $aRow['id'] . '?redirect=phone_report') . '" class="btn btn-danger btn-icon _delete" title="Delete Log"><i class="fa-regular fa-trash-can"></i></a>';
    }
    $options .= '</div>';
    $row[] = $options;

    $output['aaData'][] = $row;
}
