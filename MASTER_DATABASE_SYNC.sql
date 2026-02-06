-- ========================================================
-- NEXTGENBANK TRULY COMPLETE DATABASE SYNC
-- Database Name: nextgenbank1
-- Version: 2.0 (Plain-Text Passwords)
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. CORE IDENTITY TABLES
-- --------------------------------------------------------

-- Users master table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `cnic` varchar(13) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL, -- Storing as plain text for user convenience
  `full_name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `user_type` enum('Customer','Staff') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `cnic` (`cnic`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff specific table
CREATE TABLE IF NOT EXISTS `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `staff_role` enum('Accountant', 'Cashier', 'Complain_Handler', 'Admin') NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 2. ACCOUNT & BANKING STRUCTURE
-- --------------------------------------------------------

-- Account types
CREATE TABLE IF NOT EXISTS `account_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `minimum_balance` decimal(10,2) DEFAULT 0.00,
  `interest_rate` decimal(5,2) DEFAULT 0.00,
  `monthly_fee` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`type_id`),
  UNIQUE KEY `type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Accounts table
CREATE TABLE IF NOT EXISTS `accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `available_balance` decimal(15,2) DEFAULT 0.00,
  `opening_date` date NOT NULL,
  `status` enum('Active','Inactive','Suspended','Closed') DEFAULT 'Active',
  `branch_code` varchar(10) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `account_number` (`account_number`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `account_types` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Account requests
CREATE TABLE IF NOT EXISTS `account_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `form_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requested_type_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Under_Review') DEFAULT 'Pending',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `verified_by_staff_id` int(11) DEFAULT NULL,
  `verification_date` timestamp NULL DEFAULT NULL,
  `verification_method` enum('Biometric','Manual','Not_Verified') DEFAULT 'Not_Verified',
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `form_number` (`form_number`),
  CONSTRAINT `account_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `account_requests_ibfk_2` FOREIGN KEY (`requested_type_id`) REFERENCES `account_types` (`type_id`),
  CONSTRAINT `account_requests_ibfk_3` FOREIGN KEY (`verified_by_staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Transfer limits
CREATE TABLE IF NOT EXISTS `transfer_limits` (
  `limit_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `daily_limit` decimal(15,2) DEFAULT 100000.00,
  `weekly_limit` decimal(15,2) DEFAULT 500000.00,
  `monthly_limit` decimal(15,2) DEFAULT 2000000.00,
  `per_transaction_limit` decimal(15,2) DEFAULT 50000.00,
  `last_reset_date` date NOT NULL,
  PRIMARY KEY (`limit_id`),
  UNIQUE KEY `account_id` (`account_id`),
  CONSTRAINT `transfer_limits_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. TRANSACTIONS & TRANSFERS
-- --------------------------------------------------------

-- Transaction types
CREATE TABLE IF NOT EXISTS `transaction_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `category` enum('Credit','Debit') NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Transactions table
CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_reference` varchar(30) NOT NULL,
  `from_account_id` int(11) DEFAULT NULL,
  `to_account_id` int(11) DEFAULT NULL,
  `transaction_type_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Completed','Failed','Cancelled') DEFAULT 'Pending',
  `is_external_transfer` tinyint(1) DEFAULT 0,
  `external_bank_name` varchar(100) DEFAULT NULL,
  `external_account_number` varchar(30) DEFAULT NULL,
  `initiated_by_user_id` int(11) NOT NULL,
  `verified_by_staff_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `transaction_reference` (`transaction_reference`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`account_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`to_account_id`) REFERENCES `accounts` (`account_id`),
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`type_id`),
  CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`initiated_by_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `transactions_ibfk_5` FOREIGN KEY (`verified_by_staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Scheduled transfers
