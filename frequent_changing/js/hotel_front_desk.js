/* Hotel add-on (H2): the Front Desk board.
   Same shape as the Running Order screen: a 15 s server refresh (Hotel/boardAjax)
   plus a refresh after every action, and an offline banner when the server cannot
   be reached so stale cards are never mistaken for live ones. Every action is a
   POST answered with {ok, reason, message}; the only special answer is the
   check-in "dirty" warning ({ok:0, reason:'dirty', warn:1}), which the modal turns
   into a "check in anyway" confirmation - a warning, not a block, by decision.
   The card builders are pure functions so they can be unit tested without a DOM. */
(function (root) {
    "use strict";

    function esc(v) { return String(v === undefined || v === null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    /* "2h 05m" / "3d 4h" since a server timestamp, against the server clock */
    function since(ts, now) {
        if (!ts) { return ""; }
        var t = new Date(String(ts).replace(" ", "T")), n = now ? new Date(String(now).replace(" ", "T")) : new Date();
        var mins = Math.max(0, Math.round((n - t) / 60000)); if (isNaN(mins)) { return ""; }
        if (mins < 60) { return mins + "m"; }
        if (mins < 1440) { return Math.floor(mins / 60) + "h " + String(mins % 60).padStart(2, "0") + "m"; }
        return Math.floor(mins / 1440) + "d " + Math.floor((mins % 1440) / 60) + "h";
    }
    /* one room card (pure). ctx: {msg(key), can:{checkin,checkout,status}, now} */
    function money(v) { var n = Number(v); if (isNaN(n)) { n = 0; } return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","); }
    /* whole nights between two Y-m-d dates, never below 1 - mirrors Hotel::nightsBetween() */
    function nightsBetween(from, to) {
        if (!from || !to) { return 1; }
        var d = Math.floor((new Date(to + "T00:00:00") - new Date(from + "T00:00:00")) / 86400000);
        return isNaN(d) ? 1 : Math.max(1, d);
    }
    function roomCardHtml(r, ctx) {
        var occ = r.occupancy_status, hk = r.housekeeping_status;
        var cls = "hk_card hk_occ_" + occ + " hk_hk_" + hk;
        var h = '<div class="' + cls + '" data-room_id="' + esc(r.id) + '" data-number="' + esc(r.number) + '" data-occ="' + esc(occ) + '" data-hk="' + esc(hk) + '" data-rate="' + esc(r.base_rate || 0) + '"' +
                (r.stay_id ? ' data-stay_id="' + esc(r.stay_id) + '" data-checkin="' + esc(String(r.checkin_at || '').substring(0, 10)) + '" data-stay_rate="' + esc(r.rate || 0) + '" data-stay_amount="' + esc(r.amount || 0) + '" data-stay_expected="' + esc(r.expected_checkout || '') + '"' : '') + '>';
        h += '<div class="hk_card_head"><span class="hk_room_no">' + esc(r.number) + '</span>' +
             (r.type_name ? '<span class="hk_type">' + esc(r.type_name) + '</span>' : '') + '</div>';
        h += '<div class="hk_badges"><span class="hk_badge hk_b_occ">' + esc(ctx.msg("room_" + occ)) + '</span><span class="hk_badge hk_b_hk">' + esc(ctx.msg("room_" + hk)) + '</span>' +
             (Number(r.open_tasks) > 0 ? '<span class="hk_badge hk_b_task">' + esc(ctx.msg("hotel_task_open")) + ' ' + Number(r.open_tasks) + '</span>' : '') + '</div>';
        if (occ === "occupied" && r.guest_name) {
            h += '<div class="hk_guest"><b>' + esc(r.guest_name) + '</b>' + (r.adults ? ' <span class="hk_muted">' + Number(r.adults) + (Number(r.children) ? "+" + Number(r.children) : "") + '</span>' : '') +
                 '<div class="hk_muted">' + esc(ctx.msg("hotel_since")) + ' ' + esc(since(r.checkin_at, ctx.now)) + (r.expected_checkout ? ' &middot; ' + esc(ctx.msg("expected_checkout")) + ' ' + esc(r.expected_checkout) : '') + '</div>' +
                 (r.amount !== null && r.amount !== undefined ? '<div class="hk_muted hk_value">' + Number(r.nights || 1) + ' ' + esc(ctx.msg("nights")) + ' &middot; <b>' + esc(money(r.amount)) + '</b></div>' : '') + '</div>';
        } else {
            h += '<div class="hk_guest hk_muted">' + esc(ctx.msg("hotel_since")) + ' ' + esc(since(r.occupancy_since || r.housekeeping_since, ctx.now)) + '</div>';
        }
        h += '<div class="hk_actions">';
        if (occ === "vacant" && ctx.can.checkin) { h += '<button type="button" class="hk_btn hk_btn_primary hk_act_checkin">' + esc(ctx.msg("check_in")) + '</button>'; }
        if (occ === "occupied" && ctx.can.checkout) { h += '<button type="button" class="hk_btn hk_btn_danger hk_act_checkout">' + esc(ctx.msg("check_out")) + '</button>'; }
        if (occ === "occupied" && ctx.can.value && r.stay_id) { h += '<button type="button" class="hk_btn hk_btn_ghost hk_act_value" title="' + esc(ctx.msg("hotel_value_edit")) + '"><i class="fas fa-coins"></i></button>'; }
        if (occ === "vacant" && ctx.can.status) { h += '<button type="button" class="hk_btn hk_act_ooo" data-on="1">' + esc(ctx.msg("hotel_set_out_of_order")) + '</button>'; }
        if (occ === "out_of_order" && ctx.can.status) { h += '<button type="button" class="hk_btn hk_btn_primary hk_act_ooo" data-on="0">' + esc(ctx.msg("hotel_back_in_service")) + '</button>'; }
        h += '<button type="button" class="hk_btn hk_btn_ghost hk_act_history" title="' + esc(ctx.msg("history")) + '"><i class="fas fa-history"></i></button>';
        h += '</div></div>';
        return h;
    }
    function boardHtml(rooms, ctx) {
        if (!rooms || !rooms.length) { return '<div class="hk_empty">' + esc(ctx.msg("hotel_no_rooms")) + '</div>'; }
        var byFloor = {}, floors = [];
        rooms.forEach(function (r) { var f = r.floor || ""; if (!byFloor[f]) { byFloor[f] = []; floors.push(f); } byFloor[f].push(r); });
        var h = "";
        floors.forEach(function (f) {
            h += '<div class="hk_floor">' + (floors.length > 1 || f ? '<h4 class="hk_floor_title">' + (f ? esc(f) : "&nbsp;") + '</h4>' : '') + '<div class="hk_grid">';
            byFloor[f].forEach(function (r) { h += roomCardHtml(r, ctx); });
            h += '</div></div>';
        });
        return h;
    }
    root.irHotelBoard = { esc: esc, since: since, money: money, nightsBetween: nightsBetween, roomCardHtml: roomCardHtml, boardHtml: boardHtml };

    if (typeof $ === "undefined") { return; }   /* Node unit tests stop here */
    $(function () {
        var base_url = $("#hk_base_url").val();
        var msg = function (k) { var v = $("#hk_lang_" + k).val(); return v ? v : k; };
        var can = { checkin: $("#hk_can_checkin").val() === "1", checkout: $("#hk_can_checkout").val() === "1", status: $("#hk_can_status").val() === "1", value: $("#hk_can_value").val() === "1" };
        var polling = false, last = "", server_now = null;
        function outlet() { return $("#hk_outlet").val(); }
        function markUpdated() { var n = new Date(); last = String(n.getHours()).padStart(2, "0") + ":" + String(n.getMinutes()).padStart(2, "0") + ":" + String(n.getSeconds()).padStart(2, "0"); $("#hk_offline_banner").hide(); }
        function render(res) {
            server_now = res.server_time;
            $("#hk_board").html(boardHtml(res.rooms, { msg: msg, can: can, now: server_now }));
            $("#hk_c_occupied").text(res.counts.occupied); $("#hk_c_vacant").text(res.counts.vacant); $("#hk_c_dirty").text(res.counts.dirty); $("#hk_c_ooo").text(res.counts.out_of_order);
        }
        function refresh(done) {
            if (polling) { return; } polling = true;
            $.ajax({ url: base_url + "Hotel/boardAjax", method: "POST", dataType: "json", data: { outlet_id: outlet() },
                success: function (res) { polling = false; if (res && res.ok) { render(res); markUpdated(); } if (done) { done(res); } },
                error: function () { polling = false; $("#hk_last_updated").text(last); $("#hk_offline_banner").show(); if (done) { done(null); } } });
        }
        /* U1: a 4 s toast, same look as the app's success banner; the board refresh alone was too quiet */
        var ci_room_no = "";
        function toast(text, bad) {
            var t = $('<div class="hk_toast' + (bad ? ' hk_toast_bad' : '') + '"></div>').text(text).appendTo("body");
            setTimeout(function () { t.addClass("hk_toast_leaving"); setTimeout(function () { t.remove(); }, 450); }, 4000);
        }
        function post(action, data, done) {
            $.ajax({ url: base_url + "Hotel/" + action, method: "POST", dataType: "json", data: data,
                success: function (res) { done(res); }, error: function () { done({ ok: 0, reason: "network", message: "" }); } });
        }
        /* --- check-in --- */
        $(document).on("click", ".hk_act_checkin", function () {
            var card = $(this).closest(".hk_card");
            ci_room_no = card.attr("data-number");
            $("#hk_ci_room_id").val(card.attr("data-room_id")); $("#hk_ci_room").text(card.attr("data-number"));
            $("#hk_ci_confirm_dirty").val("0"); $("#hk_ci_warn").prop("hidden", true); $("#hk_ci_error").text("");
            $("#hk_ci_guest, #hk_ci_phone, #hk_ci_expected, #hk_ci_reference, #hk_ci_notes").val(""); $("#hk_ci_adults").val(1); $("#hk_ci_children").val(0);
            $("#hk_ci_rate").val(Number(card.attr("data-rate") || 0).toFixed(2)); ciCalc();
            $("#hk_ci_submit").text(msg("check_in"));
            $("#hkCheckinModal").modal("show"); setTimeout(function () { $("#hk_ci_guest").trigger("focus"); }, 300);
        });
        $(document).on("click", "#hk_ci_submit", function () {
            var btn = $(this).prop("disabled", true);
            post("checkIn", { room_id: $("#hk_ci_room_id").val(), guest_name: $("#hk_ci_guest").val(), guest_phone: $("#hk_ci_phone").val(), adults: $("#hk_ci_adults").val(), children: $("#hk_ci_children").val(),
                              expected_checkout: $("#hk_ci_expected").val(), reference: $("#hk_ci_reference").val(), notes: $("#hk_ci_notes").val(), confirm_dirty: $("#hk_ci_confirm_dirty").val(),
                              rate: $("#hk_ci_rate").val() }, function (res) {
                btn.prop("disabled", false);
                if (res && res.ok) { $("#hkCheckinModal").modal("hide"); toast(msg("hotel_toast_checked_in") + " " + ci_room_no + " - " + $("#hk_ci_guest").val()); refresh(); return; }
                if (res && res.warn) {
                    /* the room is not clean: warn, and let the same button confirm */
                    $("#hk_ci_warn_text").text(msg("hotel_dirty_warn_text") + " (" + msg("room_" + res.housekeeping_status) + ")");
                    $("#hk_ci_warn").prop("hidden", false); $("#hk_ci_confirm_dirty").val("1"); btn.text(msg("hotel_check_in_anyway")); return;
                }
                $("#hk_ci_error").text(res && res.message ? res.message : "Error");
            });
        });
        /* the check-in value, live: nights from the expected check-out, amount = nights x rate */
        function ciCalc() {
            var rate = Number($("#hk_ci_rate").val() || 0), exp = $("#hk_ci_expected").val(), n = nightsBetween($("#hk_today").val(), exp || $("#hk_today").val());
            $("#hk_ci_nights").val(exp ? n : (rate > 0 ? "" : n)); $("#hk_ci_amount").val(money(n * rate));
            $("#hk_ci_expected_star").toggle(rate > 0);
        }
        $(document).on("input change", "#hk_ci_rate, #hk_ci_expected", ciCalc);
        /* --- check-out (H5: a value holder is asked about a nights variance - option C) --- */
        function doCheckout(card, extra, done) {
            post("checkOut", $.extend({ room_id: card.attr("data-room_id") }, extra || {}), function (res) {
                if (res && res.ok) { toast(msg("hotel_toast_checked_out") + " " + card.attr("data-number")); refresh(); if (done) { done(true); } return; }
                if (res && res.reason === "variance" && res.warn) {
                    $("#hk_va_room").text(card.attr("data-number")); $("#hk_va_room_id").val(card.attr("data-room_id"));
                    $("#hk_va_text").text(msg("hotel_variance_text").replace("{expected}", res.expected_nights).replace("{actual}", res.actual_nights).replace("{guest}", res.guest_name || ""));
                    $("#hk_va_old").val(money(res.amount)); $("#hk_va_amount").val(Number(res.suggested_amount).toFixed(2)); $("#hk_va_note").val(""); $("#hk_va_error").text("");
                    $("#hkVarianceModal").modal("show"); if (done) { done(false); } return;
                }
                toast(res && res.message ? res.message : "Error", true); refresh(); if (done) { done(false); }
            });
        }
        $(document).on("click", ".hk_act_checkout", function () {
            var card = $(this).closest(".hk_card");
            if (!window.confirm(msg("hotel_confirm_checkout") + " " + card.attr("data-number"))) { return; }
            doCheckout(card);
        });
        $(document).on("click", "#hk_va_keep, #hk_va_adjust", function () {
            var keep = this.id === "hk_va_keep", card = $('.hk_card[data-room_id="' + $("#hk_va_room_id").val() + '"]');
            var amount = keep ? $("#hk_va_old").val().replace(/,/g, "") : $("#hk_va_amount").val();
            if (!keep && (amount === "" || Number(amount) < 0)) { $("#hk_va_error").text(msg("hotel_err_amount")); return; }
            doCheckout(card, { confirm_value: 1, amount: amount, value_note: $("#hk_va_note").val() }, function (ok) { if (ok) { $("#hkVarianceModal").modal("hide"); } });
        });
        /* --- edit value (value holders) --- */
        function evCalc(fromAmount) {
            var rate = Number($("#hk_ev_rate").val() || 0), n = nightsBetween($("#hk_ev_checkin").val(), $("#hk_ev_expected").val() || $("#hk_ev_checkin").val());
            $("#hk_ev_nights").val(n); if (!fromAmount) { $("#hk_ev_amount").val((n * rate).toFixed(2)); }
        }
        $(document).on("input change", "#hk_ev_rate, #hk_ev_expected", function () { evCalc(false); });
        $(document).on("click", ".hk_act_value", function () {
            var card = $(this).closest(".hk_card");
            $("#hk_ev_room").text(card.attr("data-number")); $("#hk_ev_stay_id").val(card.attr("data-stay_id"));
            if (!$("#hk_ev_checkin").length) { $("<input type='hidden' id='hk_ev_checkin'>").appendTo("#hkValueModal"); }
            $("#hk_ev_checkin").val((card.data("checkin") || $("#hk_today").val()));
            $("#hk_ev_expected").val(card.attr("data-stay_expected") || ""); $("#hk_ev_rate").val(Number(card.attr("data-stay_rate") || 0).toFixed(2));
            $("#hk_ev_amount").val(Number(card.attr("data-stay_amount") || 0).toFixed(2)); $("#hk_ev_nights").val(""); $("#hk_ev_note").val(""); $("#hk_ev_error").text("");
            evCalc(true); $("#hkValueModal").modal("show");
        });
        $(document).on("click", "#hk_ev_submit", function () {
            post("editStayValue", { stay_id: $("#hk_ev_stay_id").val(), expected_checkout: $("#hk_ev_expected").val(), rate: $("#hk_ev_rate").val(), amount: $("#hk_ev_amount").val(), value_note: $("#hk_ev_note").val() }, function (res) {
                if (res && res.ok) { $("#hkValueModal").modal("hide"); toast(msg("hotel_value_updated") + " " + money(res.amount)); refresh(); return; }
                $("#hk_ev_error").text(res && res.message ? res.message : "Error");
            });
        });
        /* --- out of order --- */
        $(document).on("click", ".hk_act_ooo", function () {
            var card = $(this).closest(".hk_card"), on = $(this).attr("data-on") === "1";
            $("#hk_ooo_room_id").val(card.attr("data-room_id")); $("#hk_ooo_on").val(on ? "1" : "0"); $("#hk_ooo_note").val(""); $("#hk_ooo_error").text("");
            $("#hk_ooo_title").text((on ? msg("hotel_set_out_of_order") : msg("hotel_back_in_service")) + " · " + card.attr("data-number"));
            $("#hkOooModal").modal("show");
        });
        $(document).on("click", "#hk_ooo_submit", function () {
            post("setOutOfOrder", { room_id: $("#hk_ooo_room_id").val(), out_of_order: $("#hk_ooo_on").val(), note: $("#hk_ooo_note").val() }, function (res) {
                if (res && res.ok) { $("#hkOooModal").modal("hide"); toast($("#hk_ooo_title").text()); refresh(); return; }
                $("#hk_ooo_error").text(res && res.message ? res.message : "Error");
            });
        });
        /* --- history --- */
        $(document).on("click", ".hk_act_history", function () {
            var card = $(this).closest(".hk_card");
            $("#hk_hist_room").text(card.attr("data-number")); $("#hk_hist_body").html("&hellip;"); $("#hkHistoryModal").modal("show");
            post("roomHistoryAjax", { room_id: card.attr("data-room_id") }, function (res) {
                if (!res || !res.ok) { $("#hk_hist_body").text(res && res.message ? res.message : "Error"); return; }
                if (!res.history.length) { $("#hk_hist_body").text(msg("no_data_found")); return; }
                var h = '<table class="table table-sm"><tbody>';
                res.history.forEach(function (l) { h += '<tr><td class="hk_muted" style="white-space:nowrap">' + esc(l.created_at) + '</td><td>' + esc(l.status_kind === "value" ? msg("hotel_log_value") : l.status_kind) + ': ' + esc(l.status_kind === "value" ? (l.from_status ? money(l.from_status) : "-") : (l.from_status || "")) + ' &rarr; <b>' + esc(l.status_kind === "value" ? money(l.to_status) : l.to_status) + '</b>' + (l.note ? '<div class="hk_muted">' + esc(l.note) + '</div>' : '') + '</td><td class="hk_muted">' + esc(l.user_name || "") + '</td></tr>'; });
                $("#hk_hist_body").html(h + '</tbody></table>');
            });
        });
        $(document).on("change", "#hk_outlet", function () { refresh(); });
        refresh();
        setInterval(refresh, 15000);
    });
})(typeof window !== "undefined" ? window : (typeof globalThis !== "undefined" ? globalThis : this));
