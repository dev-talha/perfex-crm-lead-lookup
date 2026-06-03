<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="tw-font-semibold tw-mt-0 pull-left">Lead Lookup Debug</h4>
                            <a class="btn btn-default pull-right" href="<?php echo admin_url('leadlookup/settings'); ?>">Back to Settings</a>
                        </div>
                        <hr />

                        <div class="alert alert-info">
                            This page is administrator-only. It does not show API keys or secrets. Webhook lead creation is currently protected by allowed domain/IP validation only.
                        </div>

                        <h4>Endpoints</h4>
                        <table class="table table-bordered">
                            <tbody>
                                <tr><th style="width:220px;">Chatwoot webhook endpoint</th><td><code><?php echo e($endpoint); ?></code></td></tr>
                                <tr><th>Phone lookup endpoint</th><td><code><?php echo e($lookup_endpoint); ?></code></td></tr>
                                <tr><th>Module version</th><td><?php echo e($module_version); ?></td></tr>
                                <tr><th>Sync log table</th><td><?php echo !empty($log_table_exists) ? '<span class="label label-success">Exists</span>' : '<span class="label label-danger">Missing</span>'; ?></td></tr>
                            </tbody>
                        </table>

                        <h4>Allowed Domain/IP Entries</h4>
                        <p class="text-muted">Raw setting accepts comma-separated values or one item per line.</p>
                        <pre><?php echo e($allowed_raw); ?></pre>
                        <?php if (empty($allowed_entries)) { ?>
                            <div class="alert alert-warning">No allowed domain/IP is configured. Chatwoot webhook requests will be rejected.</div>
                        <?php } else { ?>
                            <table class="table table-condensed table-bordered">
                                <thead><tr><th>#</th><th>Normalized entry</th><th>Type</th></tr></thead>
                                <tbody>
                                    <?php foreach ($allowed_entries as $i => $entry) { ?>
                                        <tr>
                                            <td><?php echo (int) $i + 1; ?></td>
                                            <td><code><?php echo e($entry); ?></code></td>
                                            <td><?php echo filter_var($entry, FILTER_VALIDATE_IP) ? 'IP address' : 'Domain'; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>

                        <h4>Test Domain/IP Match</h4>
                        <?php echo form_open(admin_url('leadlookup/debug'), ['method' => 'get']); ?>
                            <div class="input-group">
                                <input type="text" name="source" value="<?php echo e($test_source); ?>" class="form-control" placeholder="example.com, https://example.com/page, or 203.190.34.162">
                                <span class="input-group-btn"><button class="btn btn-primary" type="submit">Test</button></span>
                            </div>
                        <?php echo form_close(); ?>
                        <?php if (is_array($test_result)) { ?>
                            <br />
                            <div class="alert alert-<?php echo $test_result['matched'] ? 'success' : 'danger'; ?>">
                                Normalized: <code><?php echo e($test_result['normalized']); ?></code><br>
                                Result: <?php echo $test_result['matched'] ? 'Allowed' : 'Blocked'; ?>
                            </div>
                        <?php } ?>

                        <h4>Server Request Context</h4>
                        <table class="table table-condensed table-bordered">
                            <tbody>
                                <?php foreach ($server as $key => $value) { ?>
                                    <tr><th style="width:260px;"><?php echo e($key); ?></th><td><code><?php echo e($value); ?></code></td></tr>
                                <?php } ?>
                            </tbody>
                        </table>

</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
