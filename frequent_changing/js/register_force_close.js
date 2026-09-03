/*
 * Admin Register Management - Force Close (new Admin oversight screen).
 *
 * JQUERY INSTANCE: this screen's report/list pages load jQuery twice, same
 * situation documented in register_details_popup.js. Bound to window.jQuery
 * explicitly rather than relying on the ambient "$".
 *
 * TWO-STEP CONFIRM BY DESIGN: step 1 (forceCloseRegisterCalc) is read-only and
 * only DISPLAYS the system-computed figure; nothing is written until the admin
 * has typed the actually-counted cash amount and a reason, and clicked Force
 * Close explicitly in step 2 (forceCloseRegister). This mirrors a normal
 * register close's discrepancy-catching purpose - it matters most in exactly
 * the abandoned-register case this screen exists for, so the counted amount
 * is never pre-filled with the system figure.
 *
 * The server re-validates and re-computes everything from scratch on submit;
 * nothing this file sends is trusted as the actual total.
 */
(function ($) {
    "use strict";
    if (!$) { return; }

    function lang(id) {
        var el = document.getElementById(id);
        return el ? el.value : "";
    }

    function esc(v) {
        if (v === null || v === undefined) { return ""; }
        return String(v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function money(n) {
        var x = Number(n);
        if (isNaN(x)) { x = 0; }
        return x.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showModal() {
        var el = document.getElementById("irForceCloseModal");
        if (!el) { return; }
        if (window.bootstrap && window.bootstrap.Modal) {
            var inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if ($.fn && $.fn.modal) {
            $(el).modal("show");
        }
    }

    function hideModal() {
        var el = document.getElementById("irForceCloseModal");
        if (!el) { return; }
        if (window.bootstrap && window.bootstrap.Modal) {
            var inst = window.bootstrap.Modal.getInstance(el);
            if (inst) { inst.hide(); }
        } else if ($.fn && $.fn.modal) {
            $(el).modal("hide");
        }
    }

    function buildPreviewHtml(d) {
        var h = "";
        h += '<div class="row mb-3">';
        h += '<div class="col-sm-4"><strong>' + esc(lang("ir_fc_lang_user")) + ":</strong> " + esc(d.user_name) + "</div>";
        h += '<div class="col-sm-4"><strong>' + esc(lang("ir_fc_lang_outlet")) + ":</strong> " + esc(d.outlet_name) + "</div>";
        h += '<div class="col-sm-4"><strong>' + esc(lang("ir_fc_lang_counter")) + ":</strong> " + esc(d.counter_name) + "</div>";
        h += "</div>";
        h += '<div class="row mb-3">';
        h += '<div class="col-sm-6"><strong>' + esc(lang("ir_fc_lang_opened_at")) + ":</strong> " + esc(d.opening_balance_date_time) + "</div>";
        h += '<div class="col-sm-6"><strong>' + esc(lang("ir_fc_lang_opening_balance")) + ":</strong> " + money(d.opening_balance) + "</div>";
        h += "</div>";

        h += '<table class="table table-sm table-bordered"><thead><tr>';
        h += "<th>" + esc(lang("ir_fc_lang_method")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_sale")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_purchase")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_due_receive")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_due_payment")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_expense")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_refund")) + "</th>";
        h += "<th>" + esc(lang("ir_fc_lang_closing")) + "</th>";
        h += "</tr></thead><tbody>";
        (d.breakdown || []).forEach(function (row) {
            h += "<tr><td>" + esc(row.payment_name) + "</td>"
               + "<td>" + money(row.sale) + "</td>"
               + "<td>" + money(row.purchase) + "</td>"
               + "<td>" + money(row.due_receive) + "</td>"
               + "<td>" + money(row.due_payment) + "</td>"
               + "<td>" + money(row.expense) + "</td>"
               + "<td>" + money(row.refund) + "</td>"
               + "<td><strong>" + money(row.closing) + "</strong></td></tr>";
        });
        h += "</tbody></table>";

        h += '<div class="alert alert-secondary">';
        h += "<strong>" + esc(lang("ir_fc_lang_system_computed")) + ":</strong> " + money(d.system_closing_balance);
        h += "</div>";

        h += '<div class="alert alert-warning">' + esc(lang("ir_fc_lang_confirm_note")) + "</div>";

        h += '<div class="mb-3">';
        h += '<label class="form-label"><strong>' + esc(lang("ir_fc_lang_counted_amount")) + '</strong></label>';
        h += '<input type="number" step="0.01" class="form-control" id="ir_fc_counted_amount" placeholder="">';
        h += '<div class="form-text">' + esc(lang("ir_fc_lang_counted_amount_help")) + "</div>";
        h += "</div>";

        h += '<div class="mb-3">';
        h += '<label class="form-label"><strong>' + esc(lang("ir_fc_lang_reason")) + '</strong></label>';
        h += '<textarea class="form-control" id="ir_fc_reason" rows="2" placeholder="'
           + esc(lang("ir_fc_lang_reason_placeholder")) + '"></textarea>';
        h += "</div>";

        return h;
    }

    var currentRegisterId = null;

    $(document).on("click", ".ir_force_close_btn", function (e) {
        e.preventDefault();
        currentRegisterId = $(this).attr("data-register_id");
        var body = $("#ir_force_close_body");
        var submitBtn = $("#ir_force_close_submit");
        body.html('<p class="text-muted">' + esc(lang("ir_fc_lang_loading")) + "</p>");
        submitBtn.hide().prop("disabled", false);
        showModal();

        $.ajax({
            url: lang("ir_fc_base_url") + "Register/forceCloseRegisterCalc",
            method: "POST",
            dataType: "json",
            data: { register_id: currentRegisterId },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    body.html('<p class="text-danger">' + esc((res && res.message) || lang("ir_fc_lang_no_data")) + "</p>");
                    return;
                }
                body.html(buildPreviewHtml(res));
                submitBtn.data("system_closing_balance", res.system_closing_balance).show();
            },
            error: function () {
                body.html('<p class="text-danger">' + esc(lang("ir_fc_lang_no_data")) + "</p>");
            }
        });
    });

    $(document).on("click", "#ir_force_close_submit", function () {
        var btn = $(this);
        var counted = $("#ir_fc_counted_amount").val();
        var reason = $("#ir_fc_reason").val();

        if (counted === "" || counted === null || isNaN(Number(counted))) {
            swal(lang("ir_fc_lang_counted_amount_required"), "", "error");
            return;
        }
        if (!reason || !reason.trim()) {
            swal(lang("ir_fc_lang_reason_required"), "", "error");
            return;
        }

        btn.prop("disabled", true);

        $.ajax({
            url: lang("ir_fc_base_url") + "Register/forceCloseRegister",
            method: "POST",
            dataType: "json",
            data: {
                register_id: currentRegisterId,
                counted_amount: counted,
                reason: reason
            },
            success: function (res) {
                if (!res || res.status !== "ok") {
                    swal((res && res.message) || lang("ir_fc_lang_no_data"), "", "error");
                    btn.prop("disabled", false);
                    return;
                }
                swal(lang("ir_fc_lang_success"), "", "success");
                hideModal();
                var row = $(".ir_force_close_btn[data-register_id='" + currentRegisterId + "']").closest("tr");
                row.remove();
                if ($("#ir_open_registers_tbody tr").length === 0) {
                    $("#ir_open_registers_tbody").html(
                        '<tr><td colspan="8" class="text-center text-muted">'
                        + esc(lang("ir_fc_lang_already_closed")) + "</td></tr>"
                    );
                }
            },
            error: function () {
                swal(lang("ir_fc_lang_no_data"), "", "error");
                btn.prop("disabled", false);
            }
        });
    });
})(window.jQuery);
