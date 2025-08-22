ALTER TABLE reservations
ADD COLUMN decline_reason TEXT NULL
AFTER status;
