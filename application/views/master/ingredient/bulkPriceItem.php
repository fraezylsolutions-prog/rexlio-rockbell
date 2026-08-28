 <section class="main-content-wrapper">
     <?php
     if ($this->session->flashdata('exception')) {
         echo '<section class="alert-wrapper">
        <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <div class="alert-body">
        <p><i class="m-right fa fa-check"></i>';
         echo escape_output($this->session->flashdata('exception'));unset($_SESSION['exception']);
         echo '</p></div></div></section>';
     }
     if ($this->session->flashdata('exception_err')) {
         echo '<section class="alert-wrapper">
        <div class="alert alert-danger alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <div class="alert-body">
        <p><i class="m-right fa fa-times"></i>';
         // not escaped: the writer builds a multi-line <br> separated error list
         echo ($this->session->flashdata('exception_err'));unset($_SESSION['exception_err']);
         echo '</p></div></div></section>';
     }
     ?>
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('bulk_price_update_item'); ?></h3>
    </section>
    <div class="box-wrapper">
        <div class="table-box">
            <div class="callout callout-info">
                <p><?php echo lang('bulk_price_update_item_note'); ?></p>
            </div>

            <h4><?php echo lang('step').' 1: '.lang('download'); ?></h4>
            <?php echo form_open(base_url('ingredient/downloadItemPrices')); ?>
            <div class="row">
                <div class="col-md-3">
                    <button type="submit" class="btn bg-blue-btn w-100">
                        <i class="fa fa-save m-right"></i><?php echo lang('download'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
            <hr>
            <h4><?php echo lang('step').' 2: '.lang('upload_file'); ?></h4>
            <?php echo form_open_multipart(base_url('ingredient/ExcelDataUpdateItemPrices')); ?>
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label><?php echo lang('upload_file'); ?> <span class="required_star">*</span></label>
                        <input type="file" name="userfile" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <button type="submit" name="submit" value="submit"
                            class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <a class="btn bg-blue-btn w-100" href="<?php echo base_url() ?>ingredient/ingredients">
                        <?php echo lang('back'); ?></a>
                </div>
            </div>
            <?php echo form_close(); ?>
        </div>
    </div>
 </section>
