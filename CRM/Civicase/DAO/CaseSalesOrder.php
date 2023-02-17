<?php

/**
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 *
 * Generated from uk.co.compucorp.civicase/xml/schema/CRM/Civicase/CaseSalesOrder.xml
 * DO NOT EDIT.  Generated by CRM_Core_CodeGen
 * (GenCodeChecksum:12c8b8325efddd813eeaa69be2c655fb)
 */
use CRM_Civicase_ExtensionUtil as E;

/**
 * Database access object for the CaseSalesOrder entity.
 */
class CRM_Civicase_DAO_CaseSalesOrder extends CRM_Core_DAO {
  const EXT = E::LONG_NAME;
  const TABLE_ADDED = '';

  /**
   * Static instance to hold the table name.
   *
   * @var string
   */
  public static $_tableName = 'civicase_sales_order';

  /**
   * Should CiviCRM log any modifications to this table in the civicrm_log table.
   *
   * @var bool
   */
  public static $_log = TRUE;

  /**
   * Paths for accessing this entity in the UI.
   *
   * @var string[]
   */
  protected static $_paths = [
    'view' => 'civicrm/case-features?reset=1&action=view&lid=[id]',
    'update' => 'civicrm/case-features?reset=1&action=update&lid=[id]',
    'delete' => 'civicrm/case-features/delete?reset=1&lid=[id]',
  ];

  /**
   * Unique CaseSalesOrder ID
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $id;

  /**
   * FK to Contact
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $client_id;

  /**
   * FK to Contact
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $owner_id;

  /**
   * FK to Case
   *
   * @var int|string|null
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $case_id;

  /**
   * 3 character string, value from config setting or input via user.
   *
   * @var string|null
   *   (SQL type: varchar(3))
   *   Note that values will be retrieved from the database as a string.
   */
  public $currency;

  /**
   * One of the values of the case_sales_order_status option group
   *
   * @var int|string
   *   (SQL type: int unsigned)
   *   Note that values will be retrieved from the database as a string.
   */
  public $status_id;

  /**
   * Sales order deesctiption
   *
   * @var string
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $description;

  /**
   * Sales order notes
   *
   * @var string
   *   (SQL type: text)
   *   Note that values will be retrieved from the database as a string.
   */
  public $notes;

  /**
   * Total amount of the sales order line items before tax deduction.
   *
   * @var float|string
   *   (SQL type: decimal(20,2))
   *   Note that values will be retrieved from the database as a string.
   */
  public $total_before_tax;

  /**
   * Total amount of the sales order line items after tax deduction.
   *
   * @var float|string
   *   (SQL type: decimal(20,2))
   *   Note that values will be retrieved from the database as a string.
   */
  public $total_after_tax;

  /**
   * Quotation date
   *
   * @var string|null
   *   (SQL type: timestamp)
   *   Note that values will be retrieved from the database as a string.
   */
  public $quotation_date;

  /**
   * Date the sales order is created
   *
   * @var string|null
   *   (SQL type: timestamp)
   *   Note that values will be retrieved from the database as a string.
   */
  public $created_at;

