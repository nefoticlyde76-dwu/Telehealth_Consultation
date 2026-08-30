ALTER TABLE consultation_requests
    MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed') DEFAULT 'Pending';

CREATE UNIQUE INDEX uq_consultation_requests_availability_id ON consultation_requests (availability_id);
