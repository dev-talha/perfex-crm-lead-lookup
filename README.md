# Lead Creator & Lookup Module

Version 1.4.3

An internal Perfex CRM module providing a webhook endpoint for **UniChat** lead creation and an API endpoint for **Phone Lookup**, featuring domain/IP security validation, configurable duplicate rules, assignee staff mapping, chat source/channel mapping, private note callbacks, and role-based activity reports.

---

## Endpoints

### 1. Lead Creation Webhook (UniChat)
- **URL:** `POST /leadlookup/create_from_chatwoot`
- **Authentication / Security:** Protected by Domain/IP Validation (when enabled).
- **CSRF:** Automatically excluded in Perfex CRM CSRF verify hook.
- **Payload:** JSON containing sender info (`name`, `phone_number` and/or `email`, `additional_attributes`, etc.).

### 2. Phone Lookup API
- **URL:** `GET /leadlookup/by_phone?apikey=YOUR_API_KEY&phone=01700000000`
- **Authentication:** Protected by API Key (via URL `apikey` or `X-API-Key` header) **AND** Domain/IP Validation (when enabled).
- **API Key Setup:** Configurable via `Setup -> Lead Creator & Lookup -> Phone Lookup API`.

---

## Security & Domain/IP Validation

- **Setting:** `Enable domain/IP validation` (Recommended: Enabled).
- **Validation Scope:** Applied to both public endpoints (`/leadlookup/create_from_chatwoot` and `/leadlookup/by_phone`).
- **Sources Evaluated:** Inspects request metadata only (`Origin`, `Referer`, `X-UniChat-Source-Domain`, `REMOTE_ADDR`, `HTTP_X_REAL_IP`, `HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`).
- **Domain & IP Matching:** Supports exact domain names, wildcard domains (e.g. `.example.com`), IPv4/IPv6 addresses, and server-to-server IP resolution via A/AAAA records.

---

## Settings & Administration

URL: `/admin/leadlookup/settings` (Setup -> Lead Creator & Lookup)

1. **General & Webhook Settings:**
   - UniChat Base URL & Fallback Account ID.
   - **Log Maintenance & Retention:** Configurable `Auto purge logs older than (days)` setting (Default: `30` days).
2. **Security & Allowed Domains/IPs:**
   - Enable/disable domain and IP security check.
   - Textarea for allowed domains or IP addresses.
3. **Default Lead Values & Duplicate Rules:**
   - Default lead status, default lead source, and default assigned staff.
   - Enable/disable duplicate check by email.
   - Enable/disable duplicate check by phone.
   - Save raw payload toggle (full JSON vs. summary).
4. **Assignee Staff Mapping:**
   - Map UniChat Agent / Assignee IDs to Perfex CRM Staff Members.
5. **Chat Source / Channel Mapping:**
   - Map UniChat Channel types (`Channel::WebWidget`, `Channel::FacebookPage`, `Channel::Whatsapp`, `Channel::Email`, `Channel::Telegram`, `Channel::Instagram`, etc.) to Perfex CRM Lead Sources.
6. **UniChat Private Note Callback:**
   - Enable/disable private note callback upon lead creation, skip, or failure.
   - UniChat Profile API Access Token field.
   - **Requirement Note:** If using a non-admin agent's API Token, that agent must be assigned to all target inboxes in UniChat where lead creation is required.
7. **Phone Lookup API Configuration:**
   - Configurable API Key management for `/leadlookup/by_phone`.

---

## Reports & Role Capabilities

Accessible under **Utilities** menu or via direct URLs for staff members with assigned permissions:

### 1. Lead Create Report
- **URL:** `/admin/leadlookup/report`
- **Menu:** `Utilities -> Lead Create Report`
- **Permission Group:** `Lead Creator & Lookup Reports` (`view`, `delete`)

### 2. Phone Lookup Report
- **URL:** `/admin/leadlookup/phone_report`
- **Menu:** `Utilities -> Phone Lookup Report`
- **Permission Group:** `Lead Creator & Lookup Phone Reports` (`view`, `delete`)

### 3. Log Features
- Status Filter, Date From / Date To Filter, Search across IDs, statuses, payload and errors.
- Per-page pagination, CSV Export, Single & Bulk Delete.
- Manual Retry for failed/skipped lead creation logs.
- Automatic log purging based on configured `leadlookup_log_retention_days`.

---

## Debug Page

URL: `/admin/leadlookup/debug`

