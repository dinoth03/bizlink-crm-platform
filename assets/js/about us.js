(function () {
  function formatCount(value) {
    var num = Number(value || 0);
    if (!Number.isFinite(num) || num < 0) {
      return '0';
    }
    return num.toLocaleString('en-US') + '+';
  }

  function setStats(stats) {
    var vendorsEl = document.getElementById('aboutStatVendors');
    var customersEl = document.getElementById('aboutStatCustomers');
    var industriesEl = document.getElementById('aboutStatIndustries');
    var provincesEl = document.getElementById('aboutStatProvinces');

    if (vendorsEl) vendorsEl.textContent = formatCount(stats.vendors);
    if (customersEl) customersEl.textContent = formatCount(stats.customers);
    if (industriesEl) industriesEl.textContent = formatCount(stats.industries);
    if (provincesEl) provincesEl.textContent = formatCount(stats.provinces);
  }

  async function fetchStatsFrom(url) {
    var response = await fetch(url, {
      headers: {
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Stats API failed: ' + response.status);
    }

    var payload = await response.json();
    if (!payload || payload.success !== true || !payload.data) {
      throw new Error('Invalid stats payload');
    }

    return payload.data;
  }

  async function loadAboutStats() {
    var endpoints = [
      '../api/get_homepage_stats.php',
      '/bizlink-crm-platform/api/get_homepage_stats.php'
    ];

    for (var i = 0; i < endpoints.length; i++) {
      try {
        var stats = await fetchStatsFrom(endpoints[i]);
        setStats(stats);
        return;
      } catch (err) {
        // Try next endpoint for alternate local server roots.
      }
    }
  }

  loadAboutStats();
})();
