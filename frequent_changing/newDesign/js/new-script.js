/* No animation anywhere. Every fade, slide and animate() in this app is
   time the user waits after the work is already done - on a till during a
   busy service that is dead time on every tap. Turning jQuery.fx off makes
   them complete instantly while STILL firing their completion callbacks,
   so nothing that depends on an animation finishing breaks. One switch,
   rather than hunting some forty fade durations and missing one. */
if (window.jQuery) { window.jQuery.fx.off = true; }
(function ($) {
  "use strict";
  const win = $(window);
  const bodyElement = $("body");
  /**
   * Perfect Scrollbar
   */
  const ps = new PerfectScrollbar(".sidebar-menu", {
    wheelSpeed: 2,
    wheelPropagation: true,
    minScrollbarLength: 20,
  });

  ps.update();

  /**
   * Active ToolTips
   */
  let tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  let tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  /***
   * Hide Preloader
   */

  win.on("load", () => {
    /* Straight to the screen. This used to wait 500ms and then fade for another
       500ms - a full second of doing nothing, and all of it AFTER window.load,
       so none of it covered any real work; it was added on top. On a till taking
       orders through a busy service that is a second lost on every page. */
    $(".main-preloader").hide();
  });

  /**
   * Click to show Menus for mobile devices
   */
  bodyElement.on("click", ".om", function () {
    bodyElement.find(".screen-list").addClass("active");
  });
  $(document).on("click", function (event) {
    if ($(event.target).closest(".om").length === 0) {
      bodyElement.find(".screen-list").removeClass("active");
    }
  });

  /**
   * Open Sidebar When Active Arabic Language
   */
  bodyElement.on("click", ".st", function () {
    bodyElement.find(".main-sidebar2").addClass("active");
  });
  $(document).on("click", function (e) {
    if (
      $(e.target).closest(".st").length === 0 &&
      $(e.target).closest(".sidebar-menu").length === 0
    ) {
      bodyElement.find(".main-sidebar2").removeClass("active");
    }
  });

  /**
   * Language Dropdown
   */

  bodyElement.on("click", ".sub_sub > a", function (e) {
    $(this).parent().toggleClass("menu-open");
    $(this).next('.sub-menu').slideToggle(400);
  })
})(jQuery);
