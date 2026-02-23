(function () {
  'use strict';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function pad2(n) {
    return n < 10 ? '0' + n : '' + n;
  }

  function ymd(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function startOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }

  function addMonths(date, months) {
    const d = new Date(date.getTime());
    const day = d.getDate();
    d.setDate(1);
    d.setMonth(d.getMonth() + months);
    const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
    d.setDate(Math.min(day, lastDay));
    return d;
  }

  function truthy(v) {
    return v === true || v === 1 || v === '1' || v === 'true';
  }

  function sameDate(a, b) {
    return a && b &&
      a.getFullYear() === b.getFullYear() &&
      a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate();
  }

  function formatWeekday(d) {
    return d.toLocaleDateString(undefined, { weekday: 'short' }); // Tue
  }

  function formatTime(d) {
    return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); // 11:56am
  }

  function isMidnight(d) {
    return d && d.getHours() === 0 && d.getMinutes() === 0;
  }

  function buildStartEndLines(event) {
    const start = event.start;
    const end = event.end;

    if (!start) return [];

    const startLine = 'Starts ' + formatWeekday(start) + ' ' + formatTime(start);

    if (!end) return [startLine];

    const endSameDay = sameDate(start, end);
    const endLabel = formatWeekday(end);

    if (!endSameDay && isMidnight(end)) {
      return [startLine, 'Ends ' + endLabel];
    }

    return [startLine, 'Ends ' + endLabel + ' ' + formatTime(end)];
  }

  async function fetchEvents(info, success, failure, endpoint, nonce, filters) {
    try {
      const url = new URL(endpoint);
      url.searchParams.set('start', ymd(info.start));
      url.searchParams.set('end', ymd(info.end));

      // filters from shortcode only (no UI)
      if (filters && filters.type) url.searchParams.set('type', filters.type);
      if (filters && filters.format) url.searchParams.set('format', filters.format);

      const res = await fetch(url.toString(), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': nonce || '',
          'Accept': 'application/json'
        }
      });

      const text = await res.text();

      if (text.trim().startsWith('<')) {
        console.warn('[SOLAS Calendar] Non-JSON response received. RAW:', text.slice(0, 500));
        throw new Error('Non-JSON response (HTML/PHP output detected).');
      }

      let json;
      try {
        json = JSON.parse(text);
      } catch (e) {
        console.warn('[SOLAS Calendar] JSON parse failed. RAW:', text.slice(0, 500));
        throw e;
      }

      success(json);
    } catch (err) {
      console.error('[SOLAS Calendar] Events fetch failed:', err);
      failure(err);
    }
  }

  function initCalendar() {
    const el = $('#solas-events-calendar');
    if (!el) return;

    if (el.dataset.solasCalendarInit === '1') return;
    el.dataset.solasCalendarInit = '1';

    if (!window.FullCalendar || !window.FullCalendar.Calendar) {
      console.error('[SOLAS Calendar] FullCalendar not found. Check enqueue + script order.');
      return;
    }

    const cfg = window.SolasEventsCalendar || {};
    const endpoint = cfg.endpoint || '';
    const nonce = cfg.nonce || '';
    const height = cfg.height || 'auto';

    const initialFilters = (cfg.filters || {});
    const filters = {
      type: (typeof initialFilters.type === 'string') ? initialFilters.type : '',
      format: (typeof initialFilters.format === 'string') ? initialFilters.format : ''
    };

    const initialDate = new Date();
    const initialView = (cfg.view && String(cfg.view).trim()) ? String(cfg.view).trim() : 'listYear';

    const calendar = new FullCalendar.Calendar(el, {
      initialView: initialView,
      initialDate: initialDate,
      height: height,

      customButtons: {
      monthIcon: {
        text: '',
        click: function() {
          calendar.changeView('dayGridMonth');
        }
      },
      listIcon: {
        text: '',
        click: function() {
          calendar.changeView('listMonth');
        }
      }
    },
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'monthIcon,listIcon', // 'dayGridMonth,listYear'
      },

      views: {
        listYear: {
          displayEventTime: false,
          displayEventEnd: false,
          visibleRange: function (currentDate) {
            const start = startOfMonth(currentDate);
            const end = addMonths(start, 12);
            return { start: start, end: end };
          }
        }
      },

      listDayFormat: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
      listDaySideFormat: false,
      noEventsContent: 'No upcoming events.',

      events: function (info, successCallback, failureCallback) {
        return fetchEvents(info, successCallback, failureCallback, endpoint, nonce, filters);
      },

      eventContent: function (arg) {
        const ev = arg.event;
        const xp = ev.extendedProps || {};

        const isMember = truthy(xp.memberSubmitted) || truthy(xp.member_submitted);
        const isCommercial = truthy(xp.commercial) || truthy(xp.is_commercial);

        let label = '';
        if (isCommercial) {
          label = 'Commercial';
          if (truthy(xp.featured) || truthy(xp.is_sticky)) label = 'Commercial • Featured';
        } else if (isMember) {
          label = 'Member submitted';
        }

        const startEndLines = buildStartEndLines(ev);

        const formatBits = [];
        if (xp.format) formatBits.push(String(xp.format));

        const cpdVal = (xp.cpd !== undefined && xp.cpd !== null && !isNaN(xp.cpd)) ? Number(xp.cpd) : 0;

        const descRaw = xp.excerpt || xp.description || '';
        const descTrim = String(descRaw).trim();

        const wrap = document.createElement('div');
        wrap.className = 'solas-event-row';
        wrap.style.display = 'flex';
        wrap.style.gap = '10px';
        wrap.style.alignItems = 'flex-start';

        if (isCommercial && xp.logo_thumb) {
          const img = document.createElement('img');
          img.src = String(xp.logo_thumb);
          img.alt = '';
          img.style.width = '44px';
          img.style.height = '44px';
          img.style.objectFit = 'contain';
          img.style.borderRadius = '4px';
          img.style.flex = '0 0 auto';
          wrap.appendChild(img);
        }

        const main = document.createElement('div');
        main.style.minWidth = '0';

        const titleLine = document.createElement('div');

        const a = document.createElement('a');
        a.style.fontWeight = '600';
        a.href = ev.url || '#';
        a.textContent = ev.title || '';
        titleLine.appendChild(a);

        if (label) {
          const badge = document.createElement('span');
          badge.style.marginLeft = '8px';
          badge.style.fontSize = '0.9em';
          badge.style.opacity = '0.85';
          badge.textContent = label;
          titleLine.appendChild(badge);
        }

        main.appendChild(titleLine);

        const meta = document.createElement('div');
        meta.style.opacity = '0.85';
        meta.style.fontSize = '0.95em';
        meta.style.lineHeight = '1.2';
        meta.style.marginTop = '2px';

        startEndLines.forEach(line => {
          const div = document.createElement('div');
          div.textContent = line;
          meta.appendChild(div);
        });

        if (formatBits.length) {
          const div = document.createElement('div');
          div.textContent = formatBits.join(' • ');
          meta.appendChild(div);
        }

        if (cpdVal > 0) {
          const div = document.createElement('div');
          div.style.fontSize = '0.9em';
          div.style.opacity = '0.9';
          div.textContent = 'CPD: ' + cpdVal + (cpdVal === 1 ? ' point' : ' points');
          meta.appendChild(div);
        }

        main.appendChild(meta);

        if (descTrim) {
          const d = document.createElement('div');
          d.style.marginTop = '4px';
          d.style.opacity = '0.9';
          d.textContent = descTrim;
          main.appendChild(d);
        }

        wrap.appendChild(main);
        return { domNodes: [wrap] };
      }
    });

    calendar.render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalendar);
  } else {
    initCalendar();
  }
})();