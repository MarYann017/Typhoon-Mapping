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

CREATE INDEX idx_shelters_barangay ON shelters (barangay);
CREATE INDEX idx_shelters_coords ON shelters (latitude, longitude);
CREATE INDEX idx_shelters_status ON shelters (shelter_status);
CREATE INDEX idx_shelters_disaster ON shelters (current_disaster_id);
CREATE INDEX idx_shelters_name ON shelters (shelter_name);

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

INSERT INTO barangay_hazard_zones (barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description)
VALUES 
    ('Barangay Poblacion', 13.55683558, 124.19982281, 'Yes', 'Yes', 'No', 'No', 'Yes', 'High', 'Coastal area prone to typhoons, floods, and storm surges'),
    ('Barangay San Roque', 13.56000000, 124.20000000, 'Yes', 'No', 'Yes', 'Yes', 'No', 'Moderate', 'Mountainous area with landslide and liquefaction risks'),
    ('Barangay Sta. Cruz', 13.55500000, 124.19800000, 'No', 'Yes', 'No', 'No', 'Yes', 'Low', 'Low-lying area prone to flooding and storm surges');