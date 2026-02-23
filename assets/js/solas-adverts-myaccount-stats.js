(function(){
  'use strict';

  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function bars(container, series, cls){
    const max = Math.max.apply(null, series.concat([1]));
    const wrap = document.createElement('div');
    wrap.className = 'solas-stats-bars';
    series.forEach(v => {
      const b = document.createElement('div');
      b.className = 'solas-stats-bar ' + cls;
      b.style.height = Math.max(2, Math.round((v / max) * 56)) + 'px';
      wrap.appendChild(b);
    });
    container.appendChild(wrap);
  }

  async function fetchStats(advertId, days){
    const url = (window.SolasAdvertsStats && SolasAdvertsStats.restUrl)
      ? SolasAdvertsStats.restUrl + '?advert_id=' + encodeURIComponent(advertId) + '&days=' + encodeURIComponent(days)
      : null;
    if(!url) throw new Error('Missing REST URL');

    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': (window.SolasAdvertsStats && SolasAdvertsStats.nonce) ? SolasAdvertsStats.nonce : ''
      }
    });
    const json = await res.json();
    if(!res.ok || !json || !json.ok){
      throw new Error((json && json.message) ? json.message : 'Failed to load stats');
    }
    return json;
  }

  function render(panel, data){
    const summary = qs('.solas-stats-panel__summary', panel);
    const chart = qs('.solas-stats-panel__chart', panel);
    chart.innerHTML = '';

    const ctr = (typeof data.ctr === 'number') ? data.ctr : 0;
    summary.textContent = 'Last ' + data.days + ' days: ' + data.total_impressions + ' impressions, ' + data.total_clicks + ' clicks (CTR ' + ctr + '%).';

    const legend = document.createElement('div');
    legend.className = 'solas-stats-legend';
    legend.innerHTML = '<span>Impressions</span><span>Clicks</span>';
    chart.appendChild(legend);

    // Simple two-row bar charts
    bars(chart, data.series.impressions, 'solas-stats-bar--impressions');
    bars(chart, data.series.clicks, 'solas-stats-bar--clicks');
  }

  function toggleRow(advertId){
    const row = qs('.solas-stats-row[data-advert-id="' + advertId + '"]');
    if(!row) return;

    const panel = qs('.solas-stats-panel', row);
    const daysSel = qs('#solas-adverts-stats-range');
    const days = daysSel ? parseInt(daysSel.value, 10) : 30;

    const isHidden = row.style.display === 'none' || row.style.display === '';
    if(isHidden){
      row.style.display = 'table-row';
      if(panel && !panel.dataset.loadedForDays){
        panel.dataset.loadedForDays = String(days);
        fetchStats(advertId, days)
          .then(d => render(panel, d))
          .catch(err => {
            const summary = qs('.solas-stats-panel__summary', panel);
            if(summary) summary.textContent = 'Could not load performance: ' + err.message;
          });
      }
    } else {
      row.style.display = 'none';
    }
  }

  function refreshOpenPanels(){
    const daysSel = qs('#solas-adverts-stats-range');
    const days = daysSel ? parseInt(daysSel.value, 10) : 30;

    qsa('.solas-stats-row').forEach(row => {
      if(row.style.display !== 'none'){
        const advertId = row.getAttribute('data-advert-id');
        const panel = qs('.solas-stats-panel', row);
        if(!panel) return;
        if(panel.dataset.loadedForDays === String(days)) return;
        panel.dataset.loadedForDays = String(days);
        fetchStats(advertId, days)
          .then(d => render(panel, d))
          .catch(err => {
            const summary = qs('.solas-stats-panel__summary', panel);
            if(summary) summary.textContent = 'Could not load performance: ' + err.message;
          });
      }
    });
  }

  document.addEventListener('click', function(e){
    const a = e.target.closest ? e.target.closest('.solas-stats-toggle') : null;
    if(!a) return;
    e.preventDefault();
    const advertId = a.getAttribute('data-advert-id');
    if(advertId) toggleRow(advertId);
  });

  document.addEventListener('change', function(e){
    if(e.target && e.target.id === 'solas-adverts-stats-range'){
      refreshOpenPanels();
    }
  });
})();
