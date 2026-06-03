CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('space_1_name', 'Студпространство 8 корпус'),
    ('space_2_name', 'Пространство возле УМП'),
    ('space_3_name', 'Пространство 3'),
    ('space_4_name', 'Пространство 4'),
    ('space_1_opening_time', '09:00'),
    ('space_2_opening_time', '09:00'),
    ('space_3_opening_time', '09:00'),
    ('space_4_opening_time', '09:00'),
    ('space_1_closing_time', '21:00'),
    ('space_2_closing_time', '21:00'),
    ('space_3_closing_time', '21:00'),
    ('space_4_closing_time', '21:00'),
    ('space_1_description', ''),
    ('space_2_description', ''),
    ('space_3_description', ''),
    ('space_4_description', ''),
    ('space_1_visible', '1'),
    ('space_2_visible', '1'),
    ('space_3_visible', '1'),
    ('space_4_visible', '1'),
    ('space_1_image', ''),
    ('space_2_image', ''),
    ('space_3_image', ''),
    ('space_4_image', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    space_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    booking_date DATE NOT NULL,
    event_name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    responsible_name VARCHAR(150) NOT NULL,
    responsible_phone VARCHAR(40) NOT NULL,
    attendees_count INT UNSIGNED NOT NULL DEFAULT 1,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_space_date (space_id, booking_date),
    INDEX idx_booking_date (booking_date)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
