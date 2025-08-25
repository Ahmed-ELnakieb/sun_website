-- Sun Trading Company - Complete Database Schema with Current Data
-- Updated with all product images and linkings
-- Run this script to create the required database and tables with current data

CREATE DATABASE IF NOT EXISTS sun_trading_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sun_trading_admin;

-- Admin users table for authentication
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table for managing website products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    category VARCHAR(100),
    price DECIMAL(10,2) DEFAULT NULL,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product images table for managing product galleries
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    alt_text_ar VARCHAR(255),
    alt_text_en VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Site settings table for logos, content, and configurations
CREATE TABLE site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'image', 'json', 'boolean') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Website content table for managing dynamic content
CREATE TABLE website_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) UNIQUE NOT NULL,
    title_ar VARCHAR(255),
    title_en VARCHAR(255),
    content_ar TEXT,
    content_en TEXT,
    meta_description_ar VARCHAR(300),
    meta_description_en VARCHAR(300),
    page_section VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- File uploads table for tracking all uploaded files
CREATE TABLE file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    upload_category ENUM('logo', 'product', 'content', 'general') DEFAULT 'general',
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Activity logs table for tracking admin actions
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@suntrading.com', '$2y$10$.UpP1e/aWSAWm5RuyWZGtuiuAEPYJ93uojjly4gL1GbjsixsYfhr2', 'Administrator', 'admin');

-- Insert default site settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_logo', 'images/logo.png', 'image', 'branding', 'Main website logo'),
('company_name_ar', 'شركة الشمس للتصدير والاستيراد', 'text', 'company', 'Company name in Arabic'),
('company_name_en', 'Sun Trading Company', 'text', 'company', 'Company name in English'),
('contact_email', 'info@suntrading.com', 'text', 'contact', 'Main contact email'),
('contact_phone', '+1234567890', 'text', 'contact', 'Main contact phone'),
('enable_maintenance', 'false', 'boolean', 'system', 'Enable maintenance mode'),
('default_theme', 'golden', 'text', 'appearance', 'Default website theme');

-- Insert sample website content
INSERT INTO website_content (content_key, title_ar, title_en, content_ar, content_en, page_section) VALUES
('hero_title', 'شركة الشمس للتصدير والاستيراد', 'Sun Trading Company', 'شريككم الموثوق في الاستيراد والتصدير', 'Your trusted partner in import and export', 'hero'),
('about_title', 'عن الشركة', 'About Us', 'نحن شركة رائدة في مجال التجارة الدولية', 'We are a leading company in international trade', 'about'),
('contact_title', 'اتصل بنا', 'Contact Us', 'نحن هنا لخدمتكم', 'We are here to serve you', 'contact');

-- Insert all current products with complete data
INSERT INTO products (id, name_ar, name_en, description_ar, description_en, category, is_featured, is_active) VALUES
(1, 'قمح', 'Wheat', 'قمح عالي الجودة للتصدير', 'High quality wheat for export', 'grains', TRUE, TRUE),
(2, 'أرز', 'Rice', 'أرز بسمتي فاخر', 'Premium basmati rice', 'grains', TRUE, TRUE),
(3, 'ذرة', 'Corn', 'ذرة صفراء طبيعية', 'Natural yellow corn', 'grains', TRUE, TRUE),
(4, 'فاصوليا بيضاء', 'White Beans', 'فاصوليا بيضاء عالية الجودة', 'High quality white beans', 'legumes', TRUE, TRUE),
(5, 'لوبيا', 'Black Eyed Peas', 'لوبيا طبيعية ومغذية', 'Natural and nutritious black eyed peas', 'legumes', TRUE, TRUE),
(6, 'حمص', 'Chickpeas', 'حمص طبيعي عالي البروتين', 'Natural high protein chickpeas', 'legumes', TRUE, TRUE),
(7, 'فول', 'Fava Beans', 'فول مدمس عالي الجودة', 'High quality fava beans', 'legumes', TRUE, TRUE),
(8, 'عدس', 'Lentils', 'عدس أحمر وأصفر متنوع', 'Variety of red and yellow lentils', 'legumes', TRUE, TRUE);

-- Insert product images in file_uploads table
INSERT INTO file_uploads (id, original_name, file_name, file_path, file_type, file_size, upload_category) VALUES
(1, 'wheat.png', 'wheat.png', 'images/products/wheat.png', 'image/png', 45000, 'product'),
(2, 'rice.png', 'rice.png', 'images/products/rice.png', 'image/png', 42000, 'product'),
(3, 'zora.png', 'zora.png', 'images/products/zora.png', 'image/png', 48000, 'product'),
(4, 'fasolia.png', 'fasolia.png', 'images/products/fasolia.png', 'image/png', 46000, 'product'),
(5, 'lobia.png', 'lobia.png', 'images/products/lobia.png', 'image/png', 44000, 'product'),
(6, 'homos.png', 'homos.png', 'images/products/homos.png', 'image/png', 47000, 'product'),
(7, 'fol.png', 'fol.png', 'images/products/fol.png', 'image/png', 45000, 'product'),
(8, 'ads.png', 'ads.png', 'images/products/ads.png', 'image/png', 43000, 'product'),
(9, 'default.png', 'default.png', 'images/products/default.png', 'image/png', 45000, 'product');

-- Insert product images linkages (primary images for each product)
INSERT INTO product_images (product_id, image_path, image_name, alt_text_en, alt_text_ar, is_primary, sort_order, file_size) VALUES
(1, 'images/products/wheat.png', 'wheat.png', 'Wheat', 'قمح', 1, 0, 45000),
(2, 'images/products/rice.png', 'rice.png', 'Rice', 'أرز', 1, 0, 42000),
(3, 'images/products/zora.png', 'zora.png', 'Corn', 'ذرة', 1, 0, 48000),
(4, 'images/products/fasolia.png', 'fasolia.png', 'White Beans', 'فاصوليا بيضاء', 1, 0, 46000),
(5, 'images/products/lobia.png', 'lobia.png', 'Black Eyed Peas', 'لوبيا', 1, 0, 44000),
(6, 'images/products/homos.png', 'homos.png', 'Chickpeas', 'حمص', 1, 0, 47000),
(7, 'images/products/fol.png', 'fol.png', 'Fava Beans', 'فول', 1, 0, 45000),
(8, 'images/products/ads.png', 'ads.png', 'Lentils', 'عدس', 1, 0, 43000);

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_products_featured ON products(is_featured);
CREATE INDEX idx_product_images_product ON product_images(product_id);
CREATE INDEX idx_site_settings_key ON site_settings(setting_key);
CREATE INDEX idx_content_section ON website_content(page_section);
CREATE INDEX idx_file_uploads_category ON file_uploads(upload_category);
CREATE INDEX idx_activity_logs_user ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);

-- Set AUTO_INCREMENT values to continue from current data
ALTER TABLE products AUTO_INCREMENT = 9;
ALTER TABLE file_uploads AUTO_INCREMENT = 10;
ALTER TABLE product_images AUTO_INCREMENT = 9;