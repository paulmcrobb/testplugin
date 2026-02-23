/* SOLAS Events Calendar bootstrap (FullCalendar global build)
 * Looks for .solas-events-calendar elements with data-solas-events-calendar JSON.
 */
(function(){
  function parseConfig(el){
    var raw = el.getAttribute('data-solas-events-calendar') || '';
    try { return JSON.parse(raw); } catch(e){}
    try { return JSON.parse(raw.replace(/&quot;/g,'"')); } catch(e){}
    try { return JSON.parse(decodeURIComponent(raw)); } catch(e){}
    return null;
  }

  function buildEventsFetcher(restUrl, type, format){
    return function(fetchInfo, successCallback, failureCallback){
      var url = new URL(restUrl, window.location.origin);
      url.searchParams.set('start', fetchInfo.startStr.slice(0,10));
      url.searchParams.set('end', fetchInfo.endStr.slice(0,10));
      if (type) url.searchParams.set('type', type);
      if (format) url.searchParams.set('format', format);
      fetch(url.toString(), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
          // Expect either array or {events:[...]}
          if (Array.isArray(data)) return successCallback(data);
          if (data && Array.isArray(data.events)) return successCallback(data.events);
          successCallback([]);
        })
        .catch(function(err){ failureCallback(err); });
    };
  }

  function initOne(el){
    if (!window.FullCalendar || !window.FullCalendar.Calendar) return;
    var cfg = parseConfig(el);
    if (!cfg || !cfg.id) return;

    var view = cfg.view || 'listYear';
    var cal = new window.FullCalendar.Calendar(el, {
      initialView: view,
      height: (cfg.height && cfg.height !== 'auto') ? cfg.height : 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,listYear'
      },
      buttonText: {
        today: 'Today',
        month: 'Month',
        list: 'List'
      },
      events: buildEventsFetcher(cfg.rest, cfg.type, cfg.format)
    });

    cal.render();
  }

  function initAll(){
    var els = document.querySelectorAll('.solas-events-calendar[data-solas-events-calendar]');
    for (var i=0;i<els.length;i++) initOne(els[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
