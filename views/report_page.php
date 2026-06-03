<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="tw-font-semibold tw-mt-0 pull-left"><?php echo e($report_title ?? $title ?? 'Lead Create Report'); ?></h4>
                        </div>
                        <?php if (!empty($report_description)) { ?><p class="text-muted"><?php echo e($report_description); ?></p><?php } ?>
                        <hr />
                        <?php $CI = &get_instance(); $CI->load->view('leadlookup/logs_table', [
                            'filters' => $filters,
                            'logs' => $logs,
                            'pagination' => $pagination,
                            'can_delete_logs' => $can_delete_logs,
                            'base_url' => $base_url,
                            'delete_redirect' => $delete_redirect,
                            'allow_retry' => $allow_retry ?? false,
                            'table_title' => $table_title ?? 'Sync Logs',
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
