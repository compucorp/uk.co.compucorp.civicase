-- /*******************************************************
-- *
-- * civicase_contactlock
-- *
-- * This table implements a list of contacts that have been locked out of
-- * specific cases, to which they will only have basic view access.
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicase_contactlock` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique CaseContactLock ID',
  `case_id` int unsigned    COMMENT 'Case ID that is locked.',
  `contact_id` int unsigned    COMMENT 'Contact for which the case is locked.' ,
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicase_contactlock_case_id FOREIGN KEY (`case_id`) REFERENCES `civicrm_case`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicase_contactlock_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- /*******************************************************
-- *
-- * civicrm_case_category_instance
-- *
-- * Stores Case Category Instance Details
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_case_category_instance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique CaseCategoryInstance Id',
  `category_id` int unsigned NOT NULL   COMMENT 'One of the values of the case_type_categories option group',
  `instance_id` int unsigned NOT NULL   COMMENT 'One of the values of the case_category_instance_type option group',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unique_category`(category_id)
 ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- /*******************************************************
-- *
-- * civicrm_case_category_features
-- *
-- * Stores additional features enabled for a case category
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicrm_case_category_features` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique CaseCategoryFeatures ID',
  `category_id` int unsigned NOT NULL COMMENT 'One of the values of the case_type_categories option group',
  `feature_id` int unsigned NOT NULL COMMENT 'One of the values of the case_type_category_features option group',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unique_category_feature` (category_id, feature_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


-- /*******************************************************
-- *
-- * civicase_sales_order
-- *
-- * Sales order that represents quotations
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicase_sales_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique CaseSalesOrder ID',
  `client_id` int unsigned COMMENT 'FK to Contact',
  `owner_id` int unsigned COMMENT 'FK to Contact',
  `case_id` int unsigned COMMENT 'FK to Case',
  `currency` varchar(3) DEFAULT NULL COMMENT '3 character string, value from config setting or input via user.',
  `status_id` int unsigned NOT NULL COMMENT 'One of the values of the case_sales_order_status option group',
  `description` text NULL COMMENT 'Sales order deesctiption',
  `notes` text NULL COMMENT 'Sales order notes',
  `total_before_tax` decimal(20,2) NULL COMMENT 'Total amount of the sales order line items before tax deduction.',
  `total_after_tax` decimal(20,2) NULL COMMENT 'Total amount of the sales order line items after tax deduction.',
  `quotation_date` timestamp COMMENT 'Quotation date',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'Date the sales order is created',
  `is_deleted` tinyint DEFAULT 0 COMMENT 'Is this sales order deleted?',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicase_sales_order_client_id FOREIGN KEY (`client_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicase_sales_order_owner_id FOREIGN KEY (`owner_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicase_sales_order_case_id FOREIGN KEY (`case_id`) REFERENCES `civicrm_case`(`id`) ON DELETE CASCADE
)
ENGINE=InnoDB;

-- /*******************************************************
-- *
-- * civicase_sales_order_line
-- *
-- * Sales order line items
-- *
-- *******************************************************/
CREATE TABLE IF NOT EXISTS `civicase_sales_order_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique CaseSalesOrderLine ID',
  `sales_order_id` int unsigned COMMENT 'FK to CaseSalesOrder',
  `financial_type_id` int unsigned COMMENT 'FK to CiviCRM Financial Type',
  `product_id` int unsigned,
  `item_description` text NULL COMMENT 'line item deesctiption',
  `quantity` decimal(20,2) COMMENT 'Quantity',
  `unit_price` decimal(20,2) COMMENT 'Unit Price',
  `tax_rate` decimal(20,2) COMMENT 'Tax rate for the line item',
  `discounted_percentage` decimal(20,2) COMMENT 'Discount applied to the line item',
  `subtotal_amount` decimal(20,2) COMMENT 'Quantity x Unit Price x (100-Discount)%',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civicase_sales_order_line_sales_order_id FOREIGN KEY (`sales_order_id`) REFERENCES `civicase_sales_order`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_civicase_sales_order_line_financial_type_id FOREIGN KEY (`financial_type_id`) REFERENCES `civicrm_financial_type`(`id`) ON DELETE SET NULL,
  CONSTRAINT FK_civicase_sales_order_line_product_id FOREIGN KEY (`product_id`) REFERENCES `civicrm_product`(`id`) ON DELETE SET NULL
)
ENGINE=InnoDB;
