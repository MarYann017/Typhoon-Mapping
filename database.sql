-- =========================================
-- RESET DATABASE
-- =========================================
DROP DATABASE IF EXISTS evacuation_shelter;
CREATE DATABASE evacuation_shelter;
USE evacuation_shelter;

-- =========================================
-- USERS TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- DISASTERS TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS disasters (
    disaster_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('Typhoon', 'Flood', 'Earthquake', 'Landslide', 'Fire') NOT NULL,
    start_date DATE,
    end_date DATE,
    severity ENUM('Low', 'Moderate', 'High', 'Severe'),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================
-- SHELTERS TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS shelters (
    shelter_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_name VARCHAR(100) NOT NULL,
    barangay VARCHAR(50) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    full_address VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    contact_number VARCHAR(20) DEFAULT NULL,
    contact_email VARCHAR(100) DEFAULT NULL,
    shelter_type ENUM('School', 'House', 'Barangay Hall', 'Gym', 'Church', 'Other') DEFAULT 'Other',
    shelter_status ENUM('Available', 'Full', 'Under Maintenance', 'Closed') DEFAULT 'Available',
    capacity INT NOT NULL CHECK (capacity > 0),
    current_occupancy INT DEFAULT 0 CHECK (current_occupancy >= 0),
    is_full TINYINT(1) DEFAULT 0,
    typhoon_zone ENUM('Yes', 'No') NOT NULL,
    flood_zone ENUM('Yes', 'No') NOT NULL,
    landslide_zone ENUM('Yes', 'No') NOT NULL,
    liquefaction_zone ENUM('Yes', 'No') NOT NULL,
    storm_surge_zone ENUM('Yes', 'No') NOT NULL,
    elevation DECIMAL(10, 2) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    building_material_type VARCHAR(50) NOT NULL,
    building_condition VARCHAR(50) NOT NULL,
    water_supply VARCHAR(50) NOT NULL,
    electricity VARCHAR(50) NOT NULL,
    road_condition VARCHAR(50) NOT NULL,
    estimated_travel_time VARCHAR(50) NOT NULL,
    near_main_road ENUM('Yes', 'No') NOT NULL,
    is_safe_shelter TINYINT(1) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    current_disaster_id INT UNSIGNED NULL,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (current_disaster_id) REFERENCES disasters(disaster_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_shelters_barangay ON shelters (barangay);
CREATE INDEX IF NOT EXISTS idx_shelters_coords ON shelters (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_shelters_status ON shelters (shelter_status);
CREATE INDEX IF NOT EXISTS idx_shelters_disaster ON shelters (current_disaster_id);
CREATE INDEX IF NOT EXISTS idx_shelters_name ON shelters (shelter_name);

-- =========================================
-- SHELTER IMAGES TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS shelter_images (
    image_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shelter_id) REFERENCES shelters(shelter_id) ON DELETE CASCADE
);

-- =========================================
-- EVACUEES TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS evacuees (
    evacuee_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shelter_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    age INT CHECK (age >= 0),
    gender ENUM('Male', 'Female', 'Other') DEFAULT NULL,
    date_arrived TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_left TIMESTAMP NULL,
    FOREIGN KEY (shelter_id) REFERENCES shelters(shelter_id) ON DELETE CASCADE
);

-- =========================================
-- TRIGGERS FOR OCCUPANCY + STATUS UPDATE
-- =========================================
DROP TRIGGER IF EXISTS trg_after_evacuee_insert;

CREATE TRIGGER trg_after_evacuee_insert
AFTER INSERT ON evacuees
FOR EACH ROW
BEGIN
    UPDATE shelters
    SET current_occupancy = current_occupancy + 1,
        shelter_status = IF(current_occupancy + 1 >= capacity, 'Full', shelter_status),
        is_full = IF(current_occupancy + 1 >= capacity, 1, 0)
    WHERE shelter_id = NEW.shelter_id;
END;

DROP TRIGGER IF EXISTS trg_after_evacuee_delete;

CREATE TRIGGER trg_after_evacuee_delete
AFTER DELETE ON evacuees
FOR EACH ROW
BEGIN
    UPDATE shelters
    SET current_occupancy = current_occupancy - 1,
        shelter_status = IF(current_occupancy - 1 < capacity, 'Available', shelter_status),
        is_full = IF(current_occupancy - 1 < capacity, 0, 1)
    WHERE shelter_id = OLD.shelter_id;
END;


-- =========================================
-- IMPORT LOG TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS import_logs (
    import_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    total_imported INT DEFAULT 0,
    status ENUM('Pending', 'Completed', 'Failed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =========================================
-- AUDIT LOGS TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(50),
    target_table VARCHAR(50),
    target_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- =========================================
-- CURRENT LOCATION TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS mycurrentlocation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- EMERGENCY HOTLINES TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS emergency_hotlines (
    hotline_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_name VARCHAR(100) NOT NULL,
    agency_code VARCHAR(20) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- BARANGAY HAZARD ZONES TABLE
-- =========================================
CREATE TABLE IF NOT EXISTS barangay_hazard_zones (
    hazard_zone_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    typhoon_zone ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    flood_zone ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    landslide_zone ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    liquefaction_zone ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    storm_surge_zone ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
    hazard_level ENUM('Low', 'Moderate', 'High', 'Severe') DEFAULT 'Low',
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_barangay (barangay)
);

CREATE INDEX idx_barangay_hazard_zones_barangay ON barangay_hazard_zones (barangay);
CREATE INDEX idx_barangay_hazard_zones_coords ON barangay_hazard_zones (latitude, longitude);
CREATE INDEX idx_barangay_hazard_zones_typhoon ON barangay_hazard_zones (typhoon_zone);
CREATE INDEX idx_barangay_hazard_zones_flood ON barangay_hazard_zones (flood_zone);
CREATE INDEX idx_barangay_hazard_zones_landslide ON barangay_hazard_zones (landslide_zone);

-- =========================================
-- HAZARD POLYGON COORDINATES (for map overlays)
-- =========================================
CREATE TABLE IF NOT EXISTS hazard_polygons (
    polygon_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    hazard_type ENUM('typhoon','flood','landslide','liquefaction','storm_surge') NOT NULL,
    coordinates JSON NOT NULL, -- stored as array of [lat, lng]
    fill_color VARCHAR(20) DEFAULT NULL,
    stroke_color VARCHAR(20) DEFAULT NULL,
    fill_opacity DECIMAL(3,2) DEFAULT 0.40,
    weight INT DEFAULT 2,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_hazard_polygons_type ON hazard_polygons (hazard_type);

-- =========================================
-- SAMPLE DATA
-- =========================================
INSERT INTO mycurrentlocation (latitude, longitude)
VALUES (13.55683558, 124.19982281);

INSERT INTO users (username, password, is_admin)
VALUES ('admin', 'admin', 1),
       ('user', 'user', 0);

INSERT INTO emergency_hotlines (agency_name, agency_code, phone_number, description, priority_order)
VALUES 
    ('Bureau of Fire Protection', 'BFP', '0961-178-4598', 'Fire emergencies, rescue operations, and fire safety', 1),
    ('Provincial Disaster Risk Reduction and Management Office', 'PDRRMO', '0912-670-7777', 'Provincial disaster response and coordination', 2),
    ('Municipal Disaster Risk Reduction and Management Office', 'MDRRMO', '0921-425-6862', 'Municipal disaster response and coordination', 3),
    ('Philippine Red Cross', 'RED CROSS', '0917-806-8528', 'Emergency medical services, disaster relief, and humanitarian aid', 4),
    ('Philippine Coast Guard', 'COAST GUARD', '0947-325-7245', 'Maritime emergencies, search and rescue operations', 5);

INSERT INTO disasters (name, type, start_date, end_date, severity, description)
VALUES 
    ('Typhoon Odette', 'Typhoon', '2021-12-16', '2021-12-20', 'Severe', 'Super typhoon with sustained winds of 195 km/h'),
    ('Flood Alert Level 2', 'Flood', '2023-11-15', NULL, 'Moderate', 'Heavy rainfall causing localized flooding'),
    ('Earthquake 6.5 Magnitude', 'Earthquake', '2023-10-20', NULL, 'High', 'Strong earthquake affecting multiple areas');

-- =========================================
-- SAMPLE BARANGAY HAZARD ZONES DATA
-- =========================================
-- Note: Coordinates should be updated based on actual barangay locations
-- This is just sample data structure
INSERT INTO barangay_hazard_zones (barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description)
VALUES 
    ('Barangay Poblacion', 13.55683558, 124.19982281, 'Yes', 'Yes', 'No', 'No', 'Yes', 'High', 'Coastal area prone to typhoons, floods, and storm surges'),
    ('Barangay San Roque', 13.56000000, 124.20000000, 'Yes', 'No', 'Yes', 'Yes', 'No', 'Moderate', 'Mountainous area with landslide and liquefaction risks'),
    ('Barangay Sta. Cruz', 13.55500000, 124.19800000, 'No', 'Yes', 'No', 'No', 'Yes', 'Low', 'Low-lying area prone to flooding and storm surges');

-- =========================================
-- SAMPLE HAZARD POLYGON COORDS (mirrors index.html overlays)
-- =========================================
INSERT INTO hazard_polygons (name, hazard_type, coordinates, fill_color, stroke_color, fill_opacity, weight) VALUES
-- Landslide polygons
('Landslide Brgy A','landslide','[[13.6038386,124.2116132],[13.6034423,124.2012706],[13.5961636,124.2013779],[13.596101,124.2052188],[13.5949956,124.2081585],[13.5960802,124.211184],[13.5988332,124.2161193]]','orange','orange',0.40,2),
('Landslide Brgy B','landslide','[[13.6029337,124.2167419],[13.5975112,124.2168707],[13.5972609,124.2194456],[13.5980951,124.2220634],[13.6005144,124.2245954],[13.6018492,124.227342],[13.6011401,124.2332214],[13.6020161,124.2353672],[13.6058952,124.2352814],[13.6029337,124.2167419]]','orange','orange',0.40,2),
('Landslide Brgy C','landslide','[[13.6046856,124.219746],[13.6027669,124.2196173],[13.6015155,124.219746],[13.6012235,124.2215485],[13.6008481,124.2226643],[13.601307,124.2230934],[13.6020578,124.2226643],[13.6039765,124.222278],[13.6051027,124.2223639],[13.6046856,124.219746]]','orange','orange',0.40,2),
('Landslide Brgy D','landslide','[[13.6039418,124.227857],[13.6017311,124.2280716],[13.6014808,124.2295307],[13.6024402,124.2308611],[13.6033161,124.2300457],[13.6048595,124.2310757],[13.6056937,124.2311615],[13.6061942,124.2293591],[13.6039418,124.227857]]','orange','orange',0.40,2),
-- Flood polygons
('Flood Brgy A','flood','[[13.5958951,124.2116132],[13.5870518,124.2012706],[13.5933089,124.2013779],[13.5855501,124.2052188],[13.5901387,124.2081585],[13.5958951,124.211184],[13.5901387,124.2063055],[13.5953197,124.2170343],[13.5883952,124.2099533],[13.5901387,124.2063055]]','purple','purple',0.40,2),
('Flood Brgy B','flood','[[13.5707823,124.1961594],[13.5693639,124.2040559],[13.5723258,124.2131968],[13.5772484,124.2184325]]','purple','purple',0.40,2),
('Flood Brgy C','flood','[[13.5890616,124.241744],[13.5892075,124.2423233],[13.5923152,124.2454132],[13.5934415,124.2488894]]','purple','purple',0.40,2),
('Flood Brgy D','flood','[[13.5877188,124.2420822],[13.5897002,124.2458802],[13.5903885,124.2468458],[13.5908891,124.2482835]]','purple','purple',0.40,2),
('Flood Brgy E','flood','[[13.5717346,124.2175162],[13.5696905,124.2161214],[13.5686684,124.22037],[13.5711923,124.2164004],[13.5674586,124.2172372],[13.5717346,124.2175162]]','purple','purple',0.40,2),
-- Liquefaction polygons
('Liquefaction Brgy A','liquefaction','[[13.5882534,124.2043922],[13.5820796,124.2076538],[13.5817876,124.1993711],[13.5882534,124.2043922]]','yellow','yellow',0.80,2),
('Liquefaction Brgy B','liquefaction','[[13.5781323,124.2204618],[13.5747116,124.2240237],[13.5769643,124.2252683],[13.5781323,124.2204618],[13.5761717,124.2221784],[13.5755042,124.2263841],[13.5767974,124.2234658]]','yellow','yellow',0.80,2),
-- Storm surge polygon
('Storm Surge Area','storm_surge','[[13.5908123,124.2182214],[13.5863347,124.2208832],[13.5834921,124.2146517],[13.5880248,124.2120913]]','blue','blue',0.35,2);