/* Hotel add-on (H3): the Housekeeping board.
   Kitchen Panel pattern: a 10 s server refresh (Hotel/tasksAjax) plus a refresh
   after every action, cards per task with Start / Finish (own or pool tasks),
   Verify (supervisors) and Assign / Cancel (assign holders). Sections are built
   from the server's is_mine / assigned_to / status, never from role names.
   The builders are pure functions so they can be unit tested without a DOM. */
(function (root) {
    "use strict";

    function esc(v) { return String(v === undefined || v === null ? "" : v).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    function since(ts, now) {
        if (!ts) { return ""; }
        var t = new Date(String(ts).replace(" ", "T")), n = now ? new Date(String(now).replace(" ", "T")) : new Date();
        var mins = Math.max(0, Math.round((n - t) / 60000)); if (isNaN(mins)) { return ""; }
        if (mins < 60) { return mins + "m"; }
        if (mins < 1440) { return Math.floor(mins / 60) + "h " + String(mins % 60).padStart(2, "0") + "m"; }
        return Math.floor(mins / 1440) + "d " + Math.floor((mins % 1440) / 60) + "h";
    }
    /* which section a task belongs in, for THIS viewer */
    function sectionOf(t) {
        if (t.status === "done") { return "done"; }
        if (t.assigned_to === null || t.assigned_to === undefined || t.assigned_to === "") { return "pool"; }
        return Number(t.is_mine) === 1 ? "mine" : "others";
    }
    /* one task card (pure). ctx: {msg, can:{update,assign,verify}, now} */
    function taskCardHtml(t, ctx) {
        var sec = sectionOf(t);
        var h = '<div class="hkp_card hkp_' + t.status + ' hkp_type_' + esc(t.task_type) + '" data-task_id="' + esc(t.id) + '" data-room="' + esc(t.room_number) + '">';
        h += '<div class="hkp_card_head"><span class="hkp_room">' + esc(t.room_number) + '</span><span class="hkp_type">' + esc(ctx.msg("hotel_task_" + t.task_type)) + '</span></div>';
        h += '<div class="hkp_meta">' + esc(ctx.msg("hk_status_" + t.status)) + ' &middot; ' + esc(ctx.msg("hotel_since")) + ' ' + esc(since(t.status === "pending" ? t.created_at : (t.status === "done" ? t.done_at : t.started_at), ctx.now)) +
             (t.occupancy_status ? ' &middot; ' + esc(ctx.msg("room_" + t.occupancy_status)) : '') + '</div>';
        if (t.assigned_name && sec !== "mine") { h += '<div class="hkp_meta">' + esc(ctx.msg("hk_by")) + ' ' + esc(t.assigned_name) + '</div>'; }
        if (t.note) { h += '<div class="hkp_note">' + esc(t.note) + '</div>'; }
        h += '<div class="hkp_card_actions">';
        var mayWork = ctx.can.update && (sec === "mine" || sec === "pool" || (ctx.can.assign && sec === "others"));
        if (mayWork && t.status === "pending") { h += '<button type="button" class="hkp_btn hkp_btn_primary hkp_act_start">' + esc(ctx.msg("hk_start")) + '</button>'; }
        if (mayWork && (t.status === "pending" || t.status === "in_progress")) { h += '<button type="button" class="hkp_btn hkp_btn_good hkp_act_done">' + esc(ctx.msg("hk_finish")) + '</button>'; }
        if (ctx.can.verify && t.status === "done") { h += '<button type="button" class="hkp_btn hkp_btn_primary hkp_act_verify">' + esc(ctx.msg("hk_verify")) + '</button>'; }
        if (ctx.can.assign && t.status !== "done") { h += '<button type="button" class="hkp_btn hkp_act_assign">' + esc(ctx.msg("hk_assign")) + '</button>'; }
        if (ctx.can.assign) { h += '<button type="button" class="hkp_btn hkp_btn_ghost hkp_act_cancel" title="' + esc(ctx.msg("hk_cancel")) + '"><i class="fas fa-times"></i></button>'; }
        h += '</div></div>';
        return h;
    }
    /* the whole board: sections in a fixed order, each only when the viewer may see it */
    function boardHtml(tasks, ctx) {
        var groups = { mine: [], pool: [], others: [], done: [] };
        (tasks || []).forEach(function (t) { groups[sectionOf(t)].push(t); });
        var order = [["mine", "hk_mine"], ["pool", "hk_pool"]];
        if (ctx.can.assign) { order.push(["others", "hk_others"]); }
        if (ctx.can.verify) { order.push(["done", "hk_done"]); }
        var h = "", total = 0;
        order.forEach(function (o) {
            var list = groups[o[0]]; total += list.length;
            h += '<section class="hkp_section hkp_sec_' + o[0] + '"><h3 class="hkp_sec_title">' + esc(ctx.msg(o[1])) + ' <span class="hkp_sec_count">' + list.length + '</span></h3><div class="hkp_grid">';
            if (!list.length) { h += '<div class="hkp_sec_empty">&mdash;</div>'; }
            list.forEach(function (t) { h += taskCardHtml(t, ctx); });
            h += '</div></section>';
        });
        if (!total && !ctx.can.assign) { return '<div class="hk_empty">' + esc(ctx.msg("hk_no_tasks")) + '</div>'; }
        return h;
    }
    root.irHousekeeping = { esc: esc, since: since, sectionOf: sectionOf, taskCardHtml: taskCardHtml, boardHtml: boardHtml };

    if (typeof $ === "undefined") { return; }
    $(function () {
        var base_url = $("#hk_base_url").val();
        var msg = function (k) { var v = $("#hk_lang_" + k).val(); return v ? v : k; };
        var polling = false, last = "", can = { update: false, assign: false, verify: false };
        function outlet() { return $("#hk_outlet").val(); }
        function tick() { var n = new Date(); $("#hkp_clock").text(String(n.getHours()).padStart(2, "0") + ":" + String(n.getMinutes()).padStart(2, "0")); }
        function render(res) {
            can = { update: Number(res.can.update) === 1, assign: Number(res.can.assign) === 1, verify: Number(res.can.verify) === 1 };
            $("#hkp_main").html(boardHtml(res.tasks, { msg: msg, can: can, now: res.server_time }));
            $("#hkp_c_mine").text(res.counts.mine); $("#hkp_c_pool").text(res.counts.pool); $("#hkp_c_others").text(res.counts.others); $("#hkp_c_done").text(res.counts.done);
        }
        function refresh(done) {
            if (polling) { return; } polling = true;
            $.ajax({ url: base_url + "Hotel/tasksAjax", method: "POST", dataType: "json", data: { outlet_id: outlet() },
                success: function (res) { polling = false; if (res && res.ok) { render(res); var n = new Date(); last = n.toLocaleTimeString(); $("#hk_offline_banner").hide(); } if (done) { done(res); } },
                error: function () { polling = false; $("#hk_last_updated").text(last); $("#hk_offline_banner").show(); if (done) { done(null); } } });
        }
        function post(action, data, done) {
            $.ajax({ url: base_url + "Hotel/" + action, method: "POST", dataType: "json", data: data, success: function (res) { done(res); }, error: function () { done({ ok: 0, reason: "network", message: "" }); } });
        }
        function act(action, card, extra) {
            post(action, $.extend({ task_id: card.attr("data-task_id") }, extra || {}), function (res) { if (!res || !res.ok) { alert(res && res.message ? res.message : "Error"); } refresh(); });
        }
        $(document).on("click", ".hkp_act_start", function () { act("taskStart", $(this).closest(".hkp_card")); });
        $(document).on("click", ".hkp_act_done", function () { act("taskDone", $(this).closest(".hkp_card")); });
        $(document).on("click", ".hkp_act_verify", function () { act("taskVerify", $(this).closest(".hkp_card")); });
        $(document).on("click", ".hkp_act_cancel", function () { var card = $(this).closest(".hkp_card"); if (window.confirm(msg("hk_confirm_cancel") + " " + card.attr("data-room"))) { act("taskCancel", card); } });
        $(document).on("click", ".hkp_act_assign", function () {
            var card = $(this).closest(".hkp_card");
            $("#hkp_as_task").val(card.attr("data-task_id")); $("#hkp_as_room").text(card.attr("data-room")); $("#hkp_as_error").text(""); $("#hkp_assign_overlay").prop("hidden", false);
        });
        $(document).on("click", "#hkp_as_submit", function () {
            post("taskAssign", { task_id: $("#hkp_as_task").val(), user_id: $("#hkp_as_user").val() }, function (res) { if (res && res.ok) { $("#hkp_assign_overlay").prop("hidden", true); refresh(); return; } $("#hkp_as_error").text(res && res.message ? res.message : "Error"); });
        });
        $(document).on("click", "#hkp_new_task", function () { $("#hkp_nt_note").val(""); $("#hkp_nt_error").text(""); $("#hkp_new_overlay").prop("hidden", false); });
        $(document).on("click", "#hkp_nt_submit", function () {
            post("taskCreate", { room_id: $("#hkp_nt_room").val(), task_type: $("#hkp_nt_type").val(), user_id: $("#hkp_nt_user").val(), note: $("#hkp_nt_note").val() }, function (res) { if (res && res.ok) { $("#hkp_new_overlay").prop("hidden", true); refresh(); return; } $("#hkp_nt_error").text(res && res.message ? res.message : "Error"); });
        });
        $(document).on("click", "#hkp_new_close, #hkp_as_close", function () { $(this).closest(".hkp_overlay").prop("hidden", true); });
        $(document).on("change", "#hk_outlet", function () { refresh(); });
        tick(); setInterval(tick, 30000);
        refresh();
        setInterval(refresh, 10000);
    });
})(typeof window !== "undefined" ? window : (typeof globalThis !== "undefined" ? globalThis : this));
