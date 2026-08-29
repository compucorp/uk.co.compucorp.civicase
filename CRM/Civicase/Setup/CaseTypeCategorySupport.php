<?php

use CRM_Core_BAO_SchemaHandler as SchemaHandler;

class CRM_Civicase_Setup_CaseTypeCategorySupport {

  public function apply() {
    $this->addCaseCategoryDBColumn();
    $this->createCaseCategoryOptionGroup();

    return TRUE;
  }

  /**
   * Add Case Type Category Column to the Case Type table
   */
  private function addCaseCategoryDBColumn () {
    global $dbLocale;

    $caseTypeTable = CRM_Case_BAO_CaseType::getTableName();
    $caseCategoryColumnName = 'case_type_category';

    // Required for multilingual installations
    if ($dbLocale) {
      $caseTypeTable = substr($caseTypeTable, 0, -strlen($dbLocale));
    }

    if (!SchemaHandler::checkIfFieldExists($caseTypeTable, $caseCategoryColumnName)) {
      CRM_Upgrade_Incremental_Base::addColumn(NULL, $caseTypeTable, $caseCategoryColumnName, 'INT(10)');
    }
  }

  /**
   * Create Case Type Category option group
   */
  private function createCaseCategoryOptionGroup () {
    CRM_Core_BAO_OptionGroup::ensureOptionGroupExists([
      'name' => 'case_type_categories',
      'title' => ts('Case Type Categories'),
      'is_reserved' => 1,
    ]);
  }

}
