/* Hotel add-on (H6): the report filter bar's presets. A preset fills the dates AND picks the view whose
   buckets read naturally for it (today -> day, this week -> day, this month -> week, this year -> month),
   then submits. Pure date maths, local time, no library. */
(function () {
    "use strict";
    function ymd(d) { return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0"); }
    function preset(name, today) {
        var t = new Date(today + "T00:00:00"), from = new Date(t), to = new Date(t), view = "day";
        if (name === "week") { var wd = (t.getDay() + 6) % 7; from.setDate(t.getDate() - wd); to.setDate(from.getDate() + 6); view = "day"; }
        else if (name === "month") { from.setDate(1); to = new Date(t.getFullYear(), t.getMonth() + 1, 0); view = "week"; }
        else if (name === "year") { from = new Date(t.getFullYear(), 0, 1); to = new Date(t.getFullYear(), 11, 31); view = "month"; }
        return { from: ymd(from), to: ymd(to), view: view };
    }
    if (typeof module !== "undefined") { module.exports = { preset: preset, ymd: ymd }; }
    if (typeof $ === "undefined") { return; }
    $(function () {
        $(document).on("click", ".hk_rp_preset", function () {
            var p = preset($(this).attr("data-preset"), $("#hk_today").val());
            $("#hk_rp_from").val(p.from); $("#hk_rp_to").val(p.to); $("#hk_rp_view").val(p.view);
            $("#hk_report_form").trigger("submit");
        });
    });
})();