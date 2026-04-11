-- Create Database
CREATE DATABASE IF NOT EXISTS grocery_billing;
USE grocery_billing;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Users (password: 123456)
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Store Manager', 'manager'),
('cashier1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier One', 'cashier'),
('cashier2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier Two', 'cashier');

-- Products Table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(20) DEFAULT 'kg',
    category VARCHAR(50),
    current_stock DECIMAL(10,3) DEFAULT 0,
    min_stock_alert DECIMAL(10,3) DEFAULT 10,
    purchase_price DECIMAL(10,2),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Pricing Tiers Table (The magic for wholesale/retail volume pricing)
CREATE TABLE pricing_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    customer_type ENUM('wholesale', 'retail') NOT NULL,
    min_quantity DECIMAL(10,3) NOT NULL,
    max_quantity DECIMAL(10,3) NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    package_price DECIMAL(10,2) NULL COMMENT 'Fixed price for exact quantity',
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_type (product_id, customer_type)
);

-- Invoices Table
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_no VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(15),
    customer_type ENUM('wholesale', 'retail') NOT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('cash', 'card', 'upi', 'credit') DEFAULT 'cash',
    payment_status ENUM('paid', 'pending', 'cancelled') DEFAULT 'paid',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Invoice Items Table
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    tier_info VARCHAR(100) COMMENT 'Which pricing tier was applied',
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Stock Movements Table
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    movement_type ENUM('purchase', 'sale', 'adjustment', 'return') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reference_no VARCHAR(50),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Store Settings Table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('store_name', 'My Grocery Store'),
('store_address', '123 Main Street, City'),
('store_phone', '9876543210'),
('store_gst', ''),
('invoice_prefix', 'INV-'),
('receipt_header', 'Thank you for shopping!'),
('receipt_footer', 'Goods once sold cannot be returned'),
('low_stock_alert', '10'),
('currency_symbol', '₹'),
('thermal_printer_width', '72');

-- Sample Products
INSERT INTO products (code, name, unit, category, current_stock, purchase_price) VALUES
('SUG001', 'Sugar', 'kg', 'Groceries', 500, 130),
('RIC001', 'Basmati Rice', 'kg', 'Groceries', 300, 80),
('WHE001', 'Wheat Flour', 'kg', 'Groceries', 400, 35),
('OIL001', 'Cooking Oil', 'liter', 'Groceries', 200, 110),
('DAL001', 'Toor Dal', 'kg', 'Pulses', 150, 120);

-- Sample Pricing Tiers for Sugar
INSERT INTO pricing_tiers (product_id, customer_type, min_quantity, max_quantity, price_per_unit, package_price) VALUES
(1, 'wholesale', 1, 49, 140, NULL),
(1, 'wholesale', 50, 99, 138, 6900),
(1, 'wholesale', 100, NULL, 135, 13500),
(1, 'retail', 1, 49, 150, NULL),
(1, 'retail', 50, NULL, 148, NULL);

-- Create invoice number sequence
DELIMITER //
CREATE FUNCTION generate_invoice_no() RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE next_no INT;
    DECLARE prefix VARCHAR(10);
    
    SELECT setting_value INTO prefix FROM settings WHERE setting_key = 'invoice_prefix';
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_no, LENGTH(prefix) + 1) AS UNSIGNED)), 0) + 1 
    INTO next_no FROM invoices;
    
    RETURN CONCAT(prefix, LPAD(next_no, 6, '0'));
END//
DELIMITER ;

-- Trigger to update stock after sale
DELIMITER //
CREATE TRIGGER after_invoice_item_insert
AFTER INSERT ON invoice_items
FOR EACH ROW
BEGIN
    -- Update product stock
    UPDATE products 
    SET current_stock = current_stock - NEW.quantity 
    WHERE id = NEW.product_id;
    
    -- Record stock movement
    INSERT INTO stock_movements (product_id, movement_type, quantity, reference_no, created_by)
    SELECT NEW.product_id, 'sale', -NEW.quantity, i.invoice_no, i.created_by
    FROM invoices i WHERE i.id = NEW.invoice_id;
END//
DELIMITER ;