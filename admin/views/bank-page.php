<?php if (!defined('ABSPATH')) {
    exit;
} ?>
<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-database"></span> Banque de Questions</h1>
    <hr class="wp-header-end">

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="get">
                        <input type="hidden" name="page" value="zonebac-bank" />
                        <?php
                        require_once plugin_dir_path(__FILE__) . '../../includes/controllers/class-bank-list-table.php';
$bank_table = new Zonebac_Bank_List_Table();
$bank_table->prepare_items();
$bank_table->display();
?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>