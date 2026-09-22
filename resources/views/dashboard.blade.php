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
                <div
                    class="{{ in_array(session('role', Auth::user()->role ?? ''), ['admin', 'manager']) ? 'col-md-1' : 'col-md-2' }}">
                    <button class="btn btn-primary w-100" id="btn-filter" onclick="loadDashboardData()"
                        title="Apply Filter">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div
                    class="{{ in_array(session('role', Auth::user()->role ?? ''), ['admin', 'manager']) ? 'col-md-1' : 'col-md-2' }}">
                    <button class="btn btn-outline-secondary w-100" id="btn-clear" onclick="clearFilters()"
                        title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid (10 Clean Cards in 2 Balanced Rows of 5) -->
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 g-3 mb-3">
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
        <!-- Stat 3: Opened Emails -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('opened')">
                <div class="stat-label">Opened Emails</div>
                <div class="stat-value text-indigo-600" style="color: #4f46e5;" id="stat-opened-count">0</div>
            </div>
        </div>
        <!-- Stat 4: Bounced -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('bounce')">
                <div class="stat-label">Bounced</div>
                <div class="stat-value text-rose-600" style="color: #e11d48;" id="stat-bounced">0</div>
            </div>
        </div>
        <!-- Stat 5: Undelivered -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('failed')">
                <div class="stat-label">Undelivered</div>
                <div class="stat-value text-amber-600" style="color: #d97706;" id="stat-undelivered">0</div>
            </div>
        </div>
    </div>

    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 g-3 mb-4">
        <!-- Stat 6: Spam -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('spam')">
                <div class="stat-label">Spam</div>
                <div class="stat-value text-zinc-700" id="stat-spam">0</div>
            </div>
        </div>
        <!-- Stat 7: Number of Unsubscribe -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('unsubscribed')">
                <div class="stat-label">Number of Unsubscribe</div>
                <div class="stat-value text-rose-600" style="color: #e11d48;" id="stat-unsubscribed-count">0</div>
            </div>
        </div>
        <!-- Stat 8: Open Rate -->
        <div class="col">
            <div class="card stat-card h-100 p-3" style="cursor: default;">
                <div class="stat-label">Open Rate</div>
                <div class="stat-value text-zinc-900" id="stat-openrate">0.00%</div>
            </div>
        </div>
        <!-- Stat 9: Click Rate -->
        <div class="col">
            <div class="card stat-card h-100 p-3" style="cursor: default;">
                <div class="stat-label">Click Rate</div>
                <div class="stat-value text-zinc-900" id="stat-clickrate">0.00%</div>
            </div>
        </div>
        <!-- Stat 10: Unsubscribed Rate -->
        <div class="col">
            <div class="card stat-card h-100 p-3" onclick="openRecipientListModal('unsubscribed')">
                <div class="stat-label">Unsub Rate</div>
                <div class="stat-value text-zinc-900" id="stat-unsub">0.00%</div>
            </div>
        </div>
    </div>


    <!-- Geolocation Tracking Map Row -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-geo-alt me-1.5 text-zinc-500"></i> Email Delivery & Telemetry Map</span>
                    <span class="badge bg-secondary-soft">Global Dispatch Locations</span>
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
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-compass me-1.5 text-zinc-500"></i> Top Delivery & Engagement Hubs</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0" style="max-height: 380px;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">City / Region / Country</th>
                                    <th class="text-center">Delivered</th>
                                    <th class="text-center pe-4">Opens</th>
                                </tr>
                            </thead>
                            <tbody id="geo-table-body">
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No delivery geolocation logs available.
                                    </td>
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
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-envelope me-1.5 text-zinc-500"></i> Outbound
                        Campaigns</span>
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
                                    <td colspan="5" class="text-center text-muted py-4">No outbound campaigns
                                        dispatched.</td>
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
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-laptop me-1.5 text-zinc-500"></i> Device
                        Telemetry</span>
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
                    <span class="fw-semibold text-zinc-900"><i class="bi bi-link-45deg me-1.5 text-zinc-500"></i> Link
                        Clicks Breakdown</span>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        style="font-size: 0.75rem;"></button>
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
    let myMap = null;
    let markerGroup = null;

    const countryCoords = {
        // North America
        "US": [37.0902, -95.7129],
        "USA": [37.0902, -95.7129],
        "UNITED STATES": [37.0902, -95.7129],
        "UNITED STATES OF AMERICA": [37.0902, -95.7129],
        "CA": [56.1304, -106.3468],
        "CAN": [56.1304, -106.3468],
        "CANADA": [56.1304, -106.3468],
        "MX": [23.6345, -102.5528],
        "MEX": [23.6345, -102.5528],
        "MEXICO": [23.6345, -102.5528],

        // Europe
        "GB": [55.3781, -3.4360],
        "GBR": [55.3781, -3.4360],
        "UK": [55.3781, -3.4360],
        "UNITED KINGDOM": [55.3781, -3.4360],
        "GREAT BRITAIN": [55.3781, -3.4360],
        "DE": [51.1657, 10.4515],
        "DEU": [51.1657, 10.4515],
        "GERMANY": [51.1657, 10.4515],
        "FR": [46.2276, 2.2137],
        "FRA": [46.2276, 2.2137],
        "FRANCE": [46.2276, 2.2137],
        "NL": [52.1326, 5.2913],
        "NLD": [52.1326, 5.2913],
        "NETHERLANDS": [52.1326, 5.2913],
        "ES": [40.4637, -3.7492],
        "ESP": [40.4637, -3.7492],
        "SPAIN": [40.4637, -3.7492],
        "IT": [41.8719, 12.5674],
        "ITA": [41.8719, 12.5674],
        "ITALY": [41.8719, 12.5674],
        "BE": [50.5039, 4.4699],
        "BEL": [50.5039, 4.4699],
        "BELGIUM": [50.5039, 4.4699],
        "IE": [53.1424, -7.6921],
        "IRL": [53.1424, -7.6921],
        "IRELAND": [53.1424, -7.6921],
        "CH": [46.8182, 8.2275],
        "CHE": [46.8182, 8.2275],
        "SWITZERLAND": [46.8182, 8.2275],
        "SE": [60.1282, 18.6435],
        "SWE": [60.1282, 18.6435],
        "SWEDEN": [60.1282, 18.6435],
        "NO": [60.4720, 8.4689],
        "NOR": [60.4720, 8.4689],
        "NORWAY": [60.4720, 8.4689],
        "DK": [56.2639, 9.5018],
        "DNK": [56.2639, 9.5018],
        "DENMARK": [56.2639, 9.5018],
        "PL": [51.9194, 19.1451],
        "POL": [51.9194, 19.1451],
        "POLAND": [51.9194, 19.1451],
        "RU": [61.5240, 105.3188],
        "RUS": [61.5240, 105.3188],
        "RUSSIA": [61.5240, 105.3188],

        // Asia / Pacific
        "IN": [20.5937, 78.9629],
        "IND": [20.5937, 78.9629],
        "INDIA": [20.5937, 78.9629],
        "JP": [36.2048, 138.2529],
        "JPN": [36.2048, 138.2529],
        "JAPAN": [36.2048, 138.2529],
        "AU": [-25.2744, 133.7751],
        "AUS": [-25.2744, 133.7751],
        "AUSTRALIA": [-25.2744, 133.7751],
        "NZ": [-40.9006, 174.8860],
        "NZL": [-40.9006, 174.8860],
        "NEW ZEALAND": [-40.9006, 174.8860],
        "SG": [1.3521, 103.8198],
        "SGP": [1.3521, 103.8198],
        "SINGAPORE": [1.3521, 103.8198],
        "AE": [23.4241, 53.8478],
        "ARE": [23.4241, 53.8478],
        "UAE": [23.4241, 53.8478],
        "UNITED ARAB EMIRATES": [23.4241, 53.8478],
        "PK": [30.3753, 69.3451],
        "PAK": [30.3753, 69.3451],
        "PAKISTAN": [30.3753, 69.3451],
        "VN": [14.0583, 108.2772],
        "VNM": [14.0583, 108.2772],
        "VIETNAM": [14.0583, 108.2772],
        "VIET NAM": [14.0583, 108.2772],
        "TH": [15.8700, 100.9925],
        "THA": [15.8700, 100.9925],
        "THAILAND": [15.8700, 100.9925],
        "MY": [4.2105, 101.9758],
        "MYS": [4.2105, 101.9758],
        "MALAYSIA": [4.2105, 101.9758],
        "ID": [-0.7893, 113.9213],
        "IDN": [-0.7893, 113.9213],
        "INDONESIA": [-0.7893, 113.9213],
        "PH": [12.8797, 121.7740],
        "PHL": [12.8797, 121.7740],
        "PHILIPPINES": [12.8797, 121.7740],
        "CN": [35.8617, 104.1954],
        "CHN": [35.8617, 104.1954],
        "CHINA": [35.8617, 104.1954],
        "SA": [23.8859, 45.0792],
        "SAU": [23.8859, 45.0792],
        "SAUDI ARABIA": [23.8859, 45.0792],
        "LA": [19.8563, 102.4955],
        "LAOS": [19.8563, 102.4955],
        "LAO PEOPLE'S DEMOCRATIC REPUBLIC": [19.8563, 102.4955],

        // South America
        "BR": [-14.2350, -51.9253],
        "BRA": [-14.2350, -51.9253],
        "BRAZIL": [-14.2350, -51.9253],
        "AR": [-38.4161, -63.6167],
        "ARG": [-38.4161, -63.6167],
        "ARGENTINA": [-38.4161, -63.6167],
        "CO": [4.5709, -74.2973],
        "COL": [4.5709, -74.2973],
        "COLOMBIA": [4.5709, -74.2973],
        "CL": [-35.6751, -71.5430],
        "CHL": [-35.6751, -71.5430],
        "CHILE": [-35.6751, -71.5430],

        // Africa
        "ZA": [-30.5595, 22.9375],
        "ZAF": [-30.5595, 22.9375],
        "SOUTH AFRICA": [-30.5595, 22.9375],
        "KE": [-0.0236, 37.9062],
        "KEN": [-0.0236, 37.9062],
        "KENYA": [-0.0236, 37.9062],
        "ZW": [-19.0154, 29.1549],
        "ZWE": [-19.0154, 29.1549],
        "ZIMBABWE": [-19.0154, 29.1549],
        "NG": [9.0820, 8.6753],
        "NGA": [9.0820, 8.6753],
        "NIGERIA": [9.0820, 8.6753],
        "EG": [26.8206, 30.8025],
        "EGY": [26.8206, 30.8025],
        "EGYPT": [26.8206, 30.8025]
    };

    const cityCoords = {
        "SAN FRANCISCO": [37.7749, -122.4194],
        "NEW YORK": [40.7128, -74.0060],
        "BROOKLYN": [40.6782, -73.9442],
        "LOS ANGELES": [34.0522, -118.2437],
        "CHICAGO": [41.8781, -87.6298],
        "LONDON": [51.5074, -0.1278],
        "PARIS": [48.8566, 2.3522],
        "FRANKFURT": [50.1109, 8.6821],
        "BERLIN": [52.5200, 13.4050],
        "TORONTO": [43.6532, -79.3832],
        "SYDNEY": [-33.8688, 151.2093],
        "MELBOURNE": [-37.8136, 144.9631],
        "TOKYO": [35.6762, 139.6503],
        "MUMBAI": [19.0760, 72.8777],
        "DELHI": [28.6139, 77.2090],
        "BENGALURU": [12.9716, 77.5946],
        "SAO PAULO": [-23.5505, -46.6333],
        "SÃO PAULO": [-23.5505, -46.6333],
        "SHARJAH": [25.3463, 55.4209],
        "DUBAI": [25.2048, 55.2708],
        "VIENTIANE": [17.9757, 102.6331],
        "TUY HOA": [13.0882, 109.3090],
        "TUY HÒA": [13.0882, 109.3090],
        "ANTWERPEN": [51.2194, 4.4025],
        "CHANDLER": [33.3062, -111.8413],
    };

    function resolveLocationCoords(city, country) {
        if (city) {
            const cleanCity = city.trim().toUpperCase();
            if (cityCoords[cleanCity]) {
                const base = cityCoords[cleanCity];
                const jitterLat = (Math.random() - 0.5) * 0.15;
                const jitterLng = (Math.random() - 0.5) * 0.15;
                return [base[0] + jitterLat, base[1] + jitterLng];
            }
        }
        if (country) {
            const cleanCountry = country.trim().toUpperCase();
            if (countryCoords[cleanCountry]) {
                const base = countryCoords[cleanCountry];
                const jitterLat = (Math.random() - 0.5) * 1.8;
                const jitterLng = (Math.random() - 0.5) * 1.8;
                return [base[0] + jitterLat, base[1] + jitterLng];
            }
        }
        return null;
    }

    function initMap() {
        myMap = L.map('geo-map', { zoomControl: true, scrollWheelZoom: false }).setView([20, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors'
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
                if (document.getElementById('stat-opened-count')) {
                    document.getElementById('stat-opened-count').textContent = (data.counters.opened ?? data.counters.unique_opens ?? 0).toLocaleString();
                }
                document.getElementById('stat-bounced').textContent = data.counters.bounced.toLocaleString();
                document.getElementById('stat-undelivered').textContent = data.counters.undelivered.toLocaleString();
                document.getElementById('stat-spam').textContent = data.counters.spam_marked.toLocaleString();
                if (document.getElementById('stat-unsubscribed-count')) {
                    document.getElementById('stat-unsubscribed-count').textContent = (data.counters.unsubscribed || 0).toLocaleString();
                }
                document.getElementById('stat-openrate').textContent = data.counters.open_rate.toFixed(2) + '%';
                document.getElementById('stat-clickrate').textContent = data.counters.click_rate.toFixed(2) + '%';
                document.getElementById('stat-unsub').textContent = data.counters.unsubscribe_rate.toFixed(2) + '%';

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

    function renderGeoData(geoData) {
        markerGroup.clearLayers();
        const tableBody = document.getElementById('geo-table-body');
        if (!geoData || geoData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No delivery geolocation logs available.</td></tr>';
            return;
        }

        let html = '';
        geoData.forEach(row => {
            const city = row.city ? String(row.city).trim() : '';
            const region = row.region ? String(row.region).trim() : '';
            const country = row.country ? String(row.country).trim() : '';

            const parts = [city, region, country].filter(p => p && p !== 'Global' && p !== 'null');
            const locName = parts.length > 0 ? parts.join(', ') : (country || 'Unknown Hub');

            const delivered = parseInt(row.delivered_count) || (parseInt(row.total_count) || 0);
            const opens = parseInt(row.open_count) || 0;

            html += `
                <tr>
                    <td class="ps-4">
                        <i class="bi bi-geo-alt text-zinc-400 me-2"></i>
                        <span class="text-zinc-900 fw-medium">${escapeHtml(locName)}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success-soft text-success px-2 py-1 fw-semibold">${delivered}</span>
                    </td>
                    <td class="text-center pe-4">
                        <span class="badge bg-secondary-soft text-zinc-800 px-2 py-1 fw-medium">${opens}</span>
                    </td>
                </tr>
            `;

            const coords = resolveLocationCoords(city, country);
            if (coords) {
                const radius = Math.min(14, Math.max(5, Math.sqrt(delivered + opens) * 2.2));
                const marker = L.circleMarker(coords, {
                    radius: radius,
                    fillColor: '#09090b',
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.85
                });

                marker.bindPopup(`
                    <div style="min-width: 140px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 2px 0;">
                        <div style="font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; color: #09090b;">${escapeHtml(locName)}</div>
                        <div style="font-size: 0.78rem; color: #16a34a; margin-bottom: 2px;">
                            <i class="bi bi-check-circle me-1"></i> Delivered: <b>${delivered}</b>
                        </div>
                        <div style="font-size: 0.78rem; color: #2563eb;">
                            <i class="bi bi-envelope-open me-1"></i> Opened: <b>${opens}</b>
                        </div>
                    </div>
                `);

                marker.addTo(markerGroup);
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
            'opened': 'Opened Recipient Inboxes',
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