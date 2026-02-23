(function () {
  "use strict";

  function pad2(n) { return String(n).padStart(2, "0"); }

  // Accept: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
  function parseDate(value) {
    if (!value) return null;
    var v = String(value).trim();

    var iso = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (iso) {
      var y = parseInt(iso[1], 10);
      var mo = parseInt(iso[2], 10) - 1;
      var d = parseInt(iso[3], 10);
      var dt = new Date(y, mo, d);
      if (dt.getFullYear() !== y || dt.getMonth() !== mo || dt.getDate() !== d) return null;
      return dt;
    }

    var dmy = v.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (dmy) {
      var d2 = parseInt(dmy[1], 10);
      var mo2 = parseInt(dmy[2], 10) - 1;
      var y2 = parseInt(dmy[3], 10);
      var dt2 = new Date(y2, mo2, d2);
      if (dt2.getFullYear() !== y2 || dt2.getMonth() !== mo2 || dt2.getDate() !== d2) return null;
      return dt2;
    }

    return null;
  }

  function formatDMYDash(dt) {
    return pad2(dt.getDate()) + "-" + pad2(dt.getMonth() + 1) + "-" + dt.getFullYear();
  }

  function formatISO(dt) {
    return dt.getFullYear() + "-" + pad2(dt.getMonth() + 1) + "-" + pad2(dt.getDate());
  }

  function addDays(dt, days) {
    var copy = new Date(dt.getTime());
    copy.setDate(copy.getDate() + days);
    return copy;
  }

  function getDaysValue(daysEl, fallback) {
    if (!daysEl) return fallback;
    var raw = String(daysEl.value || "").trim();
    var n = parseInt(raw.replace(/[^0-9]/g, ""), 10);
    return isFinite(n) && n > 0 ? n : fallback;
  }

  function todayISO() {
    var now = new Date();
    var dt = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    return formatISO(dt);
  }

  function applyHtml5Min(input) {
    try {
      input.setAttribute("min", todayISO());
    } catch (e) {}
  }

  // Availability blocking for the Start Date datepicker
  function applyDatepickerBlocking(startInput, slotEl, daysEl) {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.datepicker) return;

    var $ = jQuery;
    var $start = $(startInput);
    if (!$start.data("datepicker")) return;

    var blockedSet = new Set();

    function getSlot() { return slotEl ? String(slotEl.value || "").trim() : ""; }
    function getDays() {
      var raw = daysEl ? String(daysEl.value || "").trim() : "";
      var n = parseInt(raw.replace(/[^0-9]/g, ""), 10);
      return isFinite(n) && n > 0 ? n : 30;
    }

    function refresh() {
      if (!window.SolasAdvertsAvailability) return;
      var slot = getSlot();
      var days = getDays();
      if (!slot) return;

      var fd = new FormData();
      fd.append("action", "solas_adverts_blocked_dates");
      fd.append("nonce", SolasAdvertsAvailability.nonce || "");
      fd.append("slot", slot);
      fd.append("days", String(days));
      fd.append("horizon", String(SolasAdvertsAvailability.horizon || 365));

      fetch(SolasAdvertsAvailability.ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          blockedSet = new Set();
          if (data && data.success && data.data && Array.isArray(data.data.blocked)) {
            data.data.blocked.forEach(function (iso) { blockedSet.add(String(iso)); });
          }

          $start.datepicker("option", "beforeShowDay", function (date) {
            var iso = date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0") + "-" + String(date.getDate()).padStart(2, "0");
            if (blockedSet.has(iso)) return [false, "solas-date-booked", "Booked"];
            return [true, "", ""];
          });

          $start.datepicker("refresh");

          // If selected date is now blocked, clear it
          var dt = parseDate(startInput.value);
          if (dt && blockedSet.has(formatISO(dt))) startInput.value = "";
        })
        .catch(function () {});
    }

    if (slotEl) slotEl.addEventListener("change", refresh);
    if (daysEl) daysEl.addEventListener("change", refresh);

    refresh();
  }

  function init() {
    if (!window.SolasAdvertsForm) return;

    var formId = SolasAdvertsForm.formId;
    var startId = SolasAdvertsForm.startFieldId;
    var endId = SolasAdvertsForm.endFieldId;
    var daysId = SolasAdvertsForm.daysFieldId;
    var slotId = SolasAdvertsForm.slotFieldId || 1;
    var fallbackDays = parseInt(SolasAdvertsForm.defaultDays || 30, 10);

    var start = document.querySelector("#input_" + formId + "_" + startId);
    var end = document.querySelector("#input_" + formId + "_" + endId);
    var daysEl = daysId ? document.querySelector("#input_" + formId + "_" + daysId) : null;
    var slotEl = document.querySelector("#input_" + formId + "_" + slotId);

    if (!start || !end) return;

    // Prevent past dates in HTML5 date input browsers (belt & braces).
    applyHtml5Min(start);
    applyHtml5Min(end);

    // End date should be system-populated
    try { end.readOnly = true; } catch (e) {}
    end.classList.add("solas-enddate-locked");

    function autofillEnd() {
      var startDt = parseDate(start.value);
      if (!startDt) return;

      var days = getDaysValue(daysEl, fallbackDays);
      var endDt = addDays(startDt, Math.max(0, days - 1));
      end.value = formatDMYDash(endDt);
    }

    start.addEventListener("change", autofillEnd);
    if (daysEl) daysEl.addEventListener("change", autofillEnd);

    // Block fully booked starts in the jQuery UI datepicker
    if (start) applyDatepickerBlocking(start, slotEl, daysEl);

    // Initial fill
    autofillEnd();
  }

  document.addEventListener("DOMContentLoaded", init);
})();
