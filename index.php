<?php
require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/app/Db.php';
require_once __DIR__ . '/app/Helper.php';

$db = new Db();

// Defaults / fallbacks
$sheltersData = [];
$hotlinesData = [];
$disastersData = [];
$hazardZonesData = [];
$hazardPolygonsData = [];
$currentLocation = ['latitude' => 13.55683558, 'longitude' => 124.19982281];
$statsData = [
    'total_shelters' => 0,
    'available_shelters' => 0,
    'total_capacity' => 0,
    'current_occupancy' => 0,
    'full_shelters' => 0,
    'maintenance_shelters' => 0,
    'closed_shelters' => 0,
];
$routingServiceUrl = $_ENV['ROUTING_SERVICE_URL'] ?? '';

try {
    if ($db->isConnected()) {
        $sheltersSql = 'SELECT s.shelter_id, s.shelter_name, s.barangay, s.owner_name, s.full_address, s.description, s.contact_person, s.contact_number, s.contact_email, s.shelter_type, s.shelter_status, s.capacity, s.current_occupancy, s.is_full, s.typhoon_zone, s.flood_zone, s.landslide_zone, s.liquefaction_zone, s.storm_surge_zone, s.elevation, s.latitude, s.longitude, s.building_material_type, s.building_condition, s.water_supply, s.electricity, s.road_condition, s.estimated_travel_time, s.near_main_road, s.is_safe_shelter, s.is_active, d.name AS disaster_name, d.type AS disaster_type, d.severity AS disaster_severity FROM shelters s LEFT JOIN disasters d ON s.current_disaster_id = d.disaster_id WHERE s.is_active = 1 ORDER BY s.shelter_name';
        $sheltersStmt = $db->prepare($sheltersSql);
        $sheltersStmt->execute();
        $sheltersData = $sheltersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sheltersData as &$shelter) {
            try {
                $imageSql = "SELECT image_path FROM shelter_images WHERE shelter_id = ? ORDER BY uploaded_at ASC";
                $imageStmt = $db->prepare($imageSql);
                $imageStmt->execute([$shelter['shelter_id']]);
                $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($images as &$image) {
                    $image['image_path'] = getImageUrl($image['image_path']);
                }
                $shelter['shelter_images'] = $images;
            } catch (Exception $e) {
                $shelter['shelter_images'] = [];
            }
        }

        // Emergency hotlines
        $hotlinesSql = 'SELECT * FROM emergency_hotlines WHERE is_active = 1 ORDER BY priority_order ASC';
        $hotlinesStmt = $db->prepare($hotlinesSql);
        $hotlinesStmt->execute();
        $hotlinesData = $hotlinesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Disasters
        $disastersSql = 'SELECT * FROM disasters ORDER BY created_at DESC';
        $disastersStmt = $db->prepare($disastersSql);
        $disastersStmt->execute();
        $disastersData = $disastersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Hazard zones (centroids / barangay flags)
        $hazardZonesSql = 'SELECT hazard_zone_id, barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description, is_active FROM barangay_hazard_zones WHERE is_active = 1 ORDER BY barangay';
        $hazardZonesStmt = $db->prepare($hazardZonesSql);
        $hazardZonesStmt->execute();
        $hazardZonesData = $hazardZonesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Hazard polygons (for overlays)
        $hazardPolygonsSql = 'SELECT polygon_id, name, hazard_type, coordinates, fill_color, stroke_color, fill_opacity, weight FROM hazard_polygons ORDER BY hazard_type, name';
        $hazardPolygonsStmt = $db->prepare($hazardPolygonsSql);
        $hazardPolygonsStmt->execute();
        $hazardPolygonsData = $hazardPolygonsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Current location
        $locStmt = $db->prepare('SELECT latitude, longitude FROM mycurrentlocation ORDER BY created_at DESC LIMIT 1');
        $locStmt->execute();
        $locRow = $locStmt->fetch(PDO::FETCH_ASSOC);
        if ($locRow) {
            $currentLocation = $locRow;
        }

        // Stats
        $statsSql = "SELECT COUNT(*) as total_shelters, SUM(CASE WHEN shelter_status = 'Available' THEN 1 ELSE 0 END) as available_shelters, SUM(capacity) as total_capacity, SUM(current_occupancy) as current_occupancy, SUM(CASE WHEN shelter_status = 'Full' THEN 1 ELSE 0 END) as full_shelters, SUM(CASE WHEN shelter_status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_shelters, SUM(CASE WHEN shelter_status = 'Closed' THEN 1 ELSE 0 END) as closed_shelters FROM shelters WHERE is_active = 1";
        $statsStmt = $db->prepare($statsSql);
        $statsStmt->execute();
        $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($statsRow) {
            $statsData = $statsRow;
        }

        // Cache to JSON for offline use (without images for smaller size)
        writeJsonFile(
            'shelters.json',
            array_map(function ($s) {
                $sData = $s;
                unset($sData['shelter_images']);
                return $sData;
            }, $sheltersData)
        );
        writeJsonFile('hotlines.json', $hotlinesData);
        writeJsonFile('disasters.json', $disastersData);
        writeJsonFile('mylocation.json', $currentLocation);
        writeJsonFile('hazard_polygons.json', $hazardPolygonsData);
    } else {
        throw new Exception('DB offline');
    }
} catch (Exception $e) {
    // Offline fallbacks
    $sheltersData = readJsonFile('shelters.json');
    $hotlinesData = readJsonFile('hotlines.json');
    $disastersData = readJsonFile('disasters.json');
    $hazardZonesData = readJsonFile('hazard_zones.json'); // optional offline cache
    $hazardPolygonsData = readJsonFile('hazard_polygons.json');
    $loc = readJsonFile('mylocation.json');
    if (!empty($loc)) {
        $currentLocation = $loc;
    }
    
    // Calculate stats from shelters data if available
    if (!empty($sheltersData) && is_array($sheltersData)) {
        $statsData = [
            'total_shelters' => count($sheltersData),
            'available_shelters' => count(array_filter($sheltersData, function($s) { return isset($s['shelter_status']) && $s['shelter_status'] === 'Available'; })),
            'total_capacity' => array_sum(array_column($sheltersData, 'capacity')),
            'current_occupancy' => array_sum(array_column($sheltersData, 'current_occupancy'))
        ];
    }
}
?>

