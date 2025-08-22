-- Add is_paused column to track if a reservation is currently paused
ALTER TABLE reservations ADD COLUMN is_paused TINYINT(1) DEFAULT 0;

-- Add pause_time column to record when a reservation was paused
ALTER TABLE reservations ADD COLUMN pause_time DATETIME NULL;

-- Add total_pause_duration column to track cumulative pause time (in seconds)
ALTER TABLE reservations ADD COLUMN total_pause_duration INT DEFAULT 0; 