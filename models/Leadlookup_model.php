<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Leadlookup_model extends App_Model
{
    private function ensure_sync_logs_table()
    {
        if (function_exists('leadlookup_create_sync_logs_table')) {
            return (bool) leadlookup_create_sync_logs_table();
        }

        return $this->db->table_exists(db_prefix() . 'leadlookup_sync_logs');
    }

    public function find_leads_by_phone($phone)
    {
        $cfg = (array) get_instance()->config->item('leadlookup');
        $matchMode = (string) ($cfg['phone_match'] ?? 'like');
        $tblLeads = db_prefix() . 'leads';
        $tblStatus = db_prefix() . 'leads_status';

        $this->db->select('l.id, l.name, l.phonenumber, l.description, l.dateadded, l.lastcontact, s.name AS status_name', false);
        $this->db->from($tblLeads . ' AS l');
        $this->db->join($tblStatus . ' AS s', 's.id = l.status', 'left');
        $matchMode === 'exact' ? $this->db->where('l.phonenumber', $phone) : $this->db->like('l.phonenumber', $phone);
        $this->db->order_by('l.id', 'DESC');
        $rows = $this->db->get()->result_array();
        if (empty($rows)) return [];

        $leadIds = array_map(static function ($r) { return (int) $r['id']; }, $rows);
        $customFieldsByLead = $this->get_custom_fields_for_leads($leadIds);
        $notesByLead = $this->get_notes_for_leads($leadIds);
        $activitiesByLead = $this->get_latest_activities_for_leads($leadIds, 3);

        $out = [];
        foreach ($rows as $r) {
            $leadId = (int) $r['id'];
            $out[] = [
                'id' => $leadId,
                'name' => (string) $r['name'],
                'phone' => (string) $r['phonenumber'],
                'status' => (string) ($r['status_name'] ?? ''),
                'description' => (string) ($r['description'] ?? ''),
                'created_date' => (string) ($r['dateadded'] ?? ''),
                'last_contact' => (string) ($r['lastcontact'] ?? ''),
                'custom_fields' => $customFieldsByLead[$leadId] ?? new stdClass(),
                'notes' => $notesByLead[$leadId] ?? [],
                'latest_activities' => $activitiesByLead[$leadId] ?? [],
            ];
        }
        return $out;
    }

    public function find_blocking_duplicate_lead_for_chatwoot($email, $phone)
    {
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        if ($email === '' && $phone === '') {
            return [];
        }

        $phoneCandidates = $this->phone_lookup_candidates($phone);

        $this->db->select('l.*, s.name as status_name');
        $this->db->from(db_prefix() . 'leads l');
        $this->db->join(db_prefix() . 'leads_status s', 's.id = l.status', 'left');
        $this->db->group_start();
        if ($email !== '') {
            $this->db->or_where('l.email', $email);
        }
        if (!empty($phoneCandidates)) {
            $this->db->or_where_in('l.phonenumber', $phoneCandidates);
        }
        $this->db->group_end();
        $this->db->order_by('l.id', 'DESC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as $row) {
            if (!$this->lead_status_is_customer($row)) {
                $row['matched_by'] = ($email !== '' && strtolower((string) ($row['email'] ?? '')) === strtolower($email)) ? 'email' : 'phone';
                return $row;
            }
        }

        return [];
    }

    private function lead_status_is_customer(array $lead)
    {
        $statusName = strtolower(trim((string) ($lead['status_name'] ?? '')));
        return $statusName === 'customer';
    }

    private function phone_lookup_candidates($phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return [];
        }
        $digits = preg_replace('/\D+/', '', $phone);
        $candidates = [$phone];
        if ($digits !== '') {
            $candidates[] = $digits;
            $candidates[] = '+' . $digits;
            if (strpos($digits, '880') === 0 && strlen($digits) > 3) {
                $local = '0' . substr($digits, 3);
                $candidates[] = $local;
                $candidates[] = '+880' . substr($digits, 3);
            } elseif (strpos($digits, '0') === 0 && strlen($digits) > 1) {
                $bd = '880' . substr($digits, 1);
                $candidates[] = $bd;
                $candidates[] = '+' . $bd;
            }
        }
        return array_values(array_unique(array_filter($candidates, static function ($value) {
            return trim((string) $value) !== '';
        })));
    }

    public function add_chatwoot_note($leadId, $note)
    {
        $leadId = (int) $leadId;
        $note = trim((string) $note);
        if ($leadId <= 0 || $note === '') return false;
        $staffId = function_exists('get_staff_user_id') ? (int) get_staff_user_id() : 0;
        $this->db->insert(db_prefix() . 'notes', [
            'rel_id' => $leadId,
            'rel_type' => 'lead',
            'description' => $note,
            'date_contacted' => null,
            'addedfrom' => $staffId,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function create_sync_log(array $payload, $status = 'pending', $error = '', $rawBody = '', $logType = 'lead_create')
    {
        if (!$this->ensure_sync_logs_table()) {
            log_message('error', 'Leadlookup: sync log table is missing and could not be created.');
            return 0;
        }

        $summary = $this->payload_summary($payload);
        $savePayload = function_exists('get_option') ? (string) get_option('leadlookup_chatwoot_save_payload') : '1';
        $payloadText = in_array(strtolower($savePayload), ['1','true','yes','on'], true)
            ? ($rawBody !== '' ? $rawBody : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            : null;

        $data = [
            'log_type' => in_array((string) $logType, ['lead_create','phone_lookup'], true) ? (string) $logType : 'lead_create',
            'chatwoot_contact_id' => $summary['contact_id'],
            'chatwoot_conversation_id' => $summary['conversation_id'],
            'chatwoot_inbox_id' => $summary['inbox_id'],
            'chatwoot_account_id' => $summary['account_id'],
            'crm_lead_id' => null,
            'status' => $status,
            'matched_by' => null,
            'error_message' => $error,
            'payload_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'payload' => $payloadText,
            'attempts' => 0,
            'last_synced_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'leadlookup_sync_logs', $data);
        return (int) $this->db->insert_id();
    }

    public function create_phone_lookup_log($phone, $status, $resultCount = 0, $crmLeadId = null, $error = '')
    {
        if (!$this->ensure_sync_logs_table()) {
            log_message('error', 'Leadlookup: phone lookup log table is missing and could not be created.');
            return 0;
        }

        $summary = [
            'phone' => (string) $phone,
            'result_count' => (int) $resultCount,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 191) : '',
        ];

        $data = [
            'log_type' => 'phone_lookup',
            'chatwoot_contact_id' => null,
            'chatwoot_conversation_id' => null,
            'chatwoot_inbox_id' => null,
            'chatwoot_account_id' => null,
            'crm_lead_id' => $crmLeadId ? (int) $crmLeadId : null,
            'status' => in_array((string) $status, ['success','failed','skipped','pending'], true) ? (string) $status : 'failed',
            'matched_by' => 'phone',
            'error_message' => (string) $error,
            'payload_summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'payload' => null,
            'attempts' => 1,
            'last_synced_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert(db_prefix() . 'leadlookup_sync_logs', $data);
        return (int) $this->db->insert_id();
    }

    public function update_sync_log($id, array $data)
    {
        $id = (int) $id;
        if ($id <= 0 || !$this->ensure_sync_logs_table()) return false;
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (isset($data['status']) && in_array($data['status'], ['success','failed','skipped'], true)) {
            $this->db->set('attempts', 'attempts+1', false);
        }
        $this->db->where('id', $id);
        return $this->db->update(db_prefix() . 'leadlookup_sync_logs', $data);
    }

    public function get_sync_log($id)
    {
        if (!$this->ensure_sync_logs_table()) return [];
        return $this->db->get_where(db_prefix() . 'leadlookup_sync_logs', ['id' => (int) $id])->row_array();
    }

    public function get_recent_sync_logs($limit = 30)
    {
        if (!$this->ensure_sync_logs_table()) return [];
        $this->db->order_by('id', 'DESC');
        $this->db->limit((int) $limit);
        return $this->db->get(db_prefix() . 'leadlookup_sync_logs')->result_array();
    }


    public function get_sync_logs_filtered(array $filters = [], $limit = 25, $offset = 0)
    {
        if (!$this->ensure_sync_logs_table()) return [];
        $this->apply_sync_log_filters($filters);
        $this->db->order_by('id', 'DESC');
        $this->db->limit((int) $limit, (int) $offset);
        return $this->db->get(db_prefix() . 'leadlookup_sync_logs')->result_array();
    }

    public function count_sync_logs_filtered(array $filters = [])
    {
        if (!$this->ensure_sync_logs_table()) return 0;
        $this->apply_sync_log_filters($filters);
        return (int) $this->db->count_all_results(db_prefix() . 'leadlookup_sync_logs');
    }

    private function apply_sync_log_filters(array $filters = [])
    {
        $status = isset($filters['status']) ? trim((string) $filters['status']) : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        $dateTo = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
        $logType = isset($filters['log_type']) ? trim((string) $filters['log_type']) : '';

        if ($logType !== '' && in_array($logType, ['lead_create', 'phone_lookup'], true)) {
            if ($logType === 'lead_create') {
                // Backward-compatible fallback: older logs created before v1.3.2
                // may have NULL/empty log_type. Treat them as lead-create logs so
                // all report tables continue to show historical records.
                $this->db->group_start();
                $this->db->where('log_type', 'lead_create');
                $this->db->or_where('log_type IS NULL', null, false);
                $this->db->or_where('log_type', '');
                $this->db->group_end();
            } else {
                $this->db->where('log_type', $logType);
            }
        }
        if ($status !== '' && in_array($status, ['pending', 'success', 'failed', 'skipped'], true)) {
            $this->db->where('status', $status);
        }
        if ($dateFrom !== '') {
            $this->db->where('created_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $this->db->where('created_at <=', $dateTo . ' 23:59:59');
        }
        if ($search !== '') {
            $this->db->group_start();
            $this->db->like('chatwoot_contact_id', $search);
            $this->db->or_like('chatwoot_conversation_id', $search);
            $this->db->or_like('crm_lead_id', $search);
            $this->db->or_like('error_message', $search);
            $this->db->or_like('payload_summary', $search);
            $this->db->or_like('payload', $search);
            $this->db->or_like('matched_by', $search);
            $this->db->or_like('status', $search);
            $this->db->or_like('log_type', $search);
            if (ctype_digit($search)) {
                $this->db->or_where('id', (int) $search);
                $this->db->or_where('crm_lead_id', (int) $search);
            }
            $this->db->group_end();
        }
    }

    public function delete_sync_log($id)
    {
        if (!$this->ensure_sync_logs_table()) return false;
        $id = (int) $id;
        if ($id <= 0) return false;
        $this->db->where('id', $id);
        return $this->db->delete(db_prefix() . 'leadlookup_sync_logs');
    }

    public function delete_sync_logs(array $ids)
    {
        if (!$this->ensure_sync_logs_table()) return 0;
        $ids = array_values(array_filter(array_map('intval', $ids), static function ($id) { return $id > 0; }));
        if (empty($ids)) return 0;
        $this->db->where_in('id', $ids);
        $this->db->delete(db_prefix() . 'leadlookup_sync_logs');
        return (int) $this->db->affected_rows();
    }

    public function find_lead_id_by_chatwoot_contact_id($contactId)
    {
        if (!$this->ensure_sync_logs_table()) return 0;
        $this->db->select('crm_lead_id');
        $this->db->where('chatwoot_contact_id', (string) $contactId);
        $this->db->where('crm_lead_id IS NOT NULL', null, false);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get(db_prefix() . 'leadlookup_sync_logs')->row_array();
        return $row ? (int) $row['crm_lead_id'] : 0;
    }

    public function resolve_country_id($countryCode, $countryName)
    {
        if (!$this->db->table_exists(db_prefix() . 'countries')) return 0;
        $countryCode = strtoupper(trim((string) $countryCode));
        $countryName = trim((string) $countryName);
        if ($countryCode !== '') {
            $this->db->select('country_id');
            $this->db->where('iso2', $countryCode);
            $this->db->limit(1);
            $row = $this->db->get(db_prefix() . 'countries')->row_array();
            if ($row) return (int) $row['country_id'];
        }
        if ($countryName !== '') {
            $this->db->select('country_id');
            $this->db->group_start();
            $this->db->where('short_name', $countryName);
            $this->db->or_like('short_name', $countryName);
            $this->db->group_end();
            $this->db->limit(1);
            $row = $this->db->get(db_prefix() . 'countries')->row_array();
            if ($row) return (int) $row['country_id'];
        }
        return 0;
    }

    private function payload_summary(array $payload)
    {
        $sender = [];
        foreach ([['meta','sender'], ['messages',0,'sender'], ['sender'], ['contact']] as $path) {
            $v = $this->array_path($payload, $path);
            if (is_array($v)) { $sender = $v; break; }
        }
        return [
            'contact_id' => (string) ($sender['id'] ?? ($payload['contact_id'] ?? '')),
            'conversation_id' => (string) ($payload['id'] ?? ($payload['conversation']['id'] ?? '')),
            'inbox_id' => (string) ($payload['inbox_id'] ?? ($payload['contact_inbox']['inbox_id'] ?? '')),
            'account_id' => (string) ($payload['account']['id'] ?? ''),
            'name' => (string) ($sender['name'] ?? ''),
            'email' => (string) ($sender['email'] ?? ''),
            'phone' => (string) ($sender['phone_number'] ?? ($sender['phone'] ?? '')),
            'event' => (string) ($payload['event'] ?? ''),
        ];
    }

    private function array_path($data, array $path)
    {
        $current = $data;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) return '';
            $current = $current[$key];
        }
        return $current;
    }

    private function get_custom_fields_for_leads(array $leadIds)
    {
        if (empty($leadIds)) return [];
        $this->db->select('cfv.relid AS lead_id, cf.name AS field_name, cfv.value AS field_value', false);
        $this->db->from(db_prefix() . 'customfieldsvalues AS cfv');
        $this->db->join(db_prefix() . 'customfields AS cf', 'cf.id = cfv.fieldid', 'inner');
        $this->db->where('cf.fieldto', 'leads');
        $this->db->where_in('cfv.relid', $leadIds);
        $rows = $this->db->get()->result_array();
        $byLead = [];
        foreach ($rows as $r) {
            $leadId = (int) $r['lead_id'];
            if (!isset($byLead[$leadId])) $byLead[$leadId] = [];
            $byLead[$leadId][(string) $r['field_name']] = (string) ($r['field_value'] ?? '');
        }
        return $byLead;
    }

    private function get_notes_for_leads(array $leadIds)
    {
        if (empty($leadIds)) return [];
        $this->db->select("n.rel_id AS lead_id, n.description AS content, n.dateadded AS date, CONCAT(st.firstname,' ',st.lastname) AS staff_name", false);
        $this->db->from(db_prefix() . 'notes AS n');
        $this->db->join(db_prefix() . 'staff AS st', 'st.staffid = n.addedfrom', 'left');
        $this->db->where('n.rel_type', 'lead');
        $this->db->where_in('n.rel_id', $leadIds);
        $this->db->order_by('n.dateadded', 'DESC');
        $rows = $this->db->get()->result_array();
        $byLead = [];
        foreach ($rows as $r) {
            $leadId = (int) $r['lead_id'];
            if (!isset($byLead[$leadId])) $byLead[$leadId] = [];
            $byLead[$leadId][] = ['content' => (string) ($r['content'] ?? ''), 'date' => (string) ($r['date'] ?? ''), 'staff_name' => $r['staff_name'] !== null ? (string) $r['staff_name'] : null];
        }
        return $byLead;
    }

    private function get_latest_activities_for_leads(array $leadIds, $limitPerLead = 3)
    {
        if (empty($leadIds) || !$this->db->table_exists(db_prefix() . 'lead_activity_log')) return [];
        $this->db->select("a.leadid AS lead_id, a.description, a.date, a.staffid, CONCAT(st.firstname,' ',st.lastname) AS staff_name", false);
        $this->db->from(db_prefix() . 'lead_activity_log AS a');
        $this->db->join(db_prefix() . 'staff AS st', 'st.staffid = a.staffid', 'left');
        $this->db->where_in('a.leadid', $leadIds);
        $this->db->order_by('a.date', 'DESC');
        $rows = $this->db->get()->result_array();
        $byLead = [];
        foreach ($rows as $r) {
            $leadId = (int) $r['lead_id'];
            if (!isset($byLead[$leadId])) $byLead[$leadId] = [];
            if (count($byLead[$leadId]) >= (int) $limitPerLead) continue;
            $byLead[$leadId][] = ['description' => (string) ($r['description'] ?? ''), 'date' => (string) ($r['date'] ?? ''), 'staff_name' => !empty($r['staffid']) && $r['staff_name'] !== null ? (string) $r['staff_name'] : null];
        }
        return $byLead;
    }
}
