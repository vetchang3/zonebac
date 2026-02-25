<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline"><span class="dashicons dashicons-portfolio"></span> Banque d'Exercices</h1>
    <hr class="wp-header-end">

    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <form method="get">
                        <input type="hidden" name="page" value="zonebac-ex-bank" />
                        <?php
                        if (isset($ex_bank_table)) {
                            $ex_bank_table->prepare_items();
                            $ex_bank_table->display();
                        }
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>