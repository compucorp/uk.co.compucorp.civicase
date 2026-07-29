<?php

/**
 * @file
 * Tests for case category word replacements.
 */

use CRM_Civicase_Helper_CaseCategory as CaseCategoryHelper;
use CRM_Civicase_Service_CaseCategoryCustomFieldsSetting as CaseCategoryCustomFieldsSetting;
use CRM_Civicase_Test_Fabricator_CaseCategory as CaseCategoryFabricator;
use CRM_Civicase_Test_Fabricator_CaseCategoryInstance as CaseCategoryInstanceFabricator;
use CRM_Civicase_Test_Fabricator_CaseCategoryInstanceType as CaseCategoryInstanceTypeFabricator;

/**
 * Word replacement fixture class.
 */
class CRM_Civicase_Helper_CaseCategoryTest_WordReplacementFixture implements CRM_Civicase_WordReplacement_BaseInterface {

  /**
   * {@inheritdoc}
   */
  public function get() {
    return [
      'a Case' => 'an Application',
      'a case' => 'an application',
      'Case client' => 'Applicant',
      'Case clients' => 'Applicants',
      'Case' => 'Application',
      'Cases' => 'Applications',
      'case' => 'application',
      'cases' => 'applications',
      'Client' => 'Applicant',
      'client' => 'applicant',
    ];
  }

}

/**
 * Runs tests on CaseCategory helper word replacement methods.
 *
 * @group headless
 */
class CRM_Civicase_Helper_CaseCategoryTest extends BaseHeadlessTest {

  /**
   * User-created categories should display their own labels.
   */
  public function testWordReplacementsUseCategoryLabelsForUserCreatedCategory() {
    $category = $this->createCategoryWithReplacementClass(
      ['label' => 'Grants', 'is_reserved' => 0],
      'Grant'
    );

    $replacements = CaseCategoryHelper::getWordReplacements($category['name']);

    $this->assertEquals('You have 0 new grants', $this->applyReplacements($replacements, 'You have 0 new cases'));
    $this->assertEquals('View all grants', $this->applyReplacements($replacements, 'View all cases'));
    $this->assertEquals('Add Grant', $this->applyReplacements($replacements, 'Add Case'));
    $this->assertEquals('Manage Grants', $this->applyReplacements($replacements, 'Manage Cases'));
    $this->assertEquals('Add a Grant', $this->applyReplacements($replacements, 'Add a Case'));
    // Domain-specific words of the instance type are kept.
    $this->assertEquals('Applicant', $this->applyReplacements($replacements, 'Case client'));
    $this->assertEquals('Applicant', $this->applyReplacements($replacements, 'Client'));
  }

  /**
   * Categories with labels starting with a vowel should use the "an" article.
   */
  public function testWordReplacementsUseCorrectArticleForVowelLabels() {
    $category = $this->createCategoryWithReplacementClass(
      ['label' => 'Enquiries', 'is_reserved' => 0],
      'Enquiry'
    );

    $replacements = CaseCategoryHelper::getWordReplacements($category['name']);

    $this->assertEquals('Add an enquiry', $this->applyReplacements($replacements, 'Add a case'));
    $this->assertEquals('Add an Enquiry', $this->applyReplacements($replacements, 'Add a Case'));
    // Irregular plural labels must not be built from singular + "s".
    $this->assertEquals('Enquiries', $this->applyReplacements($replacements, 'Cases'));
  }

  /**
   * Reserved (extension-shipped) categories keep their hardcoded words.
   */
  public function testWordReplacementsKeepHardcodedWordsForReservedCategory() {
    $category = $this->createCategoryWithReplacementClass(
      ['label' => 'Awards', 'is_reserved' => 1],
      'Award'
    );

    $replacements = CaseCategoryHelper::getWordReplacements($category['name']);

    $this->assertEquals(
      (new CRM_Civicase_Helper_CaseCategoryTest_WordReplacementFixture())->get(),
      $replacements
    );
    $this->assertEquals('You have 0 new applications', $this->applyReplacements($replacements, 'You have 0 new cases'));
  }

  /**
   * The category label is used when the singular label is missing.
   */
  public function testWordReplacementsFallBackToLabelWhenSingularLabelIsMissing() {
    $category = $this->createCategoryWithReplacementClass(
      ['label' => 'Grants', 'is_reserved' => 0],
      NULL
    );

    $replacements = CaseCategoryHelper::getWordReplacements($category['name']);

    $this->assertEquals('Grants', $this->applyReplacements($replacements, 'Case'));
    $this->assertEquals('grants', $this->applyReplacements($replacements, 'cases'));
  }

  /**
   * Instance types without a word replacement class use the category labels.
   */
  public function testWordReplacementsUseCategoryLabelsWhenNoReplacementClassExists() {
    $instanceType = CaseCategoryInstanceTypeFabricator::fabricate();
    $category = CaseCategoryFabricator::fabricate(['label' => 'Support Requests']);
    CaseCategoryInstanceFabricator::fabricate([
      'category_id' => $category['value'],
      'instance_id' => $instanceType['value'],
    ]);
    (new CaseCategoryCustomFieldsSetting())->save($category['value'], [
      'singular_label' => 'Support Request',
    ]);

    $replacements = CaseCategoryHelper::getWordReplacements($category['name']);

    $this->assertEquals('You have 0 new support requests', $this->applyReplacements($replacements, 'You have 0 new cases'));
    $this->assertEquals('Add Support Request', $this->applyReplacements($replacements, 'Add Case'));
  }

  /**
   * Creates a category whose instance type has a word replacement class.
   *
   * @param array $categoryParams
   *   Parameters for the case category option value.
   * @param string|null $singularLabel
   *   The singular label custom field value, or NULL to not set one.
   *
   * @return array
   *   The case category option value.
   */
  private function createCategoryWithReplacementClass(array $categoryParams, $singularLabel) {
    $instanceType = CaseCategoryInstanceTypeFabricator::fabricate();
    $category = CaseCategoryFabricator::fabricate($categoryParams);
    CaseCategoryInstanceFabricator::fabricate([
      'category_id' => $category['value'],
      'instance_id' => $instanceType['value'],
    ]);

    civicrm_api3('OptionValue', 'create', [
      'option_group_id' => 'case_type_category_word_replacement_class',
      'name' => $instanceType['name'] . '_word_replacement',
      'label' => $instanceType['name'] . '_word_replacement',
      'value' => CRM_Civicase_Helper_CaseCategoryTest_WordReplacementFixture::class,
      'is_active' => 1,
    ]);

    if ($singularLabel !== NULL) {
      (new CaseCategoryCustomFieldsSetting())->save($category['value'], [
        'singular_label' => $singularLabel,
      ]);
    }

    return $category;
  }

  /**
   * Applies word replacements to the given text.
   *
   * @param array $replacements
   *   The word to be replaced and replacement array.
   * @param string $text
   *   The text to apply the replacements to.
   *
   * @return string
   *   The text with the replacements applied.
   */
  private function applyReplacements(array $replacements, $text) {
    return str_replace(
      array_keys($replacements),
      array_values($replacements),
      $text
    );
  }

}