Administrator-only diagnostic tool for inspecting server environment variables (`REMOTE_ADDR`, `X-Real-IP`, `X-Forwarded-For`), database log table status, and testing incoming source domain/IP normalization and allowed matching logic.

---

## Upgrade Instructions

When upgrading from an older version (e.g. v1.3.9 to v1.4.0):

1. **Replace Files:** Overwrite all files inside your CRM's `modules/leadlookup/` directory.
2. **Re-activate Module:** Go to **Setup -> Modules**, click **Deactivate**, then click **Activate** on **Lead Creator & Lookup**.
3. **Verify Settings:** Navigate to **Setup -> Lead Creator & Lookup** to confirm settings and check the new `Log Retention Period` setting.

---

## Version Changelog

### v1.4.3
- **Perfex Native Searchable Dropdowns:** Integrated Perfex CRM's default Bootstrap Select (`selectpicker` with `data-live-search="true"`) for all dropdowns in **Chat Source Mapping** and **Assignee Staff Mapping** tables. Allows searching channels, lead sources, and staff members dynamically with live search.
- **Dynamic Row & Overflow Handling:** Enhanced table wrappers with visible overflow to prevent dropdown clipping, and bound `init_selectpicker()` to new row additions and tab switching events.

### v1.4.2
- **Lead Creator & Activity Log Attribution:** Fixed lead creator name attribution in Perfex CRM Activity Log. When leads are created via UniChat webhook, the mapped staff member is now automatically registered as `addedfrom` and assigned as the creator in `tbllead_activity_log` (`[Staff Avatar] [Staff Name] - created lead`), preventing empty `- created lead` entries.
- **Assignee Notification Log Fix:** Enhanced the initial `assigned to` activity feed entry to properly reflect attribution rather than starting with a blank string.

### v1.4.1
- **Phone Report Native DataTables UI:** Replaced legacy custom table with Perfex CRM native `render_datatable('phone-reports')` featuring server-side AJAX processing, live search, date filtering, pagination, and export tools.
- **Phone Lookup Details Modal & Clear Logs:** Added an AJAX-powered modal for inspecting query summaries and client IP/user-agent details, along with a permission-based "Clear All Logs" button.
- **Chat Source / Channel Mapping:** Added interactive mapping tab to link UniChat channel types (`Channel::WebWidget`, `Channel::FacebookPage`, `Channel::Whatsapp`, `Channel::Email`, `Channel::Telegram`, `Channel::Sms`, etc.) to specific Perfex CRM Lead Sources.
- **UniChat Private Note Delivery Hardening:** Added HTTP redirect-following (`CURLOPT_FOLLOWLOCATION` & `CURLOPT_POSTREDIR`) to handle `.com.bd` -> `.bd` domain moves seamlessly, resilient `conversation_id` & `account_id` payload parsing, and detailed error logging.
- **Database Performance Optimization:** Added `idx_created_at` index on `leadlookup_sync_logs` for lightning-fast date range filtering and cron log retention purging.
- **Security Hardening:** Enforced robust output escaping with `html_escape()`, strict integer casting on AJAX and route parameters, and explicit route definitions in `routes.php`.

### v1.4.0
- **UniChat Assignee ➔ Staff Mapping:** Added dynamic mapping between UniChat Agent/Assignee IDs and Perfex CRM Staff Members so created leads are automatically assigned to the correct staff.
- **UniChat Private Note Callback:** Added automatic API callback to post private internal notes inside UniChat conversations after a lead is created, skipped, or fails (includes direct CRM lead links & warning alerts for non-admin agent tokens).
- **Settings Page UI Redesign & Log Retention:** Redesigned Settings Page with structured navigation tabs, inbox access warning badge, and configurable Log Retention Period (`leadlookup_log_retention_days`, default 30 days).

### v1.3.9
- Rebranded visible UI and documentation text from Chatwoot to UniChat.
- Added Phone Lookup API key setup from the module settings page.

### v1.3.8
- Added admin setting to enable/disable domain/IP validation.
- Domain/IP validation now applies to both public endpoints when enabled: `/leadlookup/create_from_chatwoot` and `/leadlookup/by_phone`.
- Added settings to enable/disable duplicate checking by email and phone for UniChat lead creation.

### v1.3.3
- Standardized report log tables with responsive scroll wrappers and clear empty states.
- Normalization migration for historical log types (`lead_create` vs `phone_lookup`).
