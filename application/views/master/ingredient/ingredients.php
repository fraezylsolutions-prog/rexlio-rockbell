<section class="main-content-wrapper">
        <?php
        if ($this->session->flashdata('exception')) {

            echo '<section class="alert-wrapper"><div class="alert alert-success alert-dismissible fade show"> 
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <div class="alert-body"><p><i class="m-right fa fa-check"></i>';
            echo escape_output($this->session->flashdata('exception'));unset($_SESSION['exception']);
            echo '</p></div></div></section>';
        }
        ?>

        <section class="content-header">
            <div class="row">
                <div class="col-md-6">
                    <h2 class="top-left-header"><?php echo lang('ingredients'); ?> </h2>
                    <input type="hidden" class="datatable_name" data-title="<?php echo lang('ingredients'); ?>" data-id_name="datatable">
                </div>
                <div class="col-md-offset-2 col-md-4">
                    
                    <div class="btn_list m-right d-flex">
                            <a data-access="upload_ingredient-217" class="btn bg-blue-btn menu_assign_class" href="<?php echo base_url() ?>ingredient/uploadingredients">
                                <i data-feather="upload"></i> <?php echo lang('upload_ingredient'); ?>
                            </a>
                        
                    </div>
                </div>

            </div>
        </section>
        <div class="box-wrapper">
            <!-- filter the ingredient list, handled server side in Ingredient::ingredients() -->
            <?php echo form_open(base_url() . 'ingredient/ingredients', $arrayName = array('id' => 'ingredientFilter')) ?>
            <div class="row">
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <input tabindex="1" type="text" name="keyword" class="form-control"
                               placeholder="<?php echo lang('name'); ?>/<?php echo lang('code'); ?>"
                               value="<?php echo set_value('keyword'); ?>">
                    </div>
                </div>
                <?php if ($show_outlet_filter): ?>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <!-- scopes the Stock Qty column only; the item list itself is
                             company wide master data and is never hidden per outlet -->
                        <select tabindex="2" class="form-control select2 ir_w_100" name="outlet_scope">
                            <option <?php echo ($outlet_scope['selected'] === 'all') ? 'selected' : ''; ?> value="all"><?php echo lang('all_outlets') ?></option>
                            <?php foreach ($scope_outlets as $single_outlet): ?>
                                <option <?php echo ($outlet_scope['selected'] === (string) $single_outlet->id) ? 'selected' : ''; ?> value="<?php echo escape_output($single_outlet->id) ?>"><?php echo escape_output($single_outlet->outlet_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <select tabindex="2" class="form-control select2 ir_w_100" name="category_id">
                            <option value=""><?php echo lang('all'); ?> <?php echo lang('category'); ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option <?php echo set_select('category_id', $category->id); ?> value="<?php echo escape_output($category->id) ?>"><?php echo escape_output($category->category_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <select tabindex="3" class="form-control select2 ir_w_100" name="purchase_unit_id">
                            <option value=""><?php echo lang('all'); ?> <?php echo lang('purchase_unit'); ?></option>
                            <?php foreach ($units as $unit): ?>
                                <option <?php echo set_select('purchase_unit_id', $unit->id); ?> value="<?php echo escape_output($unit->id) ?>"><?php echo escape_output($unit->unit_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <select tabindex="4" class="form-control select2 ir_w_100" name="unit_id">
                            <option value=""><?php echo lang('all'); ?> <?php echo lang('consumption_unit'); ?></option>
                            <?php foreach ($units as $unit): ?>
                                <option <?php echo set_select('unit_id', $unit->id); ?> value="<?php echo escape_output($unit->id) ?>"><?php echo escape_output($unit->unit_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group">
                        <select tabindex="5" class="form-control select2 ir_w_100" name="is_direct_food">
                            <option value=""><?php echo lang('all'); ?> <?php echo lang('type'); ?></option>
                            <option <?php echo set_select('is_direct_food', '1'); ?> value="1"><?php echo lang('ingredient'); ?></option>
                            <option <?php echo set_select('is_direct_food', '2'); ?> value="2"><?php echo lang('product'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                    <div class="form-group d-flex">
                        <button tabindex="6" type="submit" name="submit" value="submit"
                                class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                        <a class="btn btn-secondary w-100 ms-2" href="<?php echo base_url() ?>ingredient/ingredients"><?php echo lang('reset'); ?></a>
                    </div>
                </div>
            </div>
            <?php echo form_close(); ?>
            <!-- general form elements -->
            <div class="table-box">
                <!-- /.box-header -->
                <div class="table-responsive">
                    <table id="datatable" class="table">
                        <thead>
                            <tr>
                                <th class="ir_w_1"> <?php echo lang('sn'); ?></th>
                                <th class="ir_w_6"><?php echo lang('code'); ?></th>
                                <th class="ir_w_15"><?php echo lang('name'); ?></th>
                                <th class="ir_w_10"><?php echo lang('category'); ?></th>
                                <th  class="ir_w_10"><?php echo lang('purchase_unit'); ?></th>
                                <th  class="ir_w_10"><?php echo lang('consumption_unit'); ?></th>
                                <th  class="ir_w_10"><?php echo lang('conversion_rate'); ?></th>
                                <th class="ir_w_12"><?php echo lang('purchase_price'); ?></th>
                                <th class="ir_w_12"><?php echo lang('unit_selling_price'); ?></th>
                                <th class="ir_w_12"><?php echo lang('consumption_unit_cost'); ?></th>
                                <th class="ir_w_10"><?php echo lang('stock_qty'); ?>
                                    <small class="text-muted d-block"><?php echo escape_output(outletScopeLabel($outlet_scope)); ?></small>
                                </th>
                                <th class="ir_w_8"><?php echo lang('alert_quantity_amount'); ?></th>
                                <th class="ir_w_8"><?php echo lang('added_by'); ?></th>
                                <th  class="ir_w_1 ir_txt_center not-export-col"><?php echo lang('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($ingredients && !empty($ingredients)) {
                                $i = count($ingredients);
                            }
                            foreach ($ingredients as $ingrnts) {
                                ?>
                            <tr>
                                <td class="ir_txt_center"><?php echo escape_output($i--); ?></td>
                                <td><?php echo escape_output($ingrnts->code) ?></td>
                                <td><?php echo escape_output($ingrnts->name) ?></td>
                                <td><?php echo escape_output(categoryName($ingrnts->category_id)); ?></td>
                                <td><?php echo escape_output(unitName($ingrnts->purchase_unit_id)); ?></td>
                                <td><?php echo escape_output(unitName($ingrnts->unit_id)); ?></td>
                                <td><?php echo escape_output(($ingrnts->conversion_rate)); ?></td>
                                <td><?php echo escape_output(getAmtPCustom($ingrnts->purchase_price)) ?></td>
                                <td>
                                    <?php
                                    //Only a direct sale product item carries a selling price, and it
                                    //lives on the food menu it was generated from. A recipe ingredient
                                    //is never sold on its own, so a blank here is correct, not missing data.
                                    $ir_food_id = isset($ingrnts->food_id) ? (int) $ingrnts->food_id : 0;
                                    if ($ir_food_id && isset($sale_price_by_food_menu[$ir_food_id])) {
                                        echo escape_output(getAmtPCustom($sale_price_by_food_menu[$ir_food_id]));
                                    } else {
                                        echo '<span class="text-muted">&mdash;</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo escape_output(getAmtPCustom($ingrnts->consumption_unit_cost)) ?></td>
                                <td>
                                    <?php
                                    //Stock for the selected outlet scope. Computed by the same shared
                                    //helper the inventory and alert screens use, so the figures agree.
                                    if (isset($stock_by_item[$ingrnts->id])) {
                                        $ir_stock_row = $stock_by_item[$ingrnts->id];
                                        $ir_stock = stockClosingBalance($ir_stock_row);
                                        $ir_low = ($ir_stock <= ($ingrnts->alert_quantity * (($ingrnts->conversion_rate) ? $ingrnts->conversion_rate : 1)));
                                        ?>
                                        <span style="<?php echo $ir_low ? 'color:red' : ''; ?>">
                                            <?php echo escape_output(getAmtP($ir_stock)); ?>
                                            <?php echo escape_output(" " . unitName($ingrnts->unit_id)); ?>
                                        </span>
                                        <?php
                                    } else {
                                        echo '<span class="text-muted">&mdash;</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo escape_output(getAmtPCustom($ingrnts->alert_quantity)) ?></td>
                                <td><?php echo escape_output(userName($ingrnts->user_id)); ?></td>

                                <td>
                                    <?php if($ingrnts->is_direct_food==1):?>
                                    <div class="btn_group_wrap">
                                        <a class="btn btn-warning" href="<?php echo base_url() ?>ingredient/addEditIngredient/<?php echo escape_output($this->custom->encrypt_decrypt($ingrnts->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-bs-original-title="<?php echo lang('edit'); ?>">
                                            <i class="far fa-edit"></i>
                                        </a>
                                        <a class="delete btn btn-danger" href="<?php echo base_url() ?>ingredient/deleteIngredient/<?php echo escape_output($this->custom->encrypt_decrypt($ingrnts->id, 'encrypt')); ?>" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="<?php echo lang('delete'); ?>">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </a>
                                    </div>
                                    <?php else:?>
                                    <div class="btn_group_wrap">
                                        <div class="tooltip_custom">
                                            <i data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo lang('action_tooltip_product'); ?>" data-feather="help-circle"></i>
                                        </div>
                                    </div>
                                    <?php endif?>
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

<div class="modal fade" id="uploadingredentsModal" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i
                            class="fa fa-2x">×</i></span></button>
                <h4 class="modal-title" id="myModalLabel"><i class="fa fa-plus-square-o ir_color_blue"></i>
                    <?php echo lang('upload_ingredients'); ?></h4>
            </div>
            <div class="modal-body">
                <!-- <form class="form-horizontal" action="<?php echo base_url() ?>Master/ExcelDataAddIngredints" method="post" accept-charset="utf-8"> -->
                <?php echo form_open(base_url() . 'ingredient/ExcelDataAddIngredints', $arrayName = array('id' => 'language', 'class' => 'form-horizontal', 'accept-charset' => 'utf-8')) ?>
                <div class="form-group">
                    <label class="col-sm-4 control-label"><?php echo lang('upload_file'); ?><span class="ir_color_red">
                            *</span></label>
                    <div class="col-sm-7">
                        <input type="file" class="form-control" name="userfile" id="userfile" placeholder="Upload file"
                            value="">
                        <div class="callout callout-danger my-2 error-msg customer_err_msg_contnr">
                            <p class="customer_err_msg"></p>
                        </div>
                    </div>
                </div>
                <?php echo form_close(); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="addNewGuest">
                    <i class="fa fa-save"></i> <?php echo lang('upload'); ?> </button>
                <a class="btn btn-primary" href="<?php echo base_url() ?>ingredient/downloadPDF/Ingredient_Upload">
                    <i class="fa fa-save"></i> <?php echo lang('download_sample'); ?></a>
            </div>
        </div>
    </div>
</div>

<?php $this->view('common/footer_js')?>