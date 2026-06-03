<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * Perfex loads this file very early from application/hooks/InitModules.php
 * and merges these URI patterns into the CSRF exclude list before
 * App_Security::csrf_verify() runs.
 *
 * This allows external Chatwoot/webhook POST requests to reach the module
 * controller without editing application/config/config.php.
 */
return [
    'leadlookup/create_from_chatwoot',
];
