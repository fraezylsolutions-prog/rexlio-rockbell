/*
 * Register details popup (report batch R6).
 *
 * JQUERY INSTANCE: report pages load jQuery twice and custom_report_no_sorting.js
 * calls $.noConflict(), so "$" here is not necessarily the instance other plugins
 * extended. Bound to window.jQuery explicitly, same as report_invoice_popup.js.
 *
 * MODAL API: Bootstrap 5.0.1 has getInstance but NOT getOrCreateInstance (that
 * arrived in 5.1). Native API first, jQuery bridge as fallback.
 *
 * Read only: calls Report/registerDetailsAjax, which selects and returns. Nothing
 * is written or locked, so viewing a register that is open and in use right now
 * cannot disturb it. Sale::registerDetailCalculationToShow() is never involved -
 * that belongs to the live register-closing flow.
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
        var el = document.getElementById("irRegisterDetailsModal");
        if (!el) { return; }
        if (window.bootstrap && window.bootstrap.Modal) {
            var inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if ($.fn && $.fn.modal) {
            $(el).modal("show");
        }
    }

    function line(label, value, cls) {
        return '<div class="row"><div class="col-7">' + esc(label) + '</div>'
             + '<div class="col-5 text-end ' + (cls || "") + '">' + esc(value) + "</div></div>";
    }

    function buildHtml(d) {
        var h = "";

        /* header */
        h += '<div class="row mb-3">';
        h += '<div class="col-sm-6"><strong>' + esc(lang("ir_reg_lang_counter")) + ":</strong> " + esc(d.counter_name) + "</div>";
        h += '<div class="col-sm-6"><strong>' + esc(lang("ir_reg_lang_outlet")) + ":</strong> " + esc(d.outlet_name) + "</div>";
        h += '<div class="col-sm-12 mt-1 text-muted"><small>' + esc(d.window_start) + " &nbsp;&rarr;&nbsp; " + esc(d.window_end)
           + (d.is_open ? " (open)" : "") + "</small></div>";
        h += "</div>";

        /* payment breakdown */
        h += '<div class="table-responsive"><table class="table table-sm"><thead><tr>'
           + "<th>" + esc(lang("ir_reg_lang_method")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_sell")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_expense")) + "</th>"
           + "</tr></thead><tbody>";
        if (d.payments && d.payments.length) {
            for (var i = 0; i < d.payments.length; i++) {
                var p = d.payments[i];
                h += "<tr><td>" + esc(p.method) + "</td>"
                   + '<td class="text-end">' + money(p.sell) + "</td>"
                   + '<td class="text-end">' + money(p.expense) + "</td></tr>";
            }
        } else {
            h += '<tr><td colspan="3">' + esc(lang("ir_reg_lang_no_data")) + "</td></tr>";
        }
        h += "</tbody></table></div>";

        /* summary. The derived figure and the register's own close-time figure are
           shown side by side on purpose: they can legitimately differ (a payment
           corrected after close, or a method that did not exist when the register
           was opened), and a visible, labelled difference beats a silent mismatch. */
        h += '<hr><h6>' + esc(lang("ir_reg_lang_summary")) + "</h6>";
        h += line(lang("ir_reg_lang_derived"), money(d.total_sales_derived), "fw-bold");
        h += line(lang("ir_reg_lang_atclose"), money(d.total_sales_at_close), "text-muted");
        var delta = Number(d.total_sales_derived) - Number(d.total_sales_at_close);
        if (Math.abs(delta) >= 0.005) {
            h += '<div class="row"><div class="col-12"><small class="text-danger">'
               + "&Delta; " + money(delta) + "</small></div></div>";
        }
        h += line(lang("ir_reg_lang_orders"), d.total_orders);
        h += line(lang("ir_reg_lang_completed"), d.completed_orders);
        h += line(lang("ir_reg_lang_credit"), money(d.credit_sales));
        h += line(lang("ir_reg_lang_expense"), money(d.expense_total));
        h += line(lang("ir_reg_lang_purchase"), money(d.purchase_total));
        h += line(lang("ir_reg_lang_duerecv"), money(d.due_receive_total));
        h += line(lang("ir_reg_lang_supplier"), money(d.supplier_payment_total));
        /* these four are dated by day, not by timestamp, so a register covering part
           of a day cannot be split precisely - said plainly rather than implied */
        h += '<div class="mt-1"><small class="text-muted">' + esc(lang("ir_reg_lang_daynote")) + "</small></div>";

        /* products sold */
        h += '<hr><h6>' + esc(lang("ir_reg_lang_products")) + "</h6>";
        h += '<div class="table-responsive"><table class="table table-sm"><thead><tr>'
           + "<th>" + esc(lang("ir_reg_lang_code")) + "</th>"
           + "<th>" + esc(lang("ir_reg_lang_product")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_qty")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_total")) + "</th>"
           + "</tr></thead><tbody>";
        var iq = 0, it = 0;
        if (d.items && d.items.length) {
            for (var j = 0; j < d.items.length; j++) {
                var r = d.items[j];
                iq += Number(r.qty); it += Number(r.total_amount);
                h += "<tr><td>" + esc(r.code) + "</td><td>" + esc(r.product) + "</td>"
                   + '<td class="text-end">' + esc(r.qty) + "</td>"
                   + '<td class="text-end">' + money(r.total_amount) + "</td></tr>";
            }
        } else {
            h += '<tr><td colspan="4">' + esc(lang("ir_reg_lang_no_data")) + "</td></tr>";
        }
        h += '</tbody><tfoot><tr class="fw-bold"><td></td><td></td>'
           + '<td class="text-end">' + iq + "</td>"
           + '<td class="text-end">' + money(it) + "</td></tr></tfoot></table></div>";

        /* by category - this schema has no brand concept at all, and category is the
           meaningful analogue for the sample's "by brand" section */
        h += '<hr><h6>' + esc(lang("ir_reg_lang_bycat")) + "</h6>";
        h += '<div class="table-responsive"><table class="table table-sm"><thead><tr>'
           + "<th>" + esc(lang("ir_reg_lang_category")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_qty")) + "</th>"
           + '<th class="text-end">' + esc(lang("ir_reg_lang_total")) + "</th>"
           + "</tr></thead><tbody>";
        var cq = 0, ct = 0;
        if (d.categories && d.categories.length) {
            for (var k = 0; k < d.categories.length; k++) {
                var c = d.categories[k];
                cq += Number(c.qty); ct += Number(c.total_amount);
                h += "<tr><td>" + esc(c.category) + "</td>"
                   + '<td class="text-end">' + esc(c.qty) + "</td>"
                   + '<td class="text-end">' + money(c.total_amount) + "</td></tr>";
            }
        } else {
            h += '<tr><td colspan="3">' + esc(lang("ir_reg_lang_no_data")) + "</td></tr>";
        }
        h += '</tbody><tfoot><tr class="fw-bold"><td></td>'
           + '<td class="text-end">' + cq + "</td>"
           + '<td class="text-end">' + money(ct) + "</td></tr></tfoot></table></div>";

        /* footer */
        h += '<hr><div class="row"><div class="col-sm-4"><strong>' + esc(lang("ir_reg_lang_user")) + ":</strong> " + esc(d.user_name) + "</div>";
        h += '<div class="col-sm-4"><strong>' + esc(lang("ir_reg_lang_email")) + ":</strong> " + esc(d.user_email) + "</div>";
        h += '<div class="col-sm-4"><strong>' + esc(lang("ir_reg_lang_outlet")) + ":</strong> " + esc(d.outlet_name) + "</div></div>";

        return h;
    }

    $(document).on("click", ".ir_register_view", function (e) {
        e.preventDefault();
        var id = $(this).attr("data-register_id");
        var body = $("#ir_register_details_body");
        body.html('<p class="text-muted">' + esc(lang("ir_reg_lang_loading")) + "</p>");
        showModal();

        $.ajax({
            url: lang("ir_reg_base_url") + "Report/registerDetailsAjax",
            method: "POST",
            dataType: "json",
            data: { register_id: id },
            success: function (res) {
                if (!res || !res.found) {
                    body.html('<p class="text-muted">' + esc(lang("ir_reg_lang_no_data")) + "</p>");
                    return;
                }
                body.html(buildHtml(res));
            },
            error: function () {
                body.html('<p class="text-muted">' + esc(lang("ir_reg_lang_no_data")) + "</p>");
            }
        });
    });

    /* Print just the modal body. Opening a child window avoids touching the host
       page's own print styles, which the report tables rely on. */
    $(document).on("click", "#ir_register_print", function () {
        var content = document.getElementById("ir_register_details_body");
        if (!content) { return; }
        var w = window.open("", "_blank", "width=800,height=900");
        if (!w) { return; }
        w.document.write('<html><head><title>' + esc(lang("ir_reg_lang_summary")) + "</title>"
            + '<link rel="stylesheet" href="' + lang("ir_reg_base_url") + 'assets/css-framework/bootstrap-new/bootstrap.min.css">'
            + "</head><body class=\"p-3\">" + content.innerHTML + "</body></html>");
        w.document.close();
        w.focus();
        setTimeout(function () { w.print(); }, 400);
    });
})(window.jQuery);
