<?php /* Hotel Operations - landing. Links per permission; the Front Desk board replaces this in H2. */ ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hotel_operations'); ?></h3>
    </section>
    <div class="box-wrapper">
        <div class="table-box" id="ir_hotel_landing">
            <div class="row">
                <?php if (!empty($can_front_desk)): ?>
                <div class="col-sm-12 col-md-4 mb-3"><a class="btn bg-blue-btn w-100" href="<?php echo base_url() ?>Hotel/frontDesk"><i class="fas fa-concierge-bell"></i> <?php echo lang('front_desk'); ?></a><div class="text-muted mt-1" style="font-size:12.5px"><?php echo lang('front_desk_desc'); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($can_housekeeping)): ?>
                <div class="col-sm-12 col-md-4 mb-3"><a class="btn bg-blue-btn w-100" href="<?php echo base_url() ?>Hotel/housekeeping"><i class="fas fa-broom"></i> <?php echo lang('housekeeping_board'); ?></a><div class="text-muted mt-1" style="font-size:12.5px"><?php echo lang('housekeeping_desc'); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($can_rooms)): ?>
                <div class="col-sm-12 col-md-4 mb-3">
                    <a class="btn btn-secondary w-100 mb-2" href="<?php echo base_url() ?>Hotel/rooms"><i class="fas fa-door-open"></i> <?php echo lang('rooms'); ?></a>
                    <a class="btn btn-secondary w-100" href="<?php echo base_url() ?>Hotel/roomTypes"><i class="fas fa-tags"></i> <?php echo lang('room_types'); ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
