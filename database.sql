CREATE DATABASE IF NOT EXISTS store_pk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE store_pk;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    tone VARCHAR(30) NOT NULL DEFAULT 'rose',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    tone VARCHAR(30) NOT NULL DEFAULT 'rose',
    tag VARCHAR(30) NOT NULL DEFAULT 'NEW',
    symbol VARCHAR(10) NOT NULL DEFAULT '*',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_collections_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    title VARCHAR(120) NOT NULL,
    marquee VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    section_slug VARCHAR(60) NOT NULL DEFAULT '',
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(170) NULL,
    short_name VARCHAR(60) NOT NULL,
    description TEXT NULL,
    tag VARCHAR(30) NOT NULL DEFAULT 'NEW',
    tone VARCHAR(30) NOT NULL DEFAULT 'rose',
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    compare_price DECIMAL(10,2) NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    availability VARCHAR(20) NOT NULL DEFAULT 'in_stock',
    image_path VARCHAR(255) NULL,
    is_sold_out TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_category_id (category_id),
    INDEX idx_products_section_slug (section_slug),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_category_name (category_id, name(120)),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_faqs_question (question(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_admin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    INDEX idx_cart_user_id (user_id),
    INDEX idx_cart_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
    payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    recipient_name VARCHAR(140) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(160) NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    city VARCHAR(80) NOT NULL,
    province VARCHAR(80) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_order_number (order_number),
    INDEX idx_orders_user_id (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(180) NOT NULL,
    category_name VARCHAR(120) NOT NULL DEFAULT '',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT '',
    admin_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_purchase_entries_product_id (product_id),
    INDEX idx_purchase_entries_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, slug, tone, sort_order)
VALUES
('Anklets', 'anklets', 'sand', 1),
('Bags', 'bags', 'rose', 2),
('Bangles', 'bangles', 'peach', 3),
('Bracelets', 'bracelets', 'sand', 4),
('Branded Makeup', 'branded-makeup', 'mist', 5),
('Deals', 'deals', 'mint', 6),
('Eid Special', 'eid-special', 'peach', 7),
('Fragrances', 'fragrances', 'sand', 8),
('Hair Accessories', 'hair-accessories', 'rose', 9),
('Jewellery', 'jewelry', 'peach', 10),
('Jewellery Organizers', 'jewellery-organizers', 'mist', 11),
('Makeup', 'makeup', 'peach', 12),
('Nails', 'nails', 'rose', 13),
('Necklaces', 'necklaces', 'sand', 14),
('New Arrivals', 'new-arrivals', 'mist', 15),
('Pouches', 'pouches', 'mint', 16),
('Printables', 'printables', 'mist', 17),
('Ramadan Special', 'ramadan-special', 'peach', 18),
('Rings', 'rings', 'sand', 19)
ON DUPLICATE KEY UPDATE
    tone = VALUES(tone),
    sort_order = VALUES(sort_order);

INSERT INTO sections (slug, title, marquee, sort_order)
VALUES
('makeup', 'Makeup', '* You Glow Girl * You Glow Girl * You Glow Girl *', 1),
('nails', 'Nails', '', 2),
('jewelry', 'Jewellery', '', 3)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    marquee = VALUES(marquee),
    sort_order = VALUES(sort_order);

INSERT INTO collections (name, tone, tag, symbol, sort_order)
VALUES
('Nail Art', 'rose', 'HOT', '*', 1),
('Get Ready', 'sand', 'NEW', '#', 2),
('Wedding Special', 'peach', 'SAVE', '*', 3),
('Jewelry', 'rose', 'TREND', '+', 4),
('Skincare Products', 'mist', 'NEW', '*', 5),
('Perfume Collection', 'mint', 'TOP', '#', 6),
('Self-care Kit', 'peach', 'KIT', '*', 7),
('LS Store.pk', 'sand', 'NEW', '+', 8),
('Jewelry Box', 'mist', 'TOP', '#', 9),
('Skincare', 'rose', 'SAVE', '*', 10),
('Self-love', 'peach', 'NEW', '+', 11),
('Nails', 'mint', 'BEST', '*', 12),
('Makeup Branding', 'sand', 'HOT', '#', 13),
('Lotion', 'peach', 'NEW', '*', 14),
('Bags', 'rose', 'TOP', '+', 15),
('Mailing', 'mist', 'NEW', '#', 16),
('Warmth', 'sand', 'GLOW', '*', 17)
ON DUPLICATE KEY UPDATE
    tone = VALUES(tone),
    tag = VALUES(tag),
    symbol = VALUES(symbol),
    sort_order = VALUES(sort_order);

INSERT INTO products (
    category_id, section_slug, name, slug, short_name, description, tag, tone,
    price, compare_price, cost_price, stock_qty, availability, image_path, is_sold_out, is_active, sort_order
)
VALUES
((SELECT id FROM categories WHERE slug = 'makeup' LIMIT 1), 'makeup', 'Flawless Glow Kit', 'flawless-glow-kit', 'Glow Kit', 'Blend-ready kit for daily glam.', 'SALE', 'rose', 2450, 2990, 1700, 45, 'in_stock', NULL, 0, 1, 1),
((SELECT id FROM categories WHERE slug = 'makeup' LIMIT 1), 'makeup', 'Soft Matte Set', 'soft-matte-set', 'Matte Set', 'Soft matte essentials for long wear.', 'HOT', 'peach', 2990, 3490, 2050, 34, 'in_stock', NULL, 0, 1, 2),
((SELECT id FROM categories WHERE slug = 'makeup' LIMIT 1), 'makeup', 'Pink Blend Palette', 'pink-blend-palette', 'Palette', 'Warm and cool pink shades.', 'SAVE', 'mist', 3290, 3890, 2300, 22, 'in_stock', NULL, 0, 1, 3),
((SELECT id FROM categories WHERE slug = 'makeup' LIMIT 1), 'makeup', 'Mini Lip Tint Pack', 'mini-lip-tint-pack', 'Lip Tint', 'Pocket size lip tint set.', 'NEW', 'sand', 1590, 1990, 900, 55, 'in_stock', NULL, 0, 1, 4),
((SELECT id FROM categories WHERE slug = 'makeup' LIMIT 1), 'makeup', 'Classic Brush Set', 'classic-brush-set', 'Brushes', 'Daily blend and contour brushes.', 'TOP', 'mint', 2090, 2490, 1250, 28, 'in_stock', NULL, 0, 1, 5),
((SELECT id FROM categories WHERE slug = 'nails' LIMIT 1), 'nails', 'Press-On Nail Box', 'press-on-nail-box', 'Nail Box', 'Easy salon look at home.', 'HOT', 'sand', 1950, 2390, 1200, 30, 'in_stock', NULL, 0, 1, 1),
((SELECT id FROM categories WHERE slug = 'nails' LIMIT 1), 'nails', 'French Tips Kit', 'french-tips-kit', 'French Tips', 'Clean french style set.', 'NEW', 'mist', 1790, 2190, 1080, 38, 'in_stock', NULL, 0, 1, 2),
((SELECT id FROM categories WHERE slug = 'nails' LIMIT 1), 'nails', 'Salon Effect Nails', 'salon-effect-nails', 'Salon Kit', 'Premium reusable nails.', 'SALE', 'rose', 2290, 2690, 1420, 18, 'in_stock', NULL, 0, 1, 3),
((SELECT id FROM categories WHERE slug = 'jewelry' LIMIT 1), 'jewelry', 'Pearl Charm Ring Set', 'pearl-charm-ring-set', 'Ring Set', 'Elegant pearl and charm rings.', 'HOT', 'rose', 999, 1390, 520, 65, 'in_stock', NULL, 0, 1, 1),
((SELECT id FROM categories WHERE slug = 'jewelry' LIMIT 1), 'jewelry', 'Elegant Chain Duo', 'elegant-chain-duo', 'Chain Duo', 'Minimal everyday chain set.', 'NEW', 'peach', 1450, 1890, 760, 44, 'in_stock', NULL, 0, 1, 2),
((SELECT id FROM categories WHERE slug = 'jewelry' LIMIT 1), 'jewelry', 'Gold Touch Bracelet', 'gold-touch-bracelet', 'Bracelet', 'Layered bracelet for festive wear.', 'TOP', 'sand', 1190, 1490, 640, 52, 'in_stock', NULL, 0, 1, 3)
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    short_name = VALUES(short_name),
    description = VALUES(description),
    tag = VALUES(tag),
    tone = VALUES(tone),
    price = VALUES(price),
    compare_price = VALUES(compare_price),
    cost_price = VALUES(cost_price),
    stock_qty = VALUES(stock_qty),
    availability = VALUES(availability),
    is_sold_out = VALUES(is_sold_out),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

INSERT INTO faqs (question, answer, sort_order)
VALUES
('What are the delivery charges?', 'Delivery charges are calculated at checkout based on your city and order size.', 1),
('Is this payment in advance?', 'You can choose bank transfer, wallet, or cash on delivery where available.', 2),
('When will I get my order?', 'Major cities are usually delivered within 2-4 business days and other areas within 4-7 days.', 3),
('Can I return or exchange my order?', 'Eligible products can be exchanged within 7 days if they are unused and in original condition.', 4)
ON DUPLICATE KEY UPDATE
    answer = VALUES(answer),
    sort_order = VALUES(sort_order);
