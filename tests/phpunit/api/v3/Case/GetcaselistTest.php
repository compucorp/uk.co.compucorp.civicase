<?php

use CRM_Civicase_Test_Fabricator_Contact as ContactFabricator;
use CRM_Civicase_Test_Fabricator_RelationshipType as RelationshipTypeFabricator;
use CRM_Civicase_Test_Fabricator_Relationship as RelationshipFabricator;
use CRM_Civicase_Test_Fabricator_CaseType as CaseTypeFabricator;
use CRM_Civicase_Test_Fabricator_Case as CaseFabricator;
use CRM_Civicase_Test_Fabricator_CustomField as CustomFieldFabricator;

/**
 * Runs tests for api_v3_Case_Getcaselist api class.
 *
 * @group headless
 */
class api_v3_Case_GetcaselistTest extends BaseHeadlessTest { // phpcs:ignore

  use CRM_Civicase_Helpers_SessionTrait;

  /**
   * Holds logged in contact/case creator id.
   *
   * @var int
   */
  private $creator;

  /**
   * Setup data before tests run.
   */
  public function setUp(): void {
    $contact = ContactFabricator::fabricate();
    $this->registerCurrentLoggedInContactInSession($contact['id']);
    $this->creator = $contact['id'];
  }

  /**
   * Test fetch cases filtered by relationship type and contact.
   */
  public function testFilterByRelationshipTypeAndRelatedContact() {
    $contact = ContactFabricator::fabricate();
    $client = ContactFabricator::fabricate();
    $caseType = CaseTypeFabricator::fabricate();

    $caseA = CaseFabricator::fabricate(
      [
        'case_type_id' => $caseType['id'],
        'contact_id' => $client['id'],
        'creator_id' => $this->creator,
      ]
    );

    $caseB = CaseFabricator::fabricate(
      [
        'case_type_id' => $caseType['id'],
        'contact_id' => $client['id'],
        'creator_id' => $this->creator,
      ]
    );

    $relationshipTypeParams = [
      'name_a_b' => 'Manager is',
      'name_b_a' => 'Manager',
    ];
    $relationshipType = RelationshipTypeFabricator::fabricate($relationshipTypeParams);
    $params = [
      'contact_id_b' => $contact['id'],
      'contact_id_a' => $client['id'],
      'relationship_type_id' => $relationshipType['id'],
      'case_id' => $caseA['id'],
    ];

    RelationshipFabricator::fabricate($params);

    $caseDetailsParams = [
      'has_role' =>
        [
          'contact' => ['IN' => [$contact['id']]],
          'can_be_client' => FALSE,
          'all_case_roles_selected' => FALSE,
          'role_type' => ['IN' => [$relationshipType['id']]],
        ],
    ];

    $result = civicrm_api3('Case', 'getcaselist', $caseDetailsParams);
    // Only Case A is expected to be returned because that is the only case a
    // relationship type manager was defined for with contact.
    $this->assertEquals($caseA['id'], $result['id']);
  }

  /**
   * Test cases can be filtered by custom field with multiple options.
   */
  public function testFilterByCustomFieldWithMultipleOptions() {
    $client = ContactFabricator::fabricate();
    $caseType = CaseTypeFabricator::fabricate(['name' => md5(mt_rand())]);
    $options = [['value' => 1], ['value' => 2]];
    $optionGroupId = $this->createFieldOptions($options);
    $customFieldParams = [
      'html_type' => 'Select',
      'option_group_id' => $optionGroupId,
    ];
    $customField = CustomFieldFabricator::fabricate($customFieldParams);
    $customField = 'custom_' . $customField['id'];

    $caseA = CaseFabricator::fabricate(
      [
        'subject' => md5(mt_rand()),
        'case_type_id' => $caseType['id'],
        'contact_id' => $client['id'],
        'creator_id' => $this->creator,
      ]
    );

    // Assign field with multiple options to the case.
    civicrm_api3('CustomValue', 'create', [
      $customField => [1, 2],
      'entity_id' => $caseA['id'],
    ]);

    CaseFabricator::fabricate(
      [
        'case_type_id' => $caseType['id'],
        'contact_id' => $client['id'],
        'creator_id' => $this->creator,
      ]
    );

    // Attempt to search by one of the assigned options.
    $caseDetailsParams = [
      $customField => 1,
    ];

    $result = civicrm_api3('Case', 'getdetails', $caseDetailsParams);

    // Confirm that only caseA with the searched option is returned.
    $this->assertCount(1, $result['values']);
    $this->assertEquals($caseA['id'], $result['id']);
  }

  /**
   * Create grouped custom field options.
   *
   * @param array $options
   *   Array contaning values for the options.
   *
   * @return int
   *   Returns the option group id.
   */
  private function createFieldOptions(array $options) {
    $params = [
      'name' => md5(mt_rand()),
      'is_active' => 1,
    ];
    $result = civicrm_api3('OptionGroup', 'create', $params);

    array_walk($options, function ($option) use ($result) {
      civicrm_api3('OptionValue', 'create', [
        'option_group_id' => $result['id'],
        'label' => md5(mt_rand()),
        'value' => $option['value'],
        'is_active' => 1,
      ]);
    });

    return $result['id'];
  }

}
