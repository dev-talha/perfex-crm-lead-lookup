<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                    <div>
                        <h4 class="tw-font-bold tw-text-xl tw-text-neutral-800 tw-my-0">
                            <i class="fa-solid fa-sliders tw-mr-1.5 tw-text-neutral-600"></i>
                            <?php echo _l('Lead Creator & Lookup Settings'); ?>
                        </h4>
                        <p class="tw-text-neutral-500 tw-text-sm tw-mb-0 tw-mt-1">
                            Manage settings, security policies, duplicate rules, and field mappings.
                        </p>
                    </div>
                    <div class="tw-flex tw-items-center tw-gap-2">
                        <a href="<?php echo admin_url('leadlookup/report'); ?>" class="btn btn-default btn-sm">
                            <i class="fa-solid fa-list-check tw-mr-1"></i> Lead Create Report
                        </a>
                        <a href="<?php echo admin_url('leadlookup/phone_report'); ?>" class="btn btn-default btn-sm">
                            <i class="fa-solid fa-clock-rotate-left tw-mr-1"></i> Phone Lookup Report
                        </a>
                        <a href="<?php echo admin_url('leadlookup/debug'); ?>" class="btn btn-default btn-sm">
                            <i class="fa-solid fa-bug tw-mr-1"></i> Debug
                        </a>
                    </div>
                </div>

                <?php $CI = &get_instance(); $CI->load->view('leadlookup/settings'); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
