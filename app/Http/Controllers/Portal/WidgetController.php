<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\KbCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DomainRegistrarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WidgetController extends Controller
{
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Cache-Control' => 'public, max-age=60',
        ];
    }

    /** JSON: visible products */
    public function products(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 6), 50);

        $products = Product::where('hidden', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'description', 'type', 'price', 'setup_fee', 'billing_cycle']);

        return response()->json(['data' => $products], 200, $this->corsHeaders());
    }

    /** JSON: published announcements */
    public function announcements(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 5), 20);

        $announcements = Announcement::where('published', true)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'body', 'published_at']);

        return response()->json(['data' => $announcements], 200, $this->corsHeaders());
    }

    /** JSON: published KB articles grouped by category */
    public function kb(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 5), 30);

        $categories = KbCategory::active()
            ->with(['publishedArticles' => fn ($q) => $q->select('id', 'kb_category_id', 'title', 'slug', 'views')->limit($limit)])
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json(['data' => $categories], 200, $this->corsHeaders());
    }

    public function domainSearch(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'max:63'],
        ]);

        $driver = Setting::get('integration_registrar_driver');
        if (! $driver) {
            return response()->json(['error' => 'Domain search not configured.'], 503, $this->corsHeaders());
        }

        $sld = strtolower(trim(explode('.', $request->input('domain'))[0]));
        $tldString = Setting::get('domain_search_tlds', '.com,.net,.org,.io');
        $tlds = array_filter(array_map(fn ($t) => trim($t), explode(',', $tldString)));

        $results = [];
        foreach ($tlds as $tld) {
            $tld = '.'.ltrim($tld, '.');
            $domain = $sld.$tld;
            try {
                $check = DomainRegistrarService::checkAvailability($domain);
                $results[] = ['domain' => $domain, 'available' => $check['available'] ?? false, 'price' => $check['price'] ?? null, 'currency' => $check['currency'] ?? 'USD'];
            } catch (\Throwable) {
                $results[] = ['domain' => $domain, 'available' => null];
            }
        }

        return response()->json(['results' => $results], 200, $this->corsHeaders());
    }

    /** Serves the embeddable widget JavaScript file */
    public function widgetJs(): Response
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $js = $this->buildWidgetJs($baseUrl);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function buildWidgetJs(string $baseUrl): string
    {
        return <<<JS
(function () {
  'use strict';

  var STRATA_BASE = '{$baseUrl}';

  var CYCLE_LABEL = {
    monthly: '/mo', quarterly: '/qtr', semi_annual: '/6mo',
    annual: '/yr', biennial: '/2yr', triennial: '/3yr', one_time: ' one-time'
  };

  var TYPE_COLOR = {
    shared:    '#2563eb', reseller: '#7c3aed', vps:    '#d97706',
    dedicated: '#dc2626', domain:   '#059669', ssl:    '#0891b2', other: '#6b7280'
  };

  /* ── Inject shared CSS once ───────────────────────────────────────────── */
  function injectStyles() {
    if (document.getElementById('strata-widget-css')) return;
    var s = document.createElement('style');
    s.id = 'strata-widget-css';
    s.textContent = [
      '.strata-w { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; box-sizing: border-box; }',
      '.strata-w *, .strata-w *::before, .strata-w *::after { box-sizing: inherit; }',
      /* Glass container */
      '.strata-glass { background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.18); border-radius: 16px; padding: 20px; }',
      /* Light theme (default when host site is light) */
      '.strata-light .strata-glass { background: rgba(14,116,144,0.06); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(14,116,144,0.2); }',
      /* Grid */
      '.strata-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }',
      /* Product card */
      '.strata-card { background: rgba(255,255,255,0.1); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; transition: background .2s, border-color .2s; }',
      '.strata-light .strata-card { background: rgba(255,255,255,0.75); border: 1px solid rgba(14,116,144,0.2); }',
      '.strata-card:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.35); }',
      '.strata-light .strata-card:hover { background: rgba(255,255,255,0.95); border-color: rgba(14,116,144,0.4); }',
      /* Typography */
      '.strata-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin: 0 0 6px; }',
      '.strata-light .strata-title { color: #0c4a6e; }',
      '.strata-desc { font-size: 0.8rem; color: rgba(255,255,255,0.7); margin: 0 0 12px; flex: 1; }',
      '.strata-light .strata-desc { color: #475569; }',
      '.strata-price { font-size: 1.5rem; font-weight: 800; color: #fff; }',
      '.strata-light .strata-price { color: #0c4a6e; }',
      '.strata-cycle { font-size: 0.75rem; color: rgba(255,255,255,0.6); margin-left: 2px; }',
      '.strata-light .strata-cycle { color: #64748b; }',
      '.strata-setup { font-size: 0.7rem; color: rgba(255,255,255,0.5); margin-top: 2px; }',
      '.strata-light .strata-setup { color: #94a3b8; }',
      /* Badge */
      '.strata-badge { display: inline-block; font-size: 0.65rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: .04em; color: #fff; margin-bottom: 10px; }',
      /* Button */
      '.strata-btn { display: block; width: 100%; text-align: center; padding: 9px 0; border-radius: 8px; font-size: 0.8rem; font-weight: 600; text-decoration: none; color: #fff; background: #0ea5e9; border: none; cursor: pointer; margin-top: 14px; transition: background .2s; }',
      '.strata-btn:hover { background: #38bdf8; }',
      /* Announcement item */
      '.strata-item { padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }',
      '.strata-light .strata-item { border-bottom-color: rgba(14,116,144,0.12); }',
      '.strata-item:last-child { border-bottom: none; padding-bottom: 0; }',
      '.strata-item-title { font-size: 0.9rem; font-weight: 600; color: #fff; margin: 0 0 4px; }',
      '.strata-light .strata-item-title { color: #0c4a6e; }',
      '.strata-item-body { font-size: 0.78rem; color: rgba(255,255,255,0.65); margin: 0; white-space: pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }',
      '.strata-light .strata-item-body { color: #475569; }',
      '.strata-item-date { font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 4px; }',
      '.strata-light .strata-item-date { color: #94a3b8; }',
      /* Header */
      '.strata-hdr { font-size: 1rem; font-weight: 700; color: #fff; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }',
      '.strata-light .strata-hdr { color: #0c4a6e; }',
      '.strata-hdr-sub { font-size: 0.75rem; font-weight: 400; color: rgba(255,255,255,0.5); }',
      '.strata-light .strata-hdr-sub { color: #64748b; }',
      /* Support panel */
      '.strata-support { text-align: center; padding: 24px 16px; }',
      '.strata-support p { color: rgba(255,255,255,0.7); font-size: 0.85rem; margin: 0 0 14px; }',
      '.strata-light .strata-support p { color: #475569; }',
      /* KB */
      '.strata-kb-cat { margin-bottom: 16px; }',
      '.strata-kb-cat-name { font-size: 0.8rem; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 6px; }',
      '.strata-light .strata-kb-cat-name { color: #0369a1; }',
      '.strata-kb-link { display: block; font-size: 0.82rem; color: rgba(255,255,255,0.8); text-decoration: none; padding: 3px 0; transition: color .15s; }',
      '.strata-light .strata-kb-link { color: #334155; }',
      '.strata-kb-link:hover { color: #38bdf8; text-decoration: underline; }',
      '.strata-light .strata-kb-link:hover { color: #0369a1; }',
      /* Loading */
      '.strata-loading { text-align: center; padding: 20px; color: rgba(255,255,255,0.5); font-size: 0.8rem; }',
      '.strata-light .strata-loading { color: #94a3b8; }',
      '.strata-err { text-align: center; padding: 20px; color: #fca5a5; font-size: 0.8rem; }',
      /* Domain search */
      '.strata-ds-form { display: flex; gap: 8px; margin-bottom: 12px; }',
      '.strata-ds-input { flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-size: 0.82rem; outline: none; }',
      '.strata-light .strata-ds-input { background: #fff; border-color: #d1d5db; color: #0c4a6e; }',
      '.strata-ds-input::placeholder { color: rgba(255,255,255,0.4); }',
      '.strata-light .strata-ds-input::placeholder { color: #9ca3af; }',
      '.strata-ds-result { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 8px; margin-bottom: 6px; }',
      '.strata-ds-avail { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); }',
      '.strata-ds-taken { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); }',
      '.strata-ds-domain { font-size: 0.85rem; font-weight: 600; color: #fff; }',
      '.strata-light .strata-ds-domain { color: #0c4a6e; }',
      '.strata-ds-price { font-size: 0.7rem; color: #6ee7b7; margin-top: 2px; }',
      '.strata-ds-status-taken { font-size: 0.75rem; color: #fca5a5; }',
    ].join('\\n');
    document.head.appendChild(s);
  }

  /* ── Helpers ──────────────────────────────────────────────────────────── */
  function e(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtDate(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); }
    catch(_) { return iso; }
  }

  function fetchJson(url, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status === 200) {
        try { cb(null, JSON.parse(xhr.responseText)); }
        catch(err) { cb(err); }
      } else {
        cb(new Error('HTTP ' + xhr.status));
      }
    };
    xhr.send();
  }

  /* ── Renderers ────────────────────────────────────────────────────────── */
  function renderCatalog(el, data, base, theme) {
    var items = data.data || [];
    if (!items.length) { el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass"><p style="text-align:center;opacity:.6;font-size:.85rem">No products available.</p></div>'; return; }
    var portal = base + '/services';
    var cards = items.map(function (p) {
      var col = TYPE_COLOR[p.type] || TYPE_COLOR.other;
      var cycle = CYCLE_LABEL[p.billing_cycle] || '';
      var setup = parseFloat(p.setup_fee) > 0 ? '<div class="strata-setup">+ $' + e(p.setup_fee) + ' setup fee</div>' : '';
      var orderUrl = base + '/login';
      return '<div class="strata-card">' +
        '<span class="strata-badge" style="background:' + col + '">' + e(p.type) + '</span>' +
        '<div class="strata-title">' + e(p.name) + '</div>' +
        (p.description ? '<div class="strata-desc">' + e(p.description) + '</div>' : '<div class="strata-desc"></div>') +
        '<div style="margin-top:auto">' +
          '<span class="strata-price">$' + e(p.price) + '</span>' +
          '<span class="strata-cycle">' + e(cycle) + '</span>' +
          setup +
          '<a href="' + e(orderUrl) + '" class="strata-btn">Order Now</a>' +
        '</div>' +
      '</div>';
    }).join('');

    el.innerHTML = '<div class="strata-w ' + theme + '">' +
      '<div class="strata-hdr">Services &amp; Plans <a href="' + e(portal) + '" style="margin-left:auto;font-size:.75rem;color:#38bdf8;text-decoration:none">View all →</a></div>' +
      '<div class="strata-grid">' + cards + '</div>' +
    '</div>';
  }

  function renderAnnouncements(el, data, base, theme) {
    var items = data.data || [];
    if (!items.length) { el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass"><p style="text-align:center;opacity:.6;font-size:.85rem">No announcements.</p></div>'; return; }
    var portal = base + '/announcements';
    var rows = items.map(function (a) {
      return '<div class="strata-item">' +
        '<div class="strata-item-title">' + e(a.title) + '</div>' +
        '<p class="strata-item-body">' + e(a.body) + '</p>' +
        '<div class="strata-item-date">' + fmtDate(a.published_at) + '</div>' +
      '</div>';
    }).join('');

    el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass">' +
      '<div class="strata-hdr">Announcements <a href="' + e(portal) + '" style="margin-left:auto;font-size:.75rem;color:#38bdf8;text-decoration:none">All →</a></div>' +
      rows +
    '</div>';
  }

  function renderKb(el, data, base, theme) {
    var cats = data.data || [];
    if (!cats.length) { el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass"><p style="text-align:center;opacity:.6;font-size:.85rem">No articles found.</p></div>'; return; }
    var portal = base + '/kb';
    var sections = cats.filter(function(c){ return c.published_articles && c.published_articles.length; }).map(function (c) {
      var links = (c.published_articles || []).map(function (a) {
        return '<a href="' + e(base + '/kb/' + a.slug) + '" class="strata-kb-link">' + e(a.title) + '</a>';
      }).join('');
      return '<div class="strata-kb-cat"><div class="strata-kb-cat-name">' + e(c.name) + '</div>' + links + '</div>';
    }).join('');

    el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass">' +
      '<div class="strata-hdr">Help Center <a href="' + e(portal) + '" style="margin-left:auto;font-size:.75rem;color:#38bdf8;text-decoration:none">Browse all →</a></div>' +
      sections +
    '</div>';
  }

  function renderSupport(el, base, theme) {
    el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass">' +
      '<div class="strata-support">' +
        '<div class="strata-hdr" style="justify-content:center">Need Help?</div>' +
        '<p>Our support team is ready to assist you with any questions or issues.</p>' +
        '<a href="' + e(base + '/login') + '" class="strata-btn" style="display:inline-block;width:auto;padding:9px 24px">Open a Support Ticket</a>' +
      '</div>' +
    '</div>';
  }

  function renderDomainSearch(el, base, theme) {
    var formId = 'strata-ds-' + Math.random().toString(36).slice(2);
    el.innerHTML = '<div class="strata-w ' + theme + ' strata-glass">' +
      '<div class="strata-hdr">Find Your Domain</div>' +
      '<div class="strata-ds-form">' +
        '<input id="' + formId + '" class="strata-ds-input" type="text" placeholder="yourdomain" />' +
        '<button class="strata-btn" style="width:auto;padding:8px 16px;margin:0" onclick="(function(b,id){' +
          'var v=document.getElementById(id).value.trim().split(\'.\')[0];' +
          'if(!v)return;' +
          'var res=document.getElementById(id+\'-res\');res.innerHTML=\'<div style=\\\"padding:8px;opacity:.6;font-size:.8rem\\\">Searching\u2026</div>\';' +
          'var x=new XMLHttpRequest();x.open(\'GET\',b+\'/api/widget/domain-search?domain=\'+encodeURIComponent(v));' +
          'x.onreadystatechange=function(){if(x.readyState!==4)return;' +
            'if(x.status===200){var j=JSON.parse(x.responseText);var h=\'\';' +
              '(j.results||[]).forEach(function(r){' +
                'var cls=r.available===true?\'strata-ds-avail\':r.available===false?\'strata-ds-taken\':\'\';' +
                'var right=r.available===true?\'<a href=\\\"\'+b+\'/register\\\" class=\\\"strata-btn\\\" style=\\\"display:inline-block;width:auto;padding:5px 12px;margin:0;font-size:.72rem\\\">Register</a>\':' +
                  'r.available===false?\'<span class=\\\"strata-ds-status-taken\\\">Taken</span>\':\'\';' +
                'var price=r.available&&r.price?\'<div class=\\\"strata-ds-price\\\">$\'+r.price+\'/yr</div>\':\'\';' +
                'h+=\'<div class=\\\"strata-ds-result \'+cls+\'\\\">\'+' +
                  '\'<div><div class=\\\"strata-ds-domain\\\">\'+r.domain+\'</div>\'+price+\'</div>\'+right+\'</div>\';}' +
              ');res.innerHTML=h||\'<div style=\\\"padding:8px;opacity:.6;font-size:.8rem\\\">No results.</div>\';}' +
            'else{res.innerHTML=\'<div class=\\\"strata-err\\\">Error checking domains.</div>\';}' +
          '};x.send();' +
        '})(\''+base+'\',\''+formId+'\')">Search</button>' +
      '</div>' +
      '<div id="' + formId + '-res"></div>' +
    '</div>';
  }

  /* ── Init ─────────────────────────────────────────────────────────────── */
  function init() {
    injectStyles();
    var widgets = document.querySelectorAll('[data-strata-widget]');
    for (var i = 0; i < widgets.length; i++) {
      (function (el) {
        var type  = el.getAttribute('data-strata-widget');
        var base  = (el.getAttribute('data-strata-url') || STRATA_BASE).replace(/\\/$/, '');
        var limit = el.getAttribute('data-strata-limit') || '6';
        var themeAttr = el.getAttribute('data-strata-theme') || 'glass';
        var theme = themeAttr === 'light' ? 'strata-light' : '';

        el.innerHTML = '<div class="strata-loading">Loading…</div>';

        if (type === 'support') {
          renderSupport(el, base, theme);
          return;
        }
        if (type === 'domain-search') {
          renderDomainSearch(el, base, theme);
          return;
        }

        var endpointMap = { catalog: 'products', announcements: 'announcements', kb: 'kb' };
        var endpoint = endpointMap[type];
        if (!endpoint) { el.innerHTML = '<div class="strata-err">Unknown widget type: ' + e(type) + '</div>'; return; }

        var url = base + '/api/widget/' + endpoint + '?limit=' + encodeURIComponent(limit);

        fetchJson(url, function (err, json) {
          if (err) { el.innerHTML = '<div class="strata-w strata-glass strata-err">Could not load widget. ' + e(err.message) + '</div>'; return; }
          if (type === 'catalog')       renderCatalog(el, json, base, theme);
          else if (type === 'announcements') renderAnnouncements(el, json, base, theme);
          else if (type === 'kb')       renderKb(el, json, base, theme);
        });
      })(widgets[i]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
JS;
    }
}
