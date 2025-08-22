-- Note: The column for payment proof already exists as 'screenshot_receipt'
-- No need to add a new column

-- Confirm the existing structure
DESCRIBE reservations;

-- If you need to rename the column (not recommended if data exists):
-- ALTER TABLE reservations CHANGE COLUMN screenshot_receipt payment_proof VARCHAR(255); 