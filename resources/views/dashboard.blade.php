@extends('layouts.app')

@section('extra_head')

<!-- Leaflet Maps CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #geo-map {
        height: 380px;
        border-radius: var(--radius-card);
        z-index: 10;
        border: 1px solid var(--border-color);
    }
    .stat-card {
        background: #ffffff;
        border: 1px solid var(--border-color) !important;
        border-radius: var(--radius-card) !important;
        transition: all 0.15s ease-out;
        cursor: pointer;
    }
    .stat-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
        border-color: #d4d4d8 !important;
    }
    .stat-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }
    .stat-value {
        font-size: 1.45rem;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: #09090b;
        line-height: 1.2;
    }
</style>
@endsection

@section('page_title', 'Outbound Campaign Dashboard')

@section('content')
<div class="container-fluid px-0">

    <!-- Filters & Actions Header Bar (Stripe / Linear style) -->
    <div class="card mb-4">
        <div class="card-body py-3 px-4">
            <div class="row align-items-end g-3">
                <div class="col-md-2">
                    <label class="form-label mb-1">Start Date</label>
                    <input type="date" id="start-date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">End Date</label>
                    <input type="date" id="end-date" class="form-control">
                </div>
                @if(in_array(session('role', Auth::user()->role ?? ''), ['admin', 'manager']))
                <div class="col-md-3" id="team-member-col">
                    <label class="form-label mb-1">Team Member</label>
                    <select id="filter-user" class="form-select" onchange="onTeamMemberChange()">
                        <option value="">All Team Members</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Campaign Filter</label>
                    <select id="filter-campaign" class="form-select">
                        <option value="">All Campaigns</option>
                    </select>
                </div>
                @else
                <div class="col-md-4">
                    <label class="form-label mb-1">Campaign Filter</label>
                    <select id="filter-campaign" class="form-select">
                        <option value="">All Campaigns</option>
                    </select>
                </div>
                @endif
                <div class="{{ in_array(session('role', Auth::user()->role ?? ''), ['admin', 'manager']) ? 'col-md-1' : 'col-md-2' }}">
                    <button class="btn btn-primary w-100" id="btn-filter" onclick="loadDashboardData()" title="Apply Filter">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="{{ in_array(session('role', Auth::user()->role ?? ''), ['admin', 'manager']) ? 'col-md-1' : 'col-md-2' }}">
                    <button class="btn btn-outline-secondary w-100" id="btn-clear" onclick="clearFilters()" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid (8 Clean Cards) -->
    <div class="row row-cols-2 row-cols-md-4 row-cols-xl-8 g-3 mb-4">
        <!-- Stat 1: Total Sent -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('total')">
                <div class="stat-label">Total Sent</div>
                <div class="stat-value" id="stat-sent">0</div>
            </div>
        </div>
        <!-- Stat 2: Delivered -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('delivered')">
                <div class="stat-label">Delivered</div>
                <div class="stat-value text-emerald-600" style="color: #059669;" id="stat-delivered">0</div>
            </div>
        </div>
        <!-- Stat 3: Bounced -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('bounce')">
                <div class="stat-label">Bounced</div>
                <div class="stat-value text-rose-600" style="color: #e11d48;" id="stat-bounced">0</div>
            </div>
        </div>
        <!-- Stat 4: Undelivered -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('failed')">
                <div class="stat-label">Undelivered</div>
                <div class="stat-value text-amber-600" style="color: #d97706;" id="stat-undelivered">0</div>
            </div>
        </div>
        <!-- Stat 5: Spam -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('spam')">
                <div class="stat-label">Spam</div>
                <div class="stat-value text-zinc-700" id="stat-spam">0</div>
            </div>
        </div>
        <!-- Stat 6: Open Rate -->
        <div class="col">
            <div class="card stat-card h-100 p-3" style="cursor: default;">
                <div class="stat-label">Open Rate</div>
                <div class="stat-value text-zinc-900" id="stat-openrate">0.00%</div>
            </div>
        </div>
        <!-- Stat 7: Click Rate -->
        <div class="col">
            <div class="card stat-card h-100 p-3" style="cursor: default;">
                <div class="stat-label">Click Rate</div>
                <div class="stat-value text-zinc-900" id="stat-clickrate">0.00%</div>
            </div>
        </div>
        <!-- Stat 8: Unsubscribed -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('unsubscribed')">
                <div class="stat-label">Unsub Rate</div>
                <div class="stat-value text-zinc-900" id="stat-unsub">0.00%</div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Outbound Timeline Chart -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-activity me-1.5 text-zinc-500"></i> Dispatch Volumetric Trend</span>
                    <span class="badge bg-secondary-soft">Daily Count</span>
                </div>
                <div class="card-body">
                    <div style="height: 280px; position: relative;">
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Open Timeline Chart -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-clock-history me-1.5 text-zinc-500"></i> Engagement Open Timeline</span>
                    <span class="badge bg-secondary-soft">Hourly Engagement</span>
                </div>
                <div class="card-body">
                    <div style="height: 280px; position: relative;">
                        <canvas id="openTimelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Geolocation Tracking Map Row -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-geo-alt me-1.5 text-zinc-500"></i> Recipient Geolocation Telemetry</span>
                    <span class="badge bg-secondary-soft">Live Interactive Map</span>
                </div>
                <div class="card-body p-3">
                    <div id="geo-map"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Breakdown Tables Row -->
    <div class="row g-4 mb-4">
        <!-- Geolocation breakdown -->
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-compass me-1.5 text-zinc-500"></i> Top Geographic Open Hubs</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0" style="max-height: 380px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">City / Region / Country</th>
                                    <th class="text-center pe-4">Opens Count</th>
                                </tr>
                            </thead>
                            <tbody id="geo-table-body">
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">No geolocation logs available.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campaign Progress -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-envelope me-1.5 text-zinc-500"></i> Outbound Campaigns</span>
                    @can('bulk-mail.create')
                    <a href="{{ route('campaign_create_view') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> New Campaign
                    </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0" style="max-height: 380px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Campaign Topic</th>
                                    <th>Domain</th>
                                    <th>Dispatched</th>
                                    <th>Status</th>
                                    <th class="text-center pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="campaigns-table-body">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No outbound campaigns dispatched.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Breakdown Row -->
    <div class="row g-4 mb-4">
        <!-- Device Breakdown -->
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-laptop me-1.5 text-zinc-500"></i> Device Telemetry</span>
                    <span class="badge bg-secondary-soft">User-Agent Parser</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0" style="max-height: 250px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Device Category</th>
                                    <th class="text-center pe-4">Opens Count</th>
                                </tr>
                            </thead>
                            <tbody id="device-table-body">
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">No device logs available.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link Clicks Breakdown -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-link-45deg me-1.5 text-zinc-500"></i> Link Clicks Breakdown</span>
                    <span class="badge bg-secondary-soft">Tracked Clicks</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0" style="max-height: 250px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Target Destination URL</th>
                                    <th class="text-center">Unique</th>
                                    <th class="text-center pe-4">Total</th>
                                </tr>
                            </thead>
                            <tbody id="link-clicks-table-body">
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No clicked links recorded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recipient List Modal -->
    <div class="modal fade" id="recipientListModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-3 px-4" style="border-bottom: 1px solid var(--border-color);">
                    <h6 class="modal-title fw-semibold text-zinc-900" id="recipientModalTitle">Recipient Emails</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.75rem;"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive border-0" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Recipient Email</th>
                                    <th>Salesforce ID</th>
                                    <th>Campaign Subject</th>
                                    <th class="pe-4">Sent Timestamp</th>
                                </tr>
                            </thead>
                            <tbody id="recipientModalTableBody">
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('extra_scripts')
<!-- Chart.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Leaflet Maps JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let myChart = null;
    let myMap = null;
    let markerGroup = null;

    const countryCoords = {
        "US": [37.0902, -95.7129],
        "GB": [55.3781, -3.4360],
        "IN": [20.5937, 78.9629],
        "DE": [51.1657, 10.4515],
        "CA": [56.1304, -106.3468],
        "AU": [-25.2744, 133.7751],
        "FR": [46.2276, 2.2137],
        "JP": [36.2048, 138.2529],
        "BR": [-14.2350, -51.9253],
        "RU": [61.5240, 105.3188],
        "ZA": [-30.5595, 22.9375]
    };

    function initMap() {
        myMap = L.map('geo-map', { zoomControl: true }).setView([20, 0], 1);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            maxZoom: 10,
            attribution: '&copy; CartoDB'
        }).addTo(myMap);
        markerGroup = L.layerGroup().addTo(myMap);
    }

    function loadCampaignDropdown() {
        const userSelect = document.getElementById('filter-user');
        let url = '/api/campaigns/dropdown';
        if (userSelect && userSelect.value) {
            url += '?team_member_id=' + encodeURIComponent(userSelect.value);
        }

        fetch(url)
            .then(res => res.json())
            .then(campaigns => {
                const select = document.getElementById('filter-campaign');
                const currentValue = select.value;
                select.innerHTML = '<option value="">All Campaigns</option>';
                campaigns.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.subject;
                    select.appendChild(opt);
                });
                select.value = currentValue;
            })
            .catch(err => console.error("Error loading campaigns dropdown:", err));
    }

    function onTeamMemberChange() {
        loadCampaignDropdown();
        loadDashboardData();
    }

    function clearFilters() {
        document.getElementById('start-date').value = '';
        document.getElementById('end-date').value = '';
        document.getElementById('filter-campaign').value = '';
        const userSelect = document.getElementById('filter-user');
        if (userSelect) userSelect.value = '';
        loadCampaignDropdown();
        loadDashboardData();
    }

    function loadDashboardData() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const campaignId = document.getElementById('filter-campaign').value;
        const userSelect = document.getElementById('filter-user');

        let url = '/api/dashboard/stats';
        const params = [];
        if (startDate) params.push(`start_date=${encodeURIComponent(startDate)}`);
        if (endDate) params.push(`end_date=${encodeURIComponent(endDate)}`);
        if (campaignId) params.push(`campaign_id=${encodeURIComponent(campaignId)}`);
        if (userSelect && userSelect.value) params.push(`team_member_id=${encodeURIComponent(userSelect.value)}`);
        if (params.length > 0) url += '?' + params.join('&');

        fetch(url)
            .then(res => res.json())
            .then(data => {
                // Populate team members dropdown if present
                if (data.team_members && userSelect && userSelect.options.length <= 1) {
                    const curr = userSelect.value;
                    userSelect.innerHTML = '<option value="">All Team Members</option>';
                    data.team_members.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = `${u.username} (${u.role.charAt(0).toUpperCase() + u.role.slice(1)})`;
                        userSelect.appendChild(opt);
                    });
                    userSelect.value = curr;
                }

                document.getElementById('stat-sent').textContent = data.counters.total_sent.toLocaleString();
                document.getElementById('stat-delivered').textContent = data.counters.delivered.toLocaleString();
                document.getElementById('stat-bounced').textContent = data.counters.bounced.toLocaleString();
                document.getElementById('stat-undelivered').textContent = data.counters.undelivered.toLocaleString();
                document.getElementById('stat-spam').textContent = data.counters.spam_marked.toLocaleString();
                document.getElementById('stat-openrate').textContent = data.counters.open_rate.toFixed(2) + '%';
                document.getElementById('stat-clickrate').textContent = data.counters.click_rate.toFixed(2) + '%';
                document.getElementById('stat-unsub').textContent = data.counters.unsubscribe_rate.toFixed(2) + '%';

                renderChart(data.chart_data);
                renderTimelineChart(data.timeline_data);
                renderGeoData(data.geo_data);
                renderCampaignList(data.recent_campaigns);
                renderDeviceBreakdown(data.device_breakdown);
                renderLinkClicks(data.link_clicks);
            })
            .catch(err => console.error("Error loading stats:", err));
    }

    function renderDeviceBreakdown(devices) {
        const tbody = document.getElementById('device-table-body');
        if (!devices || devices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">No device logs available.</td></tr>';
            return;
        }
        let html = '';
        devices.forEach(d => {
            html += `
                <tr>
                    <td class="ps-4 fw-medium text-zinc-900">${escapeHtml(d.device_type)}</td>
                    <td class="text-center text-zinc-600 pe-4">${d.count.toLocaleString()}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderLinkClicks(links) {
        const tbody = document.getElementById('link-clicks-table-body');
        if (!links || links.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No clicked links recorded.</td></tr>';
            return;
        }
        let html = '';
        links.forEach(l => {
            html += `
                <tr>
                    <td class="ps-4 font-monospace text-zinc-800" style="font-size: 0.78rem; word-break: break-all;"><a href="${escapeHtml(l.url)}" target="_blank" class="text-decoration-none text-zinc-900">${escapeHtml(l.url)}</a></td>
                    <td class="text-center text-zinc-700 fw-medium">${l.unique_clicks.toLocaleString()}</td>
                    <td class="text-center text-zinc-600 pe-4">${l.click_count.toLocaleString()}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderChart(chartData) {
        const labels = chartData.map(d => d.send_date);
        const counts = chartData.map(d => d.count);

        if (myChart) {
            myChart.destroy();
        }

        const ctx = document.getElementById('volumeChart').getContext('2d');
        myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Emails Sent',
                    data: counts,
                    borderColor: '#09090b',
                    backgroundColor: 'rgba(9, 9, 11, 0.04)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#09090b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#09090b',
                        padding: 8,
                        titleFont: { family: 'Inter', size: 11 },
                        bodyFont: { family: 'Inter', size: 11 },
                        cornerRadius: 6
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#71717a', font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f4f4f5' },
                        ticks: { precision: 0, color: '#71717a', font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });
    }

    let timelineChart = null;
    function renderTimelineChart(timelineData) {
        const labels = timelineData.map(d => d.open_time);
        const counts = timelineData.map(d => d.count);

        if (timelineChart) {
            timelineChart.destroy();
        }

        const ctx = document.getElementById('openTimelineChart').getContext('2d');
        timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Unique Opens',
                    data: counts,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#059669',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#09090b',
                        padding: 8,
                        titleFont: { family: 'Inter', size: 11 },
                        bodyFont: { family: 'Inter', size: 11 },
                        cornerRadius: 6
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#71717a', font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f4f4f5' },
                        ticks: { precision: 0, color: '#71717a', font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });
    }

    function renderGeoData(geoData) {
        markerGroup.clearLayers();
        const tableBody = document.getElementById('geo-table-body');
        if (geoData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">No geolocation logs available.</td></tr>';
            return;
        }

        let html = '';
        geoData.forEach(row => {
            const locName = `${row.city}, ${row.region}, ${row.country}`;
            html += `
                <tr>
                    <td class="ps-4">
                        <i class="bi bi-geo-alt text-zinc-400 me-2"></i>
                        <span class="text-zinc-900 fw-medium">${locName}</span>
                    </td>
                    <td class="text-center pe-4"><span class="badge bg-secondary-soft px-2 py-1 fw-medium">${row.open_count}</span></td>
                </tr>
            `;

            const code = row.country;
            if (countryCoords[code]) {
                const baseCoord = countryCoords[code];
                const jitterLat = (Math.random() - 0.5) * 1.5;
                const jitterLng = (Math.random() - 0.5) * 1.5;
                const finalCoord = [baseCoord[0] + jitterLat, baseCoord[1] + jitterLng];

                L.circleMarker(finalCoord, {
                    radius: 6,
                    fillColor: '#09090b',
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.85
                })
                .bindPopup(`<b>${row.city}, ${row.country}</b><br>Opens: ${row.open_count}`)
                .addTo(markerGroup);
            }
        });
        tableBody.innerHTML = html;
    }

    function renderCampaignList(campaigns) {
        const tableBody = document.getElementById('campaigns-table-body');
        if (campaigns.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No campaigns found.</td></tr>';
            return;
        }

        let html = '';
        campaigns.forEach(c => {
            let statusBadge = '';
            if (c.status === 'completed') {
                statusBadge = '<span class="badge badge-success-soft">Completed</span>';
            } else if (c.status === 'sending') {
                statusBadge = '<span class="badge badge-info-soft"><span class="spinner-grow spinner-grow-sm me-1" role="status"></span>Sending</span>';
            } else if (c.status === 'queued') {
                statusBadge = '<span class="badge badge-warning-soft">Queued</span>';
            } else {
                statusBadge = '<span class="badge bg-secondary-soft">Draft</span>';
            }

            const sendProgress = c.total_requested > 0 
                ? Math.round(((c.total_approved + c.total_blocked) / c.total_requested) * 100) 
                : 0;

            const dateStr = new Date(c.created_at).toLocaleDateString() + ' ' + new Date(c.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            html += `
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold text-zinc-900">${escapeHtml(c.subject)}</div>
                        <small class="text-zinc-500" style="font-size: 0.74rem;">Created by ${c.username}</small>
                    </td>
                    <td><span class="badge bg-secondary-soft font-monospace">${escapeHtml(c.sending_domain)}</span></td>
                    <td class="text-zinc-500" style="font-size: 0.78rem;">${dateStr}</td>
                    <td>
                        <div class="mb-1">${statusBadge}</div>
                        <div class="progress" style="height: 4px; width: 80px; background-color: #f4f4f5;">
                            <div class="progress-bar" role="progressbar" style="width: ${sendProgress}%; background-color: #09090b;" aria-valuenow="${sendProgress}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </td>
                    <td class="text-center pe-4">
                        <a href="/campaign/${c.id}" class="btn btn-outline-secondary btn-xs">
                            <i class="bi bi-shield-check"></i> Audit
                        </a>
                    </td>
                </tr>
            `;
        });
        tableBody.innerHTML = html;
    }

    function openRecipientListModal(statusType) {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const campaignId = document.getElementById('filter-campaign').value;
        const userSelect = document.getElementById('filter-user');
        
        let url = `/api/dashboard/recipient-list?status=${statusType}`;
        if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
        if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
        if (campaignId) url += `&campaign_id=${encodeURIComponent(campaignId)}`;
        if (userSelect && userSelect.value) url += `&team_member_id=${encodeURIComponent(userSelect.value)}`;
        
        const labels = {
            'total': 'Total Attempted Dispatches',
            'delivered': 'Delivered Recipient Inboxes',
            'bounce': 'Bounced Recipient Addresses',
            'failed': 'Undelivered (SMTP Errors)',
            'spam': 'Spam Complaints',
            'unsubscribed': 'Unsubscribed Recipients'
        };
        
        document.getElementById('recipientModalTitle').innerText = labels[statusType] || 'Recipient Emails';
        const tbody = document.getElementById('recipientModalTableBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Fetching email list...</td></tr>';
        
        const myModal = new bootstrap.Modal(document.getElementById('recipientListModal'));
        myModal.show();
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No recipients matching this status.</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(r => {
                    const dateStr = r.sent_at 
                        ? new Date(r.sent_at).toLocaleString() 
                        : 'N/A';
                    
                    html += `
                        <tr>
                            <td class="ps-4 fw-medium text-zinc-900">${escapeHtml(r.email)}</td>
                            <td><code class="text-zinc-600">${escapeHtml(r.salesforce_record_id || 'N/A')}</code></td>
                            <td class="text-zinc-700">${escapeHtml(r.campaign_subject)}</td>
                            <td class="pe-4 text-zinc-500">${dateStr}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            })
            .catch(err => {
                console.error("Error loading recipients modal:", err);
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Failed to load emails.</td></tr>';
            });
    }

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.onload = function() {
        initMap();
        loadCampaignDropdown();
        loadDashboardData();
        setInterval(loadDashboardData, 10000);
    };
</script>
@endsection
