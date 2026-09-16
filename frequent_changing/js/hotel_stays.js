/* Hotel add-on (H5): edit a stay's value from the Stay Log (value permission). The page is
   server-rendered, so a successful save simply reloads it. */
(function () {
    "use strict";
    if (typeof $ === "undefined") { return; }
    function nightsBetween(from, to) { if (!from || !to) { return 1; } var d = Math.floor((new Date(to + "T00:00:00") - new Date(from + "T00:00:00")) / 86400000); return isNaN(d) ? 1 : Math.max(1, d); }
    function calc(fromAmount) { var rate = Number($("#hk_ev_rate").val() || 0), n = nightsBetween($("#hk_ev_checkin").val(), $("#hk_ev_expected").val() || $("#hk_ev_checkin").val()); $("#hk_ev_nights").val(n); if (!fromAmount) { $("#hk_ev_amount").val((n * rate).toFixed(2)); } }
    $(function () {
        $(document).on("click", ".hk_stay_value", function () {
            var b = $(this);
            $("#hk_ev_room").text(b.attr("data-room")); $("#hk_ev_stay_id").val(b.attr("data-stay_id")); $("#hk_ev_checkin").val(b.attr("data-checkin"));
            $("#hk_ev_expected").val(b.attr("data-expected") || ""); $("#hk_ev_rate").val(Number(b.attr("data-rate") || 0).toFixed(2)); $("#hk_ev_amount").val(Number(b.attr("data-amount") || 0).toFixed(2));
            $("#hk_ev_note").val(""); $("#hk_ev_error").text(""); calc(true); $("#hkValueModal").modal("show");
        });
        $(document).on("input change", "#hk_ev_rate, #hk_ev_expected", function () { calc(false); });
        $(document).on("click", "#hk_ev_submit", function () {
            $.ajax({ url: $("#hk_base_url").val() + "Hotel/editStayValue", method: "POST", dataType: "json",
                data: { stay_id: $("#hk_ev_stay_id").val(), expected_checkout: $("#hk_ev_expected").val(), rate: $("#hk_ev_rate").val(), amount: $("#hk_ev_amount").val(), value_note: $("#hk_ev_note").val() },
                success: function (res) { if (res && res.ok) { window.location.reload(); return; } $("#hk_ev_error").text(res && res.message ? res.message : "Error"); },
                error: function () { $("#hk_ev_error").text("Error"); } });
        });
    });
})();
