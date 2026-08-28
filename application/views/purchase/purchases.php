 <section class="main-content-wrapper">
    <?php
        if ($this->session->flashdata('exception')) {

            echo '<section class="alert-wrapper">
            <div class="alert alert-success alert-dismissible fade show"> 
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <div class="alert-body">
            <p class="m-0"><i class="icon fa fa-check"></i>';
            echo escape_output($this->session->flashdata('exception'));unset($_SESSION['exception']);
            echo '</p></div></div></section>';
        }
    ?>

        <section class="content-header">
            <div class="row">
                <div class="col-sm-12 col-md-8">
                    <h3 class="top-left-header"><?php echo lang('purchases'); ?> </h3>
                    <input type="hidden" class="datatable_name" data-title="<?php echo lang('purchases'); ?>" data-id_name="datatable">
                </div>
                <div class="col-sm-12 col-md-4">

                </div>
            </div>
        </section>

     <div class="box-wrapper">
        <!-- filter the purchase list, handled server side in Purchase::purchases() -->
        <?php echo form_open(base_url() . 'Purchase/purchases', $arrayName = array('id' => 'purchaseFilter')) ?>
        <div class="row mb-3">
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="1" type="text" name="startDate" readonly class="form-control customDatepicker"
                           placeholder="<?php echo lang('start_date'); ?>" value="<?php echo set_value('startDate'); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="2" type="text" name="endDate" readonly class="form-control customDatepicker"
                           placeholder="<?php echo lang('end_date'); ?>" value="<?php echo set_value('endDate'); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="3" class="form-control select2 ir_w_100" name="supplier_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('supplier'); ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option <?php echo set_select('supplier_id', $supplier->id); ?> value="<?php echo escape_output($supplier->id) ?>"><?php echo escape_output($supplier->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="4" class="form-control select2 ir_w_100" name="payment_status">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('status'); ?></option>
                        <option <?php echo set_select('payment_status', 'paid'); ?> value="paid"><?php echo lang('paid'); ?></option>
                        <option <?php echo set_select('payment_status', 'due'); ?> value="due"><?php echo lang('due'); ?></option>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="5" class="form-control select2 ir_w_100" name="category_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('category'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option <?php echo set_select('category_id', $category->id); ?> value="<?php echo escape_output($category->id) ?>"><?php echo escape_output($category->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="6" class="form-control select2 ir_w_100" name="ingredient_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('item'); ?></option>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <option <?php echo set_select('ingredient_id', $ingredient->id); ?> value="<?php echo escape_output($ingredient->id) ?>"><?php echo escape_output($ingredient->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group d-flex">
                    <button tabindex="7" type="submit" name="submit" value="submit"
                            class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                    <a class="btn btn-secondary w-100 ms-2" href="<?php echo base_url() ?>Purchase/purchases"><?php echo lang('reset'); ?></a>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
        <div class="table-box">
                 <!-- /.box-header -->
                 <div class="table-responsive">
                     <table id="datatable" class="table table-responsive">
                         <thead>
                             <tr>
                                 <th class="ir_w_1"><?php echo lang('sn'); ?></th>
                                 <th class="ir_w_11"><?php echo lang('ref_no'); ?></th>
                                 <th class="ir_w_11"><?php echo lang('payment_method'); ?></th>
                                 <th class="ir_w_8"><?php echo lang('date'); ?></th>
                                 <th class="ir_w_18"><?php echo lang('supplier'); ?></th>
                                 <th class="ir_w_9"><?php echo lang('g_total'); ?></th>
                                 <th class="ir_w_9"><?php echo lang('paid'); ?></th>
                                 <th class="ir_w_9"><?php echo lang('due'); ?></th>
                                 <th class="ir_w_12"><?php echo lang('added_by'); ?></th>
                                 <th class="ir_w5_txt_center not-export-col"><?php echo lang('actions'); ?></th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php
                            if ($purchases && !empty($purchases)) {
                                $i = count($purchases);
                            }
                            foreach ($purchases as $prchs) {
                                ?>
                             <tr>
                                 <td><?php echo escape_output($i--); ?></td>
                                 <td><?php echo escape_output($prchs->reference_no) ?></td>
                                 <td> <?php echo escape_output(getPaymentName($prchs->payment_id)) ; ?></td>
                                 <td><?php echo escape_output(date($this->session->userdata('date_format'), strtotime($prchs->date))); ?> </td>
                                 <td><?php echo getSupplierNameById($prchs->supplier_id); ?></td>
                                 <td><?php echo escape_output(getAmtPCustom($prchs->grand_total)) ?></td>
                                 <td><?php echo escape_output(getAmtPCustom($prchs->paid)) ?></td>
                                 <td><?php echo escape_output(getAmtPCustom($prchs->due)) ?></td>
                                 <td><?php echo escape_output(userName($prchs->user_id)); ?></td>

                                 <td>
                                    <div class="btn_group_wrap">
                                        <a class="btn btn-unique " href="<?php echo base_url() ?>Purchase/purchaseDetails/<?php echo escape_output($this->custom->encrypt_decrypt($prchs->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-bs-original-title="<?php echo lang('view_details'); ?>">
                                            <i class="far fa-eye"></i>
                                        </a>

                                        <a class="btn btn-cyan" href="<?php echo base_url() ?>Purchase/barcode/<?php echo escape_output($this->custom->encrypt_decrypt($prchs->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-bs-original-title="<?php echo lang('barcode'); ?>">
                                            <i class="fa fa-barcode tiny-icon"></i>
                                        </a>

                                        <a class="btn btn-warning" href="<?php echo base_url() ?>Purchase/addEditPurchase/<?php echo escape_output($this->custom->encrypt_decrypt($prchs->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-bs-original-title="<?php echo lang('edit'); ?>">
                                            <i class="far fa-edit"></i>
                                        </a>
                                        <a class="delete btn btn-danger" href="<?php echo base_url() ?>Purchase/deletePurchase/<?php echo escape_output($this->custom->encrypt_decrypt($prchs->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="<?php echo lang('delete'); ?>">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>

                                 
                             </tr>
                             <?php
                            }
                            ?>
                         </tbody>
                         
                     </table>
                 </div>
                 <!-- /.box-body -->
        </div>
     </div>
 </section>


 <?php $this->view('common/footer_js')?>