  /**
   * Is this sales order deleted?
   *
   * @var bool|string|null
   *   (SQL type: tinyint)
   *   Note that values will be retrieved from the database as a string.
   */
  public $is_deleted;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->__table = 'civicase_sales_order';
    parent::__construct();
  }

  /**
   * Returns localized title of this entity.
   *
   * @param bool $plural
   *   Whether to return the plural version of the title.
   */
  public static function getEntityTitle($plural = FALSE) {
    return $plural ? E::ts('Case Sales Orders') : E::ts('Case Sales Order');
  }

  /**
   * Returns foreign keys and entity references.
   *
   * @return array
   *   [CRM_Core_Reference_Interface]
   */
  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'client_id', 'civicrm_contact', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'owner_id', 'civicrm_contact', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'case_id', 'civicrm_case', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * Returns all the column names of this table
   *
   * @return array
   */
  public static function &fields() {
    if (!isset(Civi::$statics[__CLASS__]['fields'])) {
      Civi::$statics[__CLASS__]['fields'] = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('Unique CaseSalesOrder ID'),
          'required' => TRUE,
          'where' => 'civicase_sales_order.id',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Number',
          ],
          'readonly' => TRUE,
          'add' => NULL,
        ],
        'client_id' => [
          'name' => 'client_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to Contact'),
          'where' => 'civicase_sales_order.client_id',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Client"),
          ],
          'add' => NULL,
        ],
        'owner_id' => [
          'name' => 'owner_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to Contact'),
          'where' => 'civicase_sales_order.owner_id',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'FKClassName' => 'CRM_Contact_DAO_Contact',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Owner"),
          ],
          'add' => NULL,
        ],
        'case_id' => [
          'name' => 'case_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('FK to Case'),
          'where' => 'civicase_sales_order.case_id',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'FKClassName' => 'CRM_Case_DAO_Case',
          'html' => [
            'type' => 'EntityRef',
            'label' => E::ts("Case/Opportunity"),
          ],
          'add' => NULL,
        ],
        'currency' => [
          'name' => 'currency',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => E::ts('Financial Currency'),
          'description' => E::ts('3 character string, value from config setting or input via user.'),
          'maxlength' => 3,
          'size' => CRM_Utils_Type::FOUR,
          'where' => 'civicase_sales_order.currency',
          'headerPattern' => '/cur(rency)?/i',
          'dataPattern' => '/^[A-Z]{3}$/',
          'default' => NULL,
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
          ],
          'pseudoconstant' => [
            'table' => 'civicrm_currency',
            'keyColumn' => 'name',
            'labelColumn' => 'full_name',
            'nameColumn' => 'name',
            'abbrColumn' => 'symbol',
          ],
          'add' => NULL,
        ],
        'status_id' => [
          'name' => 'status_id',
          'type' => CRM_Utils_Type::T_INT,
          'description' => E::ts('One of the values of the case_sales_order_status option group'),
          'required' => TRUE,
          'where' => 'civicase_sales_order.status_id',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Select',
            'label' => E::ts("Status"),
          ],
          'pseudoconstant' => [
            'optionGroupName' => 'case_sales_order_status',
            'optionEditPath' => 'civicrm/admin/options/case_sales_order_status',
          ],
          'add' => NULL,
        ],
        'description' => [
          'name' => 'description',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Description'),
          'description' => E::ts('Sales order deesctiption'),
          'required' => FALSE,
          'where' => 'civicase_sales_order.description',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'TextArea',
            'label' => E::ts("Description"),
          ],
          'add' => NULL,
        ],
        'notes' => [
          'name' => 'notes',
          'type' => CRM_Utils_Type::T_TEXT,
          'title' => E::ts('Notes'),
          'description' => E::ts('Sales order notes'),
          'required' => FALSE,
          'where' => 'civicase_sales_order.notes',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'RichTextEditor',
            'label' => E::ts("Notes"),
          ],
          'add' => NULL,
        ],
        'total_before_tax' => [
          'name' => 'total_before_tax',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('Total Before Tax'),
          'description' => E::ts('Total amount of the sales order line items before tax deduction.'),
          'required' => FALSE,
          'precision' => [
            20,
            2,
          ],
          'where' => 'civicase_sales_order.total_before_tax',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'total_after_tax' => [
          'name' => 'total_after_tax',
          'type' => CRM_Utils_Type::T_MONEY,
          'title' => E::ts('Total After Tax'),
          'description' => E::ts('Total amount of the sales order line items after tax deduction.'),
          'required' => FALSE,
          'precision' => [
            20,
            2,
          ],
          'where' => 'civicase_sales_order.total_after_tax',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Text',
          ],
          'add' => NULL,
        ],
        'quotation_date' => [
          'name' => 'quotation_date',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Quotation Date'),
          'description' => E::ts('Quotation date'),
          'where' => 'civicase_sales_order.quotation_date',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'html' => [
            'type' => 'Select Date',
          ],
          'add' => NULL,
        ],
        'created_at' => [
          'name' => 'created_at',
          'type' => CRM_Utils_Type::T_TIMESTAMP,
          'title' => E::ts('Created At'),
          'description' => E::ts('Date the sales order is created'),
          'where' => 'civicase_sales_order.created_at',
          'default' => 'CURRENT_TIMESTAMP',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'add' => NULL,
        ],
        'is_deleted' => [
          'name' => 'is_deleted',
          'type' => CRM_Utils_Type::T_BOOLEAN,
          'description' => E::ts('Is this sales order deleted?'),
          'where' => 'civicase_sales_order.is_deleted',
          'default' => '0',
          'table_name' => 'civicase_sales_order',
          'entity' => 'CaseSalesOrder',
          'bao' => 'CRM_Civicase_DAO_CaseSalesOrder',
          'localizable' => 0,
          'add' => NULL,
        ],
      ];
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'fields_callback', Civi::$statics[__CLASS__]['fields']);
    }
    return Civi::$statics[__CLASS__]['fields'];
  }

  /**
   * Return a mapping from field-name to the corresponding key (as used in fields()).
   *
   * @return array
   *   Array(string $name => string $uniqueName).
   */
  public static function &fieldKeys() {
    if (!isset(Civi::$statics[__CLASS__]['fieldKeys'])) {
      Civi::$statics[__CLASS__]['fieldKeys'] = array_flip(CRM_Utils_Array::collect('name', self::fields()));
    }
    return Civi::$statics[__CLASS__]['fieldKeys'];
  }

  /**
   * Returns the names of this table
   *
   * @return string
   */
  public static function getTableName() {
    return self::$_tableName;
  }

  /**
   * Returns if this table needs to be logged
   *
   * @return bool
   */
  public function getLog() {
    return self::$_log;
  }

  /**
   * Returns the list of fields that can be imported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &import($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getImports(__CLASS__, '_sales_order', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of fields that can be exported
   *
   * @param bool $prefix
   *
   * @return array
   */
  public static function &export($prefix = FALSE) {
    $r = CRM_Core_DAO_AllCoreTables::getExports(__CLASS__, '_sales_order', $prefix, []);
    return $r;
  }

  /**
   * Returns the list of indices
   *
   * @param bool $localize
   *
   * @return array
   */
  public static function indices($localize = TRUE) {
    $indices = [];
    return ($localize && !empty($indices)) ? CRM_Core_DAO_AllCoreTables::multilingualize(__CLASS__, $indices) : $indices;
  }

}
