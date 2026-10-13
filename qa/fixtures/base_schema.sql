SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  logo VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(30) NULL,
  address TEXT NULL,
  receipt_width ENUM('58','80') NOT NULL DEFAULT '80',
  receipt_printer_type ENUM('browser','network','usb','bluetooth') NOT NULL DEFAULT 'browser',
  receipt_printer_name VARCHAR(150) NULL,
  receipt_printer_host VARCHAR(150) NULL,
  receipt_printer_port INT UNSIGNED NOT NULL DEFAULT 9100,
  receipt_autoprint TINYINT(1) NOT NULL DEFAULT 0,
  receipt_bridge_token VARCHAR(128) NULL,
  map_embed TEXT NULL,
  facebook_url VARCHAR(255) NULL,
  instagram_url VARCHAR(255) NULL,
  tiktok_url VARCHAR(255) NULL,
  business_hours TEXT NULL,
  payment_methods JSON NULL,
  delivery_types JSON NULL,
  catalog_label ENUM('Catálogo','Menú') NOT NULL DEFAULT 'Catálogo',
  catalog_mode ENUM('global','branches') NOT NULL DEFAULT 'global',
  physical_store_enabled TINYINT(1) NOT NULL DEFAULT 0,
  primary_color VARCHAR(10) NOT NULL DEFAULT '#1769e0',
  accent_color VARCHAR(10) NOT NULL DEFAULT '#25d366',
  currency VARCHAR(5) NOT NULL DEFAULT 'CRC',
  tax_included TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_listed TINYINT(1) NOT NULL DEFAULT 1,
  offline_message VARCHAR(255) NOT NULL DEFAULT 'Ahorita no estamos, pronto llegaremos',
  plan VARCHAR(50) NOT NULL DEFAULT 'Profesional',
  expires_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant_active (is_active,is_listed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  whatsapp VARCHAR(30) NULL,
  phone VARCHAR(40) NULL,
  address TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_branch_slug (tenant_id,slug),
  INDEX idx_branch_tenant (tenant_id,is_active,is_default),
  CONSTRAINT fk_branch_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('master','tenant_admin','manager','editor','kitchen','cashier','driver','customer') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  force_password_change TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_user_tenant_role (tenant_id,role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_content (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  section_key VARCHAR(100) NOT NULL,
  title VARCHAR(255) NULL,
  body TEXT NULL,
  image VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_tenant_branch_content (tenant_id,branch_id,section_key,sort_order),
  CONSTRAINT fk_content_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  printer_target ENUM('receipt','kitchen','bar','dispatch','copy') NOT NULL DEFAULT 'kitchen',
  UNIQUE KEY uq_category (tenant_id,slug),
  CONSTRAINT fk_category_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  supplier_id BIGINT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  sku VARCHAR(80) NULL,
  description TEXT NULL,
  characteristics TEXT NULL,
  price DECIMAL(13,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(13,2) NULL,
  cost DECIMAL(15,4) NOT NULL DEFAULT 0,
  track_inventory TINYINT(1) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  unit_label VARCHAR(40) NOT NULL DEFAULT 'unidad',
  min_qty DECIMAL(10,3) NOT NULL DEFAULT 1,
  qty_step DECIMAL(10,3) NOT NULL DEFAULT 1,
  decimals TINYINT UNSIGNED NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  gallery JSON NULL,
  badge VARCHAR(60) NULL,
  product_type ENUM('simple','combo') NOT NULL DEFAULT 'simple',
  status ENUM('active','hidden','sold_out') NOT NULL DEFAULT 'active',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_slug (tenant_id,slug),
  INDEX idx_product_catalog (tenant_id,status,category_id),
  CONSTRAINT fk_product_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_products (
  branch_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  price_override DECIMAL(13,2) NULL,
  sale_price_override DECIMAL(13,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (branch_id,product_id),
  INDEX idx_branch_product (product_id,branch_id,is_active),
  CONSTRAINT fk_branch_product_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT fk_branch_product_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_option_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('single','multiple') NOT NULL DEFAULT 'single',
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  min_select INT UNSIGNED NOT NULL DEFAULT 0,
  max_select INT UNSIGNED NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_option_groups_product (tenant_id,product_id,is_active,sort_order),
  CONSTRAINT fk_option_group_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_option_group_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_delta DECIMAL(13,2) NOT NULL DEFAULT 0,
  linked_product_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_options_group (group_id,is_active,sort_order),
  CONSTRAINT fk_option_group FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_option_linked_product FOREIGN KEY (linked_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_zones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(13,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(13,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_zone (tenant_id,name),
  CONSTRAINT fk_zone_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  type ENUM('fixed','percent') NOT NULL DEFAULT 'percent',
  value DECIMAL(13,2) NOT NULL,
  min_order DECIMAL(13,2) NOT NULL DEFAULT 0,
  max_uses INT NULL,
  uses_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_coupon (tenant_id,code),
  CONSTRAINT fk_coupon_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_number VARCHAR(30) NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  customer_id_number VARCHAR(40) NULL,
  address TEXT NULL,
  address_reference TEXT NULL,
  scheduled_at DATETIME NULL,
  delivery_zone_id BIGINT UNSIGNED NULL,
  delivery_type ENUM('delivery','pickup','coordinated') NOT NULL DEFAULT 'delivery',
  payment_method VARCHAR(60) NOT NULL,
  coupon_code VARCHAR(60) NULL,
  subtotal DECIMAL(13,2) NOT NULL,
  tax_total DECIMAL(13,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(13,2) NOT NULL DEFAULT 0,
  delivery_total DECIMAL(13,2) NOT NULL DEFAULT 0,
  total DECIMAL(13,2) NOT NULL,
  status ENUM('new','confirmed','preparing','ready','sent','delivered','cancelled') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_number (tenant_id,order_number),
  INDEX idx_order_report (tenant_id,created_at,status),
  CONSTRAINT fk_order_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_order_zone FOREIGN KEY (delivery_zone_id) REFERENCES delivery_zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(190) NOT NULL,
  quantity DECIMAL(10,3) NOT NULL,
  unit_label VARCHAR(40) NOT NULL,
  unit_price DECIMAL(13,2) NOT NULL,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  line_subtotal DECIMAL(13,2) NOT NULL,
  line_tax DECIMAL(13,2) NOT NULL DEFAULT 0,
  options_json JSON NULL,
  notes TEXT NULL,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  media_url VARCHAR(255) NULL,
  media_type ENUM('image','video','link') NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'published',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_posts (tenant_id,status,published_at),
  CONSTRAINT fk_post_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  subject VARCHAR(190) NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','resolved') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_contact_tenant (tenant_id,created_at),
  CONSTRAINT fk_contact_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_hash CHAR(64) NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit (tenant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_sequences (
  tenant_id BIGINT UNSIGNED PRIMARY KEY,
  next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sequence_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  bucket_key CHAR(64) PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  blocked_until DATETIME NULL,
  INDEX idx_rate_limit_cleanup (window_started_at,blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL,
  address TEXT NOT NULL,
  reference TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer_addresses (user_id,is_default),
  CONSTRAINT fk_address_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_reset_expiry (expires_at,used_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_verification_expiry (expires_at,used_at),
  CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  status ENUM('sent','failed') NOT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mail_logs (tenant_id,created_at,status),
  CONSTRAINT fk_mail_log_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  query VARCHAR(190) NOT NULL,
  result_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_search_report (tenant_id,created_at,result_count),
  CONSTRAINT fk_search_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('catalog_view','checkout_completed') NOT NULL,
  session_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_report (tenant_id,created_at,event_type),
  CONSTRAINT fk_event_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  price DECIMAL(13,2) NOT NULL DEFAULT 0,
  billing_period ENUM('monthly','annual','custom') NOT NULL DEFAULT 'monthly',
  limits_json JSON NULL,
  status ENUM('active','hidden','archived') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_modules (
  plan_id BIGINT UNSIGNED NOT NULL,
  module_key VARCHAR(60) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY(plan_id,module_key),
  CONSTRAINT fk_plan_module_plan FOREIGN KEY(plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_modules (
  tenant_id BIGINT UNSIGNED NOT NULL,
  module_key VARCHAR(60) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  limits_json JSON NULL,
  PRIMARY KEY(tenant_id,module_key),
  CONSTRAINT fk_tenant_module_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  mode ENUM('points','purchases','spend') NOT NULL DEFAULT 'points',
  earn_value DECIMAL(13,2) NOT NULL DEFAULT 100,
  goal_value DECIMAL(13,2) NOT NULL DEFAULT 10,
  reward_type ENUM('percent','fixed','product') NOT NULL DEFAULT 'percent',
  reward_value DECIMAL(13,2) NOT NULL DEFAULT 10,
  reward_product_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_loyalty_program_scope(tenant_id,branch_id),
  INDEX idx_loyalty_program(tenant_id,is_active,branch_id),
  CONSTRAINT fk_loyalty_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_product FOREIGN KEY(reward_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  points_balance DECIMAL(13,2) NOT NULL DEFAULT 0,
  purchases_balance INT UNSIGNED NOT NULL DEFAULT 0,
  spend_balance DECIMAL(13,2) NOT NULL DEFAULT 0,
  rewards_available INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_loyalty_account(tenant_id,user_id),
  CONSTRAINT fk_loyalty_account_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_account_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  event ENUM('earn','redeem','adjust','reverse') NOT NULL,
  points_delta DECIMAL(13,2) NOT NULL DEFAULT 0,
  purchases_delta INT NOT NULL DEFAULT 0,
  spend_delta DECIMAL(13,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_loyalty_order_event(order_id,event),
  INDEX idx_loyalty_ledger(account_id,created_at),
  CONSTRAINT fk_loyalty_ledger_account FOREIGN KEY(account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_ledger_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL UNIQUE,
  reward_type ENUM('percent','fixed','product') NOT NULL,
  reward_value DECIMAL(13,2) NOT NULL DEFAULT 0,
  reward_product_id BIGINT UNSIGNED NULL,
  status ENUM('available','used','expired','cancelled') NOT NULL DEFAULT 'available',
  expires_at DATETIME NOT NULL,
  used_order_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loyalty_rewards(account_id,status,expires_at),
  CONSTRAINT fk_reward_account FOREIGN KEY(account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_reward_program FOREIGN KEY(program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_reward_product FOREIGN KEY(reward_product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_reward_order FOREIGN KEY(used_order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  vehicle_type ENUM('motorcycle','car','bicycle','other') NOT NULL DEFAULT 'motorcycle',
  plate VARCHAR(30) NULL,
  identity_number VARCHAR(50) NULL,
  service_area VARCHAR(190) NULL,
  sinpe_phone VARCHAR(40) NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  is_online TINYINT(1) NOT NULL DEFAULT 0,
  rating DECIMAL(3,2) NOT NULL DEFAULT 5,
  completed_deliveries INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_driver_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_affiliations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending_driver','pending_store','active','paused','rejected','blocked') NOT NULL DEFAULT 'pending_store',
  requested_by ENUM('driver','tenant','master','migration') NOT NULL DEFAULT 'driver',
  all_branches TINYINT(1) NOT NULL DEFAULT 1,
  invited_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_driver_affiliation(user_id,tenant_id),
  INDEX idx_affiliation_tenant(tenant_id,status,user_id),
  INDEX idx_affiliation_driver(user_id,status,tenant_id),
  CONSTRAINT fk_affiliation_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_affiliation_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_affiliation_inviter FOREIGN KEY(invited_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_affiliation_branches (
  affiliation_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(affiliation_id,branch_id),
  CONSTRAINT fk_affiliation_branch_affiliation FOREIGN KEY(affiliation_id) REFERENCES driver_affiliations(id) ON DELETE CASCADE,
  CONSTRAINT fk_affiliation_branch_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NULL,
  order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  assigned_driver_id BIGINT UNSIGNED NULL,
  status ENUM('available','assigned','arrived','picked_up','in_transit','delivered','issue','cancelled','expired') NOT NULL DEFAULT 'available',
  payout DECIMAL(13,2) NOT NULL DEFAULT 0,
  zone_label VARCHAR(150) NULL,
  pickup_ready_at DATETIME NULL,
  delivery_pin_hash VARCHAR(255) NULL,
  accepted_at DATETIME NULL,
  picked_up_at DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_delivery_market(tenant_id,status,branch_id,created_at),
  INDEX idx_delivery_driver(assigned_driver_id,status,updated_at),
  CONSTRAINT fk_delivery_job_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_job_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  CONSTRAINT fk_delivery_job_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_job_driver FOREIGN KEY(assigned_driver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, code VARCHAR(80) NULL, name VARCHAR(190) NOT NULL, tax_id VARCHAR(80) NULL, contact_name VARCHAR(150) NULL, phone VARCHAR(40) NULL, email VARCHAR(190) NULL, address TEXT NULL, payment_terms_days INT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_supplier_code(tenant_id,code), INDEX idx_supplier_tenant(tenant_id,is_active,name), CONSTRAINT fk_supplier_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_inventory (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, track_stock TINYINT(1) NOT NULL DEFAULT 1, quantity DECIMAL(15,3) NOT NULL DEFAULT 0, reserved_quantity DECIMAL(15,3) NOT NULL DEFAULT 0, min_stock DECIMAL(15,3) NOT NULL DEFAULT 0, reorder_point DECIMAL(15,3) NOT NULL DEFAULT 0, avg_cost DECIMAL(15,4) NOT NULL DEFAULT 0, last_cost DECIMAL(15,4) NOT NULL DEFAULT 0, allow_negative TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_inventory_branch_product(branch_id,product_id), INDEX idx_inventory_tenant(tenant_id,branch_id,quantity), CONSTRAINT fk_inventory_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_inventory_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE, CONSTRAINT fk_inventory_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, movement_type VARCHAR(40) NOT NULL, quantity DECIMAL(15,3) NOT NULL, before_quantity DECIMAL(15,3) NOT NULL, after_quantity DECIMAL(15,3) NOT NULL, unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0, reference_type VARCHAR(40) NULL, reference_id BIGINT UNSIGNED NULL, notes VARCHAR(500) NULL, user_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_kardex(tenant_id,branch_id,product_id,created_at), CONSTRAINT fk_move_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_move_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE, CONSTRAINT fk_move_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE, CONSTRAINT fk_move_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_shifts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, opening_cash DECIMAL(15,2) NOT NULL DEFAULT 0, closing_cash DECIMAL(15,2) NULL, expected_cash DECIMAL(15,2) NULL, difference_amount DECIMAL(15,2) NULL, status VARCHAR(20) NOT NULL DEFAULT 'open', opened_at DATETIME NOT NULL, closed_at DATETIME NULL, notes TEXT NULL,
 INDEX idx_shift(tenant_id,branch_id,status,opened_at), CONSTRAINT fk_shift_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_shift_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE, CONSTRAINT fk_shift_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, shift_id BIGINT UNSIGNED NULL, order_id BIGINT UNSIGNED NULL, customer_id BIGINT UNSIGNED NULL, sale_number VARCHAR(80) NOT NULL, sale_type VARCHAR(30) NOT NULL DEFAULT 'quick', table_number VARCHAR(40) NULL, customer_name VARCHAR(190) NULL, payment_method VARCHAR(60) NOT NULL, subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, tax_total DECIMAL(15,2) NOT NULL DEFAULT 0, discount_total DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0, amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0, change_due DECIMAL(15,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'completed', notes TEXT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_sale_number(tenant_id,sale_number), INDEX idx_sales_period(tenant_id,branch_id,created_at,status), CONSTRAINT fk_sale_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_sale_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, CONSTRAINT fk_sale_shift FOREIGN KEY(shift_id) REFERENCES pos_shifts(id) ON DELETE SET NULL, CONSTRAINT fk_sale_customer FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_sale_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sale_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL, product_name VARCHAR(190) NOT NULL, sku VARCHAR(100) NULL, quantity DECIMAL(15,3) NOT NULL, unit_price DECIMAL(15,2) NOT NULL, unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0, tax_rate DECIMAL(7,3) NOT NULL DEFAULT 0, line_subtotal DECIMAL(15,2) NOT NULL, line_tax DECIMAL(15,2) NOT NULL DEFAULT 0,
 INDEX idx_sale_items(sale_id,product_id), CONSTRAINT fk_sale_item_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE CASCADE, CONSTRAINT fk_sale_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, supplier_id BIGINT UNSIGNED NOT NULL, purchase_number VARCHAR(80) NOT NULL, invoice_number VARCHAR(100) NULL, purchase_date DATE NOT NULL, due_date DATE NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', payment_status VARCHAR(30) NOT NULL DEFAULT 'pending', subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, tax_total DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0, balance DECIMAL(15,2) NOT NULL DEFAULT 0, notes TEXT NULL, created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_purchase_number(tenant_id,purchase_number), INDEX idx_purchases(tenant_id,branch_id,purchase_date,status), CONSTRAINT fk_purchase_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_purchase_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, CONSTRAINT fk_purchase_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT, CONSTRAINT fk_purchase_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, purchase_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(15,3) NOT NULL, unit_cost DECIMAL(15,4) NOT NULL, tax_rate DECIMAL(7,3) NOT NULL DEFAULT 0, line_subtotal DECIMAL(15,2) NOT NULL, line_tax DECIMAL(15,2) NOT NULL DEFAULT 0,
 CONSTRAINT fk_purchase_item_purchase FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE, CONSTRAINT fk_purchase_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts_receivable (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NULL, customer_id BIGINT UNSIGNED NULL, document_number VARCHAR(100) NOT NULL, customer_name VARCHAR(190) NULL, issue_date DATE NOT NULL, due_date DATE NULL, amount DECIMAL(15,2) NOT NULL, balance DECIMAL(15,2) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_ar(tenant_id,status,due_date), CONSTRAINT fk_ar_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_ar_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, CONSTRAINT fk_ar_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE SET NULL, CONSTRAINT fk_ar_customer FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts_payable (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, purchase_id BIGINT UNSIGNED NULL, supplier_id BIGINT UNSIGNED NOT NULL, document_number VARCHAR(100) NOT NULL, issue_date DATE NOT NULL, due_date DATE NULL, amount DECIMAL(15,2) NOT NULL, balance DECIMAL(15,2) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_ap(tenant_id,status,due_date), CONSTRAINT fk_ap_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_ap_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, CONSTRAINT fk_ap_purchase FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE SET NULL, CONSTRAINT fk_ap_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ar_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, receivable_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(15,2) NOT NULL, payment_method VARCHAR(60) NOT NULL, reference VARCHAR(150) NULL, notes VARCHAR(500) NULL, user_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ar_payment_account FOREIGN KEY(receivable_id) REFERENCES accounts_receivable(id) ON DELETE CASCADE, CONSTRAINT fk_ar_payment_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ap_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payable_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(15,2) NOT NULL, payment_method VARCHAR(60) NOT NULL, reference VARCHAR(150) NULL, notes VARCHAR(500) NULL, user_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ap_payment_account FOREIGN KEY(payable_id) REFERENCES accounts_payable(id) ON DELETE CASCADE, CONSTRAINT fk_ap_payment_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_customers (
 tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,user_id), INDEX idx_tenant_customer_user(user_id), CONSTRAINT fk_tc_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_tc_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_credit_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, credit_enabled TINYINT(1) NOT NULL DEFAULT 0, credit_limit DECIMAL(15,2) NOT NULL DEFAULT 0, current_balance DECIMAL(15,2) NOT NULL DEFAULT 0, payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30, notes TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_customer_credit(tenant_id,user_id), CONSTRAINT fk_credit_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_credit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS restaurant_tables (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, capacity INT UNSIGNED NOT NULL DEFAULT 4, area VARCHAR(120) NULL, status VARCHAR(30) NOT NULL DEFAULT 'available', sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_restaurant_table(tenant_id,branch_id,name), INDEX idx_tables(tenant_id,branch_id,status,sort_order), CONSTRAINT fk_table_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_table_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, branch_id BIGINT UNSIGNED NOT NULL, table_id BIGINT UNSIGNED NULL, customer_id BIGINT UNSIGNED NULL, ticket_number VARCHAR(80) NOT NULL, sale_type VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'open', subtotal DECIMAL(15,2) NOT NULL DEFAULT 0, tax_total DECIMAL(15,2) NOT NULL DEFAULT 0, discount_total DECIMAL(15,2) NOT NULL DEFAULT 0, total DECIMAL(15,2) NOT NULL DEFAULT 0, paid_total DECIMAL(15,2) NOT NULL DEFAULT 0, split_parts TINYINT UNSIGNED NOT NULL DEFAULT 0, split_part_amount DECIMAL(15,2) NOT NULL DEFAULT 0, split_parts_paid TINYINT UNSIGNED NOT NULL DEFAULT 0, opened_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pos_ticket(tenant_id,ticket_number), INDEX idx_pos_ticket_status(tenant_id,branch_id,status,updated_at), CONSTRAINT fk_ticket_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE, CONSTRAINT fk_ticket_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE RESTRICT, CONSTRAINT fk_ticket_table FOREIGN KEY(table_id) REFERENCES restaurant_tables(id) ON DELETE SET NULL, CONSTRAINT fk_ticket_customer FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_ticket_user FOREIGN KEY(opened_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_ticket_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL, product_name VARCHAR(190) NOT NULL, quantity DECIMAL(15,3) NOT NULL, paid_quantity DECIMAL(15,3) NOT NULL DEFAULT 0, unit_price DECIMAL(15,2) NOT NULL, unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0, tax_rate DECIMAL(7,3) NOT NULL DEFAULT 0, line_subtotal DECIMAL(15,2) NOT NULL, line_tax DECIMAL(15,2) NOT NULL DEFAULT 0, options_json JSON NULL, kitchen_status VARCHAR(30) NOT NULL DEFAULT 'pending', sent_at DATETIME NULL, notes VARCHAR(500) NULL,
 INDEX idx_ticket_items(ticket_id,kitchen_status), CONSTRAINT fk_ticket_item_ticket FOREIGN KEY(ticket_id) REFERENCES pos_tickets(id) ON DELETE CASCADE, CONSTRAINT fk_ticket_item_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NOT NULL, payment_method VARCHAR(40) NOT NULL, amount DECIMAL(15,2) NOT NULL, reference VARCHAR(150) NULL, received_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_pos_payments(ticket_id,created_at), CONSTRAINT fk_pos_payment_ticket FOREIGN KEY(ticket_id) REFERENCES pos_tickets(id) ON DELETE CASCADE, CONSTRAINT fk_pos_payment_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE CASCADE, CONSTRAINT fk_pos_payment_customer FOREIGN KEY(customer_id) REFERENCES users(id) ON DELETE RESTRICT, CONSTRAINT fk_pos_payment_user FOREIGN KEY(received_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- FISI-CHAT v1.4.3
CREATE TABLE IF NOT EXISTS fisichat_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NULL,
 branch_id BIGINT UNSIGNED NULL,
 user_id BIGINT UNSIGNED NULL,
 action_name VARCHAR(100) NOT NULL,
 result VARCHAR(30) NOT NULL,
 metadata_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_fisichat_audit(tenant_id,created_at,action_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