<body class="bg-light m-0 p-0 h-100 overflow-hidden">

    <!-- Google-Maps style sidenav -->
    <div id="gmSideNav" class="position-fixed top-0 start-0 h-100 d-flex flex-column bg-white shadow-sm"
        style="width: 320px; transform: translateX(0); transition: transform .3s ease; z-index: 1050;">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="gmSideNavToggle">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div>
                <div class="fw-semibold">Evacuation Shelter</div>
            </div>
        </div>
        <div class="p-3 border-bottom position-relative">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="gmSideNavSearch"
                    placeholder="Search shelter / barangay">
            </div>
            <div id="gmSearchSuggestions"
                class="list-group position-absolute top-100 start-0 w-100 shadow-sm"
                style="z-index: 2000; max-height: 240px; overflow-y: auto; display: none;">
    </div>
    </div>
        <div class="p-3 d-grid gap-2 flex-grow-1 overflow-auto">
            <!-- Quick Stats -->
            <div class="card mb-2">
                <div class="card-header py-2"><small class="fw-semibold text-uppercase">Quick Stats</small></div>
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold" id="totalSheltersStat">—</div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-success" id="availableSheltersStat">—</div>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold" id="totalCapacityStat">—</div>
                                <small class="text-muted">Capacity</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold" id="currentOccupancyStat">—</div>
                                <small class="text-muted">Occupied</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quality Indicators -->
            <div class="card mb-2">
                <div class="card-header py-2"><small class="fw-semibold text-uppercase">Quality Indicators</small></div>
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Map Load:</small>
                                <span class="fw-semibold" id="metricMapLoad">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Shelter Fetch:</small>
                                <span class="fw-semibold" id="metricShelterFetch">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Route Calc:</small>
                                <span class="fw-semibold" id="metricRouteCalc">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Connectivity:</small>
                                <span class="fw-semibold" id="metricOnline">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Device:</small>
                                <span class="fw-semibold" id="metricDevice">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Service Worker:</small>
                                <span class="fw-semibold" id="metricSW">—</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center p-1">
                                <small class="text-muted">Offline Cache:</small>
                                <span class="fw-semibold" id="metricIDB">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button class="btn btn-outline-primary d-flex justify-content-between align-items-center"
                id="gmBtnMyLocation">
                <span><i class="bi bi-geo-alt-fill me-2"></i>My Location</span>
                <i class="bi bi-arrow-return-left"></i>
            </button>
            <div class="card">
                <div class="card-header py-2"><small class="fw-semibold text-uppercase">Layers</small></div>
                <div class="list-group list-group-flush">
                    <label class="list-group-item d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input hazard-checkbox" data-hazard="flood">
                        <span class="legend-color" style="background-color: #b39ddb;"></span> Flood
                    </label>
                    <label class="list-group-item d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input hazard-checkbox" data-hazard="landslide">
                        <span class="legend-color" style="background-color: #ff922b;"></span> Landslide
                    </label>
                    <label class="list-group-item d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input hazard-checkbox" data-hazard="liquefaction">
                        <span class="legend-color" style="background-color: #ffd43b;"></span> Liquefaction
                    </label>
                    <label class="list-group-item d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input hazard-checkbox" data-hazard="storm_surge">
                        <span class="legend-color" style="background-color: #d32f2f;"></span> Storm Surge
                    </label>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2"><small class="fw-semibold text-uppercase">Shortcuts</small></div>
                <div class="list-group list-group-flush">
                    <button class="list-group-item list-group-item-action text-start" id="gmBtnDisasters">
                        <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Disasters
                    </button>
                    <button class="list-group-item list-group-item-action text-start" id="gmBtnHotlines">
                        <i class="bi bi-telephone me-2 text-primary"></i>Hotlines
                    </button>
                    <button class="list-group-item list-group-item-action text-start" id="gmBtnHazards">
                        <i class="bi bi-shield-exclamation me-2 text-danger"></i>Hazard Panel
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <small class="fw-semibold text-uppercase">Disasters</small>
                    <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle"></i></span>
                </div>
                <div class="list-group list-group-flush" id="gmDisastersList">
                    <div class="list-group-item text-muted small">No data</div>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <small class="fw-semibold text-uppercase">Hotlines</small>
                    <span class="badge text-bg-primary"><i class="bi bi-telephone"></i></span>
                </div>
                <div class="list-group list-group-flush" id="gmHotlinesList">
                    <div class="list-group-item text-muted small">No data</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toggle chip for closed sidenav -->
    <button id="gmSideNavOpen" class="btn btn-primary position-fixed top-0 end-0 m-3 shadow"
        style="z-index:1049; display:none;">
        <i class="bi bi-list"></i>
    </button>

    <!-- Shelter Detail Panel -->
    <div class="detail-panel" id="detailPanel">
        <div class="detail-panel-header">
            <h5 class="mb-0">Shelter Details</h5>
            <button class="close-btn" id="closeDetailPanel"><i class="bi bi-x"></i></button>
        </div>
        <div class="detail-panel-content" id="detailContent">
            <!-- Content will be dynamically loaded here -->
        </div>
    </div>

    <!-- Overlay for detail panel -->
    <div class="overlay" id="detailOverlay"></div>

    <div class="container-fluid p-0 h-100">
        <div class="position-relative h-100" style="height: 100vh;">
            <!-- Map full height -->
            <div id="map" style="height: 100vh; width: 100%;"></div>

            <!-- Map Theme Switcher (lower-right) -->
            <div class="map-theme-switcher card shadow-sm p-2 position-absolute bottom-0 end-0 m-3"
                id="mapThemeSwitcher">
                <div class="btn-group" role="group" aria-label="Map themes">
                    <button class="btn btn-outline-secondary theme-btn active" data-theme="street" title="Street View">
                        <i class="bi bi-map"></i>
                    </button>
                    <button class="btn btn-outline-secondary theme-btn" data-theme="satellite" title="Satellite View">
                        <i class="bi bi-globe"></i>
                    </button>
                    <button class="btn btn-outline-secondary theme-btn" data-theme="hybrid" title="Hybrid View">
                        <i class="bi bi-layers"></i>
                    </button>
                    <button class="btn btn-outline-secondary theme-btn" data-theme="google" title="Google Road">
                        <i class="bi bi-geo"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Minimal hidden containers to avoid JS errors -->
    <div class="d-none">
        <span id="totalShelters"></span>
        <span id="availableShelters"></span>
        <span id="totalCapacity"></span>
        <span id="currentOccupancy"></span>
        <div id="sheltersTableBody"></div>
        <div id="disastersContainer"></div>
        <div id="hotlinesContainer"></div>
        <input id="shelterSearch" />
        <input id="mainShelterSearch" />
    </div>

    <script>
        const sheltersData = <?php echo json_encode($sheltersData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const hotlinesData = <?php echo json_encode($hotlinesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const disastersData = <?php echo json_encode($disastersData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const hazardZonesData = <?php echo json_encode($hazardZonesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const hazardPolygonsData = <?php echo json_encode($hazardPolygonsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const currentLocation = <?php echo json_encode($currentLocation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const statsData = <?php echo json_encode($statsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const routingServiceUrl = "<?php echo $routingServiceUrl; ?>";

        const performanceMetrics = {
            mapLoadMs: null,
            sheltersFetchMs: null,
            lastRouteMs: null,
            online: navigator.onLine,
            device: (window.innerWidth <= 768 || 'ontouchstart' in window) ? 'Mobile' : 'Desktop',
            sw: '—',
            idb: '—'
        };

        function updateQualityIndicators() {
            const setText = (id, val, suffix = '') => {
                const el = document.getElementById(id);
                if (el) {
                    if (typeof val === 'number') {
                        el.textContent = val.toLocaleString() + suffix;
                    } else {
                        el.textContent = (val ?? '—');
                    }
                }
            };
            setText('metricMapLoad', performanceMetrics.mapLoadMs, ' ms');
            setText('metricShelterFetch', performanceMetrics.sheltersFetchMs, ' ms');
            setText('metricRouteCalc', performanceMetrics.lastRouteMs, ' ms');
            setText('metricOnline', performanceMetrics.online ? 'Online' : 'Offline');
            setText('metricDevice', performanceMetrics.device);
            setText('metricSW', performanceMetrics.sw);
            setText('metricIDB', performanceMetrics.idb);
        }

        const _mapLoadStart = performance.now();
        const defaultLatLng = [Number(currentLocation.latitude) || 13.55683558, Number(currentLocation.longitude) ||
            124.19982281
        ];
        const map = L.map('map', {
            zoomControl: true
        }).setView(defaultLatLng, 13);
        
        // Track map load time
        let mapLoadMeasured = false;
        const measureMapLoad = () => {
            if (!mapLoadMeasured) {
                mapLoadMeasured = true;
                performanceMetrics.mapLoadMs = Math.round(performance.now() - _mapLoadStart);
                updateQualityIndicators();
            }
        };
        map.once('load', measureMapLoad);
        map.whenReady(measureMapLoad);
        setTimeout(() => {
            if (!mapLoadMeasured) {
                measureMapLoad();
            }
        }, 2000);

        // Base layers
        const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        const satellite = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            });
        const hybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google'
        });
        const googleRoad = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '© Google'
        });

        const themeButtons = document.querySelectorAll('#mapThemeSwitcher .theme-btn');
        themeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                themeButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const theme = btn.dataset.theme;
                if (theme === 'street') {
                    map.addLayer(street);
                    map.removeLayer(satellite);
                    map.removeLayer(hybrid);
                    map.removeLayer(googleRoad);
                } else if (theme === 'satellite') {
                    map.addLayer(satellite);
                    map.removeLayer(street);
                    map.removeLayer(hybrid);
                    map.removeLayer(googleRoad);
                } else if (theme === 'hybrid') {
                    map.addLayer(hybrid);
                    map.removeLayer(street);
                    map.removeLayer(satellite);
                    map.removeLayer(googleRoad);
                } else if (theme === 'google') {
                    map.addLayer(googleRoad);
                    map.removeLayer(street);
                    map.removeLayer(satellite);
                    map.removeLayer(hybrid);
                }
            });
        });

       // ===============================
        // HAZARD LAYERS & SHELTER MARKERS
        // ===============================
        const hazardLayers = [];
        const shelterMarkers = new Map();

        // Hazard severity colors
        const hazardSeverityColors = {
            flood: {
                high: '#6a1b9a',    // dark purple
                medium: '#9c27b0',  // medium purple
                low: '#d1c4e9'      // light purple
            },
            landslide: {
                high: '#b35a00',    // dark orange
                medium: '#ff922b',  // medium orange
                low: '#ffc88f'      // light orange
            },
            liquefaction: {
                high: '#f57f17',    // dark yellow
                medium: '#ffd54f',  // medium yellow
                low: '#fff9c4'      // light yellow
            },
            storm_surge: {
                high: '#b71c1c',    // dark red
                medium: '#f44336',  // medium red
                low: '#ffcdd2'      // light red
            }
        }

        const STATIC_HAZARDS = {
            landslide: [
                {
                    name: "",
                    severity: "high",
                    coords: [
                        [13.6038386, 124.2116132],
                        [13.6034423, 124.2012706],
                        [13.5961636, 124.2013779],
                        [13.596101, 124.2052188],
                        [13.5949956, 124.2081585],
                        [13.5960802, 124.211184],
                        [13.5988332, 124.2161193]
                    ]
                },
                {
                    name: "",
                    severity: "high",
                    coords: [
                        [13.6029337, 124.2167419],
                        [13.5975112, 124.2168707],
                        [13.5972609, 124.2194456],
                        [13.5980951, 124.2220634],
                        [13.6005144, 124.2245954],
                        [13.6018492, 124.227342],
                        [13.6011401, 124.2332214],
                        [13.6020161, 124.2353672],
                        [13.6058952, 124.2352814]
                    ]
                }
            ],

            flood: [
                {
                    name: "Flood Area Gogon",
                    severity: "high",
                    coords: [
                        [13.5808536, 124.226457],
                        [13.5791433, 124.2239679],
                        [13.5773912, 124.2237747],
                        [13.5771618, 124.2252124],
                        [13.5790181, 124.2275298],
                        [13.5808536, 124.226457]
                        ]
                    },
                {
                    name: "CatSU",
                    severity: "high",
                    coords: [
                        [13.5841708, 124.2078162],
                        [13.5840039, 124.2078162],
                        [13.5832217, 124.2091036],
                        [13.5817617, 124.2111019],
                        [13.5816013, 124.2114144],
                        [13.5816587, 124.2114425],
                        [13.5817043, 124.2114479],
                        [13.5817409, 124.2113996],
                        [13.5818849, 124.2111287],
                        [13.5820811, 124.2108578],
                        [13.5824631, 124.2103482],
                        [13.5832008, 124.2093236],
                        [13.5841708, 124.2078162]
                        ]
                    },
                {
                    name: "San Isidro",
                    severity: "high",
                    coords: [
                        [13.5802666, 124.2177982],
                        [13.5801624, 124.2177660],
                        [13.5793906, 124.2217035],
                        [13.5796200, 124.2217357],
                        [13.5802666, 124.2177982]
                        ]
                    },
                {
                    name: "Pajo ",
                    severity: "high",
                    coords: [
                        [13.56594, 124.213687],
                        [13.5662112, 124.2165623],
                        [13.566842, 124.2187439],
                        [13.5697533, 124.2192022],
                        [13.5718302, 124.2188881],
                        [13.5727271, 124.2158411],
                        [13.571851, 124.2139957],
                        [13.56594, 124.213687]
                        ]
                    },
                {
                    name: "Igang",
                    severity: "high",
                    coords: [
                        [13.5362814, 124.2005794],
                        [13.533778, 124.2067163],
                        [13.5384718, 124.202575],
                        [13.5362814, 124.2005794]
                        ]
                    },
                {
                    name: "Igang",
                    severity: "medium",
                    coords: [
                        [13.5406599, 124.207107],
                        [13.539815, 124.2055835],
                        [13.5365606, 124.2090811],
                        [13.5404773, 124.2072947],
                        [13.5406599, 124.207107]
                        ]
                    },
                {
                    name: "Igang",
                    severity: "low",
                    coords: [
                        [13.539815, 124.2055835],
                        [13.5392645, 124.205079],
                        [13.5365606, 124.2090811],
                        [13.539815, 124.2055835]
                        ]
                    }
            
                ],

            liquefaction: [
                {
                    name: "Cavinitan",
                    severity: "high",
                    coords: [
                        [13.584374, 124.1992638],
                        [13.5855837, 124.2013667],
                        [13.5858213, 124.2007694],
                        [13.5863161, 124.1987026],
                        [13.5878538, 124.1951766],
                        [13.586591,   124.198038],
                        [13.5799146, 124.1954999],
                        [13.5785985, 124.1959442],
                        [13.5775535, 124.196131],
                        [13.5830703, 124.2019031],
                        [13.5836126, 124.1999075],
                        [13.584374,  124.1992638]
                        ]
                    },
                {
                    name: "Brgy Palnab",
                    severity: "high",
                    coords: [
                        [13.5756516, 124.2215165],
                        [13.5753231, 124.2203471],
                        [13.5747025, 124.221487],
                        [13.5739985, 124.2225518],
                        [13.5748329, 124.2231527],
                        [13.5751484, 124.2223024],
                        [13.5756516, 124.2215165] 
                        ]
                    },
                {
                    name: "Gogon Centro",
                    severity: "high",
                    coords: [
                        [13.577491, 124.2215982],
                        [13.5771885, 124.2216841],
                        [13.5773763, 124.2221132],
                        [13.5773346, 124.222596],
                        [13.5768131, 124.2233792],
                        [13.5750089, 124.226673],
                        [13.5770634, 124.2273489],
                        [13.5779186, 124.2268446],
                        [13.5788259, 124.2261687],
                        [13.5804319, 124.2264799],
                        [13.5812975, 124.2258683],
                        [13.5814331, 124.2254177],
                        [13.5817251, 124.2251495],
                        [13.581673, 124.224495],
                        [13.5791433, 124.2239679],
                        [13.577491, 124.2215982]
                        ]
                    }, 
                {
                    name: "Gogon Sirangan",
                    severity: "high",
                    coords: [
                        [13.5804319, 124.2264799],
                        [13.5788259, 124.2261687],
                        [13.57765, 124.2270249],
                        [13.5775561, 124.2271751],
                        [13.5774519, 124.2274648],
                        [13.5781715, 124.2277545],
                        [13.5786512, 124.2285591],
                        [13.5803928, 124.2285484],
                        [13.5808308, 124.226585],
                        [13.5804319, 124.2264799]
                        ]
                    },
                {
                    name: "Calatagan Proper",
                    severity: "medium",
                    coords: [
                        [13.5901429, 124.2141683],
                        [13.5842194, 124.2151983],
                        [13.5843863, 124.2175279],
                        [13.5845531, 124.2198575],
                        [13.5858046, 124.2199111],
                        [13.5901429, 124.2141683]
                        ]
                    },
                {
                    name: "Calatagan Proper",
                    severity: "high",
                    coords: [
                        [13.5857514, 124.2199418],
                        [13.5858046, 124.2199124],
                        [13.5851293, 124.219883],
                        [13.5845531, 124.2198588],
                        [13.5846095, 124.22043],
                        [13.5857514, 124.2199418]
                        ]
                    },
                {
                    name: "Calatagan Tibang",
                    severity: "medium",
                    coords: [
                        [13.5955815, 124.2122792],
                        [13.5958784, 124.2122126],
                        [13.5943298, 124.2116608],
                        [13.5932609, 124.2109159],
                        [13.5926509, 124.21148],
                        [13.5902602, 124.2124271],
                        [13.5901429, 124.2141683],
                        [13.5955815, 124.2122792]
                        ]
                    },
                {
                    name: "San Isidro Village",
                    severity: "medium",
                    coords: [
                         [13.5724713, 124.2083997],
                        [13.5703646, 124.1994089],
                        [13.569405, 124.2033357],
                        [13.5694051, 124.2082924],
                        [13.5709903, 124.209215],
                        [13.5724713, 124.2083997]
                        ]
                    }
                 ],
                 
            storm_surge: [
                {
                    name: "Brgy Pajo",
                    severity: "high",
                    coords: [
                        [13.5710763, 124.2227886],
                        [13.568323, 124.2205087],
                        [13.566842, 124.2187439],
                        [13.5654757, 124.220482],
                        [13.5644328, 124.22325],
                        [13.5672592, 124.2258464],
                        [13.5704088, 124.2246877],
                        [13.5710763, 124.2227886]
                        ]
                    },
                {
                    name: "Brgy Palnab",
                    severity: "high",
                    coords: [
                        [13.5710763, 124.2227886],
                        [13.5704088, 124.2246877],
                        [13.5753229, 124.2277484],
                        [13.5764735, 124.2261565],
                        [13.5752858, 124.2252126],
                        [13.5737853, 124.2242258],
                        [13.5719302, 124.2231102],
                        [13.5710763, 124.2227886]
                        ]
                    },
                {
                    name: "",
                    severity: "high",
                    coords: [
                        [13.5825688, 124.2334991],
                        [13.5769163, 124.2303449],
                        [13.575619, 124.2291861],
                        [13.5752624, 124.228478],
                        [13.5753229, 124.2277699],
                        [13.5743924, 124.2287999],
                        [13.5816302, 124.2343145],
                        [13.5825688, 124.2334991]
                        ]
                    },
                {
                    name: "",
                    severity: "high",
                    coords: [
                        [13.5822978, 124.2338676],
                        [13.5827983, 124.235434],
                        [13.5827358, 124.2367214],
                        [13.5817763, 124.2381376],
                        [13.5820475, 124.2390389],
                        [13.5837995, 124.2395968],
                        [13.5853638, 124.2397041],
                        [13.5862607, 124.2398972],
                        [13.5882004, 124.2426867],
                        [13.5896395, 124.2451114],
                        [13.5911204, 124.2481798],
                        [13.593498, 124.2563767],
                        [13.5935397, 124.2600245],
                        [13.5903278, 124.24833],
                        [13.5864484, 124.2417854],
                        [13.5815886, 124.2401976],
                        [13.5804623, 124.2387599],
                        [13.5816302, 124.2343145],
                        [13.5823603, 124.2336959],
                        [13.5825481, 124.23464]
                        ]
                    },
                {
                    name: "Brgy Pajo",
                    severity: "high",
                    coords: [
                        [13.566842, 124.2187439],
                        [13.5665032, 124.217678],
                        [13.5662112, 124.2165623],
                        [13.56594, 124.213687],
                        [13.5633118, 124.2145453],
                        [13.5644328, 124.22325],
                        [13.5654603, 124.2203388],
                        [13.566842, 124.2187439]
                        ]
                    },
                {
                    name: "Brgy Pajo Baguio",
                    severity: "high",
                    coords: [
                        [13.5658623, 124.2137224],
                        [13.5634635, 124.2137009],
                        [13.5631716, 124.2127354],
                        [13.5623371, 124.2114694],
                        [13.5610439, 124.210418],
                        [13.5596403, 124.2094309],
                        [13.5587642, 124.2090554],
                        [13.5581176, 124.2087657],
                        [13.555823, 124.2079289],
                        [13.5503794, 124.2068196],
                        [13.5492942, 124.2069236],
                        [13.5520892, 124.2081129],
                        [13.5553432, 124.2087443],
                        [13.5594109, 124.2101819],
                        [13.5622478, 124.2126925],
                        [13.5633118, 124.2145453],
                        [13.563784, 124.2143162],
                        [13.5634635, 124.2137009]
                        ]
                    },
                {
                    name: "Brgy Antipolo",
                    severity: "high",
                    coords: [
                        [13.5503794, 124.2068196],
                        [13.5465202, 124.2059613],
                        [13.5445384, 124.2052961],
                        [13.542494, 124.2055107],
                        [13.5405539, 124.2068196],
                        [13.5407, 124.2072487],
                        [13.5503794, 124.2068196]
                        ]
                    },
                {
                    name: "Brgy Igang",
                    severity: "high",
                    coords: [
                        [13.5383072, 124.208214],
                        [13.5350737, 124.2098555],
                        [13.5336863, 124.2098018],
                        [13.5316523, 124.2105207],
                        [13.5298895, 124.2112502],
                        [13.5351467, 124.2100701],
                        [13.5373997, 124.2094585],
                        [13.5383072, 124.208214]
                        ]
                    },
                {
                    name: "Brgy Talisoy",
                    severity: "high",
                    coords: [
                        [13.5189149, 124.203412],
                        [13.5174023, 124.2062122],
                        [13.5170477, 124.2081434],
                        [13.5181117, 124.2096669],
                        [13.5187063, 124.2093987],
                        [13.5189879, 124.2087764],
                        [13.5194365, 124.2084653],
                        [13.5203232, 124.2084116],
                        [13.520198, 124.2075104],
                        [13.5198329, 124.2073066],
                        [13.5171415, 124.2080683],
                        [13.5175275, 124.2071886],
                        [13.5175692, 124.2063839],
                        [13.5184038, 124.2065126],
                        [13.5186959, 124.2060942],
                        [13.5189149, 124.203412]
                        ]
                    },
                {
                    name: "Brgy Magnesia Del Norte",
                    severity: "high",
                    coords: [
                        [13.5327808, 124.1757228],
                        [13.5310441, 124.1794457],
                        [13.532374, 124.1779383],
                        [13.5330364, 124.176903],
                        [13.5329477, 124.1767957],
                        [13.5327913, 124.1765167],
                        [13.5327808, 124.1757228] 
                        ]
                    },
                {
                    name: "Brgy Marilima",
                    severity: "high",
                    coords: [
                        [13.5375077, 124.167571],
                        [13.5377737, 124.1663962],
                        [13.534222, 124.1717767],
                        [13.5350982, 124.1709505],
                        [13.5375077, 124.167571] 
                        ]
                    },
                {
                    name: "Brgy Batag",
                    severity: "high",
                    coords: [
                        [13.542738, 124.1603272],
                        [13.5427171, 124.1599571],
                        [13.5380807, 124.1643291],
                        [13.538258, 124.164769],
                        [13.5386127, 124.1650533],
                        [13.5394159, 124.1643989],
                        [13.5399517, 124.163794],
                        [13.5405867, 124.1631462],
                        [13.541867, 124.1618507],
                        [13.542738, 124.1603272]
                        ]
                    },
                {
                    name: "Brgy Balite",
                    severity: "high",
                    coords: [
                        [13.5502912, 124.1551532],
                        [13.5511465, 124.152825],
                        [13.546505, 124.158565],
                        [13.5472247, 124.159037],
                        [13.5487371, 124.1569235],
                        [13.5488727, 124.1560544],
                        [13.5498427, 124.1557969],
                        [13.5502912, 124.1551532]
                        ]
                    }
                ]    
            };
            
        // ===============================
        // GENERATE HAZARD POLYGONS
        // ===============================
        Object.keys(STATIC_HAZARDS).forEach(hType => {
            STATIC_HAZARDS[hType].forEach(h => {

                const severityColor =
                    hazardSeverityColors[hType][h.severity] || '#888';

                const layer = L.polygon(h.coords, {
                    color: severityColor,
                    weight: 2,
                    fillColor: severityColor,
                    fillOpacity: 0.4
                }).bindPopup(`${h.name} (${hType.replace('_',' ')}, ${h.severity})`);

                hazardLayers.push({
                    type: hType,
                    layer,
                    severity: h.severity
                });
            });
        });

        // ===============================
        // HELPER FUNCTION TO UPDATE HAZARD COORDINATES
        // ===============================
        function updateHazardCoordinates(type, newCoords) {
            hazardLayers.forEach(h => {
                if (h.type === type) {
                    h.layer.setLatLngs(newCoords); // Update polygon
                    const center = h.layer.getBounds().getCenter();
                    h.marker.setLatLng(center);     // Update marker
                }
            });
        }

        let activeRoutes = [];
        const shelterStatusColors = {
            'Available': '#28a745',
            'Full': '#6c757d',
            'Under Maintenance': '#f0ad4e',
            'Closed': '#6c757d'
        };
        const shelterIconFactory = (color = '#28a745') => L.divIcon({
            html: `
                <svg width="26" height="32" viewBox="0 0 26 32" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 0C7.2 0 2.5 4.68 2.5 10.46c0 7.63 9.21 14.93 10.06 15.61.27.22.61.34.94.34s.68-.12.94-.34c.85-.68 10.06-8 10.06-15.61C24.5 4.68 19.8 0 14 0h-1z" fill="${color}" stroke="#ffffff" stroke-width="1.5" />
                    <circle cx="13" cy="11" r="4" fill="#ffffff" />
                </svg>
            `,
            className: 'shelter-pin',
            iconSize: [26, 32],
            iconAnchor: [13, 32]
        });

        function statusBadge(status) {
            const cls = status === 'Available' ? 'success' :
                status === 'Full' ? 'danger' :
                status === 'Under Maintenance' ? 'warning' : 'secondary';
            return `<span class="badge bg-${cls}">${status || 'Unknown'}</span>`;
        }

        function isPointInPolygon(point, polygonCoords) {
    let x = point.lng, y = point.lat;
    let inside = false;
    for (let i = 0, j = polygonCoords.length - 1; i < polygonCoords.length; j = i++) {
        let xi = polygonCoords[i][1], yi = polygonCoords[i][0];
        let xj = polygonCoords[j][1], yj = polygonCoords[j][0];

        let intersect = ((yi > y) != (yj > y))
            && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1e-10) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}


