$(function () {
    "use strict";
    $(document).on('click', '.set_credentials', function(){
       let username = $(this).attr("data-username");
       let password = $(this).attr("data-password");
       $("#email_address").val(username);
       $("#password").val(password);
    });
    
    $('.toggle').on('click', function() {
        $('.container').stop().addClass('active');
    });

    $('.close').on('click', function() {
        $('.container').stop().removeClass('active');
    });

    // ---- Single pincode input -----
    // ir_pin_idx tracks which of the 4 boxes is next to be filled. It is driven
    // by the plugin's own callbacks rather than read back from the DOM: the
    // plugin never re-locks a field once filled, and with hideinput on it
    // replaces the typed digit with a placeholder, so neither :read-only nor the
    // field value can tell you where you are. The keypad below uses this index.
    let ir_pin_idx = 0;
    let  loginpin = $('#loginpin').pinlogin({
        fields : 4,
        input : function(e, field, nr){
            ir_pin_idx = nr + 1;
        },
        complete : function(pin){
            ir_pin_idx = 0;
            $("#login_pin").val(pin);
            $(".submit_login").click();
            loginpin.disable();
        },
    });

    /* ---- PIN keypad -------------------------------------------------------
       The keypad holds NO pin state of its own. It writes into the pinlogin
       fields and lets the plugin do the work it already does: pattern
       validation, storing the digit, unlocking and focusing the next box, and
       firing complete() on the fourth - which is what sets #login_pin and
       submits. Duplicating any of that here would mean two sources of truth for
       the same 4 digits.

       mousedown is prevented so a tap does not pull focus out of the pin field
       before we write to it. Order matters on the digit path: the plugin's own
       focus handler clears the box, so the value must be set AFTER focusing. */
    $(document).on('mousedown', '.ir_keypad_key', function (e) {
        e.preventDefault();
    });

    $(document).on('click', '.ir_keypad_digit', function (e) {
        e.preventDefault();
        if (ir_pin_idx > 3) { return; }              // already complete
        let field = $('#loginpin_pinlogin_' + ir_pin_idx);
        if (!field.length) { return; }
        field.prop('readonly', false).focus();
        field.val($(this).attr('data-digit')).trigger('input');
    });

    $(document).on('click', '.ir_keypad_back', function (e) {
        e.preventDefault();
        if (ir_pin_idx <= 0) { return; }             // nothing entered yet
        ir_pin_idx -= 1;
        // resetField clears both the stored value and the box, so a backspaced
        // digit cannot survive in the plugin's array and reappear in the pin.
        loginpin.resetField(ir_pin_idx);
        loginpin.focus(ir_pin_idx);
    });

    $(document).on('click', '.ir_keypad_clear', function (e) {
        e.preventDefault();
        ir_pin_idx = 0;
        loginpin.enable();                           // undo any earlier disable
        loginpin.reset();                            // clears all 4 + refocuses
    });

    function show_hide(btn_type) {
        if(btn_type==1){
            $(".div_1").hide(200);
            $(".div_2").show(200);
        }else{
            $(".div_1").show(200);
            $(".div_2").hide(200);
            $("#loginpin_pinlogin_0").focus();
        }
    }

   let active_login_button_hidden =  $("#active_login_button_hidden").val();
    show_hide(active_login_button_hidden);
    //generate random code button
    $('body').on('click', '.btn_login_pin_type', function (e) {
        e.preventDefault();

        $(".btn_login_pin_type").removeClass('active_login_btn');
        $(this).addClass('active_login_btn');

        let btn_type = Number($(this).attr('data-id'));
        let login_title = $(this).text();
        $(".login_title").text(login_title);
        $("#active_login_button_hidden").val(btn_type);
        show_hide(btn_type);
    });
    let login_button_type = Number($("#login_button_type").val()); 
    if(login_button_type==3){
        $(".btn_pin_trigger").click();
    }

    toastr.options = {
        positionClass:'toast-bottom-right'
    };
     

    $('body').on('click', '.set_data', function (e) {
        e.preventDefault();
        let role_name = $(this).text();
        let email = $(this).attr("data-email");
        let password = $(this).attr("data-password");
        let pin = $(this).attr("data-pin");

        $("#email_address").val(email);
        $("#password").val(password);
        $("#login_pin").val(pin);
        let active_login_button_hidden =  Number($("#active_login_button_hidden").val());
        if(active_login_button_hidden==2){
            $(".submit_login").click();
        }
        toastr['warning']("Click the Login button to log in as "+role_name, '');
    });
});