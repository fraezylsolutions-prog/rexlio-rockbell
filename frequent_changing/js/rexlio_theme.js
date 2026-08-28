/*
 * Rexlio theme switch.
 *
 * Applied to <html> as data-theme, so the stylesheet's :root[data-theme="dark"]
 * block wins over the prefers-color-scheme default. Stored per browser in
 * localStorage: this is a per-device viewing preference, not company data, so it
 * deliberately does NOT round-trip to the server - a cashier switching to dark
 * on the till should not change what the owner sees on their laptop.
 *
 * The <html> attribute is set by an inline snippet in the page <head> BEFORE
 * this file runs, so the saved theme is already applied on first paint. Doing it
 * here alone would show a flash of the wrong theme on every page load.
 */
(function (window, document) {
    "use strict";

    var KEY = "rexlio_theme";

    function read() {
        try { return window.localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function write(value) {
        // Private-browsing and blocked-storage both throw here. A theme that
        // cannot be remembered is a small loss; a script that dies takes the
        // toggle with it, so this never rethrows.
        try { window.localStorage.setItem(KEY, value); } catch (e) {}
    }


    /*
     * Chart.js draws into a <canvas>. Canvas pixels are painted by JavaScript,
     * so NONE of the theme stylesheet reaches them - the chart keeps whatever
     * colours it was constructed with. The dashboard config sets no tick or
     * gridline colour at all, so Chart.js falls back to its defaults: #666 text
     * and rgba(0,0,0,.1) gridlines. On the dark navy ground that grid is pure
     * black at 10% opacity, i.e. invisible, and the axis labels sit near the
     * contrast floor. So the tokens have to be pushed into Chart.js by hand and
     * the live instances redrawn whenever the theme changes.
     *
     * Chart.js here is v3.7.1, so Chart.getChart(canvas) is available. Guarded
     * throughout: the dashboard is the only screen with charts, and this same
     * script runs on every screen including the POS.
     */
    /* Chart fills need the line colour at a low alpha. Accepts #rgb, #rrggbb
       and rgb()/rgba() - the token could be written any of those ways. */
    function fade(color, alpha) {
        var c = String(color || '').trim();
        var m = c.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (m) {
            var h = m[1];
            if (h.length === 3) { h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2]; }
            return 'rgba(' + parseInt(h.slice(0,2),16) + ',' + parseInt(h.slice(2,4),16)
                 + ',' + parseInt(h.slice(4,6),16) + ',' + alpha + ')';
        }
        var p = c.match(/rgba?\(([^)]+)\)/i);
        if (p) {
            var v = p[1].split(',');
            return 'rgba(' + v[0].trim() + ',' + v[1].trim() + ',' + v[2].trim() + ',' + alpha + ')';
        }
        return 'rgba(76,141,255,' + alpha + ')';
    }

    function retintCharts() {
        if (!window.Chart) { return; }
        var css = getComputedStyle(document.documentElement);
        var ink  = css.getPropertyValue("--rx-muted").trim() || "#666";
        var grid = css.getPropertyValue("--rx-grid").trim()  || "rgba(0,0,0,.1)";
        var accent = css.getPropertyValue("--rx-accent").trim() || "#4C8DFF";
        try {
            Chart.defaults.color = ink;
            Chart.defaults.borderColor = grid;
        } catch (e) { return; }

        /* Collecting the charts is not as simple as walking the canvases.
           This app loads TWO Chart.js builds - v3 first, then v2, which
           overwrites window.Chart. Chart.getChart() is v3-only, so on the
           dashboard it is undefined and every lookup here returned null:
           this whole function was silently doing nothing on the one screen
           that actually has charts. dashboard_chart_custom.js now hands its
           v3 instance over directly, so it is picked up regardless. */
        var charts = [];
        var canvases = document.querySelectorAll("canvas");
        for (var i = 0; i < canvases.length; i++) {
            try {
                var found = Chart.getChart ? Chart.getChart(canvases[i]) : null;
                if (found) { charts.push(found); }
            } catch (e) {}
        }
        if (window.rexlioDashChart && charts.indexOf(window.rexlioDashChart) === -1) {
            charts.push(window.rexlioDashChart);
        }

        for (var n = 0; n < charts.length; n++) {
            var chart = charts[n];
            if (!chart || !chart.options) { continue; }
            try {
                /* The dashboard line chart is constructed with NO scales block
                   at all, so the old loop over Object.keys(scales) ran zero
                   times and themed nothing: Chart.js fell back to its own
                   defaults - #666 labels and rgba(0,0,0,.1) gridlines - which
                   on the navy ground meant no visible axis numbers and no
                   grid. The scales have to be CREATED here, not just tinted. */
                /* Write to chart.config.options, NOT chart.options.
                   In Chart.js v3 chart.options is a RESOLVED PROXY; assigning
                   into it registers the value as a scriptable option that
                   resolves through itself, and the chart then dies during
                   render with 'Recursion detected: _scriptable->_scriptable'
                   - asynchronously, so a try/catch around the call sees
                   nothing. The config object underneath is plain data. */
                var opts = (chart.config && chart.config.options)
                    ? chart.config.options
                    : chart.options;
                opts.scales = opts.scales || {};
                if (!opts.scales.x) { opts.scales.x = {}; }
                if (!opts.scales.y) { opts.scales.y = {}; }
                Object.keys(opts.scales).forEach(function (key) {
                    var sc = opts.scales[key];
                    if (!sc) { return; }
                    sc.ticks = sc.ticks || {};
                    sc.ticks.color = ink;
                    sc.grid = sc.grid || {};
                    sc.grid.color = grid;
                    sc.grid.borderColor = grid;
                });
                /* Abbreviate the value axis (40000 -> 40K) so the numbers fit
                   without crowding, and always start it at zero - a revenue
                   axis that floats exaggerates every peak. */
                opts.scales.y.beginAtZero = true;
                opts.scales.y.ticks.callback = function (value) {
                    var n = Number(value);
                    if (!isFinite(n)) { return value; }
                    if (Math.abs(n) >= 1000000) { return (n / 1000000) + 'M'; }
                    if (Math.abs(n) >= 1000) { return (n / 1000) + 'K'; }
                    return n;
                };

                /* Line styling. The vendor sets borderColor '#00000000' - a
                   fully transparent line - so the series showed only as a
                   flat silhouette with no stroke and no data points. Only
                   transparent/absent strokes are replaced, so a chart that
                   deliberately sets its own colour keeps it. */
                var ctx2d = chart.ctx;
                (chart.data.datasets || []).forEach(function (d) {
                    var kind = d.type || (chart.config && chart.config.type);
                    if (kind !== 'line') { return; }
                    var stroke = String(d.borderColor || '');
                    var invisible = !stroke
                        || stroke === '#00000000'
                        || /rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\s*\)/.test(stroke)
                        || /^#[0-9a-f]{6}00$/i.test(stroke);
                    /* Resolve the stroke into a plain local string FIRST.
                       Assigning point colours by reading d.borderColor back
                       out sends the read through Chart's scriptable-option
                       proxy, which then resolves into itself and throws
                       'Recursion detected: _scriptable->_scriptable'. */
                    var stroke2 = invisible ? accent : stroke;
                    d.borderColor = stroke2;
                    d.borderWidth = 3;
                    d.pointRadius = 3;
                    d.pointHoverRadius = 5;
                    d.pointBackgroundColor = stroke2;
                    d.pointBorderColor = stroke2;
                    /* Vertical gradient under the line, fading to nothing at
                       the baseline. Needs a canvas context, so it is built
                       here rather than in the stylesheet. */
                    if (ctx2d && chart.chartArea) {
                        try {
                            var area = chart.chartArea;
                            var g = ctx2d.createLinearGradient(0, area.top, 0, area.bottom);
                            g.addColorStop(0, fade(stroke2, 0.55));
                            g.addColorStop(1, fade(stroke2, 0.02));
                            d.backgroundColor = g;
                            d.fill = true;
                        } catch (e) {}
                    }
                });
                chart.update('none');
            } catch (e) {}
        }
    }

    function apply(mode) {
        document.documentElement.setAttribute("data-theme", mode);
        retintCharts();
        var buttons = document.querySelectorAll(".rx-theme-toggle button[data-mode]");
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute(
                "aria-pressed",
                buttons[i].getAttribute("data-mode") === mode ? "true" : "false"
            );
        }
    }

    function current() {
        var saved = read();
        if (saved === "dark" || saved === "light") { return saved; }
        // No stored choice: follow the operating system.
        return (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches)
            ? "dark" : "light";
    }

    function init() {
        apply(current());
        document.addEventListener("click", function (e) {
            var btn = e.target && e.target.closest
                ? e.target.closest(".rx-theme-toggle button[data-mode]") : null;
            if (!btn) { return; }
            e.preventDefault();
            var mode = btn.getAttribute("data-mode");
            write(mode);
            apply(mode);
        });
        // Follow the OS only while the user has expressed no preference.
        if (window.matchMedia) {
            var mq = window.matchMedia("(prefers-color-scheme: dark)");
            var onChange = function () { if (!read()) { apply(current()); } };
            if (mq.addEventListener) { mq.addEventListener("change", onChange); }
            else if (mq.addListener) { mq.addListener(onChange); }
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    // Charts are constructed by their own scripts, which may run after this one.
    // A retint on window.load catches instances that did not exist at init.
    window.addEventListener("load", function () { retintCharts(); });

    window.rexlioTheme = { apply: apply, current: current, retintCharts: retintCharts };
})(window, document);
