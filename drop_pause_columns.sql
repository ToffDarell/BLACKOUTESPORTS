-- Drop columns related to pause functionality
ALTER TABLE reservations DROP COLUMN is_paused;
ALTER TABLE reservations DROP COLUMN pause_time;
ALTER TABLE reservations DROP COLUMN total_pause_duration; 