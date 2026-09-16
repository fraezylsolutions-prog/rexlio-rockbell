<?php /* Hotel add-on: the success / error banners every screen shows */ ?>
<?php if ($this->session->flashdata('exception')): ?>
    <section class="alert-wrapper"><div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <div class="alert-body"><p><i class="m-right fa fa-check"></i><?php echo escape_output($this->session->flashdata('exception')); ?></p></div></div></section>
<?php endif; ?>
<?php if ($this->session->flashdata('exception_1')): ?>
    <section class="alert-wrapper"><div class="alert alert-danger alert-dismissible">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <div class="alert-body"><p><i class="m-right fa fa-exclamation-triangle"></i><?php echo escape_output($this->session->flashdata('exception_1')); ?></p></div></div></section>
<?php endif; ?>
