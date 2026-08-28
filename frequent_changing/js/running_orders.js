/* Running Order screen.
   Timers tick client side once a second from the server supplied minutes/seconds,
   the card grid is refreshed from the server every 15 seconds. Same cadence the
   kitchen panel uses, so the server is not hit once per second. */
$(function () {
    "use strict";

    var base_url = $("#ro_base_url").val();
    var refresh_ms = 15000;
    var polling = false;

    function pad(n) {
        n = Number(n);
        return (n < 10 ? "0" : "") + n;
    }

    /* advance every visible timer by one second */
    function tickTimers() {
        $(".ro_timer").each(function () {
            var minutes = Number($(this).attr("data-minutes"));
            var seconds = Number($(this).attr("data-seconds"));
            if (isNaN(minutes) || isNaN(seconds)) {
                return;
            }
            seconds = seconds + 1;
            if (seconds > 59) {
                seconds = 0;
                minutes = minutes + 1;
            }
            $(this).attr("data-minutes", minutes);
            $(this).attr("data-seconds", seconds);
            $(this).text(pad(minutes) + ":" + pad(seconds));
        });
    }

    /* current filter values, so a refresh keeps whatever the user selected */
    function currentFilters() {
        return {
            table_id: $("#ro_table_id").length ? $("#ro_table_id").val() : "",
            sale_no: $("#ro_sale_no").length ? $("#ro_sale_no").val() : "",
            view_user_id: $("#ro_view_user_id").length ? $("#ro_view_user_id").val() : ""
        };
    }

    /* Timestamp of the last successful server read, so the offline banner can say
       how stale the cards are rather than just "offline". */
    var last_updated_text = "";

    function twoDigits(n) {
        return (n < 10 ? "0" : "") + n;
    }

    function markUpdated() {
        var now = new Date();
        last_updated_text = twoDigits(now.getHours()) + ":" + twoDigits(now.getMinutes()) + ":" + twoDigits(now.getSeconds());
        $("#ro_offline_banner").hide();
    }

    function showOfflineBanner() {
        $("#ro_last_updated").text(last_updated_text);
        $("#ro_offline_banner").show();
    }

    function refreshCards() {
        if (polling) {
            return;
        }
        polling = true;
        $.ajax({
            url: base_url + "Monitor/runningOrdersAjax",
            method: "POST",
            dataType: "json",
            data: currentFilters(),
            success: function (response) {
                if (response && response.html !== undefined) {
                    $("#ro_card_grid").html(response.html);
                    $("#ro_total_count").text(response.count);
                    markUpdated();
                }
                polling = false;
            },
            error: function () {
                /* The cards stay on screen but they are now stale, so say so.
                   This screen reads the server on every refresh and has no local
                   store to fall back on, unlike the POS which works from IndexedDB. */
                showOfflineBanner();
                polling = false;
            }
        });
    }

    /* Close this screen and go back to the POS.
       This tab was opened by a user clicking a target="_blank" link, not by
       window.open(), so the browser only allows window.close() while the tab
       still has a single history entry. Submitting the filter form adds an
       entry and close() is then silently refused, so fall back to navigating
       to the POS instead. The link href is that same POS URL, which also keeps
       the button working if scripting is unavailable. */
    $(document).on("click", "#ro_close_screen", function (e) {
        e.preventDefault();
        var pos_url = $(this).attr("href");

        /* put the operator back on the POS tab that opened this one */
        try {
            if (window.opener && !window.opener.closed) {
                window.opener.focus();
            }
        } catch (err) {
            /* opener may be unavailable, closing still returns to the prior tab */
        }

        window.close();

        setTimeout(function () {
            if (!window.closed) {
                window.location.href = pos_url;
            }
        }, 300);
    });

    /* order details, the only action that stays on this screen */
    $(document).on("click", ".ro_btn_details", function (e) {
        e.preventDefault();
        var sale_id = $(this).attr("data-sale_id");
        var body = $("#ro_details_body");
        body.html("");
        $("#roOrderDetailsModal").modal("show");

        $.ajax({
            url: base_url + "Monitor/orderDetailsAjax",
            method: "POST",
            dataType: "json",
            data: { sale_id: sale_id },
            success: function (response) {
                if (!response || !response.found) {
                    body.html('<p class="ro_empty">' + $("#ro_lang_no_data").val() + "</p>");
                    return;
                }
                body.html(buildDetailsHtml(response));
            },
            error: function () {
                body.html('<p class="ro_empty">' + $("#ro_lang_no_data").val() + "</p>");
            }
        });
    });

    function esc(value) {
        if (value === null || value === undefined) {
            return "";
        }
        return $("<div>").text(value).html();
    }

    function buildDetailsHtml(response) {
        var sale = response.sale;
        var html = '<div class="ro_details_head">';
        html += "<div><b>" + esc(sale.sale_no) + "</b></div>";
        html += "<div>" + esc(response.tables_booked || $("#ro_lang_none").val()) + "</div>";
        html += "<div>" + esc(response.waiter_name) + "</div>";
        html += "</div>";

        html += '<table class="table ro_details_table"><thead><tr>';
        html += "<th>#</th><th>Item</th><th>Qty</th><th>Price</th><th>Total</th>";
        html += "</tr></thead><tbody>";

        var i = 1;
        $.each(response.items, function (key, item) {
            html += "<tr>";
            html += "<td>" + i + "</td>";
            html += "<td>" + esc(item.menu_name);
            if (item.modifiers && item.modifiers.length) {
                var names = [];
                $.each(item.modifiers, function (k, modifier) {
                    names.push(esc(modifier.modifier_name));
                });
                html += '<div class="ro_modifiers">' + names.join(", ") + "</div>";
            }
            html += "</td>";
            html += "<td>" + esc(item.qty) + "</td>";
            html += "<td>" + esc(item.menu_unit_price) + "</td>";
            html += "<td>" + esc(item.menu_price_with_discount) + "</td>";
            html += "</tr>";
            i++;
        });

        html += "</tbody></table>";
        return html;
    }

    /* the page itself was just served by the server, so that counts as a read */
    markUpdated();

    setInterval(tickTimers, 1000);
    setInterval(refreshCards, refresh_ms);
});
