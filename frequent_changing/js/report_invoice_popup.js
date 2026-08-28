/*
 * Shared invoice-detail popup for report screens (report batch R2).
 *
 * JQUERY INSTANCE. Report pages load jQuery TWICE - once in the userHome head,
 * then again inside the report view - and custom_report.js then calls
 * $.noConflict(), handing window.$ back to the FIRST instance while Bootstrap
 * bridges its plugins onto window.jQuery, the SECOND. So "$" on a report page is
 * not necessarily the instance other plugins extended. This binds to window.jQuery
 * explicitly to stay on the same instance as everything else.
 * (That ambiguity is real on this page - the pre-existing "$(...).slick is not a
 * function" error in new_ui_design.js is the same shape - but it was NOT what broke
 * this popup; see below.)
 *
 * MODAL API. The actual first failure here was calling
 * bootstrap.Modal.getOrCreateInstance(), which does not exist in Bootstrap 5.0.1
 * (added in 5.1). Native API first, jQuery bridge as fallback.
 *
 * Delegated off document, so rows DataTables paginates in later still work.
 *
 * Read only: it calls Report/invoiceDetailsAjax, which selects and returns. It
 * writes nothing and holds no lock, so opening a popup cannot disturb a register
 * or a sale that is live at that moment.
 */
(function ($) {
    "use strict";
    if (!$) { return; }

    function lang(id) {
        var el = document.getElementById(id);
        return el ? el.value : "";
    }

    function esc(value) {
        if (value === null || value === undefined) {
            return "";
        }
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function row(label, value) {
        if (value === null || value === undefined || String(value).trim() === "") {
            return "";
        }
        return '<div class="col-sm-6 mb-1"><strong>' + esc(label) + ":</strong> " + esc(value) + "</div>";
    }

    function buildHtml(d) {
        var html = '<div class="row mb-3">';
        html += row(lang("ir_inv_lang_invoice"), d.sale_no);
        html += row(lang("ir_inv_lang_date"), d.sale_date);
        /* order_time is the clock time the order was taken. It can belong to the
           previous calendar day when a late sale books to the next business day,
           so the full timestamp is shown beside it rather than instead of it. */
        html += row(lang("ir_inv_lang_time"), d.order_time);
        html += row(lang("ir_inv_lang_seller"), d.seller_name);
        html += row(lang("ir_inv_lang_waiter"), d.waiter_name);
        html += row(lang("ir_inv_lang_customer"), d.customer_name);
        html += row(lang("ir_inv_lang_outlet"), d.outlet_name);
        html += "</div>";

        html += '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr>'
              + "<th>" + esc(lang("ir_inv_lang_code")) + "</th>"
              + "<th>" + esc(lang("ir_inv_lang_item")) + "</th>"
              + '<th class="text-end">' + esc(lang("ir_inv_lang_qty")) + "</th>"
              + '<th class="text-end">' + esc(lang("ir_inv_lang_price")) + "</th>"
              + '<th class="text-end">' + esc(lang("ir_inv_lang_total")) + "</th>"
              + "</tr></thead><tbody>";

        if (!d.items || !d.items.length) {
            html += '<tr><td colspan="5">' + esc(lang("ir_inv_lang_no_data")) + "</td></tr>";
        } else {
            for (var i = 0; i < d.items.length; i++) {
                var it = d.items[i];
                html += "<tr>"
                      + "<td>" + esc(it.code) + "</td>"
                      + "<td>" + esc(it.menu_name);
                if (it.modifiers && it.modifiers.length) {
                    var names = [];
                    for (var m = 0; m < it.modifiers.length; m++) {
                        names.push(it.modifiers[m].name);
                    }
                    html += '<br><small class="text-muted">+ ' + esc(names.join(", ")) + "</small>";
                }
                html += "</td>"
                      + '<td class="text-end">' + esc(it.qty) + "</td>"
                      + '<td class="text-end">' + esc(it.menu_unit_price) + "</td>"
                      + '<td class="text-end">' + esc(it.menu_price_with_discount) + "</td>"
                      + "</tr>";
            }
        }
        html += "</tbody></table></div>";

        html += '<div class="row mt-2">';
        html += row(lang("ir_inv_lang_subtotal"), d.sub_total);
        html += row(lang("ir_inv_lang_discount"), d.discount);
        html += row(lang("ir_inv_lang_vat"), d.vat);
        html += row(lang("ir_inv_lang_payable"), d.total_payable);
        if (d.payments && d.payments.length) {
            var pay = [];
            for (var p = 0; p < d.payments.length; p++) {
                pay.push(d.payments[p].payment_name + ": " + d.payments[p].amount);
            }
            html += row(lang("ir_inv_lang_payment"), pay.join(" - "));
        }
        /* only shown when there is something outstanding; on this install every
           completed sale is settled, so a permanent "Due: 0" would be noise */
        if (d.due_amount && Number(d.due_amount) > 0) {
            html += row(lang("ir_inv_lang_due"), d.due_amount);
        }
        html += "</div>";
        return html;
    }

    /* Native API first; the jQuery bridge exists only when Bootstrap found
       window.jQuery at load time, and "$" here may be a different instance. */
    function showInvoiceModal() {
        var el = document.getElementById("irInvoiceDetailsModal");
        if (!el) { return; }
        if (window.bootstrap && window.bootstrap.Modal) {
            /* Bootstrap 5.0.1 exposes getInstance but NOT getOrCreateInstance -
               that arrived in 5.1. Verified against this bundle: getOrCreateInstance
               appears zero times in it. Construct when no instance exists yet. */
            var inst = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
            inst.show();
        } else if ($.fn && $.fn.modal) {
            $(el).modal("show");
        }
    }

    $(document).on("click", ".ir_invoice_link", function (e) {
        e.preventDefault();
        var sale_id = $(this).attr("data-sale_id");
        var body = $("#ir_invoice_details_body");
        body.html('<p class="text-muted">' + esc(lang("ir_inv_lang_loading")) + "</p>");
        showInvoiceModal();

        /* base_url is not a global in this app; it arrives via the hidden input the
           modal partial renders, matching how the Phase C monitor screens do it. */
        $.ajax({
            url: lang("ir_inv_base_url") + "Report/invoiceDetailsAjax",
            method: "POST",
            dataType: "json",
            data: { sale_id: sale_id },
            success: function (response) {
                if (!response || !response.found) {
                    body.html('<p class="text-muted">' + esc(lang("ir_inv_lang_no_data")) + "</p>");
                    return;
                }
                body.html(buildHtml(response));
            },
            error: function () {
                body.html('<p class="text-muted">' + esc(lang("ir_inv_lang_no_data")) + "</p>");
            }
        });
    });
})(window.jQuery);
