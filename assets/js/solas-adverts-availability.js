(function(){
  'use strict';

  function qs(sel, root){ return (root||document).querySelector(sel); }

  function ymd(d){
    const pad=n=> String(n).padStart(2,'0');
    return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
  }

  function addDays(d, n){
    const x = new Date(d.getTime());
    x.setDate(x.getDate()+n);
    return x;
  }

  function daysInclusive(a, b){
    const ms = 24*60*60*1000;
    const start = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
    const end = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
    if (end < start) return 0;
    return Math.floor((end - start) / ms) + 1;
  }

  function init(root){
    const calEl = qs('.solas-avail-calendar', root);
    const slotSel = qs('.solas-avail-slot', root);
    const result = qs('.solas-avail-result', root);

    if (!calEl || !window.FullCalendar) return;

    const formUrl = root.getAttribute('data-form-url') || '/advertise/';
    const restRoot = (window.wpApiSettings && wpApiSettings.root) ? wpApiSettings.root : '/wp-json/';
    const nonce = root.getAttribute('data-rest-nonce') || '';

    async function fetchDays(slot, startStr, endStr){
      const url = restRoot + 'solas/v1/adverts/availability?slot='+encodeURIComponent(slot)+
        '&start='+encodeURIComponent(startStr)+'&end='+encodeURIComponent(endStr);
      const res = await fetch(url, { headers: { 'X-WP-Nonce': nonce }});
      return await res.json();
    }

    async function checkRange(slot, startStr, endStr){
      const url = restRoot + 'solas/v1/adverts/check-range?slot='+encodeURIComponent(slot)+
        '&start='+encodeURIComponent(startStr)+'&end='+encodeURIComponent(endStr);
      const res = await fetch(url, { headers: { 'X-WP-Nonce': nonce }});
      return await res.json();
    }

    function renderAvailable(slot, startStr, endStr, duration, blocks){
      const jump = new URL(formUrl, window.location.origin);
      jump.searchParams.set('slot', slot);
      jump.searchParams.set('start_date', startStr);
      jump.searchParams.set('end_date', endStr);

      result.innerHTML =
        '<div class="solas-avail-card">' +
          '<div class="solas-avail-title"><strong>Selected:</strong> '+startStr+' → '+endStr+'</div>'+
          '<div class="solas-avail-sub">'+duration+' days. <strong>Billed as '+blocks+' × 30‑day block'+(blocks===1?'':'s')+'</strong>.</div>'+
          '<div class="solas-avail-actions">' +
            '<a class="solas-btn solas-btn--primary" href="'+jump.toString()+'">Proceed to booking</a>'+
            '<button type="button" class="solas-btn solas-btn--ghost solas-avail-clear">Clear</button>'+
          '</div>'+
        '</div>';

      const clearBtn = qs('.solas-avail-clear', result);
      if (clearBtn) clearBtn.addEventListener('click', function(){
        result.textContent = '';
        calendar.unselect();
      });
    }

    function renderUnavailable(slot, startStr, endStr, duration, blocks, nextStart){
      const nextEnd = nextStart ? ymd(addDays(new Date(nextStart+'T00:00:00'), duration-1)) : null;

      let html = '<div class="solas-avail-card solas-avail-card--warn">' +
        '<div class="solas-avail-title"><strong>Selected:</strong> '+startStr+' → '+endStr+'</div>'+
        '<div class="solas-avail-sub">'+duration+' days. <strong>Billed as '+blocks+' × 30‑day block'+(blocks===1?'':'s')+'</strong>.</div>'+
        '<div class="solas-avail-msg">That range is fully booked for this slot.</div>';

      if (nextStart && nextEnd){
        const jump = new URL(formUrl, window.location.origin);
        jump.searchParams.set('slot', slot);
        jump.searchParams.set('start_date', nextStart);
        jump.searchParams.set('end_date', nextEnd);

        html += '<div class="solas-avail-msg"><strong>Next available:</strong> '+nextStart+' → '+nextEnd+'</div>'+
          '<div class="solas-avail-actions">' +
            '<a class="solas-btn solas-btn--primary" href="'+jump.toString()+'">Use next available</a>'+
            '<button type="button" class="solas-btn solas-btn--ghost solas-avail-clear">Clear</button>'+
          '</div>';
      } else {
        html += '<div class="solas-avail-actions">' +
          '<button type="button" class="solas-btn solas-btn--ghost solas-avail-clear">Clear</button>'+
          '</div>';
      }

      html += '</div>';
      result.innerHTML = html;

      const clearBtn = qs('.solas-avail-clear', result);
      if (clearBtn) clearBtn.addEventListener('click', function(){
        result.textContent = '';
        calendar.unselect();
      });
    }

    const today = new Date(); today.setHours(0,0,0,0);

    const calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'dayGridMonth',
      height: 'auto',
      fixedWeekCount: false,
      showNonCurrentDates: true,
      selectable: true,
      selectMirror: true,
      unselectAuto: false,
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
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'monthIcon,listIcon', // '' },
      validRange: { start: ymd(today) },
      events: async function(info, success, failure){
        try{
          const slot = slotSel ? slotSel.value : (root.getAttribute('data-slot') || 'header_banner');
          const data = await fetchDays(slot, info.startStr, info.endStr);
          success((data && data.days) ? data.days : []);
        }catch(e){ failure(e); }
      },
      select: async function(selInfo){
        const startDateRaw = selInfo.start;
        const startDate = new Date(startDateRaw.getTime()); startDate.setHours(0,0,0,0);
        if (startDate < today){
          result.innerHTML = '<div class="solas-avail-card solas-avail-card--warn"><div class="solas-avail-msg">Please choose dates from today onwards.</div></div>';
          calendar.unselect();
          return;
        }
        const slot = slotSel ? slotSel.value : (root.getAttribute('data-slot') || 'header_banner');
        const endExclusive = selInfo.end;
        const endDate = addDays(endExclusive, -1);

        const startStr = ymd(startDate);
        const endStr = ymd(endDate);

        try{
          const data = await checkRange(slot, startStr, endStr);
          if (!data || data.error){
            result.textContent = (data && data.error) ? data.error : 'Unable to check that date range.';
            return;
          }

          const duration = parseInt(data.duration_days || daysInclusive(startDate, endDate), 10);
          const blocks = parseInt(data.billing_blocks_30 || Math.ceil(duration/30), 10);

          if (data.available){
            renderAvailable(slot, startStr, endStr, duration, blocks);
          } else {
            renderUnavailable(slot, startStr, endStr, duration, blocks, data.next_available_start || null);
          }
        }catch(e){
          result.textContent = 'Unable to check that date range right now.';
        }
      }
    });

    calendar.render();

    if (slotSel){
      slotSel.addEventListener('change', function(){
        calendar.refetchEvents();
        result.textContent = '';
        calendar.unselect();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.solas-adverts-availability').forEach(init);
  });
})();
