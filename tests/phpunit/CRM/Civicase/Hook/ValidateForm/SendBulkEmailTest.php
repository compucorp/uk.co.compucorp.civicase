<?php

use CRM_Civicase_Hook_ValidateForm_SendBulkEmail as SendBulkEmailHook;

/**
 * Test class for the CRM_Civicase_Hook_ValidateForm_SendBulkEmail.
 *
 * @group headless
 */
class CRM_Civicase_Hook_SendBulkEmailTest extends BaseHeadlessTest {

  use CRM_Civicase_Helpers_SessionTrait;
  use Helpers_MailHelpersTrait;

  /**
   * Instance of the form sent on the hook.
   *
   * @var CRM_Core_Form
   */
  private $form;

  /**
   * Case Type created for using on tests.
   *
   * @var array
   */
  private $caseType;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    $loggedInUser = CRM_Civicase_Test_Fabricator_Contact::fabricateWithEmail();
    $this->registerCurrentLoggedInContactInSession($loggedInUser['id']);

    $this->caseType = CRM_Civicase_Test_Fabricator_CaseType::fabricate()['id'];
  }

  /**
   * Verify that one email is created for every case involved.
   */
  public function testHookCreateEmailForCases() {
    $clientA = $this->createContact('client_a');
    $clientB = $this->createContact('client_b');

    $contact = $this->createContact('contact');
    $relationshipType = CRM_Civicase_Test_Fabricator_RelationshipType::fabricate();

    $caseA = $this->createCaseForContact($clientA['id']);
    $caseB = $this->createCaseForContact($clientB['id']);
    CRM_Civicase_Test_Fabricator_Relationship::fabricate([
      'case_id' => $caseA['id'],
      'contact_id_a' => $clientA['id'],
      'contact_id_b' => $contact['id'],
      'relationship_type_id' => $relationshipType['id'],
    ]);
    CRM_Civicase_Test_Fabricator_Relationship::fabricate([
      'case_id' => $caseB['id'],
      'contact_id_a' => $clientB['id'],
      'contact_id_b' => $contact['id'],
      'relationship_type_id' => $relationshipType['id'],
    ]);

    $_REQUEST['caseRoles'] = $_GET['caseRoles'] = "client,{$relationshipType['id']}";

    $this->deleteEmailNotificationsInDatabase();
    $emailsSent = $this->getNotificationsByEmail(
      ['client_a@example.com', 'client_b@example.com', 'contact@example.com']
    );
    $this->assertEquals(0, count($emailsSent));

    $this->form = new CRM_Contact_Form_Task_Email();
    $this->runHook(
      'Test {case.id} for contact {contact.contact_id}',
      'Body for {contact.first_name} {case.id}',
      [$caseA['id'], $caseB['id']],
      [$clientA, $clientB, $contact]
    );

    $emailsSent = $this->getNotificationsByEmail([
      'client_a@example.com', 'client_b@example.com', 'contact@example.com',
    ]
    );

    // Check emails sent for client A.
    $emails = $emailsSent['client_a@example.com'];
    $this->assertCount(1, $emails);
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'Subject', "Test {$caseA['id']} for contact {$clientA['id']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'body', "Body for {$clientA['first_name']} {$caseA['id']}")
    );

    // Check emails sent for client B.
    $emails = $emailsSent['client_b@example.com'];
    $this->assertCount(1, $emails);
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'Subject', "Test {$caseB['id']} for contact {$clientB['id']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'body', "Body for {$clientB['first_name']} {$caseB['id']}")
    );

    // Check emails sent for contact (involved on both cases)
    $emails = $emailsSent['contact@example.com'];
    $this->assertCount(2, $emails);
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'Subject', "Test {$caseA['id']} for contact {$contact['id']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'body', "Body for {$contact['first_name']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'Subject', "Test {$caseB['id']} for contact {$contact['id']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'body', "Body for {$contact['first_name']}")
    );
  }

  /**
   * Verify client is not notified if it was excluded from receiving contacts.
   */
  public function testHookDoNotSendEmailToExcludedContact() {
    $clientA = $this->createContact('client_a');
    $clientB = $this->createContact('client_b');

    $caseA = $this->createCaseForContact($clientA['id']);
    $caseB = $this->createCaseForContact($clientB['id']);

    $_REQUEST['caseRoles'] = $_GET['caseRoles'] = "client";

    $this->deleteEmailNotificationsInDatabase();
    $emailsSent = $this->getNotificationsByEmail(
      ['client_a@example.com', 'client_b@example.com']
    );
    $this->assertEquals(0, count($emailsSent));

    $this->form = new CRM_Contact_Form_Task_Email();
    $this->runHook(
      'Test {case.id} for contact {contact.contact_id}',
      'Body for {contact.first_name} {case.id}',
      [$caseA['id'], $caseB['id']],
      [$clientA]
    );

    $emailsSent = $this->getNotificationsByEmail(
      ['client_a@example.com', 'client_b@example.com']
    );

    // Check emails sent for client A.
    $emails = $emailsSent['client_a@example.com'];
    $this->assertCount(1, $emails);
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'Subject', "Test {$caseA['id']} for contact {$clientA['id']}")
    );
    $this->assertTrue(
      $this->searchKeyOnEmailInfo($emails, 'body', "Body for {$clientA['first_name']} {$caseA['id']}")
    );

    // Check there are no emails sent for client B.
    $this->assertFalse(isset($emailsSent['client_b@example.com']));
  }

  /**
   * Test that the hook is executed when we use an email form.
   */
  public function testHookRunsWhenEmailFormIsUsed() {
    $contact = $this->createContact('ContactTest');
    $case = $this->createCaseForContact($contact['id']);

    $_REQUEST['caseRoles'] = $_GET['caseRoles'] = 'client';

    $this->form = new CRM_Contact_Form_Task_Email();
    $result = $this->runHook(
      '',
      '',
      [$case['id']],
      [$contact]
    );

    $this->assertTrue($result);
  }

  /**
   * Test that the hook is not executed when we use another form.
   */
  public function testHookDoesNotRunWhenOtherFormIsUsed() {
    $contact = $this->createContact('ContactTest');
    $case = $this->createCaseForContact($contact['id']);

    $_REQUEST['caseRoles'] = $_GET['caseRoles'] = 'client';

    $this->form = new CRM_Contact_Form_Task();
    $result = $this->runHook(
      '',
      '',
      [$case['id']],
      [$contact]
    );

    $this->assertFalse($result);
  }

  /**
   * Test that the hook is not executed when no cases are selected.
   */
  public function testHookDoesNotRunWhenNoCasesAreSelected() {
    $_REQUEST['caseRoles'] = $_GET['caseRoles'] = '';

    $this->form = new CRM_Contact_Form_Task_Email();
    $result = $this->runHook('', '', [], []);

    $this->assertFalse($result);
  }

  /**
   * Utility for checking on the emails sent array for a given key/value.
   *
   * @param array $emailsSent
   *   Info of the emails sent.
   * @param string $key
   *   Key name of the attribute to be searched.
   * @param string $expected
   *   Expected value for the attribute.
   *
   * @return bool
   *   Returns TRUE if the key if found with the expected value.
   */
  private function searchKeyOnEmailInfo(array $emailsSent, string $key, string $expected) {
    foreach ($emailsSent as $emailSent) {
      // In some situations the case hash is added, that's why we
      // don't search for equality.
      if (strpos($emailSent[$key], $expected) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Create a test contact with an email.
   *
   * @param string $name
   *   String to be used for generating the information.
   *
   * @return array
   *   Contact details, with the email included.
   */
  private function createContact($name) {
    $contact = CRM_Civicase_Test_Fabricator_Contact::fabricateWithEmail(
      [
        'first_name' => "{$name}_FN",
        'last_name' => "{$name}_LN",
      ],
      "{$name}@example.com"
    );
    $contact['email'] = "{$name}@example.com";

    return $contact;
  }

  /**
   * Create a case for the given contact.
   *
   * @param int $contactId
   *   ID of the contact.
   *
   * @return array
   *   Array with case information.
   */
  private function createCaseForContact(int $contactId) {
    return CRM_Civicase_Test_Fabricator_Case::fabricate(
      [
        'contact_id' => $contactId,
        'creator_id' => $contactId,
        'case_type_id' => $this->caseType,
      ]
    );
  }

  /**
   * Perform the necessary setup for running the hook, and run it.
   *
   * @param string $subject
   *   Subject of the email.
   * @param string $body
   *   Body of the email.
   * @param array $cases
   *   Array with case ids.
   * @param array $contacts
   *   Array with contacts ids.
   *
   * @return bool
   *   Return the result of calling the hook.
   */
  private function runHook(string $subject, string $body, array $cases, array $contacts) {
    $_REQUEST['allCaseIds'] = $_GET['allCaseIds'] = implode(',', $cases);

    $formName = 'Test Form';
    $fields = $files = $errors = [];

    $this->form->_contactIds = [];
    $this->form->_toContactEmails = [];
    $this->form->_contactDetails = [];
    foreach ($contacts as $contact) {
      $this->form->_contactIds[] = $contact['id'];
      $this->form->_toContactEmails[] = $contact['email'];
      $this->form->_contactDetails[$contact['id']] = [
        'email' => $contact['email'],
        'contact_id' => $contact['id'],
        'preferred_mail_format' => 'HTML',
      ];
    }

    $this->form->addElement('hidden', 'cc_id', []);
    $this->form->addElement('hidden', 'bcc_id', []);
    $this->form->addElement('hidden', 'subject', $subject);
    $this->form->addElement('hidden', 'text_message', $body);
    $this->form->addElement('hidden', 'html_message', $body);
    $this->form->addElement('hidden', 'from_email_address', 'example@mail.com');

    return (new SendBulkEmailHook())->run($formName, $fields, $files, $this->form, $errors);
  }

  /**
   * Count the email activities created for a given case.
   *
   * @param int $case
   *   Id of the case.
   *
   * @return int
   *   Number of activities that match the search parameters.
   */
  private function countEmailActivitiesCreated($case = NULL) {
    $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Email');

    $activityParams = [
      'activity_type_id' => $activityTypeID,
      'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed'),
    ];
    if (!empty($case)) {
      $activityParams['case_id'] = $case;
    }

    return civicrm_api3('Activity', 'getcount', $activityParams);
  }

}