CREATE TABLE IF NOT EXISTS `scheduled_transfers` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_account_id` int(11) NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `frequency` enum('Daily','Weekly','Monthly','Quarterly','Yearly') NOT NULL,
  `next_transfer_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  CONSTRAINT `scheduled_transfers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `scheduled_transfers_ibfk_2` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`account_id`),
  CONSTRAINT `scheduled_transfers_ibfk_3` FOREIGN KEY (`to_account_id`) REFERENCES `accounts` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 4. CARDS MANAGEMENT
-- --------------------------------------------------------

-- Card types
CREATE TABLE IF NOT EXISTS `card_types` (
  `card_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `card_category` enum('Standard','Gold','Platinum','Infinite') NOT NULL,
  `annual_fee` decimal(10,2) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  PRIMARY KEY (`card_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cards table
CREATE TABLE IF NOT EXISTS `cards` (
  `card_id` int(11) NOT NULL AUTO_INCREMENT,
  `card_number` varchar(16) NOT NULL,
  `account_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `card_type_id` int(11) NOT NULL,
  `expiry_date` date NOT NULL,
  `cvv_hash` varchar(255) NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `status` enum('Active','Inactive','Lost','Stolen','Expired','Blocked') DEFAULT 'Active',
  `issue_date` date NOT NULL,
  `activation_date` date DEFAULT NULL,
  `daily_spending_limit` decimal(15,2) DEFAULT NULL,
  `monthly_spending_limit` decimal(15,2) DEFAULT NULL,
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `card_number` (`card_number`),
  CONSTRAINT `cards_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  CONSTRAINT `cards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `cards_ibfk_3` FOREIGN KEY (`card_type_id`) REFERENCES `card_types` (`card_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Card requests
CREATE TABLE IF NOT EXISTS `card_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `requested_card_type_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Issued') DEFAULT 'Pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by_staff_id` int(11) DEFAULT NULL,
  `process_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `card_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `card_requests_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  CONSTRAINT `card_requests_ibfk_3` FOREIGN KEY (`requested_card_type_id`) REFERENCES `card_types` (`card_type_id`),
  CONSTRAINT `card_requests_ibfk_4` FOREIGN KEY (`processed_by_staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 5. SUPPORT & CRM
-- --------------------------------------------------------

-- Complaint categories
CREATE TABLE IF NOT EXISTS `complaint_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `sla_hours` int(11) DEFAULT 48,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Complaints table
CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `status` enum('Open','In_Progress','Resolved','Closed','Escalated') DEFAULT 'Open',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to_staff_id` int(11) DEFAULT NULL,
  `assigned_date` timestamp NULL DEFAULT NULL,
  `resolution_date` timestamp NULL DEFAULT NULL,
  `resolution_details` text DEFAULT NULL,
  `user_rating` int(11) DEFAULT NULL CHECK (`user_rating` >= 1 and `user_rating` <= 5),
  `feedback` text DEFAULT NULL,
  PRIMARY KEY (`complaint_id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`),
  CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`assigned_to_staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Complaint updates
CREATE TABLE IF NOT EXISTS `complaint_updates` (
  `update_id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `update_type` enum('Status_Change','Note','Assignment','Resolution') NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`update_id`),
  CONSTRAINT `complaint_updates_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `complaint_updates_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Prospective customers
CREATE TABLE IF NOT EXISTS `prospective_customers` (
  `prospect_id` int(11) NOT NULL AUTO_INCREMENT,
  `cnic` varchar(13) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `requested_account_type_id` int(11) NOT NULL,
  `form_number` varchar(30) NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `verified_by_staff_id` int(11) DEFAULT NULL,
  `verification_date` timestamp NULL DEFAULT NULL,
  `verification_method` enum('Biometric','Manual','Not_Verified') DEFAULT 'Not_Verified',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`prospect_id`),
  UNIQUE KEY `cnic` (`cnic`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `form_number` (`form_number`),
  CONSTRAINT `prospective_customers_ibfk_1` FOREIGN KEY (`requested_account_type_id`) REFERENCES `account_types` (`type_id`),
  CONSTRAINT `prospective_customers_ibfk_2` FOREIGN KEY (`verified_by_staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Appointments
CREATE TABLE IF NOT EXISTS `appointments` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `prospect_id` int(11) DEFAULT NULL,
  `appointment_type` enum('Account_Opening','Card_Collection','Issue_Resolution','Other') NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `status` enum('Scheduled','Completed','Cancelled','No_Show') DEFAULT 'Scheduled',
  `purpose` text DEFAULT NULL,
  `assigned_to_staff_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`assigned_to_staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`prospect_id`) REFERENCES `prospective_customers` (`prospect_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 6. SYSTEM & LOGS
-- --------------------------------------------------------

-- User sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_id` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`session_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Audit logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Two-factor codes
CREATE TABLE IF NOT EXISTS `two_factor_codes` (
  `code_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `code_type` enum('Email','SMS') NOT NULL,
  `expires_at` timestamp NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`code_id`),
  CONSTRAINT `two_factor_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notification types
CREATE TABLE IF NOT EXISTS `notification_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(50) NOT NULL,
  `template_subject` varchar(200) DEFAULT NULL,
  `template_body` text DEFAULT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_via` enum('Email','SMS','In_App') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `notification_types` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pending dues
CREATE TABLE IF NOT EXISTS `pending_dues` (
  `due_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `due_type` enum('Credit_Card','Loan','Fee','Penalty') NOT NULL,
  `due_amount` decimal(15,2) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Pending','Partially_Paid','Paid','Overdue') DEFAULT 'Pending',
  `penalty_applied` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_reminder_sent` date DEFAULT NULL,
  PRIMARY KEY (`due_id`),
  CONSTRAINT `pending_dues_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `pending_dues_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 7. INITIAL LOOKUP DATA
-- --------------------------------------------------------

INSERT INTO `account_types` (`type_name`, `description`, `minimum_balance`, `interest_rate`, `monthly_fee`) VALUES
('Current', 'Transactional account with no interest', 5000.00, 0.00, 500.00),
('Saving', 'High-yield savings account', 1000.00, 5.50, 100.00),
('Student', 'Zero balance student account', 0.00, 3.00, 0.00),
('Business', 'Commercial business account', 10000.00, 2.50, 1000.00),
('Islamic', 'Sharia profit sharing account', 5000.00, 0.00, 200.00);

INSERT INTO `transaction_types` (`type_name`, `category`) VALUES
('Deposit', 'Credit'),
('Withdrawal', 'Debit'),
('Transfer', 'Debit'),
('Received Transfer', 'Credit'),
('Bill Payment', 'Debit');

INSERT INTO `card_types` (`type_name`, `card_category`, `annual_fee`, `credit_limit`, `benefits`) VALUES
('Debit', 'Standard', 0.00, 0.00, 'Basic ATM access'),
('Debit', 'Gold', 1000.00, 0.00, 'Lounge access + Higher limits'),
('Credit', 'Platinum', 5000.00, 500000.00, 'Luxury travel perks');

INSERT INTO `complaint_categories` (`category_name`, `sla_hours`) VALUES
('Transaction Issue', 24),
('Card Problem', 12),
('Account Issue', 48),
('Online Banking', 24),
('Other', 72);

-- --------------------------------------------------------
-- 8. SAMPLE USERS AND ACCOUNTS (Plain-Text: 123)
-- --------------------------------------------------------

-- CUSTOMER: Ali Bhatti
INSERT INTO `users` (`cnic`, `username`, `password_hash`, `full_name`, `date_of_birth`, `contact_number`, `email`, `gender`, `user_type`) VALUES
('1234567890123', 'ali_bhatti', '123', 'Ali Bhatti', '1995-01-01', '03001234567', 'ali@example.com', 'Male', 'Customer'),
('1122334455667', 'sara_khan', '123', 'Sara Khan', '1997-03-12', '03219876543', 'sara@example.com', 'Female', 'Customer');

-- STAFF: Salman Admin
INSERT INTO `users` (`cnic`, `username`, `password_hash`, `full_name`, `date_of_birth`, `contact_number`, `email`, `gender`, `user_type`) VALUES
('9999999999999', 'admin', '123', 'Salman Admin', '1980-01-10', '03511234567', 'salman.ahmed@nextgenbank.com', 'Male', 'Staff'),
('1111122223333', 'accountant', '123', 'Ahmad Accountant', '1992-05-15', '03217654321', 'ahmad@nextgenbank.com', 'Male', 'Staff'),
('2222233334444', 'cashier', '123', 'Mubashir Cashier', '1995-12-20', '03120000000', 'mubashir@nextgenbank.com', 'Male', 'Staff'),
('3333344445555', 'support', '123', 'Saira Support', '1998-03-30', '03450000001', 'saira@nextgenbank.com', 'Female', 'Staff');

-- STAFF Details
INSERT INTO `staff` (`user_id`, `employee_id`, `staff_role`, `department`, `hire_date`, `salary`) VALUES
((SELECT user_id FROM users WHERE username = 'admin'), 'EMP-ADM-001', 'Admin', 'Management', '2018-03-15', 250000.00),
((SELECT user_id FROM users WHERE username = 'accountant'), 'EMP-ACC-002', 'Accountant', 'Accounts', '2020-01-10', 85000.00),
((SELECT user_id FROM users WHERE username = 'cashier'), 'EMP-CSH-003', 'Cashier', 'Operations', '2021-06-12', 65000.00),
((SELECT user_id FROM users WHERE username = 'support'), 'EMP-SUP-004', 'Complain_Handler', 'Customer Service', '2022-09-01', 55000.00);

-- Customer Primary Account
INSERT INTO `accounts` (`account_number`, `user_id`, `type_id`, `current_balance`, `available_balance`, `opening_date`, `branch_code`) VALUES
('ACC-NGB-1001', (SELECT user_id FROM users WHERE username = 'ali_bhatti'), (SELECT type_id FROM account_types WHERE type_name = 'Saving'), 1240500.00, 1240500.00, '2024-01-15', 'BR-LHR-01'),
('ACC-NGB-1002', (SELECT user_id FROM users WHERE username = 'sara_khan'), (SELECT type_id FROM account_types WHERE type_name = 'Current'), 50000.00, 50000.00, '2024-02-01', 'BR-ISL-02');

-- Transfer Limits for Ali Bhatti
INSERT INTO `transfer_limits` (`account_id`, `daily_limit`, `weekly_limit`, `monthly_limit`, `per_transaction_limit`, `last_reset_date`) VALUES
((SELECT account_id FROM accounts WHERE account_number = 'ACC-NGB-1001'), 200000.00, 1000000.00, 5000000.00, 100000.00, CURDATE());

-- Sample Active Card
INSERT INTO `cards` (`card_number`, `account_id`, `user_id`, `card_type_id`, `expiry_date`, `cvv_hash`, `pin_hash`, `status`, `issue_date`, `daily_spending_limit`) VALUES
('4111222233334444', (SELECT account_id FROM accounts WHERE account_number = 'ACC-NGB-1001'), (SELECT user_id FROM users WHERE username = 'ali_bhatti'), 2, '2027-12-31', '123', '1234', 'Active', '2024-01-20', 50000.00);

-- Sample Data for Accountant (Pending Requests)
INSERT INTO `prospective_customers` (`cnic`, `full_name`, `father_name`, `date_of_birth`, `address`, `contact_number`, `email`, `gender`, `requested_account_type_id`, `form_number`, `status`) VALUES
('5440112345671', 'Test User One', 'Father One', '2000-01-01', 'Street 1, Lahore', '03000000001', 'test1@example.com', 'Male', 1, 'FORM-2026-0001', 'Pending'),
('5440112345672', 'Test User Two', 'Father Two', '1998-05-10', 'Street 2, Karachi', '03000000002', 'test2@example.com', 'Female', 2, 'FORM-2026-0002', 'Pending');

-- Sample Data for Support (Complaints)
INSERT INTO `complaints` (`ticket_number`, `user_id`, `category_id`, `title`, `description`, `status`) VALUES
('TK-100501', (SELECT user_id FROM users WHERE username = 'ali_bhatti'), 1, 'ATM Transaction Failed', 'Money deducted but not dispensed at Mall Road ATM.', 'Open'),
('TK-100502', (SELECT user_id FROM users WHERE username = 'ali_bhatti'), 2, 'Card PIN Reset', 'Need a new physical PIN for my Gold debit card.', 'In_Progress');

-- Sample Pending Dues
INSERT INTO `pending_dues` (`user_id`, `account_id`, `due_type`, `due_amount`, `original_amount`, `due_date`, `status`, `description`) VALUES
((SELECT user_id FROM users WHERE username = 'ali_bhatti'), (SELECT account_id FROM accounts WHERE account_number = 'ACC-NGB-1001'), 'Fee', 1200.00, 1200.00, CURDATE() + INTERVAL 15 DAY, 'Pending', 'Annual Card Maintenance Fee'),
((SELECT user_id FROM users WHERE username = 'ali_bhatti'), (SELECT account_id FROM accounts WHERE account_number = 'ACC-NGB-1001'), 'Penalty', 450.00, 450.00, CURDATE() + INTERVAL 5 DAY, 'Pending', 'Late Payment Surcharge');

COMMIT;