function routePassesHazard(routeCoords) {
    const detected = [];
    routeCoords.forEach(pt => {
        hazardLayers.forEach(h => {
            const allCoords = h.layer.getLatLngs().flat(2).map(ll => [ll.lat, ll.lng]); // flatten all rings
            if (isPointInPolygon(pt, allCoords)) {
                detected.push(h.type);
            }
        });
    });
    return [...new Set(detected)];
}

        function generateAlternativeWaypoints(origin, destination, detectedHazards) {
            // Generate alternative waypoints to avoid detected hazard zones
            const alternatives = [];
            const midLat = (origin.lat + destination.lat) / 2;
            const midLng = (origin.lng + destination.lng) / 2;
            const latDiff = Math.abs(origin.lat - destination.lat);
            const lngDiff = Math.abs(origin.lng - destination.lng);
            const maxDiff = Math.max(latDiff, lngDiff, 0.01); // Minimum offset
            const offset = maxDiff * 0.5; // 50% offset
            
            // Get hazard bounds to avoid (only for detected hazards)
            const hazardBounds = [];
            hazardLayers.forEach(h => {
                if (detectedHazards && detectedHazards.length > 0 && detectedHazards.includes(h.type)) {
                    hazardBounds.push(h.layer.getBounds());
                }
            });
            
            // Generate waypoints in multiple directions around the midpoint
            const waypoints = [
                L.latLng(midLat + offset, midLng), // North
                L.latLng(midLat - offset, midLng), // South
                L.latLng(midLat, midLng + offset), // East
                L.latLng(midLat, midLng - offset), // West
                L.latLng(midLat + offset * 0.7, midLng + offset * 0.7), // Northeast
                L.latLng(midLat + offset * 0.7, midLng - offset * 0.7), // Northwest
                L.latLng(midLat - offset * 0.7, midLng + offset * 0.7), // Southeast
                L.latLng(midLat - offset * 0.7, midLng - offset * 0.7)  // Southwest
            ];
            
            // Filter waypoints that avoid hazard zones
            waypoints.forEach(wp => {
                let inHazard = false;
                
                // Check if waypoint is in any of the detected hazard bounds
                hazardBounds.forEach(bounds => {
                    if (bounds.contains(wp)) {
                        inHazard = true;
                    }
                });
                
                // Also check if waypoint is reasonable distance
                const distToOrigin = wp.distanceTo(origin);
                const distToDest = wp.distanceTo(destination);
                const directDist = origin.distanceTo(destination);
                const reasonable = distToOrigin < directDist * 2.5 && distToDest < directDist * 2.5;
                
                if (!inHazard && reasonable && alternatives.length < 8) {
                    alternatives.push(wp);
                }
            });
            
            return alternatives.slice(0, 8); // Return up to 8 alternatives
        }

        function tryAlternativeRoute(origin, destination, shelterName, detectedHazards, attempt = 0) {
            const hazards = detectedHazards || [];
            const alternatives = generateAlternativeWaypoints(origin, destination, hazards);
            const maxAttempts = Math.min(alternatives.length, 8);
            
            if (attempt >= maxAttempts || alternatives.length === 0) {
                const hazardText = hazards.length > 0 ? ` (${hazards.join(', ')})` : '';
                alert(`⚠️ WARNING! Route to ${shelterName} passes through hazard zones${hazardText}.`);
                return null;
            }
            
            // Try the next alternative waypoint
            const waypoint = alternatives[attempt];
            const waypoints = [origin, waypoint, destination];
            
            const routingControl = L.Routing.control({
                waypoints: waypoints,
                router: L.Routing.osrmv1({
                    serviceUrl: routingServiceUrl
                        ? `${routingServiceUrl.replace(/\/+$/, '')}/route/v1`
                        : 'https://router.project-osrm.org/route/v1'
                }),
                show: false,
                addWaypoints: false,
                lineOptions: {
                    styles: [{
                        color: '#28a745',
                        weight: 5,
                        opacity: 0.7
                    }]
                }
            }).addTo(map);
            
            let routeChecked = false;
            
            routingControl.on('routingerror', () => {
                if (routeChecked) return;
                routeChecked = true;
                map.removeControl(routingControl);
                setTimeout(() => {
                    tryAlternativeRoute(origin, destination, shelterName, hazards, attempt + 1);
                }, 100);
            });
            
            routingControl.on('routesfound', e => {
        if (routeChecked) return;
        routeChecked = true;

        const coords = e.routes[0].coordinates || [];
        const hazardsInRoute = routePassesHazard(coords);

        if (hazardsInRoute.length === 0) {
            // ✅ Safe route found, add only if less than max 1 active route
            if (activeRoutes.length < 1) {
                activeRoutes.push(routingControl);

                // Notification
                const notification = L.popup()
                    .setLatLng(waypoint)
                    .setContent(`<div style="text-align: center;"><strong>✓ Safe Alternative Route</strong><br/>Route to ${shelterName} avoids hazard zones</div>`)
                    .openOn(map);
                setTimeout(() => map.closePopup(notification), 4000);
            } else {
                // Already have a route, remove extra
                map.removeControl(routingControl);
            }
            return routingControl;
        } else {
            // Alternative also has hazards, try next one
            map.removeControl(routingControl);
            setTimeout(() => {
                tryAlternativeRoute(origin, destination, shelterName, hazardsInRoute, attempt + 1);
            }, 100);
        }
    });

    return routingControl;
}

        function drawRoute(destination, shelterName) {
    if (!currentMarker) {
        alert('Waiting for current location...');
        return;
    }

    // Clear existing routes
    activeRoutes.forEach(rc => map.removeControl(rc));
    activeRoutes = [];

    const origin = currentMarker.getLatLng();
    let routeFound = false;

    // Step 1: Compute direct route first
const directRoute = L.Routing.control({
    waypoints: [origin, destination],
    router: L.Routing.osrmv1({
        serviceUrl: routingServiceUrl || 'https://router.project-osrm.org/route/v1'
    }),
    show: false,
    addWaypoints: false,
    createMarker: () => null, // ✅ Disable automatic markers
    lineOptions: { styles: [{ color: '#0d6efd', weight: 5, opacity: 0.7 }] }
}).addTo(map);


directRoute.on('routesfound', e => {
    if (routeFound) return;
    routeFound = true;

    const coords = e.routes[0].coordinates || [];
    const hazards = routePassesHazard(coords);

    if (hazards.length === 0) {
        // Direct route is safe
        activeRoutes.push(directRoute);
    } else {
        // Direct route has hazards → show warning
        const hazardText = hazards.join(', ');
        alert(`⚠️ WARNING! Direct route to ${shelterName} passes through hazard zones (${hazardText}).`);

        // Remove unsafe direct route
        map.removeControl(directRoute);

        // Try a simple alternative route (one safe waypoint only)
        tryAlternativeRoute(origin, destination, shelterName, hazards);
    }
});

directRoute.on('routingerror', () => {
    if (routeFound) return;
    routeFound = true;
    map.removeControl(directRoute);

    // If direct route fails, try alternative
    tryAlternativeRoute(origin, destination, shelterName);
});

}


        function renderSheltersList(list) {
            const tbody = document.getElementById('sheltersTableBody');
            tbody.innerHTML = '';
            if (!list.length) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No shelters found</td></tr>`;
                return;
            }
            list.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${s.shelter_name}</td>
                    <td>${statusBadge(s.shelter_status)}</td>
                    <td>${s.current_occupancy || 0}/${s.capacity || 0}</td>
                    <td><button class="btn btn-sm btn-primary" data-shelter="${s.shelter_id}">View</button></td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderHotlines(list) {
            const container = document.getElementById('hotlinesContainer');
            container.innerHTML = '';
            list.forEach(h => {
                const card = document.createElement('div');
                card.className = 'hotline-card';
                card.innerHTML = `
                    <div class="fw-bold">${h.agency_name} (${h.agency_code})</div>
                    <div class="text-muted">${h.description || ''}</div>
                    <div class="text-primary fw-semibold">${h.phone_number}</div>
                `;
                container.appendChild(card);
            });
        }

        function renderDisasters(list) {
            const container = document.getElementById('disastersContainer');
            container.innerHTML = '';
            list.forEach(d => {
                const card = document.createElement('div');
                card.className = 'disaster-card';
                card.innerHTML = `
                    <div class="fw-bold">${d.name}</div>
                    <div class="text-muted">${d.type} • ${d.severity || 'Unknown'}</div>
                    <div>${d.description || ''}</div>
                `;
                container.appendChild(card);
            });
        }

        function renderSideDisasters(list) {
            const container = document.getElementById('gmDisastersList');
            if (!container) return;
            if (!list.length) {
                container.innerHTML = '<div class="list-group-item text-muted small">No disasters</div>';
                return;
            }
            container.innerHTML = list.map(d => `
                <div class="list-group-item py-2">
                    <div class="fw-semibold">${d.name}</div>
                    <div class="small text-muted">${d.type} • ${d.severity || '—'}</div>
                </div>
            `).join('');
        }

        function renderSideHotlines(list) {
            const container = document.getElementById('gmHotlinesList');
            if (!container) return;
            if (!list.length) {
                container.innerHTML = '<div class="list-group-item text-muted small">No hotlines</div>';
                return;
            }
            container.innerHTML = list.map(h => `
                <div class="list-group-item py-2">
                    <div class="fw-semibold">${h.agency_name} (${h.agency_code})</div>
                    <div class="small text-primary fw-semibold">${h.phone_number}</div>
                    <div class="small text-muted">${h.description || ''}</div>
                </div>
            `).join('');
        }

        function fillStats(stats) {
            if (!stats) {
                console.warn('Stats data is missing');
                return;
            }
            
            const set = (id, val) => {
                const el = document.getElementById(id);
                if (el) {
                    const numVal = val != null ? (typeof val === 'number' ? val : parseInt(val) || 0) : 0;
                    el.textContent = numVal.toLocaleString();
                }
            };
            
            set('totalShelters', stats.total_shelters || 0);
            set('availableShelters', stats.available_shelters || 0);
            set('totalCapacity', stats.total_capacity || 0);
            set('currentOccupancy', stats.current_occupancy || 0);
            
            set('totalSheltersStat', stats.total_shelters || 0);
            set('availableSheltersStat', stats.available_shelters || 0);
            set('totalCapacityStat', stats.total_capacity || 0);
            set('currentOccupancyStat', stats.current_occupancy || 0);
        }

        let currentMarker = null;
        
        function checkLocationHazards(latlng) {
            const hazardsAtLocation = [];
            hazardLayers.forEach(h => {
                if (h.layer && h.layer.getLatLngs && h.layer.getLatLngs().length > 0) {
                    const polygonCoords = h.layer.getLatLngs()[0].map(ll => [ll.lat, ll.lng]);
                    if (isPointInPolygon(latlng, polygonCoords)) {
                        hazardsAtLocation.push(h.type);
                    }
                }
            });
            
            if (hazardsAtLocation.length > 0) {
                const uniqueHazards = [...new Set(hazardsAtLocation)];
                const hazardText = uniqueHazards.map(h => h.charAt(0).toUpperCase() + h.slice(1)).join(', ');
                
                // Show warning notification
                const warningPanel = document.createElement('div');
                warningPanel.id = 'hazardWarning';
                warningPanel.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #ff6b6b, #ff5252);
                    color: white;
                    padding: 16px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    font-weight: 500;
                    z-index: 1050;
                    max-width: 350px;
                    font-size: 14px;
                    animation: slideIn 0.3s ease-out;
                `;
                warningPanel.innerHTML = `
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="font-size: 20px; margin-top: 2px;">⚠️</div>
                        <div>
                            <strong style="display: block; margin-bottom: 4px;">You are in a hazardous location!</strong>
                            <div style="font-size: 13px; opacity: 0.95;">Active hazards: <strong>${hazardText}</strong></div>
                            <div style="font-size: 12px; opacity: 0.85; margin-top: 6px;">Please seek shelter immediately or move to a safer location.</div>
                        </div>
                    </div>
                `;
                
                // Add animation style
                if (!document.getElementById('hazardWarningStyle')) {
                    const style = document.createElement('style');
                    style.id = 'hazardWarningStyle';
                    style.textContent = `
                        @keyframes slideIn {
                            from {
                                transform: translateX(400px);
                                opacity: 0;
                            }
                            to {
                                transform: translateX(0);
                                opacity: 1;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                }
                
                // Remove old warning if exists
                const oldWarning = document.getElementById('hazardWarning');
                if (oldWarning) oldWarning.remove();
                
                document.body.appendChild(warningPanel);
                
                // Auto-remove after 8 seconds
                setTimeout(() => {
                    if (warningPanel.parentNode) warningPanel.remove();
                }, 8000);
            }
        }
        
        map.locate({
            setView: true,
            maxZoom: 16
        });
        map.on('locationfound', e => {
            if (currentMarker) map.removeLayer(currentMarker);
            currentMarker = L.marker(e.latlng).addTo(map).bindPopup('📍 Current Location').openPopup();
            
            // Check if current location is in hazard zone
            checkLocationHazards(e.latlng);
        });
        map.on('locationerror', () => {
            if (currentMarker) return;
            currentMarker = L.marker(defaultLatLng).addTo(map).bindPopup('📍 Default Location').openPopup();
            
            // Check if default location is in hazard zone
            checkLocationHazards(defaultLatLng);
        });

        sheltersData.forEach(s => {

    let firstImage = '';
    if (Array.isArray(s.shelter_images) && s.shelter_images.length > 0) {
        firstImage = `/storage/${s.shelter_images[0].image_path}`;
    }

    const occ = s.capacity > 0
        ? Math.round(((s.current_occupancy || 0) / s.capacity) * 100)
        : 0;

    const occColor = occ >= 100 ? '#dc3545' : occ >= 80 ? '#ffc107' : '#28a745';

    let popupContent = `<div style="min-width:200px;text-align:center;">`;

    if (firstImage) {
        popupContent += `
            <img src="${firstImage}"
                 style="width:100%;height:120px;object-fit:cover;border-radius:4px;margin-bottom:8px;">
        `;
    }

    popupContent += `
        <strong>${s.shelter_name}</strong><br>
        ${statusBadge(s.shelter_status)}
        <div style="margin-top:6px;">
            <strong>Capacity:</strong>
            <span style="color:${occColor};font-weight:bold;">
                ${s.current_occupancy || 0}/${s.capacity || 0}
            </span>
            (${occ}%)
        </div>
    </div>`;

    const marker = L.marker([s.latitude, s.longitude], {
        icon: shelterIconFactory(
            shelterStatusColors[s.shelter_status] || '#28a745'
        )
    }).addTo(map).bindPopup(popupContent);

    marker.on('click', () => {
        showShelterDetails(s);
        map.flyTo([s.latitude, s.longitude], 16);
    });
});


        renderSheltersList(sheltersData);
        renderHotlines(hotlinesData);
        renderDisasters(disastersData);
        renderSideHotlines(hotlinesData);
        renderSideDisasters(disastersData);
        
        // Fill stats from database - ensure statsData is properly formatted
        if (statsData && typeof statsData === 'object') {
            fillStats(statsData);
        } else {
            // Fallback: calculate stats from sheltersData if statsData is not available
            const calculatedStats = {
                total_shelters: sheltersData.length || 0,
                available_shelters: sheltersData.filter(s => s.shelter_status === 'Available').length || 0,
                total_capacity: sheltersData.reduce((sum, s) => sum + (parseInt(s.capacity) || 0), 0),
                current_occupancy: sheltersData.reduce((sum, s) => sum + (parseInt(s.current_occupancy) || 0), 0)
            };
            fillStats(calculatedStats);
        }

        function filterShelters(term) {
            const t = term.toLowerCase();
            const filtered = sheltersData.filter(s =>
                (s.shelter_name || '').toLowerCase().includes(t) ||
                (s.barangay || '').toLowerCase().includes(t)
            );
            renderSheltersList(filtered);
        }
        const mainSearch = document.getElementById('mainShelterSearch');
        const sideSearch = document.getElementById('shelterSearch');
        [mainSearch, sideSearch].forEach(input => {
            if (!input) return;
            input.addEventListener('input', e => filterShelters(e.target.value));
        });

        updateQualityIndicators();
        
        // Service Worker registration
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js')
                .then(() => {
                    performanceMetrics.sw = 'Registered';
                    updateQualityIndicators();
                })
                .catch(() => {
                    performanceMetrics.sw = 'Failed';
                    updateQualityIndicators();
                });
        } else {
            performanceMetrics.sw = 'Not Supported';
            updateQualityIndicators();
        }
        
        // IndexedDB check
        if ('indexedDB' in window) {
            performanceMetrics.idb = 'Ready';
            updateQualityIndicators();
        } else {
            performanceMetrics.idb = 'Not Supported';
            updateQualityIndicators();
        }
        
        // Monitor online/offline status
        window.addEventListener('online', () => {
            performanceMetrics.online = true;
            updateQualityIndicators();
        });
        window.addEventListener('offline', () => {
            performanceMetrics.online = false;
            updateQualityIndicators();
        });
        
        // Track shelter fetch time (data is already loaded from PHP)
        const shelterFetchStart = performance.now();
        setTimeout(() => {
            performanceMetrics.sheltersFetchMs = Math.round(performance.now() - shelterFetchStart);
            updateQualityIndicators();
        }, 100);

        // Google Maps style sidenav toggle + shortcuts
        const gmSideNav = document.getElementById('gmSideNav');
        const gmSideNavOpen = document.getElementById('gmSideNavOpen');
        const gmSideNavToggle = document.getElementById('gmSideNavToggle');

        function closeSideNav() {
            gmSideNav.style.transform = 'translateX(-100%)';
            gmSideNavOpen.style.display = 'inline-flex';
        }

        function openSideNav() {
            gmSideNav.style.transform = 'translateX(0)';
            gmSideNavOpen.style.display = 'none';
        }
        gmSideNavToggle.addEventListener('click', closeSideNav);
        gmSideNavOpen.addEventListener('click', openSideNav);

        document.getElementById('gmSideNavSearch').addEventListener('input', e => {
            filterShelters(e.target.value);
        });
        document.getElementById('gmBtnMyLocation').addEventListener('click', () => {
            if (currentMarker) {
                map.flyTo(currentMarker.getLatLng(), 15);
                currentMarker.openPopup();
            }
        });
        document.getElementById('gmBtnDisasters').addEventListener('click', () => {
            const disastersTab = document.getElementById('disasters-tab');
            if (disastersTab) disastersTab.click();
            closeSideNav();
        });
        document.getElementById('gmBtnHotlines').addEventListener('click', () => {
            const hotlinesTab = document.getElementById('hotlines-tab');
            if (hotlinesTab) hotlinesTab.click();
            closeSideNav();
        });
        document.getElementById('gmBtnHazards').addEventListener('click', () => {
            const panel = document.getElementById('hazardControlContent');
            panel.classList.toggle('d-none');
        });

        function toggleHazardLayer(type, visible) {
            hazardLayers.filter(h => h.type === type).forEach(h => {
                if (visible) {
                    if (!map.hasLayer(h.layer)) h.layer.addTo(map);
                    if (h.marker && !map.hasLayer(h.marker)) h.marker.addTo(map);
                } else {
                    if (map.hasLayer(h.layer)) map.removeLayer(h.layer);
                    if (h.marker && map.hasLayer(h.marker)) map.removeLayer(h.marker);
                }
            });
        }

        document.querySelectorAll('.hazard-checkbox').forEach(cb => {
            cb.checked = false;
            cb.addEventListener('change', e => {
                toggleHazardLayer(e.target.dataset.hazard, e.target.checked);
            });
        });

        // Autocomplete suggestions for shelters + barangays
        const suggestionsBox = document.getElementById('gmSearchSuggestions');
        function buildSuggestions(term) {
            const q = term.toLowerCase().trim();
            if (!q) {
                suggestionsBox.style.display = 'none';
                suggestionsBox.innerHTML = '';
                return;
            }
            const barangays = Array.from(new Set(sheltersData.map(s => s.barangay))).map(b => ({ type: 'barangay', label: b, barangay: b }));
            const shelters = sheltersData.map(s => ({ type: 'shelter', label: s.shelter_name, barangay: s.barangay, lat: s.latitude, lng: s.longitude, id: s.shelter_id }));
            const all = [...shelters, ...barangays];
            const filtered = all.filter(item =>
                item.label.toLowerCase().includes(q) ||
                (item.barangay && item.barangay.toLowerCase().includes(q))
            ).slice(0, 8);
            if (!filtered.length) {
                suggestionsBox.style.display = 'none';
                suggestionsBox.innerHTML = '';
                return;
            }
            suggestionsBox.innerHTML = filtered.map(item => `
                <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-type="${item.type}" data-id="${item.id || ''}" data-barangay="${item.barangay || ''}" data-lat="${item.lat || ''}" data-lng="${item.lng || ''}">
                    <span>${item.label}${item.barangay && item.type === 'shelter' ? ' • ' + item.barangay : ''}</span>
                    <span class="badge rounded-pill ${item.type === 'shelter' ? 'bg-primary' : 'bg-secondary'}">${item.type}</span>
                </button>
            `).join('');
            suggestionsBox.style.display = 'block';
        }

        document.getElementById('gmSideNavSearch').addEventListener('input', e => {
            buildSuggestions(e.target.value);
        });

        suggestionsBox.addEventListener('click', e => {
            const btn = e.target.closest('button[data-type]');
            if (!btn) return;
            const type = btn.dataset.type;
            if (type === 'shelter') {
                const id = btn.dataset.id;
                const shelter = sheltersData.find(s => String(s.shelter_id) === String(id));
                if (shelter) {
                    map.flyTo([shelter.latitude, shelter.longitude], 16);
                    showShelterDetails(shelter);
                }
            } else if (type === 'barangay') {
                const barangay = btn.dataset.barangay;
                let targetLatLng = null;
                const hz = hazardZonesData.find(h => (h.barangay || '').toLowerCase() === barangay.toLowerCase());
                if (hz) {
                    targetLatLng = [hz.latitude, hz.longitude];
                } else {
                    const shelterMatches = sheltersData.filter(s => (s.barangay || '').toLowerCase() === barangay.toLowerCase());
                    if (shelterMatches.length) {
                        const avgLat = shelterMatches.reduce((sum, s) => sum + Number(s.latitude), 0) / shelterMatches.length;
                        const avgLng = shelterMatches.reduce((sum, s) => sum + Number(s.longitude), 0) / shelterMatches.length;
                        targetLatLng = [avgLat, avgLng];
                    }
                }
                if (targetLatLng) {
                    map.flyTo(targetLatLng, 15);
                }
            }
            suggestionsBox.style.display = 'none';
        });

        document.getElementById('sheltersTableBody').addEventListener('click', e => {
            if (e.target.tagName === 'BUTTON') {
                const id = e.target.dataset.shelter;
                const shelter = sheltersData.find(s => String(s.shelter_id) === String(id));
                if (shelter) {
                    map.flyTo([shelter.latitude, shelter.longitude], 16);
                    showShelterDetails(shelter);
                }
            }
        });
        
        // Detail panel close handlers
        const detailPanel = document.getElementById('detailPanel');
        const closeDetailBtn = document.getElementById('closeDetailPanel');
        const detailOverlay = document.getElementById('detailOverlay');
        
        if (closeDetailBtn) {
            closeDetailBtn.addEventListener('click', () => {
                detailPanel.classList.remove('active');
                detailOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }
        
        if (detailOverlay) {
            detailOverlay.addEventListener('click', () => {
                detailPanel.classList.remove('active');
                detailOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }
        
        function showShelterDetails(shelter) {
    const detailContent = document.getElementById('detailContent');
    const occ = shelter.capacity > 0 ? Math.round((shelter.current_occupancy / shelter.capacity) * 100) : 0;
    const isUnavailable = shelter.shelter_status === 'Closed' || shelter.shelter_status === 'Under Maintenance';
    
    let zones = [];
    if (shelter.flood_zone === 'Yes') zones.push('Flood');
    if (shelter.landslide_zone === 'Yes') zones.push('Landslide');
    if (shelter.liquefaction_zone === 'Yes') zones.push('Liquefaction');
    if (shelter.storm_surge_zone === 'Yes') zones.push('Storm Surge');
    
    const firstImage = shelter.shelter_images && shelter.shelter_images.length > 0 
        ? shelter.shelter_images[0].image_path 
        : '';

    detailContent.innerHTML = `
        ${isUnavailable ? `
        <div class="alert alert-${shelter.shelter_status === 'Closed' ? 'secondary' : 'warning'} mb-3" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Shelter Not Available</h5>
            <p class="mb-0">This shelter is currently <strong>${shelter.shelter_status}</strong> and is not available for evacuation.</p>
        </div>
        ` : ''}
        
        ${shelter.shelter_images && shelter.shelter_images.length > 0 ? `
        <div class="mb-3">
            <div id="shelterImageCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                <div class="carousel-inner">
                    ${shelter.shelter_images.map((img, index) => `
                        <div class="carousel-item ${index === 0 ? 'active' : ''}">
                            <div class="image-carousel-wrapper" style="position: relative; width: 100%; height: 220px; background: #e9ecef; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <img src="uploads/shelters/${img.image_path}" alt="${shelter.shelter_name}" class="detail-image-carousel" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="skeleton-image-carousel" style="display: none; position: absolute; inset: 0; align-items: center; justify-content: center; flex-direction: column; color: #999; background: #f0f0f0;">
                                    <i class="bi bi-image" style="font-size: 3rem;"></i>
                                    <span style="font-size: 0.85rem; margin-top: 8px;">No Image Available</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>

                ${shelter.shelter_images.length > 1 ? `
                <button class="carousel-control-prev" type="button" data-bs-target="#shelterImageCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#shelterImageCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
                <div class="carousel-indicators">
                    ${shelter.shelter_images.map((img, index) => `
                        <button type="button" data-bs-target="#shelterImageCarousel" data-bs-slide-to="${index}" ${index === 0 ? 'class="active" aria-current="true"' : ''} aria-label="Slide ${index + 1}"></button>
                    `).join('')}
                </div>
                ` : ''}
            </div>
        </div>
        ` : `
        <div class="image-preview mb-3" style="position: relative; width: 100%; height: 220px; background: #e9ecef; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; color: #999;">
            <div style="text-align: center;">
                <i class="bi bi-image" style="font-size: 3rem;"></i>
                <div style="font-size: 0.85rem; margin-top: 8px;">No Image Available</div>
            </div>
        </div>
        `}

        <h4 class="mb-2 fw-bold">${shelter.shelter_name}</h4>
        <div class="mb-3">
            <span class="badge bg-${shelter.shelter_status === 'Available' ? 'success' : shelter.shelter_status === 'Full' ? 'danger' : shelter.shelter_status === 'Closed' ? 'secondary' : 'warning'} me-2">${shelter.shelter_status}</span>
            <span class="badge bg-info">${shelter.shelter_type}</span>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">Overview</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#details" type="button">Details</button>
            </li>
        </ul>
    

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="overview">
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                                <strong>${shelter.full_address || shelter.barangay}</strong>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-person me-2 text-primary"></i>
                                <span>${shelter.owner_name || 'N/A'}</span>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div>
                                        <i class="bi bi-people-fill me-2 text-primary"></i>
                                        <strong>Capacity:</strong> 
                                        <span style="color: ${occ >= 100 ? '#dc3545' : occ >= 80 ? '#ffc107' : '#28a745'}; font-weight: bold; font-size: 1.1rem;">
                                            ${shelter.current_occupancy || 0} / ${shelter.capacity || 0}
                                        </span>
                                        <small class="text-muted"> (${occ}% occupied)</small>
                                    </div>
                                    ${occ >= 100 ? '<span class="badge bg-danger">Full</span>' : occ >= 80 ? '<span class="badge bg-warning">Nearly Full</span>' : '<span class="badge bg-success">Available</span>'}
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar ${occ >= 100 ? 'bg-danger' : occ >= 80 ? 'bg-warning' : 'bg-success'}" role="progressbar" style="width: ${occ}%" aria-valuenow="${occ}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>

                        ${shelter.description ? `
                        <p class="text-muted mb-3">${shelter.description}</p>
                        ` : ''}

                        ${shelter.contact_number || shelter.contact_email ? `
                        <div class="mb-3">
                            <h6 class="fw-bold mb-2">Contact</h6>
                            ${shelter.contact_number ? `
                            <div class="mb-1">
                                <i class="bi bi-telephone me-2"></i>
                                <a href="tel:${shelter.contact_number}" class="text-decoration-none">${shelter.contact_number}</a>
                            </div>
                            ` : ''}
                            ${shelter.contact_email ? `
                            <div class="mb-1">
                                <i class="bi bi-envelope me-2"></i>
                                <a href="mailto:${shelter.contact_email}" class="text-decoration-none">${shelter.contact_email}</a>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}

                        <div class="mb-3">
                            <h6 class="fw-bold mb-2">Hazard Zones</h6>
                            <div class="zones-badge">
                                ${zones.length > 0 ? zones.map(zone => `<span class="badge bg-warning me-1 mb-1">${zone}</span>`).join('') : '<span class="badge bg-success">No Hazard Zones</span>'}
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="details">
                        <div class="detail-grid">
                            <div class="grid-item">
                                <strong>Building Material:</strong>
                                <span>${shelter.building_material_type || 'N/A'}</span>
                            </div>
                            <div class="grid-item">
                                <strong>Condition:</strong>
                                <span>${shelter.building_condition || 'N/A'}</span>
                            </div>
                            <div class="grid-item">
                                <strong>Water Supply:</strong>
                                <span>${shelter.water_supply || 'N/A'}</span>
                            </div>
                            <div class="grid-item">
                                <strong>Electricity:</strong>
                                <span>${shelter.electricity || 'N/A'}</span>
                            </div>
                            <div class="grid-item">
                                <strong>Road Condition:</strong>
                                <span>${shelter.road_condition || 'N/A'}</span>
                            </div>
                            <div class="grid-item">
                                <strong>Travel Time:</strong>
                                <span>${shelter.estimated_travel_time || 'N/A'}</span>
                            </div>
                        </div>
                        <div class="detail-info mt-2">
                            <div class="info-row">
                                <strong><i class="bi bi-signpost"></i> Near Main Road:</strong>
                                <span>${shelter.near_main_road || 'N/A'}</span>
                            </div>
                            <div class="info-row">
                                <strong><i class="bi bi-mountain"></i> Elevation:</strong>
                                <span>${shelter.elevation || 'N/A'} m</span>
                            </div>
                            <div class="info-row">
                                <strong><i class="bi bi-shield-check"></i> Safe Shelter:</strong>
                                <span class="badge bg-${shelter.is_safe_shelter ? 'success' : 'danger'}" style="color: white; font-weight: 600;">${shelter.is_safe_shelter ? 'Yes' : 'No'}</span>
                            </div>
                        </div>

                        ${shelter.disaster_name ? `
                        <div class="mt-3">
                            <h6 class="fw-bold mb-2">Current Disaster</h6>
                            <div class="alert alert-${shelter.disaster_severity === 'Severe' ? 'danger' : shelter.disaster_severity === 'High' ? 'warning' : 'info'} mb-0">
                                <strong>${shelter.disaster_name}</strong><br>
                                <small>Type: ${shelter.disaster_type} | Severity: ${shelter.disaster_severity}</small>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="mt-4">
                    <button class="btn btn-primary w-100" onclick="calculateRouteToShelter(${shelter.latitude}, ${shelter.longitude}, '${shelter.shelter_name.replace(/'/g, "\\'")}')">
                        <i class="bi bi-signpost-split"></i> Get Directions
                    </button>
                </div>
            `;
            
            detailPanel.classList.add('active');
            detailOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Initialize Bootstrap carousel if it exists
            setTimeout(() => {
                const carousel = document.getElementById('shelterImageCarousel');
                if (carousel && typeof bootstrap !== 'undefined') {
                    const bsCarousel = new bootstrap.Carousel(carousel, {
                        interval: 3000,
                        wrap: true
                    });
                }
            }, 100);
        }
        
        function calculateRouteToShelter(lat, lng, shelterName) {
            if (!currentMarker) {
                alert('Waiting for current location...');
                return;
            }
            const origin = currentMarker.getLatLng();
            drawRoute(L.latLng(lat, lng), shelterName);
            // Close detail panel after routing
            setTimeout(() => {
                document.getElementById('detailPanel').classList.remove('active');
                document.getElementById('detailOverlay').classList.remove('active');
                document.body.style.overflow = 'auto';
            }, 500);
        }
        
    </script>
    
    <style>
        /* Detail Panel */
        .detail-panel {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: white;
            z-index: 3002;
            transition: right .3s ease;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
            overflow-y: auto;
        }

        .detail-panel.active {
            right: 0;
        }

        .detail-panel-header {
            background: #28a745;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .detail-panel-header .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
        }

        .detail-panel-content {
            padding: 20px;
        }

        .detail-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-section:last-child {
            border-bottom: none;
        }

        .detail-title {
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .detail-subtitle {
            color: #495057;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .detail-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .detail-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row strong {
            color: #2c3e50;
            min-width: 120px;
            font-size: 0.9rem;
        }

        .info-row span {
            color: #6c757d;
            font-size: 0.9rem;
            text-align: right;
        }

        .zones-badge {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .grid-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .grid-item strong {
            color: #2c3e50;
            font-size: 0.85rem;
        }

        .grid-item span {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 2999;
            opacity: 0;
            visibility: hidden;
            transition: .3s;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Image Carousel */
        #shelterImageCarousel {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .image-carousel-wrapper {
            position: relative;
            width: 100%;
            height: 250px;
            overflow: hidden;
        }

        .detail-image-carousel {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .skeleton-image-carousel {
            display: none;
            width: 100%;
            height: 100%;
            background: #f0f0f0;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #adb5bd;
        }

        .skeleton-image-carousel i {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .skeleton-image-carousel span {
            font-size: 0.85rem;
        }

        .carousel-indicators {
            margin-bottom: 10px;
        }

        .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            border: none;
            margin: 0 3px;
        }

        .carousel-indicators button.active {
            background-color: #007bff;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 40px;
            height: 40px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
        }

        .carousel-control-prev {
            left: 10px;
        }

        .carousel-control-next {
            right: 10px;
        }

        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            width: 20px;
            height: 20px;
        }

        @media (max-width: 768px) {
            .detail-panel {
                width: 100%;
                right: -100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
            }

            .info-row strong {
                min-width: auto;
            }

            .info-row span {
                text-align: left;
            }
        }
    </style>
</body>

</html>