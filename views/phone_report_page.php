<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="_buttons tw-mb-4">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <?php echo render_date_input('phone_report_date', '', '', ['placeholder' => _l('filter_by_date') ? _l('filter_by_date') : 'Filter by Date'], [], 'tw-mb-0', 'phone-report-date'); ?>
                        </div>
                        <div class="col-md-8 col-sm-6 text-right">
                            <?php if ($can_delete_logs) { ?>
                                <a href="<?php echo admin_url('leadlookup/clear_phone_logs'); ?>" class="btn btn-danger _delete">
                                    <i class="fa-regular fa-trash-can tw-mr-1"></i> Clear All Logs
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="panel-table-full">
                            <?php render_datatable([
                                _l('the_number_sign'),
                                'Status',
                                'Phone Number',
                                'Matched Lead',
                                'IP Address',
                                'Note / Error',
                                'Date & Time',
                                _l('options'),
                            ], 'phone-reports'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="phone_log_details_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa-solid fa-circle-info tw-mr-1"></i> Phone Lookup Query Details</h4>
            </div>
            <div class="modal-body" id="phone_log_modal_body">
                <div class="text-center ptop20 pbot20"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function(){
    var PhoneReportServerParams = {
        "report_date": '[name="phone_report_date"]'
    };
    var table_phone_reports = $('table.table-phone-reports');
    if (table_phone_reports.length) {
        initDataTable(table_phone_reports, window.location.href, [7], [7], PhoneReportServerParams, [0, 'desc']);

        $('input[name="phone_report_date"]').on('change', function() {
            table_phone_reports.DataTable().ajax.reload();
        });
    }

    $('body').on('click', '.view-phone-log-details', function(e){
        e.preventDefault();
        var logId = $(this).data('id');
        $('#phone_log_details_modal').modal('show');
        $('#phone_log_modal_body').html('<div class="text-center ptop20 pbot20"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
        $.get(admin_url + 'leadlookup/phone_log_details/' + logId, function(response){
            $('#phone_log_modal_body').html(response);
        });
    });
});
</script>
</body>
</html>
