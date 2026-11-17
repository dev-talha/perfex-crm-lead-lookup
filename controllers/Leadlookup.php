<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Leadlookup extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('leadlookup/leadlookup_model');
        $this->load->model('leads_model');
        $this->config->load('leadlookup/leadlookup');
        if (function_exists('leadlookup_create_sync_logs_table')) {
            leadlookup_create_sync_logs_table();
        }
    }

    /** Existing endpoint kept unchanged. */
    public function by_phone()
    {
        $this->enforce_api_key();
        $phone = $this->sanitize_phone((string) $this->input->get('phone', true));
        if ($phone === '') {
            return $this->json_error(400, 'Query parameter "phone" is required.');
        }
        $leads = $this->leadlookup_model->find_leads_by_phone($phone);
        if (empty($leads)) {
            return $this->json_error(404, 'No leads found for the provided phone number.');
        }
        return $this->json_success($leads, 200);
    }

    /** POST /leadlookup/create_from_chatwoot */
    public function create_from_chatwoot()
    {
        $rawBody = (string) $this->input->raw_input_stream;
        $method = strtoupper((string) $this->input->method(true));

        if ($method !== 'POST') {
            $this->leadlookup_model->create_sync_log(['error' => 'HTTP method ' . $method . ' used.'], 'failed', 'Only POST requests are allowed. Received ' . $method . ' request.', $rawBody);
            return $this->json_error(405, 'Only POST requests are allowed.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->leadlookup_model->create_sync_log(['raw_body' => $rawBody], 'failed', 'Invalid or empty JSON payload received.', $rawBody);
            return $this->json_error(400, 'Invalid JSON payload. Payload must be valid JSON in application/json format.');
        }

        // Create the sync log before domain/IP validation so every valid JSON hit
        // appears on the debug page, including rejected requests.
        $logId = $this->leadlookup_model->create_sync_log($payload, 'pending', '', $rawBody);

        $domainCheck = $this->validate_allowed_domain($payload);
        if (!$domainCheck['ok']) {
            $this->leadlookup_model->update_sync_log($logId, [
                'status' => 'failed',
                'error_message' => $domainCheck['message'],
            ]);
            return $this->json_error(403, $domainCheck['message'], [
                'sync_log_id' => $logId,
                'detected_sources' => $domainCheck['detected_sources'] ?? [],
            ]);
        }

        // Webhook lead creation is intentionally protected only by allowed domain/IP rules.
        // Token and HMAC checks were removed by request. Keep POST, JSON, request
        // domain/IP, required-field and sanitization validation active.

        $result = $this->sync_chatwoot_payload($payload, (int) $logId, $domainCheck['domain']);

        if (!$result['ok']) {
            return $this->json_error($result['http_code'], $result['message'], [
                'sync_log_id' => $logId,
                'retryable' => true,
            ]);
        }

        return $this->json_success([
            'sync_log_id' => $logId,
            'created' => $result['created'],
            'lead' => $result['lead'],
            'message' => $result['message'],
        ], !empty($result['created']) ? 201 : 200);
    }

    /** Administrator-only settings, mapping, logs, and retry panel. */
    public function settings()
    {
        if (!$this->is_admin_allowed()) {
            access_denied('Lead Lookup');
        }

        if (function_exists('leadlookup_create_sync_logs_table')) {
            leadlookup_create_sync_logs_table();
        }

        if ($this->input->post()) {
            $settings = $this->input->post('settings');
            if (!is_array($settings)) {
                $settings = [];
            }

            // Process assignee staff mapping array into JSON
            if (isset($settings['leadlookup_assignee_staff_mapping']) && is_array($settings['leadlookup_assignee_staff_mapping'])) {
                $rawMap = $settings['leadlookup_assignee_staff_mapping'];
                $cleanMap = [];
                if (isset($rawMap['unichat_id'], $rawMap['staff_id']) && is_array($rawMap['unichat_id'])) {
                    foreach ($rawMap['unichat_id'] as $idx => $uId) {
                        $uId = trim((string) $uId);
                        $sId = trim((string) ($rawMap['staff_id'][$idx] ?? ''));
                        if ($uId !== '' && $sId !== '') {
                            $cleanMap[$uId] = $sId;
                        }
                    }
                } else {
                    foreach ($rawMap as $k => $v) {
                        $k = trim((string) $k);
                        $v = trim((string) $v);
                        if ($k !== '' && $v !== '') {
                            $cleanMap[$k] = $v;
                        }
                    }
                }
                update_option('leadlookup_assignee_staff_mapping', json_encode($cleanMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                unset($settings['leadlookup_assignee_staff_mapping']);
            }

            // Process channel source mapping array into JSON
            if (isset($settings['leadlookup_channel_source_mapping'])) {
                $cleanChanMap = [];
                if (is_array($settings['leadlookup_channel_source_mapping'])) {
                    $rawChanMap = $settings['leadlookup_channel_source_mapping'];
                    if (isset($rawChanMap['channel'], $rawChanMap['source_id']) && is_array($rawChanMap['channel'])) {
                        foreach ($rawChanMap['channel'] as $idx => $chan) {
                            $chan = trim((string) $chan);
                            $sId = trim((string) ($rawChanMap['source_id'][$idx] ?? ''));
                            if ($chan !== '' && $sId !== '') {
                                $cleanChanMap[$chan] = $sId;
                            }
                        }
                    } else {
                        foreach ($rawChanMap as $k => $v) {
                            $k = trim((string) $k);
                            $v = trim((string) $v);
                            if ($k !== '' && $v !== '') {
                                $cleanChanMap[$k] = $v;
                            }
                        }
                    }
                }
                update_option('leadlookup_channel_source_mapping', json_encode($cleanChanMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                unset($settings['leadlookup_channel_source_mapping']);
            }

            $allowed = [
                'leadlookup_api_key',
                'leadlookup_chatwoot_allowed_domains',
                'leadlookup_domain_ip_validation_enabled',
                'leadlookup_log_retention_days',
                'leadlookup_chatwoot_default_status_id',
                'leadlookup_chatwoot_default_source_id',
                'leadlookup_chatwoot_default_assigned_id',
                'leadlookup_chatwoot_save_payload',
                'leadlookup_duplicate_check_email_enabled',
                'leadlookup_duplicate_check_phone_enabled',
                'leadlookup_chatwoot_base_url',
                'leadlookup_chatwoot_account_id',
                'leadlookup_chatwoot_private_note_enabled',
                'leadlookup_chatwoot_api_token',
            ];

            foreach ($allowed as $option) {
                if (!array_key_exists($option, $settings)) {
                    continue;
                }

                $value = $settings[$option];
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                // Never log secret values. Store them only in Perfex options table.
                update_option($option, (string) $value);
            }

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('leadlookup/settings'));
        }

        // Auto purge all sync logs based on configured retention period
        $this->leadlookup_model->purge_old_sync_logs();

        $data['title'] = 'Lead Creator & Lookup Settings';
        $this->load->view('settings_page', $data);
    }

    /** Administrator-only debug/diagnostic page. */
    public function debug()
    {
        if (!$this->is_admin_allowed()) {
            access_denied('Lead Lookup');
        }

        if (function_exists('leadlookup_create_sync_logs_table')) {
            leadlookup_create_sync_logs_table();
        }

        $data['title'] = 'Lead Lookup Debug';
        $data['module_version'] = '1.4.2';
        $data['endpoint'] = site_url('leadlookup/create_from_chatwoot');
        $data['lookup_endpoint'] = site_url('leadlookup/by_phone');
        $data['allowed_raw'] = get_option('leadlookup_chatwoot_allowed_domains');
        $data['allowed_entries'] = $this->get_allowed_domains();
        $data['log_table_exists'] = function_exists('leadlookup_sync_logs_table_exists') ? leadlookup_sync_logs_table_exists() : false;
        $data['server'] = [
            'REMOTE_ADDR' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'HTTP_X_FORWARDED_FOR' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            'HTTP_X_REAL_IP' => isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : '',
            'HTTP_HOST' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'PHP_VERSION' => PHP_VERSION,
            'CI_VERSION' => defined('CI_VERSION') ? CI_VERSION : '',
        ];

        $testSource = trim((string) $this->input->get('source', true));
        $data['test_source'] = $testSource;
        $data['test_result'] = null;
        if ($testSource !== '') {
            $normalized = $this->normalize_domain($testSource);
            $matched = false;
            foreach ($data['allowed_entries'] as $allowed) {
                if ($this->source_matches_allowed_entry($normalized, $allowed)) {
                    $matched = true;
                    break;
                }
            }
            $data['test_result'] = [
                'normalized' => $normalized,
                'matched' => $matched,
            ];
        }

        $this->load->view('debug_page', $data);
    }

    /** Role-based lead create report page under Utilities menu. */
    public function report()
    {
        if (!$this->can_view_report()) {
            access_denied('Lead Create Report');
        }

        if (function_exists('leadlookup_create_sync_logs_table')) {
            leadlookup_create_sync_logs_table();
        }

        $retentionDays = $this->get_retention_days();
        $this->leadlookup_model->purge_old_sync_logs($retentionDays, 'lead_create');

        $getStatus = $this->input->get('status', true);
        $getSearch = $this->input->get('search', true);
        $getDateFrom = $this->input->get('date_from', true);
        $getDateTo = $this->input->get('date_to', true);

        $filters = [
            'log_type' => 'lead_create',
            'status' => trim((string) ($getStatus !== null && $getStatus !== '' ? $getStatus : $this->input->get('creator_status', true))),
            'search' => trim((string) ($getSearch !== null && $getSearch !== '' ? $getSearch : $this->input->get('creator_search', true))),
            'date_from' => trim((string) ($getDateFrom !== null && $getDateFrom !== '' ? $getDateFrom : $this->input->get('creator_date_from', true))),
            'date_to' => trim((string) ($getDateTo !== null && $getDateTo !== '' ? $getDateTo : $this->input->get('creator_date_to', true))),
        ];
        if ((string) $this->input->get('export', true) === 'csv') {
            $this->export_sync_logs_csv($filters, 'leadlookup-lead-create-report.csv', 'lead');
            return;
        }
        $perPage = (int) ($this->input->get('per_page', true) ?? $this->input->get('creator_per_page', true));
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $page = max(1, (int) ($this->input->get('page', true) ?? $this->input->get('creator_page', true)));
        $totalRows = $this->leadlookup_model->count_sync_logs_filtered($filters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $data['title'] = 'Lead Create Report';
        $data['report_title'] = 'Lead Create Report';
        $data['report_description'] = 'UniChat webhook lead creation logs. Logs older than ' . $retentionDays . ' days are automatically purged.';
        $data['table_title'] = 'Lead Create Activity Logs';
        $data['filters'] = $filters;
        $data['logs'] = $this->leadlookup_model->get_sync_logs_filtered($filters, $perPage, $offset);
        $data['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ];
        $data['summary_counts'] = $this->leadlookup_model->get_sync_logs_summary('lead_create', $filters);
        $data['can_delete_logs'] = $this->can_delete_report_logs();
        $data['base_url'] = admin_url('leadlookup/report');
        $data['delete_redirect'] = 'report';
        $data['allow_retry'] = true;
        $this->load->view('report_page', $data);
    }

    /** Standalone role-based phone lookup report page under Utilities menu. */
    public function phone_report()
    {
        if (!$this->can_view_phone_report()) {
            access_denied('Phone Lookup Report');
        }

        if (function_exists('leadlookup_create_sync_logs_table')) {
            leadlookup_create_sync_logs_table();
        }

        $retentionDays = $this->get_retention_days();
        $this->leadlookup_model->purge_old_sync_logs($retentionDays, 'phone_lookup');

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('leadlookup', 'tables/phone_report_table'));
        }

        $data['title'] = 'Phone Lookup Report';
        $data['can_delete_logs'] = $this->can_delete_phone_report_logs();
        $data['retention_days'] = $retentionDays;

        $this->load->view('phone_report_page', $data);
    }

    /** AJAX details modal for single phone lookup log */
    public function phone_log_details($id)
    {
        if (!$this->can_view_phone_report()) {
            ajax_access_denied();
        }

        $log = $this->leadlookup_model->get_sync_log((int) $id);
        if (!$log || $log['log_type'] !== 'phone_lookup') {
            echo '<div class="alert alert-danger">Log entry not found.</div>';
            return;
        }

        $data['log'] = $log;
        $this->load->view('phone_log_modal_content', $data);
    }

    /** Clear all phone lookup logs */
    public function clear_phone_logs()
    {
        if (!$this->can_delete_phone_report_logs()) {
            access_denied('Phone Lookup Report');
        }

        $this->db->where('log_type', 'phone_lookup');
        $this->db->delete(db_prefix() . 'leadlookup_sync_logs');

        set_alert('success', 'All phone lookup search logs cleared successfully.');
        redirect(admin_url('leadlookup/phone_report'));
    }

    /** Admin manual retry for failed/pending logs. */
    public function retry_log($id)
    {
        if (!$this->is_admin_allowed()) {
            return $this->json_error(403, 'Unauthorized action.');
        }

        $log = $this->leadlookup_model->get_sync_log((int) $id);
        if (!$log) {
            return $this->json_error(404, 'Sync log not found.');
        }

        $payload = json_decode((string) $log['payload'], true);
        if (!is_array($payload)) {
            return $this->json_error(422, 'Stored payload is missing or invalid.');
        }

        $result = $this->sync_chatwoot_payload($payload, (int) $id, 'manual_retry');
        if (!$result['ok']) {
            return $this->json_error($result['http_code'], $result['message']);
        }
        return $this->json_success($result, 200);
    }

    public function delete_log($id)
    {
        $redirectKey = (string) $this->input->get('redirect', true);
        $canDelete = $redirectKey === 'phone_report' ? $this->can_delete_phone_report_logs() : ($redirectKey === 'settings' ? $this->is_admin_allowed() : $this->can_delete_report_logs());
        if (!$canDelete) {
            access_denied('Lead Lookup');
        }
        $this->leadlookup_model->delete_sync_log((int) $id);
        set_alert('success', 'Sync log deleted.');
        $redirectRaw = (string) $this->input->get('redirect', true);
        if ($redirectRaw === 'phone_report') {
            $redirect = admin_url('leadlookup/phone_report');
        } elseif ($redirectRaw === 'report') {
            $redirect = admin_url('leadlookup/report');
        } else {
            $redirect = admin_url('leadlookup/settings');
        }
        redirect($redirect);
    }

    public function bulk_delete_logs()
    {
        $redirectRaw = (string) $this->input->post('redirect');
        $canDelete = $redirectRaw === 'phone_report' ? $this->can_delete_phone_report_logs() : ($redirectRaw === 'settings' ? $this->is_admin_allowed() : $this->can_delete_report_logs());
        if (!$canDelete) {
            access_denied('Lead Lookup');
        }
        $ids = $this->input->post('ids');
        if (!is_array($ids)) {
            $ids = [];
        }
        $deleted = $this->leadlookup_model->delete_sync_logs($ids);
        set_alert('success', $deleted . ' sync log(s) deleted.');
        if ($redirectRaw === 'phone_report') {
            $redirect = admin_url('leadlookup/phone_report');
        } elseif ($redirectRaw === 'report') {
            $redirect = admin_url('leadlookup/report');
        } else {
            $redirect = admin_url('leadlookup/settings');
        }
        redirect($redirect);
    }

    private function sync_chatwoot_payload(array $payload, $logId, $verifiedDomain)
    {
        try {
            $mapped = $this->map_chatwoot_payload_to_lead($payload, $verifiedDomain);

            $validation = $this->validate_mapped_fields($mapped);
            if (!$validation['ok']) {
                $this->leadlookup_model->update_sync_log($logId, [
                    'status' => 'failed',
                    'error_message' => $validation['message'],
                ]);
                $this->leadlookup_model->send_chatwoot_private_note($payload, 'failed', [
                    'error_message' => $validation['message'],
                ]);
                return ['ok' => false, 'http_code' => 422, 'message' => $validation['message']];
            }

            $leadData = $this->remove_empty_default_values($mapped['lead_data']);
            $checkEmailDuplicate = $this->get_bool_setting('leadlookup_duplicate_check_email_enabled', true);
            $checkPhoneDuplicate = $this->get_bool_setting('leadlookup_duplicate_check_phone_enabled', true);
            $duplicate = $this->leadlookup_model->find_blocking_duplicate_lead_for_chatwoot(
                $mapped['core']['email'],
                $mapped['core']['phonenumber'],
                $checkEmailDuplicate,
                $checkPhoneDuplicate
            );

            if (!empty($duplicate)) {
                $leadId = (int) $duplicate['id'];
                $matchedBy = (string) ($duplicate['matched_by'] ?? 'email_or_phone');
                $message = 'Duplicate lead found by ' . $matchedBy . '. New lead was not created because the existing lead status is not Customer.';
                $this->leadlookup_model->update_sync_log($logId, [
                    'status' => 'skipped',
                    'crm_lead_id' => $leadId,
                    'matched_by' => $matchedBy,
                    'error_message' => $message,
                    'last_synced_at' => date('Y-m-d H:i:s'),
                ]);

                $this->leadlookup_model->send_chatwoot_private_note($payload, 'skipped', [
                    'lead_id' => $leadId,
                    'lead_name' => isset($duplicate['name']) ? (string) $duplicate['name'] : '',
                    'phone' => isset($duplicate['phonenumber']) ? (string) $duplicate['phonenumber'] : '',
                    'email' => isset($duplicate['email']) ? (string) $duplicate['email'] : '',
                    'matched_by' => $matchedBy,
                ]);

                return [
                    'ok' => true,
                    'created' => false,
                    'skipped' => true,
                    'message' => $message,
                    'lead' => [
                        'id' => $leadId,
                        'name' => isset($duplicate['name']) ? (string) $duplicate['name'] : '',
                        'email' => isset($duplicate['email']) ? (string) $duplicate['email'] : '',
                        'phone' => isset($duplicate['phonenumber']) ? (string) $duplicate['phonenumber'] : '',
                        'status_id' => isset($duplicate['status']) ? $duplicate['status'] : '',
                        'source_id' => isset($duplicate['source']) ? $duplicate['source'] : '',
                        'assigned_id' => isset($duplicate['assigned']) ? $duplicate['assigned'] : '',
                    ],
                ];
            }

            $leadId = (int) $this->leads_model->add($leadData);
            if ($leadId <= 0) {
                $this->leadlookup_model->update_sync_log($logId, [
                    'status' => 'failed',
                    'error_message' => 'Failed to create lead.',
                ]);
                $this->leadlookup_model->send_chatwoot_private_note($payload, 'failed', [
                    'error_message' => 'Failed to create lead in Perfex CRM.',
                ]);
                return ['ok' => false, 'http_code' => 500, 'message' => 'Failed to create lead.'];
            }

            // Set lead creator attribution & fix activity log
            $assignedStaffId = isset($leadData['assigned']) ? (int) $leadData['assigned'] : 0;
            $unichatAgentName = trim((string) (
                $payload['meta']['assignee']['name'] 
                ?? ($payload['meta']['assignee']['available_name'] 
                ?? ($payload['messages'][0]['sender']['name'] 
                ?? ($payload['messages'][0]['sender']['available_name'] ?? '')))
            ));
            $this->leadlookup_model->set_lead_creator_and_activity($leadId, $assignedStaffId, $unichatAgentName);

            $this->leadlookup_model->update_sync_log($logId, [
                'status' => 'success',
                'crm_lead_id' => $leadId,
                'matched_by' => 'created',
                'error_message' => '',
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);

            $assignedName = $this->leadlookup_model->get_staff_name($leadData['assigned'] ?? '');

            $this->leadlookup_model->send_chatwoot_private_note($payload, 'success', [
                'lead_id' => $leadId,
                'lead_name' => $leadData['name'],
                'phone' => isset($leadData['phonenumber']) ? $leadData['phonenumber'] : '',
                'email' => isset($leadData['email']) ? $leadData['email'] : '',
                'assigned_name' => $assignedName,
            ]);

            return [
                'ok' => true,
                'created' => true,
                'skipped' => false,
                'message' => 'Lead created successfully from UniChat.',
                'lead' => [
                    'id' => $leadId,
                    'name' => $leadData['name'],
                    'email' => isset($leadData['email']) ? $leadData['email'] : '',
                    'phone' => isset($leadData['phonenumber']) ? $leadData['phonenumber'] : '',
                    'status_id' => isset($leadData['status']) ? $leadData['status'] : '',
                    'source_id' => isset($leadData['source']) ? $leadData['source'] : '',
                    'assigned_id' => isset($leadData['assigned']) ? $leadData['assigned'] : '',
                ],
            ];
        } catch (Throwable $e) {
            $this->leadlookup_model->update_sync_log($logId, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $this->leadlookup_model->send_chatwoot_private_note($payload, 'failed', [
                'error_message' => $e->getMessage(),
            ]);
            return ['ok' => false, 'http_code' => 500, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    private function map_chatwoot_payload_to_lead(array $payload, $verifiedDomain)
    {
        $sender = $this->extract_sender($payload);
        $attrs = isset($sender['additional_attributes']) && is_array($sender['additional_attributes']) ? $sender['additional_attributes'] : [];
        $customAttrs = $this->extract_custom_attributes($payload, $sender);

        $name = $this->clean_text($sender['name'] ?? ($payload['name'] ?? ''));
        $email = $this->clean_email($sender['email'] ?? ($payload['email'] ?? ''));
        $phone = $this->sanitize_phone($sender['phone_number'] ?? ($sender['phone'] ?? ($payload['phone_number'] ?? ($payload['phone'] ?? ''))));

        $countryId = $this->resolve_country_id(
            $this->clean_text($attrs['country_code'] ?? ''),
            $this->clean_text($attrs['country'] ?? '')
        );

        // Assignee to Staff Mapping
        $assigneeMapRaw = get_option('leadlookup_assignee_staff_mapping');
        $assigneeMap = !empty($assigneeMapRaw) ? json_decode($assigneeMapRaw, true) : [];
        if (!is_array($assigneeMap)) {
            $assigneeMap = [];
        }

        $unichatAssigneeId = (string) ($payload['meta']['assignee']['id'] ?? ($payload['conversation']['assignee_id'] ?? ($payload['messages'][0]['sender_id'] ?? '')));

        $assignedStaffId = null;
        if ($unichatAssigneeId !== '' && isset($assigneeMap[$unichatAssigneeId]) && !empty($assigneeMap[$unichatAssigneeId])) {
            $assignedStaffId = (int) $assigneeMap[$unichatAssigneeId];
        } else {
            $assignedStaffId = $this->get_optional_int_setting('leadlookup_chatwoot_default_assigned_id');
        }

        // Chat Channel to Lead Source Mapping
        $channelMapRaw = get_option('leadlookup_channel_source_mapping');
        $channelMap = !empty($channelMapRaw) ? json_decode($channelMapRaw, true) : [];
        if (!is_array($channelMap)) {
            $channelMap = [];
        }

        $rawChannel = (string) (
            $payload['channel'] 
            ?? ($payload['conversation']['channel'] 
            ?? ($payload['inbox']['channel_type'] 
            ?? ($payload['contact_inbox']['channel_type'] 
            ?? ($payload['meta']['channel'] ?? ''))))
        );

        $matchedSourceId = null;
        if ($rawChannel !== '') {
            if (isset($channelMap[$rawChannel]) && !empty($channelMap[$rawChannel])) {
                $matchedSourceId = (int) $channelMap[$rawChannel];
            } else {
                foreach ($channelMap as $mappedChan => $sId) {
                    if (strcasecmp($mappedChan, $rawChannel) === 0 || 
                        strcasecmp(str_replace('Channel::', '', $mappedChan), str_replace('Channel::', '', $rawChannel)) === 0) {
                        $matchedSourceId = (int) $sId;
                        break;
                    }
                }
            }
        }

        if ($matchedSourceId === null || $matchedSourceId <= 0) {
            $matchedSourceId = $this->get_optional_int_setting('leadlookup_chatwoot_default_source_id');
        }

        $leadData = [
            'name' => $name,
            'email' => $email,
            'phonenumber' => $phone,
            'title' => $this->clean_text($attrs['description'] ?? ''),
            'company' => $this->clean_text($attrs['company_name'] ?? ''),
            'city' => $this->clean_text($attrs['city'] ?? ''),
            'country' => $countryId,
            'website' => $this->pick_social_profile_url($attrs['social_profiles'] ?? []),
            'description' => '',
            'address' => '',
            'status' => $this->get_optional_int_setting('leadlookup_chatwoot_default_status_id'),
            'source' => $matchedSourceId,
            'assigned' => $assignedStaffId,
            'is_public' => 0,
        ];

        $mappingErrors = [];

        $messageContent = $this->clean_multiline($payload['messages'][0]['content'] ?? ($payload['content'] ?? ''));
        $referer = $this->extract_referer_from_payload($payload);
        $conversationId = (string) ($payload['id'] ?? ($payload['conversation']['id'] ?? ''));
        $contactId = (string) ($sender['id'] ?? ($payload['contact_id'] ?? ''));
        $inboxId = (string) ($payload['inbox_id'] ?? ($payload['contact_inbox']['inbox_id'] ?? ''));
        $accountId = (string) ($payload['account']['id'] ?? $this->get_string_setting('leadlookup_chatwoot_account_id', $this->get_config_string('chatwoot_account_id', '')));
        $event = (string) ($payload['event'] ?? '');
        $conversationUrl = $this->build_chatwoot_conversation_url($payload);

        $descriptionParts = [];
        $descriptionParts[] = 'Lead created from UniChat';
        if ($conversationUrl !== '') {
            $descriptionParts[] = 'Conversation link: ' . $conversationUrl;
        }
        $leadData['description'] = implode("\n", $descriptionParts);

        $noteLines = [];
        $noteLines[] = 'UniChat Sync';
        $noteLines[] = 'Event: ' . ($event !== '' ? $event : 'N/A');
        $noteLines[] = 'Contact ID: ' . ($contactId !== '' ? $contactId : 'N/A');
        $noteLines[] = 'Conversation ID: ' . ($conversationId !== '' ? $conversationId : 'N/A');
        $noteLines[] = 'Inbox ID: ' . ($inboxId !== '' ? $inboxId : 'N/A');
        if ($conversationUrl !== '') {
            $noteLines[] = 'Conversation URL: ' . $conversationUrl;
        }
        if ($messageContent !== '') {
            $noteLines[] = 'Message: ' . $messageContent;
        }
        if ($referer !== '') {
            $noteLines[] = 'Visitor page URL: ' . $referer;
        }
        if (!empty($customAttrs)) {
            $noteLines[] = 'Custom attributes: ' . json_encode($customAttrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return [
            'lead_data' => hooks()->apply_filters('leadlookup_chatwoot_lead_data', $leadData, $payload),
            'core' => ['name' => $name, 'email' => $email, 'phonenumber' => $phone],
            'chatwoot' => ['contact_id' => $contactId, 'conversation_id' => $conversationId, 'inbox_id' => $inboxId, 'account_id' => $accountId],
            'chatwoot_note' => implode("\n", $noteLines),
            'mapping_errors' => $mappingErrors,
        ];
    }

    private function remove_empty_default_values(array $leadData)
    {
        foreach (['status', 'source', 'assigned'] as $field) {
            if (array_key_exists($field, $leadData) && ($leadData[$field] === '' || $leadData[$field] === null)) {
                unset($leadData[$field]);
            }
        }
        return $leadData;
    }

    private function validate_mapped_fields(array $mapped)
    {
        $lead = $mapped['lead_data'];
        if (trim((string) ($lead['name'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Name is required.'];
        }
        if (trim((string) ($lead['phonenumber'] ?? '')) === '' && trim((string) ($lead['email'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Either phone or email is required.'];
        }
        if (!empty($lead['email']) && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid email address.'];
        }
        foreach ($mapped['mapping_errors'] as $error) {
            if (stripos($error, 'required') !== false) {
                return ['ok' => false, 'message' => $error];
            }
        }
        return ['ok' => true, 'message' => ''];
    }

    private function extract_sender(array $payload)
    {
        $candidates = [
            ['meta', 'sender'],
            ['messages', 0, 'sender'],
            ['sender'],
            ['contact'],
        ];
        foreach ($candidates as $path) {
            $value = $this->array_path($payload, $path);
            if (is_array($value)) {
                return $value;
            }
        }
        return [];
    }

    private function extract_custom_attributes(array $payload, array $sender)
    {
        if (isset($sender['custom_attributes']) && is_array($sender['custom_attributes'])) {
            return $sender['custom_attributes'];
        }
        if (isset($payload['custom_attributes']) && is_array($payload['custom_attributes'])) {
            return $payload['custom_attributes'];
        }
        if (isset($payload['meta']['sender']['custom_attributes']) && is_array($payload['meta']['sender']['custom_attributes'])) {
            return $payload['meta']['sender']['custom_attributes'];
        }
        return [];
    }

    private function pick_social_profile_url($profiles)
    {
        if (!is_array($profiles)) {
            return '';
        }
        $priority = ['linkedin','github','twitter','x','facebook','tiktok','instagram'];
        foreach ($priority as $network) {
            if (!empty($profiles[$network])) {
                return $this->social_to_url($network, (string) $profiles[$network]);
            }
        }
        foreach ($profiles as $network => $value) {
            if (!empty($value)) {
                return $this->social_to_url((string) $network, (string) $value);
            }
        }
        return '';
    }

    private function social_to_url($network, $value)
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }
        $username = ltrim($value, '@/');
        $username = preg_replace('/[^A-Za-z0-9_.-]/', '', $username);
        $base = [
            'linkedin' => 'https://www.linkedin.com/in/',
            'github' => 'https://github.com/',
            'twitter' => 'https://x.com/',
            'x' => 'https://x.com/',
            'facebook' => 'https://www.facebook.com/',
            'tiktok' => 'https://www.tiktok.com/@',
            'instagram' => 'https://www.instagram.com/',
        ];
        return (isset($base[strtolower($network)]) ? $base[strtolower($network)] : 'https://') . $username;
    }

    private function resolve_country_id($countryCode, $countryName)
    {
        return $this->leadlookup_model->resolve_country_id($countryCode, $countryName);
    }

    private function build_chatwoot_conversation_url(array $payload)
    {
        $base = $this->get_string_setting('leadlookup_chatwoot_base_url', $this->get_config_string('chatwoot_base_url', ''));
        $accountId = (string) ($payload['account']['id'] ?? $this->get_string_setting('leadlookup_chatwoot_account_id', $this->get_config_string('chatwoot_account_id', '')));
        $conversationId = (string) ($payload['id'] ?? ($payload['conversation']['id'] ?? ''));
        if ($base === '' || $accountId === '' || $conversationId === '') {
            return '';
        }
        return rtrim($base, '/') . '/app/accounts/' . rawurlencode($accountId) . '/conversations/' . rawurlencode($conversationId);
    }

    private function validate_allowed_domain(array $payload)
    {
        if (!$this->is_domain_ip_validation_enabled()) {
            return [
                'ok' => true,
                'message' => '',
                'domain' => 'validation_disabled',
                'matched_allowed_entry' => '',
                'detected_sources' => $this->get_request_candidate_hosts($payload),
            ];
        }

        $allowedEntries = $this->get_allowed_domains();
        if (empty($allowedEntries)) {
            return [
                'ok' => false,
                'message' => 'No allowed domain or IP address configured for UniChat endpoint.',
                'domain' => '',
                'detected_sources' => $this->get_request_candidate_hosts($payload),
            ];
        }

        $candidates = $this->get_request_candidate_hosts($payload);
        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_domain($candidate);
            if ($normalized === '') {
                continue;
            }
            foreach ($allowedEntries as $allowed) {
                if ($this->source_matches_allowed_entry($normalized, $allowed)) {
                    return ['ok' => true, 'message' => '', 'domain' => $normalized];
                }
            }
        }

        return [
            'ok' => false,
            'message' => 'Request rejected because the incoming request domain/IP is not allowed. Payload referer is ignored for security validation.',
            'domain' => '',
            'detected_sources' => array_values(array_unique($candidates)),
        ];
    }

    private function validate_webhook_token()
    {
        // Intentionally disabled. Webhook endpoint security is based on allowed domain/IP only.
        return ['ok' => true, 'message' => ''];
    }

    private function validate_chatwoot_signature($rawBody)
    {
        // Intentionally disabled. Webhook endpoint security is based on allowed domain/IP only.
        return ['ok' => true, 'message' => ''];
    }

    private function is_domain_ip_validation_enabled()
    {
        return $this->get_bool_setting('leadlookup_domain_ip_validation_enabled', true);
    }

    private function get_allowed_domains()
    {
        $raw = $this->get_string_setting('leadlookup_chatwoot_allowed_domains', $this->get_config_string('chatwoot_allowed_domains', ''));
        $items = preg_split('/[\r\n,]+/', (string) $raw);
        $entries = [];

        foreach ($items as $item) {
            $entry = $this->normalize_domain($item);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return array_values(array_unique($entries));
    }

    private function get_request_candidate_hosts(array $payload)
    {
        $candidates = [];

        // Only use the actual incoming request metadata for domain/IP validation.
        // IMPORTANT: UniChat payload additional_attributes.referer is the visitor page
        // referrer, not the UniChat webhook source, so it is intentionally ignored here.
        // Host/X-Forwarded-Host are also ignored because they usually represent the CRM
        // destination host, not the sender.
        foreach (['Origin', 'Referer', 'X-UniChat-Source-Domain', 'X-Chatwoot-Source-Domain'] as $header) {
            $value = $this->get_header_value($header);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        // IP candidates from server/proxy metadata. Allow admins to whitelist the
        // UniChat server IP, reverse proxy IP, or Cloudflare real visitor IP.
        foreach (['REMOTE_ADDR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'] as $key) {
            if (!empty($_SERVER[$key])) {
                $candidates[] = (string) $_SERVER[$key];
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $candidates[] = trim($ip);
            }
        }

        return array_values(array_unique(array_filter($candidates, static function ($value) {
            return trim((string) $value) !== '';
        })));
    }

    private function extract_referer_from_payload(array $payload)
    {
        foreach ([['additional_attributes','referer'], ['conversation','additional_attributes','referer'], ['meta','conversation','additional_attributes','referer']] as $path) {
            $value = $this->array_path($payload, $path);
            if (is_scalar($value) && (string) $value !== '') return (string) $value;
        }
        return '';
    }

    private function array_path($data, $path)
    {
        if (is_string($path)) $path = explode('.', $path);
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) return '';
            $current = $current[$key];
        }
        return $current;
    }

    private function normalize_domain($value)
    {
        $value = trim(strtolower((string) $value));
        if ($value === '') {
            return '';
        }

        // If a full URL is provided, extract only the host.
        if (strpos($value, '://') !== false) {
            $host = parse_url($value, PHP_URL_HOST);
            $value = $host ? $host : $value;
        }

        // X-Forwarded-For can provide a list; keep the first normalized value when this helper is called directly.
        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            $value = trim((string) $parts[0]);
        }

        // Strip IPv6 brackets and domain/IP ports.
        $value = trim($value, " \t\n\r\0\x0B./[]");
        if (preg_match('/^(.+):(\d+)$/', $value, $m) && filter_var($m[1], FILTER_VALIDATE_IP)) {
            $value = $m[1];
        } elseif (preg_match('/^([^:]+):(\d+)$/', $value, $m)) {
            $value = $m[1];
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        return preg_match('/^[a-z0-9.-]+$/', $value) ? $value : '';
    }

    private function source_matches_allowed_entry($source, $allowed)
    {
        $source = $this->normalize_domain($source);
        $allowed = $this->normalize_domain($allowed);
        if ($source === '' || $allowed === '') {
            return false;
        }

        $sourceIsIp = (bool) filter_var($source, FILTER_VALIDATE_IP);
        $allowedIsIp = (bool) filter_var($allowed, FILTER_VALIDATE_IP);
        if ($sourceIsIp || $allowedIsIp) {
            return $sourceIsIp && $allowedIsIp && hash_equals($allowed, $source);
        }

        return $source === $allowed || substr($source, -1 * (strlen($allowed) + 1)) === '.' . $allowed;
    }

    private function domain_matches($host, $allowed)
    {
        return $this->source_matches_allowed_entry($host, $allowed);
    }

    private function get_header_value($name)
    {
        $value = $this->input->get_request_header($name, true);
        if ($value !== null && $value !== false && $value !== '') return trim((string) $value);
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) return trim((string) $_SERVER[$serverKey]);
        if (strtolower($name) === 'authorization' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        return '';
    }

    private function sanitize_phone($phone)
    {
        $phone = trim((string) $phone);
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        $phone = preg_replace('/^\++/', '+', $phone);
        return strlen($phone) > 30 ? substr($phone, 0, 30) : $phone;
    }

    private function clean_email($email)
    {
        $email = filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
        return strlen($email) > 191 ? substr($email, 0, 191) : $email;
    }

    private function clean_text($value, $maxLength = 191)
    {
        $value = preg_replace('/\s+/', ' ', strip_tags(trim((string) $value)));
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function clean_multiline($value, $maxLength = 2000)
    {
        $value = strip_tags(trim((string) $value));
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }

    private function format_url($value)
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (!preg_match('/^https?:\/\//i', $value)) $value = 'https://' . ltrim($value, '/');
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function is_empty_value($value)
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    private function truthy($value)
    {
        return in_array(strtolower((string) $value), ['1','true','yes','on'], true) || $value === true || $value === 1;
    }

    private function get_config_string($key, $default = '')
    {
        $cfg = (array) $this->config->item('leadlookup');
        return isset($cfg[$key]) ? trim((string) $cfg[$key]) : $default;
    }

    private function get_config_int($key, $default = 0)
    {
        $cfg = (array) $this->config->item('leadlookup');
        return isset($cfg[$key]) ? (int) $cfg[$key] : (int) $default;
    }

    private function get_string_setting($optionName, $default = '')
    {
        if (function_exists('get_option')) {
            $value = get_option($optionName);
            if ($value !== null && $value !== false && $value !== '') return trim((string) $value);
        }
        return $default;
    }

    private function get_int_setting($optionName, $default = 0)
    {
        $value = $this->get_string_setting($optionName, '');
        return $value !== '' ? (int) $value : (int) $default;
    }

    private function get_optional_int_setting($optionName)
    {
        $value = $this->get_string_setting($optionName, '');
        return $value !== '' ? (int) $value : '';
    }

    private function get_bool_setting($optionName, $default = false)
    {
        $value = $this->get_string_setting($optionName, $default ? '1' : '0');
        return $this->truthy($value);
    }

    private function get_retention_days()
    {
        $days = $this->get_int_setting('leadlookup_log_retention_days', 30);
        return $days > 0 ? $days : 30;
    }

    private function export_sync_logs_csv(array $filters, $filename, $reportType = 'lead')
    {
        $allowed = $reportType === 'phone' ? $this->can_view_phone_report() : ($this->can_view_report() || $this->is_admin_allowed());
        if (!$allowed) {
            access_denied($reportType === 'phone' ? 'Phone Lookup Report' : 'Lead Create Report');
        }
        $rows = $this->leadlookup_model->get_sync_logs_filtered($filters, 5000, 0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Time', 'Status', 'UniChat Contact', 'Conversation', 'Lead', 'Attempts', 'Matched By', 'Error']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['status'] ?? '',
                $row['chatwoot_contact_id'] ?? '',
                $row['chatwoot_conversation_id'] ?? '',
                $row['crm_lead_id'] ?? '',
                $row['attempts'] ?? '',
                $row['matched_by'] ?? '',
                $row['error_message'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function can_view_report()
    {
        return (function_exists('is_admin') && is_admin()) || has_permission('leadlookup_reports', '', 'view');
    }

    private function can_delete_report_logs()
    {
        return (function_exists('is_admin') && is_admin()) || has_permission('leadlookup_reports', '', 'delete');
    }

    private function can_view_phone_report()
    {
        return (function_exists('is_admin') && is_admin()) || has_permission('leadlookup_phone_reports', '', 'view');
    }

    private function can_delete_phone_report_logs()
    {
        return (function_exists('is_admin') && is_admin()) || has_permission('leadlookup_phone_reports', '', 'delete');
    }

    private function is_admin_allowed()
    {
        return function_exists('is_staff_logged_in') && is_staff_logged_in() && (function_exists('is_admin') && is_admin());
    }

    private function json_success($data, $httpCode = 200)
    {
        $this->output->set_status_header((int) $httpCode)->set_content_type('application/json', 'utf-8')->set_output(json_encode(['status' => 'success', 'data' => is_array($data) ? $data : [$data]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->output->_display();
        exit;
    }

    private function json_error($httpCode, $message, array $data = [])
    {
        $this->output->set_status_header((int) $httpCode)->set_content_type('application/json', 'utf-8')->set_output(json_encode(['status' => 'error', 'message' => (string) $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->output->_display();
        exit;
    }
}
