# Lead Lookup & Lead Create API from Chatwoot
![Version](https://img.shields.io/badge/version-1.3.6-blue.svg)
![Perfex CRM](https://img.shields.io/badge/Perfex%20CRM-Compatible-success.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-3.x-EF4223.svg)
![License](https://img.shields.io/badge/license-Proprietary-lightgrey.svg)

A professional Perfex CRM module that provides phone-based lead lookup and Chatwoot webhook-based lead creation with reporting, logging, role-based access control, and configurable domain/IP validation.

> [!IMPORTANT]
> This module is designed for Perfex CRM installations. Install it as a standard Perfex module under `modules/leadlookup`.

---

## Table of Contents

- [Project Overview](#project-overview)
- [What the Module Does](#what-the-module-does)
- [Key Features and Benefits](#key-features-and-benefits)
- [Supported Platforms and Environments](#supported-platforms-and-environments)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [User Guide](#user-guide)
- [Developer Guide](#developer-guide)
- [API Reference](#api-reference)
- [Reports and Permissions](#reports-and-permissions)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Performance and Security Notes](#performance-and-security-notes)
- [Versioning and Changelog](#versioning-and-changelog)
- [Support](#support)
- [License](#license)

---

## Project Overview

**Lead Lookup & Chatwoot Lead Sync** is a Perfex CRM module built to connect customer communication workflows with CRM lead management.

The module provides:

1. A phone lookup API endpoint for retrieving lead information by phone number.
2. A Chatwoot webhook endpoint for creating new leads from Chatwoot conversation/contact payloads.
3. Sync logs and reports for monitoring successful, failed, skipped, and pending requests.
4. Domain/IP validation with an admin-controlled enable/disable option.
5. Role-based report access for staff.
6. Administrator-only settings and debug tools.

This module is suitable for internal CRM automation, call center workflows, Chatwoot integration, softphone integrations, and support-to-sales lead creation processes.

---

## What the Module Does

### Phone Lookup API

Endpoint:

```text
GET /leadlookup/by_phone
```

This endpoint allows an external system to search Perfex CRM leads by phone number.

Example use cases:

- Softphone caller popup
- Call center screen pop
- Internal lead lookup service
- CRM-integrated telephony system

### Chatwoot Lead Creation API

Endpoint:

```text
POST /leadlookup/create_from_chatwoot
```

This endpoint receives Chatwoot webhook or macro payloads and creates a new Perfex CRM lead using sender information. The module checks existing leads by phone or email before creating a new one.

---

## Key Features and Benefits

| Feature | Benefit |
|---|---|
| Phone lookup endpoint | Quickly find CRM leads by phone number |
| Chatwoot webhook endpoint | Automatically create Perfex leads from Chatwoot conversations |
| Create-only lead sync | Prevents accidental updates to existing lead data |
| Duplicate prevention | Avoids duplicate leads when phone/email already exists |
| Customer-status exception | Allows creating a new lead if the existing matched record is already a customer |
| Domain/IP validation | Restricts API access to approved sources |
| Enable/disable domain/IP validation | Flexible testing and production control |
| Static phone lookup API key | Keeps legacy phone lookup behavior simple and predictable |
| Sync logs | Tracks webhook request results |
| Phone lookup report | Tracks phone lookup API usage |
| Lead create report | Tracks Chatwoot lead creation activity |
| Role-based report permissions | Allows staff access control |
| Admin-only settings | Protects module configuration |
| Debug page | Helps diagnose endpoint, table, and source validation issues |

---

## Supported Platforms and Environments

| Component | Supported |
|---|---|
| Perfex CRM | Compatible with standard Perfex CRM module architecture |
| PHP | 7.4+ recommended |
| Database | MySQL / MariaDB |
| Framework | CodeIgniter 3.x / Perfex CRM environment |
| Web Server | Apache, Nginx, LiteSpeed |
| Chatwoot | Self-hosted or cloud webhook payloads |
| OS | Linux hosting recommended |

> [!NOTE]
> This module is intended to run inside Perfex CRM. It is not a standalone PHP application.

---

## Installation

### Prerequisites

Before installing, make sure you have:

- A working Perfex CRM installation.
- Administrator access to Perfex CRM.
- File manager, FTP, SFTP, or SSH access to the server.
- PHP extensions required by Perfex CRM.
- MySQL/MariaDB database access.
- Chatwoot webhook/macro access if using Chatwoot sync.

### Installation Steps

1. Download the module ZIP package.
2. Extract the ZIP file.
3. Upload the `leadlookup` folder to:

```text
modules/leadlookup
```

4. Go to Perfex CRM admin panel:

```text
Setup → Modules
```

5. Find **Lead Lookup**.
6. Click **Activate**.
7. Go to:

```text
Setup → Lead Lookup
```

8. Configure allowed domains/IPs, default lead values, and Chatwoot settings as needed.

### Dependency Requirements

No external Composer packages are required.

The module depends on existing Perfex CRM core services:

- Database layer
- Lead model
- Staff permissions
- Module activation hooks
- Admin menu hooks
- Config loading
- CSRF exclude URI loading through module config

---

## Quick Start

### 1. Configure Static Phone Lookup API Key

Edit:

```text
modules/leadlookup/config/leadlookup.php
```

Set:

```php
$config['leadlookup'] = [
    'api_key' => 'my-secret-static-key-2026',
    'phone_match' => 'like',
];
```

Test:

```text
https://your-crm-domain.com/leadlookup/by_phone?apikey=my-secret-static-key-2026&phone=01769947530
```

### 2. Configure Chatwoot Webhook Endpoint

Use this endpoint in Chatwoot:

```text
https://your-crm-domain.com/leadlookup/create_from_chatwoot
```

Method:

```text
POST
```

Content-Type:

```text
application/json
```

### 3. Configure Domain/IP Validation

Go to:

```text
Setup → Lead Lookup
```

Set:

```text
Enable domain/IP validation: Yes
Allowed source domains/IP addresses: app.example.com, 203.0.113.10
```

> [!TIP]
> For self-hosted Chatwoot, allowlisting the server public IP is usually more reliable than only using the Chatwoot domain.

---

## Configuration

### Static Config File

Path:

```text
modules/leadlookup/config/leadlookup.php
```

| Option | Type | Default | Description |
|---|---:|---|---|
| `api_key` | string | `YOUR_STATIC_SECRET_KEY_HERE` | Static API key for `/leadlookup/by_phone` |
| `phone_match` | string | `like` | Phone search mode. Usually `like` or exact-style implementation depending on module version |
| `chatwoot_default_status_id` | string/int | empty | Optional default lead status ID |
| `chatwoot_default_source_id` | string/int | empty | Optional default lead source ID |
| `chatwoot_default_assigned_id` | string/int | empty | Optional default assigned staff ID |
| `chatwoot_allowed_domains` | string | empty | Fallback allowed domains/IP list |
| `domain_ip_validation_enabled` | string | `1` | Fallback validation toggle |
| `chatwoot_base_url` | string | empty | Chatwoot base URL for conversation links |
| `chatwoot_account_id` | string/int | empty | Chatwoot account ID for conversation links |

Example:

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['leadlookup'] = [
    'api_key' => 'my-secret-static-key-2026',
    'phone_match' => 'like',

    'chatwoot_default_status_id'   => '',
    'chatwoot_default_source_id'   => '',
    'chatwoot_default_assigned_id' => '',

    'chatwoot_allowed_domains' => '',
    'domain_ip_validation_enabled' => '1',
    'chatwoot_base_url' => 'https://app.unichat.com.bd',
    'chatwoot_account_id' => '5',
];
```

### Admin Settings

Location:

```text
Setup → Lead Lookup
```

| Setting | Description |
|---|---|
| Enable domain/IP validation | Enables or disables allowed domain/IP validation |
| Allowed source domains/IP addresses | Comma-separated domain/IP allowlist |
| Default assigned staff ID | Optional staff ID for new leads |
| Default lead source ID | Optional source ID for new leads |
| Default lead status ID | Optional status ID for new leads |
| Chatwoot base URL | Used to generate conversation links |
| Chatwoot account ID | Used to generate conversation links |
| Save raw webhook payload in logs | Stores raw webhook JSON for debugging |

> [!WARNING]
> Saving raw webhook payloads may store customer names, phone numbers, emails, IP addresses, messages, and metadata. Disable this in production unless needed for debugging.

### Environment Variables

This module does not require environment variables by default.

For enterprise deployments, you may adapt the module to read sensitive values from environment variables, for example:

```text
LEADLOOKUP_API_KEY
CHATWOOT_BASE_URL
CHATWOOT_ACCOUNT_ID
```

---

## User Guide

### Common Workflow: Phone Lookup

1. External system sends a request to `/leadlookup/by_phone`.
2. The module validates the static API key.
3. If domain/IP validation is enabled, the module validates the request source.
4. The module searches leads by phone number.
5. The result is returned as JSON.
6. The lookup request is recorded in the Phone Lookup Report.

### Common Workflow: Chatwoot Lead Creation

1. Chatwoot macro/webhook sends a POST request to `/leadlookup/create_from_chatwoot`.
2. The module validates the request method, JSON payload, required sender fields, and source domain/IP if enabled.
3. The module extracts name, phone, email, city, company, position/description, country, and social profile as website.
4. The module checks existing leads by phone or email.
5. If a matching lead exists and it is not a customer, the request is skipped.
6. If a matching lead exists and it is already a customer, a new lead can be created.
7. If no matching lead exists, a new lead is created.
8. A sync log is saved.

### Best Practices

- Use a strong static API key for phone lookup.
- Keep domain/IP validation enabled in production.
- Add the actual Chatwoot server public IP to the allowed list.
- Use HTTPS only.
- Disable raw payload logging after testing.
- Regularly review failed and skipped logs.
- Do not expose admin report URLs publicly.
- Use role-based permissions for staff report access.

---

## Developer Guide

### Project Structure

Typical structure:

```text
leadlookup/
├── config/
│   ├── leadlookup.php
│   ├── routes.php
│   └── csrf_exclude_uris.php
├── controllers/
│   ├── Leadlookup.php
│   └── Leadlookup_api.php
├── models/
│   └── Leadlookup_model.php
├── views/
│   ├── settings.php
│   ├── debug.php
│   ├── report.php
│   ├── phone_report.php
│   └── partials/
├── language/
│   └── english/
├── install.php
├── uninstall.php
├── leadlookup.php
└── README.md
```

### Architecture Overview

The module separates public API handling from admin/report handling.

| Layer | Responsibility |
|---|---|
| `Leadlookup_api` controller | Public API endpoints |
| `Leadlookup` controller | Admin settings, debug, reports |
| `Leadlookup_model` | Database operations, logs, lead lookup, lead creation |
| `views/` | Admin UI, reports, debug page |
| `config/leadlookup.php` | Static API configuration |
| `config/csrf_exclude_uris.php` | CSRF exclusion for webhook endpoint |
| `install.php` | Database table creation and migration |
| `leadlookup.php` | Module bootstrap, hooks, menus, permissions |

### Development Setup

1. Install Perfex CRM locally or on a staging server.
2. Copy the module into `modules/leadlookup`.
3. Enable development logging in Perfex.
4. Activate the module from the admin panel.
5. Test endpoints with cURL/Postman.
6. Check logs under `application/logs`.

### Build and Test Instructions

No build step is required.

Recommended syntax tests:

```bash
php -l modules/leadlookup/leadlookup.php
php -l modules/leadlookup/controllers/Leadlookup.php
php -l modules/leadlookup/controllers/Leadlookup_api.php
php -l modules/leadlookup/models/Leadlookup_model.php
```

Test phone lookup:

```bash
curl "https://your-crm-domain.com/leadlookup/by_phone?apikey=my-secret-static-key-2026&phone=01765997530"
```

Test Chatwoot lead creation:

```bash
curl -X POST "https://your-crm-domain.com/leadlookup/create_from_chatwoot" \
  -H "Content-Type: application/json" \
  -H "X-Chatwoot-Source-Domain: app.unichat.com.bd" \
  -d '{
    "meta": {
      "sender": {
        "id": 681795,
        "name": "Abu Talha",
        "email": "talha@example.com",
        "phone_number": "+8801765997530",
        "additional_attributes": {
          "city": "Bogura",
          "country": "Bangladesh",
          "description": "CEO at AT Space",
          "company_name": "AT Space",
          "country_code": "BD",
          "social_profiles": {
            "linkedin": "abutalha"
          }
        }
      }
    },
    "id": 7966,
    "inbox_id": 126,
    "event": "macro.executed"
  }'
```

### Contribution Guidelines

1. Create a feature branch.
2. Keep backward compatibility for existing endpoints.
3. Do not modify Perfex core files.
4. Add migration-safe database changes.
5. Add admin permission checks for new admin pages.
6. Sanitize all input.
7. Log meaningful errors.
8. Test with both successful and failed payloads.
9. Update the README and changelog.

---

## API Reference

### `GET /leadlookup/by_phone`

Searches Perfex leads by phone number.

#### Authentication

Static API key from:

```text
modules/leadlookup/config/leadlookup.php
```

#### Query Parameters

| Parameter | Type | Required | Description |
|---|---:|---:|---|
| `apikey` | string | Yes | Static API key |
| `phone` | string | Yes | Phone number to search |

#### Header Alternative

| Header | Required | Description |
|---|---:|---|
| `X-API-Key` | Optional | Static API key alternative |

#### Example Request

```text
https://your-crm-domain.com/leadlookup/by_phone?apikey=my-secret-static-key-2026&phone=01765997530
```

#### Success Response

```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "name": "Abu Talha",
      "phonenumber": "+8801765997530",
      "email": "talha@example.com",
      "status": "New"
    }
  ]
}
```

#### Error Response

```json
{
  "status": "error",
  "message": "Invalid or missing API key.",
  "data": []
}
```

---

### `POST /leadlookup/create_from_chatwoot`

Creates a new Perfex lead from a Chatwoot webhook payload.

#### Authentication

No API key is required for this endpoint in the current design.

Security is controlled by:

- Domain/IP validation toggle
- Allowed source domains/IP addresses
- POST-only validation
- JSON validation
- Required field validation

#### Required Payload Fields

| Field | Required | Description |
|---|---:|---|
| `meta.sender.name` or `messages[0].sender.name` | Yes | Lead name |
| `meta.sender.phone_number` or `messages[0].sender.phone_number` | Conditional | Required if email is missing |
| `meta.sender.email` or `messages[0].sender.email` | Conditional | Required if phone is missing |

#### Example Request

```json
{
  "meta": {
    "sender": {
      "id": 681795,
      "name": "Abu Talha",
      "email": "talha@example.com",
      "phone_number": "+8801799447530",
      "additional_attributes": {
        "city": "Bogura",
        "country": "Bangladesh",
        "description": "CEO at AT Space",
        "company_name": "AT Space",
        "country_code": "BD",
        "social_profiles": {
          "linkedin": "abutalha"
        }
      }
    }
  },
  "id": 7966,
  "inbox_id": 126,
  "event": "macro.executed"
}
```

#### Created Lead Description

The module keeps the lead description clean:

```text
Lead synced from Chatwoot webhook with
Conversation link: https://app.demo.com.bd/app/accounts/{id}/conversations/{conversation-id}
```

#### Success Response

```json
{
  "status": "success",
  "message": "Lead created successfully.",
  "data": {
    "lead_id": 123
  }
}
```

#### Duplicate/Skipped Response

```json
{
  "status": "skipped",
  "message": "Existing lead found by phone or email. New lead was not created.",
  "data": {
    "matched_lead_id": 123
  }
}
```

#### Error Response

```json
{
  "status": "error",
  "message": "Name and at least one contact field, phone or email, are required.",
  "data": []
}
```

---

## Reports and Permissions

### Admin Pages

| Page | URL | Access |
|---|---|---|
| Settings | `/admin/leadlookup/settings` | Administrator only |
| Debug | `/admin/leadlookup/debug` | Administrator only |
| Lead Create Report | `/admin/leadlookup/report` | Permission-based |
| Phone Lookup Report | `/admin/leadlookup/phone_report` | Permission-based |

### Permissions

| Permission Group | Permission | Description |
|---|---|---|
| Lead Lookup Reports | View Lead Create Report | Allows viewing Chatwoot lead create logs |
| Lead Lookup Reports | Delete Lead Create Report Logs | Allows deleting lead create logs |
| Lead Lookup Phone Reports | View Phone Lookup Report | Allows viewing phone lookup logs |
| Lead Lookup Phone Reports | Delete Phone Lookup Report Logs | Allows deleting phone lookup logs |

### Report Filters

Both report pages support:

- Status filter
- Date from
- Date to
- Search
- Per-page selection
- Pagination
- Export button
- Single delete if permitted
- Bulk delete if permitted

---

## Troubleshooting

### Issue: `Invalid or missing API key`

**Root Cause**

The `/leadlookup/by_phone` endpoint did not receive the correct static API key.

**Fix**

Check:

```text
modules/leadlookup/config/leadlookup.php
```

Make sure:

```php
'api_key' => 'my-secret-static-key-2026',
```

Then call:

```text
/leadlookup/by_phone?apikey=my-secret-static-key-2026&phone=01765997530
```

If OPcache is enabled, restart PHP-FPM or clear OPcache.

### Issue: `419 Page Expired`

**Root Cause**

CSRF protection is blocking the webhook POST request.

**Fix**

Ensure the module file exists:

```text
modules/leadlookup/config/csrf_exclude_uris.php
```

It should include:

```php
return [
    'leadlookup/create_from_chatwoot',
];
```

Deactivate and activate the module again.

> [!IMPORTANT]
> Do not disable CSRF globally in Perfex CRM.

### Issue: Request rejected because domain/IP is not allowed

**Root Cause**

Domain/IP validation is enabled, but the incoming request source does not match the allowlist.

**Fix**

1. Go to `Setup → Lead Lookup`.
2. Add the actual source IP/domain:

```text
app.unichat.com.bd, 203.0.113.10
```

3. For self-hosted Chatwoot, check server access logs to find the real incoming IP.

### Issue: Chatwoot sends payload but no lead is created

**Possible Causes**

- Domain/IP validation failed.
- Missing name.
- Both phone and email are missing.
- Matching lead already exists and is not a customer.
- Database error.
- Required default status/source/assigned value invalid.

**Fix**

1. Check Lead Create Report.
2. Check Debug page.
3. Check `application/logs`.
4. Test with cURL.
5. Confirm source IP/domain allowlist.

### Issue: Logs remain `pending`

**Root Cause**

The request log was created but the sync process did not reach success, skipped, or failed status.

**Fix**

- Check PHP error logs.
- Check database errors.
- Retry the request if retry is available.
- Review raw payload if enabled.
- Confirm required fields are present.

### Issue: Reports show blank table

**Root Cause**

Older logs may have missing `log_type`, or the module migration was not run.

**Fix**

Deactivate and activate the module once. The module should normalize old records to `lead_create`.

---

## FAQ

### Does the module update existing leads?

No. The current design is create-only.

If a matching lead exists by email or phone and that lead is not a customer, the module skips creating a duplicate.

### When can a new lead be created even if a match exists?

If the existing matched record has customer status, the module may create a new lead.

### Where is the phone lookup API key stored?

It is stored statically in:

```text
modules/leadlookup/config/leadlookup.php
```

### Can I disable domain/IP validation?

Yes. Go to `Setup → Lead Lookup` and set:

```text
Enable domain/IP validation: No
```

### Does the Chatwoot endpoint require an API key?

No. It uses domain/IP validation and payload validation.

### Can staff view reports?

Yes, if they have the correct role permission.

### Can staff delete logs?

Only if their role has delete permission for the related report.

### Does the module modify Perfex core files?

No. The module is designed to avoid core file modifications.

---

## Performance and Security Notes

### Optimization Recommendations

- Keep report tables paginated.
- Regularly delete old logs.
- Disable raw payload logging in production.
- Add database indexes for large installations.
- Use exact or normalized phone matching if the database is large.
- Avoid unnecessary large payload storage.

### Security Considerations

- Use HTTPS.
- Use a strong static API key for phone lookup.
- Keep domain/IP validation enabled in production.
- Add only trusted IPs/domains to the allowlist.
- Restrict report access with staff permissions.
- Do not expose admin URLs publicly.
- Do not log secrets.
- Disable raw payload logging unless troubleshooting.
- Do not modify Perfex core files to bypass security.

> [!WARNING]
> Domain headers can be spoofed. For production, IP allowlisting is stronger than relying only on source domain headers.

---

## Versioning and Changelog

This project uses semantic-style versioning:

```text
MAJOR.MINOR.PATCH
```

### Release Process

1. Update module version.
2. Run PHP syntax checks.
3. Test activation and database migrations.
4. Test phone lookup endpoint.
5. Test Chatwoot webhook lead creation.
6. Test reports and permissions.
7. Update README and changelog.
8. Package module ZIP.

### Version Compatibility

| Version | Notes |
|---|---|
| 1.3.6 | Domain/IP validation toggle added while keeping static phone API key |
| 1.3.5 | Restored static config API key for phone lookup |
| 1.3.4 | Added settings-based phone API key field, later reverted |
| 1.3.3 | Fixed all report table rendering issues |
| 1.3.2 | Added phone lookup report |
| 1.3.1 | Added create-only lead sync and report permissions |
| 1.3.0 | Removed custom attribute mapping and cleaned logs |
| 1.2.x | Added advanced Chatwoot sync, debug page, CSRF module fix |
| 1.1.x | Added initial Chatwoot create endpoint |
| 1.0.x | Initial phone lookup module |

---

## Support

### How to Report Bugs

When reporting an issue, include:

- Module version
- Perfex CRM version
- PHP version
- Endpoint used
- Request method
- Error response
- Relevant log entry
- Screenshot if UI-related
- Example payload with sensitive data removed

### Feature Requests

For feature requests, describe:

- Business use case
- Expected workflow
- Required fields
- Security requirements
- Report or UI requirements
- Backward compatibility needs


---

## Final Notes

This README is suitable for GitHub, private repositories, enterprise documentation, and developer handoff.

For production use, review these settings carefully:

- Static phone lookup API key
- Domain/IP validation
- Raw payload logging
- Staff report permissions
- Server access logs
