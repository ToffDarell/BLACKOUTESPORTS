-- Add cancellation-related columns to reservations table
ALTER TABLE reservations
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT,
ADD COLUMN IF NOT EXISTS cancellation_time DATETIME;

-- Add a new status 'Refund Requested' to handle refund process
-- Note: This assumes your status column is ENUM type, modify as needed
-- If status is VARCHAR/etc, you can skip this part
-- ALTER TABLE reservations MODIFY COLUMN status ENUM('Pending', 'Confirmed', 'Declined', 'Completed', 'Cancelled', 'Refund Requested', 'Refunded');

-- Add the refund_requests table if it doesn't exist
CREATE TABLE IF NOT EXISTS refund_requests (
    refund_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_id INT NOT NULL,
    reason TEXT NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    refund_status ENUM('Pending', 'Approved', 'Declined', 'Refunded') DEFAULT 'Pending',
    refund_proof VARCHAR(255), -- e.g., image/screenshot of GCash refund
    gcash_reference_number VARCHAR(100), -- optional tracking reference
    refund_date DATETIME,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
);

-- Create directory for refund proofs if needed
-- This is a reminder, as directories need to be created in PHP code:
-- mkdir("uploads/refunds", 0777, true); 