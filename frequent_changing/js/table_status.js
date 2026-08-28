/* Table status screen.
   Same shape as the running order screen: a 15 second server refresh with the
   timers ticked locally once a second, and an offline banner when the refresh
   cannot reach the server. This screen has no local store of its own, so an
   unreachable server means the cards are stale, never silently wrong. */
$(function () {
    "use strict";

    var base_url = $("#ts_base_url").val();
    var refresh_ms = 15000;
    var polling = false;
    var last_updated_text = "";

    function twoDigits(n) {
        return (n < 10 ? "0" : "") + n;
    }

    function markUpdated() {
        var now = new Date();
        last_updated_text = twoDigits(now.getHours()) + ":" + twoDigits(now.getMinutes()) + ":" + twoDigits(now.getSeconds());
        $("#ts_offline_banner").hide();
    }

    function showOfflineBanner() {
        $("#ts_last_updated").text(last_updated_text);
        $("#ts_offline_banner").show();
    }

    /* advance every visible occupied timer by one second */
    function tickTimers() {
        $(".ts_timer").each(function () {
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
            $(this).text(twoDigits(minutes) + ":" + twoDigits(seconds));
        });
    }

    function refreshTables() {
        if (polling) {
            return;
        }
        polling = true;
        $.ajax({
            url: base_url + "Monitor/tablesAjax",
            method: "POST",
            dataType: "json",
            success: function (response) {
                if (response && response.html !== undefined) {
                    $("#ts_card_area").html(response.html);
                    $("#ts_occupied_count").text(response.occupied);
                    $("#ts_free_count").text(response.free);
                    markUpdated();
                }
                polling = false;
            },
            error: function () {
                showOfflineBanner();
                polling = false;
            }
        });
    }

    /* Close this screen and go back to the POS. The tab was opened from a
       target="_blank" link, so window.close() is only permitted while the tab has
       a single history entry. Fall back to navigating to the POS when refused. */
    $(document).on("click", "#ts_close_screen", function (e) {
        e.preventDefault();
        var pos_url = $(this).attr("href");
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

    /* the page itself was just served by the server, so that counts as a read */
    markUpdated();

    setInterval(tickTimers, 1000);
    setInterval(refreshTables, refresh_ms);
});
