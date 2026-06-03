<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Leadlookup_api extends App_Controller
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
        $phone = $this->sanitize_phone((string) $this->input->get('phone', true));
        $authorized = $this->check_api_key();
        if (!$authorized) {
            $this->leadlookup_model->create_phone_lookup_log($phone, 'failed', 0, null, 'Invalid or missing API key.');
            return $this->json_error(401, 'Invalid or missing API key.');
        }

        $domainCheck = $this->validate_allowed_domain([]);
        if (!$domainCheck['ok']) {
            $this->leadlookup_model->create_phone_lookup_log($phone, 'failed', 0, null, $domainCheck['message']);
            return $this->json_error(403, $domainCheck['message'], [
                'detected_sources' => $domainCheck['detected_sources'] ?? [],
            ]);
        }

        if ($phone === '') {
            $this->leadlookup_model->create_phone_lookup_log($phone, 'failed', 0, null, 'Query parameter "phone" is required.');
            return $this->json_error(400, 'Query parameter "phone" is required.');
        }
        $leads = $this->leadlookup_model->find_leads_by_phone($phone);
        if (empty($leads)) {
            $this->leadlookup_model->create_phone_lookup_log($phone, 'failed', 0, null, 'No leads found for the provided phone number.');
            return $this->json_error(404, 'No leads found for the provided phone number.');
        }
        $firstLeadId = isset($leads[0]['id']) ? (int) $leads[0]['id'] : null;
        $this->leadlookup_model->create_phone_lookup_log($phone, 'success', count($leads), $firstLeadId, '');
        return $this->json_success($leads, 200);
    }

    /** POST /leadlookup/create_from_chatwoot */
    public function create_from_chatwoot()
    {
        if (strtoupper((string) $this->input->method(true)) !== 'POST') {
            return $this->json_error(405, 'Only POST requests are allowed.');
        }

        $rawBody = (string) $this->input->raw_input_stream;
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->json_error(400, 'Invalid JSON payload.');
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
                return ['ok' => false, 'http_code' => 422, 'message' => $validation['message']];
            }

            $leadData = $this->remove_empty_default_values($mapped['lead_data']);
            $duplicate = $this->leadlookup_model->find_blocking_duplicate_lead_for_chatwoot(
                $mapped['core']['email'],
                $mapped['core']['phonenumber']
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
                return ['ok' => false, 'http_code' => 500, 'message' => 'Failed to create lead.'];
            }

            $this->leadlookup_model->update_sync_log($logId, [
                'status' => 'success',
                'crm_lead_id' => $leadId,
                'matched_by' => 'created',
                'error_message' => '',
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => true,
                'created' => true,
                'skipped' => false,
                'message' => 'Lead created successfully from Chatwoot.',
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
        $phone = $this->sanitize_phone($sender['phone_number'] ?? ($sender['phone'] ?? ($payload['phone'] ?? '')));

        $countryId = $this->resolve_country_id(
            $this->clean_text($attrs['country_code'] ?? ''),
            $this->clean_text($attrs['country'] ?? '')
        );

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
            'source' => $this->get_optional_int_setting('leadlookup_chatwoot_default_source_id'),
            'assigned' => $this->get_optional_int_setting('leadlookup_chatwoot_default_assigned_id'),
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
        $descriptionParts[] = 'Lead synced from Chatwoot webhook with';
        if ($conversationUrl !== '') {
            $descriptionParts[] = 'Conversation link: ' . $conversationUrl;
        }
        $leadData['description'] = implode("\n", $descriptionParts);

        $noteLines = [];
        $noteLines[] = 'Chatwoot Sync';
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
                'message' => 'No allowed domain or IP address configured for Chatwoot endpoint.',
                'domain' => '',
                'detected_sources' => $this->get_request_candidate_hosts($payload),
            ];
        }

        $candidates = $this->get_request_candidate_hosts($payload);
        $detected = array_values(array_unique($candidates));

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize_domain($candidate);
            if ($normalized === '') {
                continue;
            }
            foreach ($allowedEntries as $allowed) {
                if ($this->source_matches_allowed_entry($normalized, $allowed)) {
                    return [
                        'ok' => true,
                        'message' => '',
                        'domain' => $normalized,
                        'matched_allowed_entry' => $allowed,
                        'detected_sources' => $detected,
                    ];
                }
            }
        }

        $message = 'Request rejected because the incoming request domain/IP is not allowed. Payload referer is ignored for security validation.';
        if (!empty($detected)) {
            $message .= ' Detected request sources: ' . implode(', ', array_slice($detected, 0, 8));
        }

        return [
            'ok' => false,
            'message' => $message,
            'domain' => '',
            'detected_sources' => $detected,
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
        // IMPORTANT: Chatwoot payload additional_attributes.referer is the visitor page
        // referrer, not the Chatwoot webhook source, so it is intentionally ignored here.
        // Host/X-Forwarded-Host are also ignored because they usually represent the CRM
        // destination host, not the sender.
        foreach (['Origin', 'Referer', 'X-Chatwoot-Source-Domain'] as $header) {
            $value = $this->get_header_value($header);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        // IP candidates from server/proxy metadata. Allow admins to whitelist the
        // Chatwoot server IP, reverse proxy IP, or Cloudflare real visitor IP.
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

        if ($sourceIsIp && $allowedIsIp) {
            return hash_equals($allowed, $source);
        }

        // Important for self-hosted Chatwoot: webhooks are server-to-server requests.
        // Chatwoot usually does not send Origin/Referer, so adding only
        // app.example.com to settings would not match unless we resolve that
        // allowed domain to its server IP and compare it with REMOTE_ADDR / proxy IPs.
        if ($sourceIsIp && !$allowedIsIp) {
            $allowedIps = $this->resolve_domain_ips($allowed);
            return in_array($source, $allowedIps, true);
        }

        if (!$sourceIsIp && $allowedIsIp) {
            $sourceIps = $this->resolve_domain_ips($source);
            return in_array($allowed, $sourceIps, true);
        }

        return $source === $allowed || substr($source, -1 * (strlen($allowed) + 1)) === '.' . $allowed;
    }

    private function resolve_domain_ips($domain)
    {
        $domain = $this->normalize_domain($domain);
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP)) {
            return [];
        }

        static $cache = [];
        if (array_key_exists($domain, $cache)) {
            return $cache[$domain];
        }

        $ips = [];

        $aRecords = @gethostbynamel($domain);
        if (is_array($aRecords)) {
            foreach ($aRecords as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    foreach (['ip', 'ipv6'] as $key) {
                        if (!empty($record[$key]) && filter_var($record[$key], FILTER_VALIDATE_IP)) {
                            $ips[] = $record[$key];
                        }
                    }
                }
            }
        }

        $cache[$domain] = array_values(array_unique($ips));
        return $cache[$domain];
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

    private function is_admin_allowed()
    {
        return function_exists('is_staff_logged_in') && is_staff_logged_in() && (function_exists('is_admin') && is_admin());
    }


    private function check_api_key()
    {
        // Phone lookup API key is intentionally static, same as the original module.
        // Set it only in modules/leadlookup/config/leadlookup.php.
        // It is not stored in Perfex options and it is not editable from the UI.
        $cfg = (array) $this->config->item('leadlookup');
        $expected = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';

        $provided = trim((string) $this->input->get('apikey', true));
        if ($provided === '') {
            $provided = trim((string) $this->input->get_request_header('X-API-Key', true));
        }

        if ($expected === '' || $expected === 'YOUR_STATIC_SECRET_KEY_HERE') {
            return false;
        }

        return function_exists('hash_equals') ? hash_equals($expected, $provided) : $expected === $provided;
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
