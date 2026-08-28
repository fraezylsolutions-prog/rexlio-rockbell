$(function () {
      "use strict";
      let img_select_error_msg  = $("#img_select_error_msg").val();
      let hidden_alert  = $("#warning").val(); 
      let hidden_ok  = $("#ok").val();
      let hidden_cancel  = $("#cancel").val();
      let are_you_sure  = $("#are_you_sure").val();
      let base_url_custom  = $("#base_url_custom").val();
      let hidden_outlet_id  = $("#hidden_outlet_id").val();
    $(document).on('click', '.img_thumb_btn', function(e){
        let id = $(this).attr("data-id");
        $("#active_id_for_upload").val(id);
        $("#thumb_modal").modal('show');
    });

    $(document).on('click', '.img_large_btn', function(e){
        let id = $(this).attr("data-id");
        $("#active_id_for_upload").val(id);
        $("#large_modal").modal('show');
    });

    $(document).on('change', '#outlet_id', function(e){
        let id = $(this).val(); 
        let url = base_url_custom+"Frontend/availableOnlineFoods/"+id;
        window.location.href = url;
    });

    $(document).on('click', '.cancel', function(e){
        $("#thumb_modal").modal('hide');
        $("#large_modal").modal('hide');
    });
    function callUpdateInfo(checked_value,food_menu_id,thumb_image,large_image){
        $.ajax({
            url: base_url_custom+"Authentication/callUpdateInfo",
            type: "POST",
            dataType: 'json',
            data: {checked_value:checked_value,food_menu_id:food_menu_id,thumb_image:thumb_image,large_image:large_image,hidden_outlet_id:hidden_outlet_id},
            success: function (data) {
             
            }
        });
    }
    $(document).on('click', '.remvoe_thumb_image', function(e){
         let this_id = $(this).attr("data-id");
         let this_action = $(this);
         $("#active_id_for_upload").val(this_id);
         let image = $(this).attr("data-image");
        swal({
            title: hidden_alert+"!",
            text: are_you_sure+"?",
            cancelButtonText: hidden_cancel,
            confirmButtonText: hidden_ok,
            confirmButtonColor: '#3c8dbc',
            showCancelButton: true
        }, function() {
            $("#img_thumb_value_"+this_id).val('');
            $("#img_thumb_"+this_id).attr("src", image);

            let checked_value =  $("#checker_"+this_id).is(":checked") ? 1 : 2;;
            let food_menu_id = this_id;
            let thumb_image = $("#img_thumb_value_"+this_id).val();
            let large_image = $("#img_large_value_"+this_id).val();


            callUpdateInfo(checked_value,food_menu_id,thumb_image,large_image);
            this_action.parent().empty();
        });

        
    });

    $(".checkbox_user").on("change", function(){
        let checked_value =  $(this).is(":checked") ? 1 : 2;; 
        let this_id =$(this).val();

        let food_menu_id =  this_id;
        let thumb_image = $("#img_thumb_value_"+this_id).val();
        let large_image = $("#img_large_value_"+this_id).val(); 
        callUpdateInfo(checked_value,food_menu_id,thumb_image,large_image);

    });

    $(document).on('click', '.remvoe_large_image_action', function(e){
         let this_id = $(this).attr("data-id");
         let this_action = $(this);
         swal({
            title: hidden_alert+"!",
            text: are_you_sure+"?",
            cancelButtonText: hidden_cancel,
            confirmButtonText: hidden_ok,
            confirmButtonColor: '#3c8dbc',
            showCancelButton: true
        }, function() {
                $("#active_id_for_upload").val(this_id);
                $("#img_large_value_"+this_id).val('');
                let checked_value =  $("#checker_"+this_id).is(":checked") ? 1 : 2;;
                let food_menu_id = this_id;
                let thumb_image = $("#img_thumb_value_"+this_id).val();
                let large_image = $("#img_large_value_"+this_id).val(); 
                callUpdateInfo(checked_value,food_menu_id,thumb_image,large_image);
                this_action.parent().empty();
        });


    });

    $(document).on("click", ".show_large_img", function (e) {
        e.preventDefault();
        let file_path = base_url_custom+"uploads/website/"+$(this).attr("data-url");  

        $("#show_id").attr("src", file_path);
        $("#show_id").css("width", "unset");
        $("#large_modal_preview").modal("show");
      });



      let height_ = 304;
      let width_ = 296;
  
      let tmp_height_ = 304+50;
      let tmp_width_ = 296+50;
   
      let uploadCrop = $('#thumb_modal_div').croppie({
          enableExif: true,
          viewport: {
              width: width_,
              height: height_,
              type: 'square'
          },
          boundary: {
              width: tmp_width_,
              height: tmp_height_
          }
      });
      $(document).on('change', '#img_thumb_file', function (e) {
          let reader = new FileReader();
          reader.onload = function (e) {
              uploadCrop.croppie('bind', {
                  url: e.target.result
              }).then(function(){
                 
              });
  
          }
          reader.readAsDataURL(this.files[0]);
      });
      $(document).on('click', '.del_img', function (e) {
          let this_action = $(this);
          swal({
              title: hidden_alert+"!",
              text: are_you_sure+"?",
              cancelButtonText:hidden_cancel,
              confirmButtonText:hidden_ok,
              confirmButtonColor: '#3c8dbc',
              showCancelButton: true
          }, function() {
              this_action.parent().parent().parent().remove();
          });
  
      });
      $(document).on('click', '.del_img_details', function (e) {
          let this_action = $(this);
          swal({
              title: hidden_alert+"!",
              text: are_you_sure+"?",
              cancelButtonText:hidden_cancel,
              confirmButtonText:hidden_ok,
              confirmButtonColor: '#3c8dbc',
              showCancelButton: true
          }, function() {
              this_action.parent().parent().remove();
          });
  
      });
      
      $(document).on('click', '.upload-result', function (ev) {
        let active_id_for_upload = $("#active_id_for_upload").val();
        let another_image = $("#img_large_value_"+active_id_for_upload).val();
        let checked_value =  $("#checker_"+active_id_for_upload).is(":checked") ? 1 : 2;
          uploadCrop.croppie('result', {
              type: 'canvas',
              size: 'viewport'
          }).then(function (resp) {
              let selected_image =  $("#img_thumb_file").val();
              if(selected_image==''){
                  swal({
                      title: hidden_alert+"!",
                      text: img_select_error_msg,
                      confirmButtonText:hidden_ok,
                      confirmButtonColor: '#3c8dbc'
                  });
                  return false;
              }else{
                  $.ajax({
                      url: base_url_custom+"Authentication/saveItemImage",
                      type: "POST",
                      dataType: 'json',
                      data: {"image":resp,food_menu_id:active_id_for_upload,hidden_outlet_id:hidden_outlet_id,another_image:another_image,type:1,checked_value:checked_value},
                      success: function (data) {
                          $("#thumb_modal").modal('hide');
                          $("#img_thumb_value_"+active_id_for_upload).val(data.image_name);
                          $("#img_thumb_file").val('');
                          $("#img_thumb_"+active_id_for_upload).attr('src',base_url_custom+"uploads/website/"+data.image_name);
                          let button_html = '<i data-id="'+active_id_for_upload+'" data-image="'+base_url_custom+'assets/media/no_image.png" class="color_notice remvoe_thumb_image fa fa-trash"></i>';
                          $(".img_thumb_"+active_id_for_upload).html(button_html);
                      }
                  });
              }
  
          });
      }); 




      let height_1 = 526;
      let width_1 = 495;
  
      let tmp_height_1 = 526+50;
      let tmp_width_1 = 495+50;
   
      let uploadCrop1 = $('#large_modal_div').croppie({
          enableExif: true,
          viewport: {
              width: width_1,
              height: height_1,
              type: 'square'
          },
          boundary: {
              width: tmp_width_1,
              height: tmp_height_1
          }
      });
      $(document).on('change', '#img_large_file', function (e) {
          let reader = new FileReader();
          reader.onload = function (e) {
              uploadCrop1.croppie('bind', {
                  url: e.target.result
              }).then(function(){
                 
              });
  
          }
          reader.readAsDataURL(this.files[0]);
      });
      $(document).on('click', '.del_img1', function (e) {
          let this_action = $(this);
          swal({
              title: hidden_alert+"!",
              text: are_you_sure+"?",
              cancelButtonText:hidden_cancel,
              confirmButtonText:hidden_ok,
              confirmButtonColor: '#3c8dbc',
              showCancelButton: true
          }, function() {
              this_action.parent().parent().parent().remove();
          });
  
      });
       
      $(document).on('click', '.upload-result_large', function (ev) {
        let active_id_for_upload = $("#active_id_for_upload").val();
        let checked_value =  $("#checker_"+active_id_for_upload).is(":checked") ? 1 : 2;
        let another_image = $("#img_thumb_value_"+active_id_for_upload).val();
          uploadCrop1.croppie('result', {
              type: 'canvas',
              size: 'viewport'
          }).then(function (resp) {
              let selected_image =  $("#img_large_file").val();
              if(selected_image==''){
                  swal({
                      title: hidden_alert+"!",
                      text: img_select_error_msg,
                      confirmButtonText:hidden_ok,
                      confirmButtonColor: '#3c8dbc'
                  });
                  return false;
              }else{
                  $.ajax({
                      url: base_url_custom+"Authentication/saveItemImage",
                      type: "POST",
                      dataType: 'json',
                      data: {"image":resp,food_menu_id:active_id_for_upload,hidden_outlet_id:hidden_outlet_id,another_image:another_image,type:2,checked_value:checked_value},
                      success: function (data) {
                          $("#large_modal").modal('hide');
                         
                          $("#img_large_value_"+active_id_for_upload).val(data.image_name);
                          $("#img_large_file").val('');
                          let html = '<i  data-id="'+active_id_for_upload+'" class="color_notice remvoe_large_image fa fa-trash"></i><i data-url="'+data.image_name+'" class="remvoe_large_image show_large_img fa fa-eye"></i>';
                          $(".img_large_"+active_id_for_upload).html(html);
                      }
                  });
              }
  
          });
      });


      $("#search_string").on("keyup", function () {
        let value = $(this).val().toLowerCase();

        $(".food_title").each(function () {
            let title = $(this).text().toLowerCase();
            $(this).parent().parent().parent().parent().toggle(title.includes(value));
        });
    });


  });