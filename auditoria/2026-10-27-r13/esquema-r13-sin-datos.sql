-- Referencia estructural R13, generada desde el entorno ficticio de QA.
-- Sin registros, disparadores, rutinas, credenciales ni contadores de datos.
-- Para revisión en una base desechable; no es una migración de producción.

SET @FISITAAP_QA_OLD_FOREIGN_KEYS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

/*M!999999\- enable the sandbox mode */
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts_payable` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ap` (`tenant_id`,`status`,`due_date`),
  KEY `fk_ap_branch` (`branch_id`),
  KEY `fk_ap_purchase` (`purchase_id`),
  KEY `fk_ap_supplier` (`supplier_id`),
  CONSTRAINT `fk_ap_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_ap_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_ap_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts_receivable` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `document_number` varchar(100) NOT NULL,
  `customer_name` varchar(190) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ar` (`tenant_id`,`status`,`due_date`),
  KEY `fk_ar_branch` (`branch_id`),
  KEY `fk_ar_sale` (`sale_id`),
  KEY `fk_ar_customer` (`customer_id`),
  CONSTRAINT `fk_ar_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_ar_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ap_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payable_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(60) NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_key` char(32) DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_r2_ap_payment` (`payable_id`,`payment_key`),
  KEY `fk_ap_payment_user` (`user_id`),
  CONSTRAINT `fk_ap_payment_account` FOREIGN KEY (`payable_id`) REFERENCES `accounts_payable` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ar_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receivable_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(60) NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_key` char(32) DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_r2_ar_payment` (`receivable_id`,`payment_key`),
  KEY `fk_ar_payment_user` (`user_id`),
  CONSTRAINT `fk_ar_payment_account` FOREIGN KEY (`receivable_id`) REFERENCES `accounts_receivable` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `summary` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit` (`tenant_id`,`created_at`),
  KEY `period180` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branch_products` (
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `price_override` decimal(13,2) DEFAULT NULL,
  `sale_price_override` decimal(13,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`branch_id`,`product_id`),
  KEY `idx_branch_product` (`product_id`,`branch_id`,`is_active`),
  CONSTRAINT `fk_branch_product_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_branch_product_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt_width` varchar(2) NOT NULL DEFAULT '80',
  `receipt_printer_type` varchar(20) NOT NULL DEFAULT 'browser',
  `receipt_printer_name` varchar(150) DEFAULT NULL,
  `receipt_printer_host` varchar(150) DEFAULT NULL,
  `receipt_printer_port` int(11) NOT NULL DEFAULT 9100,
  `receipt_autoprint` tinyint(4) NOT NULL DEFAULT 0,
  `receipt_bridge_token` varchar(128) DEFAULT NULL,
  `printer_points_json` longtext DEFAULT NULL,
  `business_hours` text DEFAULT NULL,
  `payment_methods` longtext DEFAULT NULL,
  `delivery_types` longtext DEFAULT NULL,
  `web_order_acceptance` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_slug` (`tenant_id`,`slug`),
  KEY `idx_branch_tenant` (`tenant_id`,`is_active`,`is_default`),
  CONSTRAINT `fk_branch_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `printer_target` varchar(32) NOT NULL DEFAULT 'kitchen',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category` (`tenant_id`,`slug`),
  CONSTRAINT `fk_category_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `subject` varchar(190) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','resolved') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_tenant` (`tenant_id`,`created_at`),
  CONSTRAINT `fk_contact_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(60) NOT NULL,
  `type` enum('fixed','percent') NOT NULL DEFAULT 'percent',
  `value` decimal(13,2) NOT NULL,
  `min_order` decimal(13,2) NOT NULL DEFAULT 0.00,
  `max_uses` int(11) DEFAULT NULL,
  `uses_count` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `applicable_products` text DEFAULT NULL,
  `applicable_categories` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon` (`tenant_id`,`code`),
  CONSTRAINT `fk_coupon_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `label` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `reference` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `maps_url180` varchar(1500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_addresses` (`user_id`,`is_default`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_credit_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `credit_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_terms_days` int(10) unsigned NOT NULL DEFAULT 30,
  `notes` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_blocked` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_credit` (`tenant_id`,`user_id`),
  KEY `fk_credit_user` (`user_id`),
  CONSTRAINT `fk_credit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `assigned_driver_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('available','assigned','arrived','picked_up','in_transit','delivered','issue','cancelled','expired') NOT NULL DEFAULT 'available',
  `payout` decimal(13,2) NOT NULL DEFAULT 0.00,
  `zone_label` varchar(150) DEFAULT NULL,
  `pickup_ready_at` datetime DEFAULT NULL,
  `delivery_pin_hash` varchar(255) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `issue_previous_status` varchar(20) DEFAULT NULL,
  `billing180` tinyint(4) NOT NULL DEFAULT 0,
  `delivery_fee180` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payout_percent180` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `idx_delivery_market` (`tenant_id`,`status`,`branch_id`,`created_at`),
  KEY `idx_delivery_driver` (`assigned_driver_id`,`status`,`updated_at`),
  KEY `fk_delivery_job_branch` (`branch_id`),
  KEY `period180` (`tenant_id`,`created_at`),
  CONSTRAINT `fk_delivery_job_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_job_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_job_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_delivery_job_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(13,2) NOT NULL DEFAULT 0.00,
  `min_order` decimal(13,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zone` (`tenant_id`,`name`),
  CONSTRAINT `fk_zone_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `driver_affiliation_branches` (
  `affiliation_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`affiliation_id`,`branch_id`),
  KEY `fk_affiliation_branch_branch` (`branch_id`),
  CONSTRAINT `fk_affiliation_branch_affiliation` FOREIGN KEY (`affiliation_id`) REFERENCES `driver_affiliations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_affiliation_branch_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `driver_affiliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending_driver','pending_store','active','paused','rejected','blocked') NOT NULL DEFAULT 'pending_store',
  `requested_by` enum('driver','tenant','master','migration') NOT NULL DEFAULT 'driver',
  `all_branches` tinyint(1) NOT NULL DEFAULT 1,
  `invited_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payout_mode180` varchar(16) NOT NULL DEFAULT 'all',
  `payout_percent180` decimal(7,2) NOT NULL DEFAULT 100.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_driver_affiliation` (`user_id`,`tenant_id`),
  KEY `idx_affiliation_tenant` (`tenant_id`,`status`,`user_id`),
  KEY `idx_affiliation_driver` (`user_id`,`status`,`tenant_id`),
  KEY `fk_affiliation_inviter` (`invited_by`),
  CONSTRAINT `fk_affiliation_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_affiliation_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_affiliation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `driver_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `vehicle_type` enum('motorcycle','car','bicycle','other') NOT NULL DEFAULT 'motorcycle',
  `plate` varchar(30) DEFAULT NULL,
  `identity_number` varchar(50) DEFAULT NULL,
  `service_area` varchar(190) DEFAULT NULL,
  `sinpe_phone` varchar(40) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `rating` decimal(3,2) NOT NULL DEFAULT 5.00,
  `completed_deliveries` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_driver_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_verification_expiry` (`expires_at`,`used_at`),
  KEY `fk_verification_user` (`user_id`),
  CONSTRAINT `fk_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisichat_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action_name` varchar(100) NOT NULL,
  `result` varchar(30) NOT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fisichat_audit` (`tenant_id`,`created_at`,`action_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_catalog_blobs180` (
  `hash` char(64) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(20) NOT NULL,
  `data` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`hash`),
  KEY `scope` (`tenant_id`,`branch_id`,`kind`),
  CONSTRAINT `fk180_b2519959beff9f8e` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_coupon_uses180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `operation_key` varchar(80) NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `once_per_coupon` (`tenant_id`,`coupon_id`,`operation_key`),
  CONSTRAINT `fk180_13536a15c862b06b` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_demo_sessions` (
  `token` char(32) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `template_id` bigint(20) unsigned NOT NULL,
  `browser_hash` char(64) NOT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `initial_mode` varchar(12) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  KEY `expiry` (`expires_at`),
  CONSTRAINT `fk_demo_session_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_driver_earnings180` (
  `job_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `delivery_fee` decimal(15,2) NOT NULL,
  `payout` decimal(15,2) NOT NULL,
  `settlement_id` bigint(20) unsigned DEFAULT NULL,
  `earned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`job_id`),
  KEY `pending` (`tenant_id`,`user_id`,`settlement_id`,`job_id`),
  CONSTRAINT `fkop180_c239ae74e728c5` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_driver_settlements180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `paid_by` bigint(20) unsigned NOT NULL,
  `operation_key` char(32) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `movement_id` bigint(20) unsigned NOT NULL,
  `reference` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `once_only` (`tenant_id`,`operation_key`),
  KEY `driver_history` (`tenant_id`,`user_id`,`id`),
  CONSTRAINT `fkop180_6ea4bf50b7f8d8` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_guest_contacts180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `phone` varchar(16) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `phone_login181` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tenant_id`,`phone`),
  KEY `customer` (`user_id`),
  CONSTRAINT `fkop180_3cf7e7029fc8c8` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_live180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `revision` bigint(20) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `fkop180_845a47410d0a37` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_modifier_categories180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `modifier_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`modifier_id`,`category_id`),
  KEY `scope` (`tenant_id`,`category_id`),
  KEY `fk180_19a838881c42a2e0` (`category_id`),
  CONSTRAINT `fk180_19a838881c42a2e0` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_4d7901e4fb5f3d8f` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_c282acf79fa54380` FOREIGN KEY (`modifier_id`) REFERENCES `fisitaap_r2_modifiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_modifier_products180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `modifier_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`modifier_id`,`product_id`),
  KEY `scope` (`tenant_id`,`product_id`),
  KEY `fk180_0da33cf6fd61fe04` (`product_id`),
  CONSTRAINT `fk180_0da33cf6fd61fe04` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_308a4f50a5b8bccd` FOREIGN KEY (`modifier_id`) REFERENCES `fisitaap_r2_modifiers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_7526121bc75d6676` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_money_accounts180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'bank',
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `named` (`tenant_id`,`name`),
  CONSTRAINT `fk180_a5dff3e4b8218f3a` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_money_entries180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `register_id` bigint(20) unsigned DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation_key` varchar(100) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `kind` varchar(32) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` varchar(250) NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `entity_type` varchar(30) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `operation` (`tenant_id`,`operation_key`),
  KEY `cash` (`tenant_id`,`shift_id`,`created_at`),
  KEY `account` (`tenant_id`,`account_id`,`created_at`),
  KEY `period180` (`tenant_id`,`created_at`),
  CONSTRAINT `fk180_43c503a9c0b427ff` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_operations180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation_key` char(32) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `kind` varchar(40) NOT NULL,
  `result_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `operation` (`tenant_id`,`operation_key`),
  CONSTRAINT `fk180_da8a379378cc9228` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_order_events180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL,
  `message` varchar(500) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `timeline` (`tenant_id`,`order_id`,`id`),
  CONSTRAINT `fkop180_bf4539f4ee78bc` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_order_receipts180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `ticket` (`ticket_id`),
  KEY `fk180_8636f0e6036ee4f9` (`tenant_id`),
  CONSTRAINT `fk180_8636f0e6036ee4f9` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_order_uploads180` (
  `token_hash` char(64) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `browser_hash` char(64) NOT NULL,
  `operation_key` char(32) NOT NULL,
  `path` varchar(250) NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_hash`),
  KEY `cleanup` (`order_id`,`created_at`),
  KEY `scope` (`tenant_id`,`operation_key`),
  CONSTRAINT `fkop180_bdd3ce7199cd53` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_primary179` (
  `tenant_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `generation` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_print_jobs180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `device_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation_key` varchar(200) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `point` varchar(30) NOT NULL,
  `payload` longtext NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'queued',
  `claim_token` char(32) DEFAULT NULL,
  `lease_expires` datetime DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `error` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operation` (`tenant_id`,`operation_key`),
  KEY `queue` (`tenant_id`,`branch_id`,`device_id`,`status`,`id`),
  CONSTRAINT `fk180_2a11fa66a1a7e838` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_purchase_drafts180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation_key` varchar(64) NOT NULL,
  `data` longtext NOT NULL,
  `revision` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `purchase_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `once_per_tenant` (`tenant_id`,`operation_key`),
  KEY `scope` (`tenant_id`,`branch_id`,`status`),
  CONSTRAINT `fk180_33ca949d2b37f8cc` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r1_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `section_key` varchar(60) NOT NULL,
  `action_key` varchar(30) NOT NULL,
  PRIMARY KEY (`role_id`,`section_key`,`action_key`),
  CONSTRAINT `fk_r1_permission_role` FOREIGN KEY (`role_id`) REFERENCES `fisitaap_r1_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r1_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_r1_role_name` (`tenant_id`,`name`),
  UNIQUE KEY `uq_r1_role_tenant` (`id`,`tenant_id`),
  CONSTRAINT `fk_r1_role_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r1_user_roles` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `previous_role` varchar(30) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `ix_r1_assignment_role` (`role_id`,`tenant_id`),
  CONSTRAINT `fk_r1_assignment_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `fisitaap_r1_roles` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_r1_assignment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_combo_items` (
  `combo_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  PRIMARY KEY (`combo_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_customer_sources` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `source` varchar(30) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `pair_hash` char(64) DEFAULT NULL,
  `pair_expires` datetime DEFAULT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `last_sync` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `scope` (`tenant_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_floor` (
  `table_id` bigint(20) unsigned NOT NULL,
  `room_id` bigint(20) unsigned NOT NULL,
  `x` int(11) NOT NULL DEFAULT 30,
  `y` int(11) NOT NULL DEFAULT 30,
  `width` int(11) NOT NULL DEFAULT 100,
  `height` int(11) NOT NULL DEFAULT 85,
  `shape` varchar(20) NOT NULL DEFAULT 'square',
  PRIMARY KEY (`table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_gift_keys` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `issue_key` char(32) NOT NULL,
  `reward_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`issue_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_modifier_groups` (
  `modifier_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`modifier_id`,`product_id`),
  UNIQUE KEY `managed_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_modifiers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(12) NOT NULL,
  `name` varchar(120) NOT NULL,
  `choices_json` text NOT NULL,
  `required` tinyint(4) NOT NULL DEFAULT 0,
  `multiple` tinyint(4) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `legacy_family180` bigint(20) unsigned DEFAULT NULL,
  `legacy_name180` varchar(120) DEFAULT NULL,
  `legacy_min180` int(11) NOT NULL DEFAULT 0,
  `legacy_max180` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `scope` (`tenant_id`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_offline_users` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`branch_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `scope` (`tenant_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_purchase_keys` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `purchase_key` varchar(100) NOT NULL,
  `purchase_id` bigint(20) unsigned NOT NULL,
  `payload_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`tenant_id`,`purchase_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `width` int(11) NOT NULL DEFAULT 1000,
  `height` int(11) NOT NULL DEFAULT 650,
  `service_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_name` (`tenant_id`,`branch_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_settings` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `value` longtext NOT NULL,
  PRIMARY KEY (`tenant_id`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_shift_links` (
  `device_id` bigint(20) unsigned NOT NULL,
  `shift_uuid` char(36) NOT NULL,
  `shift_id` bigint(20) unsigned NOT NULL,
  `opening_hash` char(64) DEFAULT NULL,
  `close_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`device_id`,`shift_uuid`),
  KEY `idx_shift_link_shift` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_snapshots` (
  `id` char(32) NOT NULL,
  `device_id` bigint(20) unsigned NOT NULL,
  `data` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `catalog_hash180` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalog180` (`device_id`,`catalog_hash180`),
  KEY `device` (`device_id`),
  KEY `idx_snapshot_device_created` (`device_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r2_synced_sales` (
  `device_id` bigint(20) unsigned NOT NULL,
  `operation_id` char(36) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `sale_id` bigint(20) unsigned NOT NULL,
  `register_name` varchar(80) NOT NULL,
  `conflicts` text DEFAULT NULL,
  `local_date` datetime NOT NULL,
  PRIMARY KEY (`device_id`,`operation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_checkout_keys` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `operation_key` char(32) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`tenant_id`,`user_id`,`operation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_gift_cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `code` varchar(32) NOT NULL,
  `issue_key` char(32) NOT NULL,
  `initial_amount` decimal(15,2) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `issuance` (`tenant_id`,`issue_key`),
  UNIQUE KEY `gift_code` (`tenant_id`,`code`),
  KEY `customer` (`tenant_id`,`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_gift_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `card_id` bigint(20) unsigned NOT NULL,
  `operation_key` varchar(80) NOT NULL,
  `kind` varchar(16) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `once_per_card` (`card_id`,`operation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_objects` (
  `id` char(36) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `room_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `label` varchar(120) NOT NULL DEFAULT '',
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `rotation` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `scope` (`tenant_id`,`branch_id`,`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_room_versions` (
  `room_id` bigint(20) unsigned NOT NULL,
  `revision` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_sectors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `room_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` char(7) NOT NULL DEFAULT '#e7f1ff',
  `x` int(11) NOT NULL DEFAULT 30,
  `y` int(11) NOT NULL DEFAULT 30,
  `width` int(11) NOT NULL DEFAULT 600,
  `height` int(11) NOT NULL DEFAULT 550,
  `service_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `service_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  PRIMARY KEY (`id`),
  KEY `scope` (`tenant_id`,`branch_id`,`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_r3_table_details` (
  `table_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `sector_id` bigint(20) unsigned DEFAULT NULL,
  `rotation` int(11) NOT NULL DEFAULT 0,
  `basic_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`table_id`),
  KEY `scope` (`tenant_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_registers180` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `device_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `drawer_enabled` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `device` (`device_id`),
  KEY `scope` (`tenant_id`,`branch_id`,`is_active`),
  CONSTRAINT `fk180_d8ee06d5780c07e5` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_tenant_updates180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 179,
  `modifiers_migrated` tinyint(4) NOT NULL DEFAULT 0,
  `activated_by` bigint(20) unsigned DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `fk180_2d1ce9a37243d0ec` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fisitaap_ticket_tables180` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `table_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`ticket_id`,`table_id`),
  KEY `table_scope` (`tenant_id`,`branch_id`,`table_id`),
  KEY `fk180_7c731b4c68bbe5a4` (`table_id`),
  CONSTRAINT `fk180_7c731b4c68bbe5a4` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_cfb5ce2e5b53e26e` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk180_e4678d7a8e308e70` FOREIGN KEY (`ticket_id`) REFERENCES `pos_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `movement_type` varchar(40) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `before_quantity` decimal(15,3) NOT NULL,
  `after_quantity` decimal(15,3) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kardex` (`tenant_id`,`branch_id`,`product_id`,`created_at`),
  KEY `fk_move_branch` (`branch_id`),
  KEY `fk_move_product` (`product_id`),
  KEY `fk_move_user` (`user_id`),
  KEY `period180` (`tenant_id`,`created_at`),
  CONSTRAINT `fk_move_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `points_balance` decimal(13,2) NOT NULL DEFAULT 0.00,
  `purchases_balance` int(10) unsigned NOT NULL DEFAULT 0,
  `spend_balance` decimal(13,2) NOT NULL DEFAULT 0.00,
  `rewards_available` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loyalty_account` (`tenant_id`,`user_id`),
  KEY `fk_loyalty_account_user` (`user_id`),
  CONSTRAINT `fk_loyalty_account_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_account_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `event` enum('earn','redeem','adjust','reverse') NOT NULL,
  `points_delta` decimal(13,2) NOT NULL DEFAULT 0.00,
  `purchases_delta` int(11) NOT NULL DEFAULT 0,
  `spend_delta` decimal(13,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loyalty_order_event` (`order_id`,`event`),
  UNIQUE KEY `uq_r2_loyalty_sale` (`sale_id`,`event`),
  KEY `idx_loyalty_ledger` (`account_id`,`created_at`),
  CONSTRAINT `fk_loyalty_ledger_account` FOREIGN KEY (`account_id`) REFERENCES `loyalty_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_ledger_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `mode` enum('points','purchases','spend') NOT NULL DEFAULT 'points',
  `earn_value` decimal(13,2) NOT NULL DEFAULT 100.00,
  `goal_value` decimal(13,2) NOT NULL DEFAULT 10.00,
  `reward_type` enum('percent','fixed','product') NOT NULL DEFAULT 'percent',
  `reward_value` decimal(13,2) NOT NULL DEFAULT 10.00,
  `reward_product_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loyalty_program_scope` (`tenant_id`,`branch_id`),
  KEY `idx_loyalty_program` (`tenant_id`,`is_active`,`branch_id`),
  KEY `fk_loyalty_branch` (`branch_id`),
  KEY `fk_loyalty_product` (`reward_product_id`),
  CONSTRAINT `fk_loyalty_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_product` FOREIGN KEY (`reward_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loyalty_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint(20) unsigned NOT NULL,
  `program_id` bigint(20) unsigned NOT NULL,
  `code` varchar(40) NOT NULL,
  `reward_type` enum('percent','fixed','product') NOT NULL,
  `reward_value` decimal(13,2) NOT NULL DEFAULT 0.00,
  `reward_product_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('available','used','expired','cancelled') NOT NULL DEFAULT 'available',
  `expires_at` datetime NOT NULL,
  `used_order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_loyalty_rewards` (`account_id`,`status`,`expires_at`),
  KEY `fk_reward_program` (`program_id`),
  KEY `fk_reward_product` (`reward_product_id`),
  KEY `fk_reward_order` (`used_order_id`),
  CONSTRAINT `fk_reward_account` FOREIGN KEY (`account_id`) REFERENCES `loyalty_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reward_order` FOREIGN KEY (`used_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reward_product` FOREIGN KEY (`reward_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reward_program` FOREIGN KEY (`program_id`) REFERENCES `loyalty_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `recipient` varchar(190) NOT NULL,
  `subject` varchar(190) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mail_logs` (`tenant_id`,`created_at`,`status`),
  CONSTRAINT `fk_mail_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(190) NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_label` varchar(40) NOT NULL,
  `unit_price` decimal(13,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_subtotal` decimal(13,2) NOT NULL,
  `line_tax` decimal(13,2) NOT NULL DEFAULT 0.00,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `notes` text DEFAULT NULL,
  `pricing_json180` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pricing_json180`)),
  `discount_cents180` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_item_order` (`order_id`),
  KEY `fk_item_product` (`product_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_sequences` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `next_number` bigint(20) unsigned NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tenant_id`),
  CONSTRAINT `fk_sequence_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(40) NOT NULL,
  `customer_id_number` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `address_reference` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `delivery_zone_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_type` enum('delivery','pickup','coordinated') NOT NULL DEFAULT 'delivery',
  `payment_method` varchar(60) NOT NULL,
  `coupon_code` varchar(60) DEFAULT NULL,
  `subtotal` decimal(13,2) NOT NULL,
  `tax_total` decimal(13,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(13,2) NOT NULL DEFAULT 0.00,
  `delivery_total` decimal(13,2) NOT NULL DEFAULT 0.00,
  `total` decimal(13,2) NOT NULL,
  `status` enum('new','confirmed','preparing','ready','sent','delivered','cancelled') NOT NULL DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revision180` int(10) unsigned NOT NULL DEFAULT 0,
  `maps_url180` varchar(1500) DEFAULT NULL,
  `pin_cipher180` varchar(250) DEFAULT NULL,
  `cancel_reason180` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`tenant_id`,`order_number`),
  KEY `idx_order_report` (`tenant_id`,`created_at`,`status`),
  KEY `fk_order_user` (`user_id`),
  KEY `fk_order_zone` (`delivery_zone_id`),
  KEY `period180` (`tenant_id`,`created_at`),
  KEY `kanban180` (`tenant_id`,`branch_id`,`status`,`created_at`),
  CONSTRAINT `fk_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_order_zone` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `event_type` enum('catalog_view','checkout_completed') NOT NULL,
  `session_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_report` (`tenant_id`,`created_at`,`event_type`),
  CONSTRAINT `fk_event_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_password_reset_expiry` (`expires_at`,`used_at`),
  KEY `fk_reset_user` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_modules` (
  `plan_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`plan_id`,`module_key`),
  CONSTRAINT `fk_plan_module_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `price` decimal(13,2) NOT NULL DEFAULT 0.00,
  `billing_period` enum('monthly','annual','custom') NOT NULL DEFAULT 'monthly',
  `limits_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limits_json`)),
  `status` enum('active','hidden','archived') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `payment_method` varchar(40) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `received_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pos_payments` (`ticket_id`,`created_at`),
  KEY `fk_pos_payment_sale` (`sale_id`),
  KEY `fk_pos_payment_customer` (`customer_id`),
  KEY `fk_pos_payment_user` (`received_by`),
  CONSTRAINT `fk_pos_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_pos_payment_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_payment_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `pos_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pos_payment_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_shifts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `opening_cash` decimal(15,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(15,2) DEFAULT NULL,
  `expected_cash` decimal(15,2) DEFAULT NULL,
  `difference_amount` decimal(15,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `register_id` bigint(20) unsigned DEFAULT NULL,
  `device_id` bigint(20) unsigned DEFAULT NULL,
  `opening_key` char(32) DEFAULT NULL,
  `closing_key` char(32) DEFAULT NULL,
  `closing_payload_hash` char(64) DEFAULT NULL,
  `denominations_json` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `opening180` (`tenant_id`,`user_id`,`opening_key`),
  KEY `idx_shift` (`tenant_id`,`branch_id`,`status`,`opened_at`),
  KEY `fk_shift_branch` (`branch_id`),
  KEY `fk_shift_user` (`user_id`),
  KEY `period180` (`tenant_id`,`opened_at`),
  KEY `status180` (`tenant_id`,`status`,`branch_id`),
  CONSTRAINT `fk_shift_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shift_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_table_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `service_enabled` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_name` (`tenant_id`,`branch_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_ticket_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(190) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `paid_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(15,2) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(7,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(15,2) NOT NULL,
  `line_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `kitchen_status` varchar(30) NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `paid_net` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_service` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pricing_json` text DEFAULT NULL,
  `original_ticket_id180` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_items` (`ticket_id`,`kitchen_status`),
  KEY `fk_ticket_item_product` (`product_id`),
  CONSTRAINT `fk_ticket_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_item_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `pos_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `table_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `ticket_number` varchar(80) NOT NULL,
  `sale_type` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `split_parts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `split_part_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `split_parts_paid` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `opened_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_label` varchar(150) DEFAULT NULL,
  `service_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `service_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `open_key` char(32) DEFAULT NULL,
  `revision` int(11) NOT NULL DEFAULT 0,
  `delivery_address` text DEFAULT NULL,
  `fulfillment_state` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `register_id` bigint(20) unsigned DEFAULT NULL,
  `coupon_id` bigint(20) unsigned DEFAULT NULL,
  `merged_into` bigint(20) unsigned DEFAULT NULL,
  `start_payload_hash` char(64) DEFAULT NULL,
  `source_order_id` bigint(20) unsigned DEFAULT NULL,
  `fulfillment180` varchar(30) NOT NULL DEFAULT 'new',
  `service_adjustment180` decimal(15,2) NOT NULL DEFAULT 0.00,
  `delivery_fee180` decimal(15,2) NOT NULL DEFAULT 0.00,
  `maps_url180` varchar(1500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pos_ticket` (`tenant_id`,`ticket_number`),
  UNIQUE KEY `uq_r2_open` (`tenant_id`,`open_key`),
  KEY `idx_pos_ticket_status` (`tenant_id`,`branch_id`,`status`,`updated_at`),
  KEY `fk_ticket_branch` (`branch_id`),
  KEY `fk_ticket_table` (`table_id`),
  KEY `fk_ticket_customer` (`customer_id`),
  KEY `fk_ticket_user` (`opened_by`),
  KEY `pending180` (`tenant_id`,`branch_id`,`shift_id`,`status`),
  KEY `migration180` (`tenant_id`,`status`),
  CONSTRAINT `fk_ticket_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_ticket_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `author_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `media_url` varchar(255) DEFAULT NULL,
  `media_type` enum('image','video','link') DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_posts` (`tenant_id`,`status`,`published_at`),
  KEY `fk_post_author` (`author_id`),
  CONSTRAINT `fk_post_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_post_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `reserved_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `min_stock` decimal(15,3) NOT NULL DEFAULT 0.000,
  `reorder_point` decimal(15,3) NOT NULL DEFAULT 0.000,
  `avg_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `last_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `allow_negative` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_branch_product` (`branch_id`,`product_id`),
  KEY `idx_inventory_tenant` (`tenant_id`,`branch_id`,`quantity`),
  KEY `fk_inventory_product` (`product_id`),
  CONSTRAINT `fk_inventory_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_option_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `type` enum('single','multiple') NOT NULL DEFAULT 'single',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `min_select` int(10) unsigned NOT NULL DEFAULT 0,
  `max_select` int(10) unsigned NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `modifier_id180` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modifier180` (`tenant_id`,`product_id`,`modifier_id180`),
  KEY `idx_option_groups_product` (`tenant_id`,`product_id`,`is_active`,`sort_order`),
  KEY `fk_option_group_product` (`product_id`),
  KEY `fk180_bb53258e5c66f61c` (`modifier_id180`),
  CONSTRAINT `fk180_bb53258e5c66f61c` FOREIGN KEY (`modifier_id180`) REFERENCES `fisitaap_r2_modifiers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_option_group_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_option_group_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `price_delta` decimal(13,2) NOT NULL DEFAULT 0.00,
  `linked_product_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `modifier_option180` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modifier_option180` (`group_id`,`modifier_option180`),
  KEY `idx_options_group` (`group_id`,`is_active`,`sort_order`),
  KEY `fk_option_linked_product` (`linked_product_id`),
  CONSTRAINT `fk_option_group` FOREIGN KEY (`group_id`) REFERENCES `product_option_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_option_linked_product` FOREIGN KEY (`linked_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `characteristics` text DEFAULT NULL,
  `price` decimal(13,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(13,2) DEFAULT NULL,
  `cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `track_inventory` tinyint(1) NOT NULL DEFAULT 0,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `unit_label` varchar(40) NOT NULL DEFAULT 'unidad',
  `min_qty` decimal(10,3) NOT NULL DEFAULT 1.000,
  `qty_step` decimal(10,3) NOT NULL DEFAULT 1.000,
  `decimals` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `badge` varchar(60) DEFAULT NULL,
  `product_type` enum('simple','combo') NOT NULL DEFAULT 'simple',
  `status` enum('active','hidden','sold_out') NOT NULL DEFAULT 'active',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_slug` (`tenant_id`,`slug`),
  KEY `idx_product_catalog` (`tenant_id`,`status`,`category_id`),
  KEY `fk_product_category` (`category_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_product_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL,
  `tax_rate` decimal(7,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(15,2) NOT NULL,
  `line_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_purchase_item_purchase` (`purchase_id`),
  KEY `fk_purchase_item_product` (`product_id`),
  CONSTRAINT `fk_purchase_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_purchase_item_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_number` varchar(80) NOT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `payment_status` varchar(30) NOT NULL DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_number` (`tenant_id`,`purchase_number`),
  KEY `idx_purchases` (`tenant_id`,`branch_id`,`purchase_date`,`status`),
  KEY `fk_purchase_branch` (`branch_id`),
  KEY `fk_purchase_supplier` (`supplier_id`),
  KEY `fk_purchase_creator` (`created_by`),
  CONSTRAINT `fk_purchase_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_purchase_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_purchase_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `bucket_key` char(64) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `blocked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`bucket_key`),
  KEY `idx_rate_limit_cleanup` (`window_started_at`,`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurant_tables` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `capacity` int(10) unsigned NOT NULL DEFAULT 4,
  `area` varchar(120) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'available',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `space_type` varchar(20) NOT NULL DEFAULT 'table',
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `service_enabled` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_restaurant_table` (`tenant_id`,`branch_id`,`name`),
  KEY `idx_tables` (`tenant_id`,`branch_id`,`status`,`sort_order`),
  KEY `fk_table_branch` (`branch_id`),
  CONSTRAINT `fk_table_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_table_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(190) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(7,3) NOT NULL DEFAULT 0.000,
  `line_subtotal` decimal(15,2) NOT NULL,
  `line_tax` decimal(15,2) NOT NULL DEFAULT 0.00,
  `options_json` text DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_items` (`sale_id`,`product_id`),
  KEY `fk_sale_item_product` (`product_id`),
  CONSTRAINT `fk_sale_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_item_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `shift_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `sale_number` varchar(80) NOT NULL,
  `sale_type` varchar(30) NOT NULL DEFAULT 'quick',
  `table_number` varchar(40) DEFAULT NULL,
  `customer_name` varchar(190) DEFAULT NULL,
  `payment_method` varchar(60) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `service_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_key` char(32) DEFAULT NULL,
  `kitchen_jobs_json` longtext DEFAULT NULL,
  `payment_payload_hash` char(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sale_number` (`tenant_id`,`sale_number`),
  UNIQUE KEY `uq_r2_payment` (`tenant_id`,`created_by`,`payment_key`),
  KEY `idx_sales_period` (`tenant_id`,`branch_id`,`created_at`,`status`),
  KEY `fk_sale_branch` (`branch_id`),
  KEY `fk_sale_shift` (`shift_id`),
  KEY `fk_sale_customer` (`customer_id`),
  KEY `fk_sale_creator` (`created_by`),
  KEY `idx_sales_tenant_date178` (`tenant_id`,`created_at`,`status`),
  KEY `period180` (`tenant_id`,`created_at`),
  CONSTRAINT `fk_sale_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_sale_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_shift` FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `query` varchar(190) NOT NULL,
  `result_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_search_report` (`tenant_id`,`created_at`,`result_count`),
  CONSTRAINT `fk_search_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` longtext DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(80) DEFAULT NULL,
  `name` varchar(190) NOT NULL,
  `tax_id` varchar(80) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms_days` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_code` (`tenant_id`,`code`),
  KEY `idx_supplier_tenant` (`tenant_id`,`is_active`,`name`),
  KEY `tax180` (`tenant_id`,`tax_id`),
  CONSTRAINT `fk_supplier_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_content` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `section_key` varchar(100) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch_content` (`tenant_id`,`branch_id`,`section_key`,`sort_order`),
  CONSTRAINT `fk_content_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_customers` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tenant_id`,`user_id`),
  KEY `idx_tenant_customer_user` (`user_id`),
  CONSTRAINT `fk_tc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_modules` (
  `tenant_id` bigint(20) unsigned NOT NULL,
  `module_key` varchar(60) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `limits_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limits_json`)),
  PRIMARY KEY (`tenant_id`,`module_key`),
  CONSTRAINT `fk_tenant_module_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `receipt_width` enum('58','80') NOT NULL DEFAULT '80',
  `receipt_printer_type` enum('browser','network','usb','bluetooth') NOT NULL DEFAULT 'browser',
  `receipt_printer_name` varchar(150) DEFAULT NULL,
  `receipt_printer_host` varchar(150) DEFAULT NULL,
  `receipt_printer_port` int(10) unsigned NOT NULL DEFAULT 9100,
  `receipt_autoprint` tinyint(1) NOT NULL DEFAULT 0,
  `receipt_bridge_token` varchar(128) DEFAULT NULL,
  `map_embed` text DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `tiktok_url` varchar(255) DEFAULT NULL,
  `business_hours` text DEFAULT NULL,
  `payment_methods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_methods`)),
  `delivery_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_types`)),
  `catalog_label` enum('Catálogo','Menú') NOT NULL DEFAULT 'Catálogo',
  `catalog_mode` enum('global','branches') NOT NULL DEFAULT 'global',
  `physical_store_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `primary_color` varchar(10) NOT NULL DEFAULT '#1769e0',
  `accent_color` varchar(10) NOT NULL DEFAULT '#25d366',
  `currency` varchar(5) NOT NULL DEFAULT 'CRC',
  `tax_included` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_listed` tinyint(1) NOT NULL DEFAULT 1,
  `offline_message` varchar(255) NOT NULL DEFAULT 'Ahorita no estamos, pronto llegaremos',
  `plan` varchar(50) NOT NULL DEFAULT 'Profesional',
  `expires_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tax_id` varchar(30) DEFAULT NULL,
  `tax_id_required` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tenant_active` (`is_active`,`is_listed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('master','tenant_admin','manager','editor','kitchen','cashier','driver','customer') NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_tenant_role` (`tenant_id`,`role`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET FOREIGN_KEY_CHECKS = @FISITAAP_QA_OLD_FOREIGN_KEYS;
