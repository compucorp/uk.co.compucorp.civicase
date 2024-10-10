<?php

use Civi\Api4\CiviCase;
use Civi\Api4\Contact;

/**
 * Handles link case logic when case is created.
 */
class CRM_Civicase_Hook_Post_LinkCase {

  /**
   * Link Cases.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   * @param int $objectId
   *   Object ID.
   * @param object $objectRef
   *   Object reference.
   */
  public function run(string $op, string $objectName, ?int $objectId, &$objectRef) {
    if (!$this->shouldRun($op, $objectName)) {
      return;
    }

    $linkToCaseId = (int) CRM_Utils_Request::retrieve('linkToCaseId', 'Positive');
    $linkedToCaseDetails = $this->getLinkedToCaseDetails($linkToCaseId);
    $caseDetails = $this->getCaseDetails($objectId);

    $params = [
      'case_id' => $linkToCaseId,
      'link_to_case_id' => $objectId,
      'activity_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Link Cases'),
      'medium_id' => CRM_Core_OptionGroup::values('encounter_medium', FALSE, FALSE, FALSE, 'AND is_default = 1'),
      'activity_date_time' => date('YmdHis'),
      'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_status_id', 'Completed'),
      'subject' => $this->getActivitySubject($caseDetails, $linkedToCaseDetails),
      'source_contact_id' => \CRM_Core_Session::getLoggedInContactID(),
      'target_contact_id' => $linkedToCaseContacts[0]['contact_id'] ?? NULL,
    ];

    $activity = CRM_Activity_BAO_Activity::create($params);

    $caseParams = [
      'case_id' => $objectId,
      'activity_id' => $activity->id,
    ];
    CRM_Case_BAO_Case::processCaseActivity($caseParams);

    $caseParams = [
      'case_id' => $linkToCaseId,
      'activity_id' => $activity->id,
    ];
    CRM_Case_BAO_Case::processCaseActivity($caseParams);
  }

  /**
   * Determines if the hook should run or not.
   *
   * @param string $op
   *   The operation being performed.
   * @param string $objectName
   *   Object name.
   *
   * @return bool
   *   returns a boolean to determine if hook will run or not.
   */
  private function shouldRun(string $op, string $objectName): bool {
    return $objectName == 'Case' && $op === 'create'
      && (int) CRM_Utils_Request::retrieve('linkToCaseId', 'Positive') > 0;
  }

  /**
   * Get case details required to create the activity subject.
   *
   * @param int $id
   *   The case id.
   *
   * @return array
   *   case details.
   */
  private function getLinkedToCaseDetails(int $id): array {
    $case = CiviCase::get(FALSE)
      ->addSelect('contact.display_name', 'case_type_id.title')
      ->addJoin('CaseContact AS case_contact', 'INNER', ['id', '=', 'case_contact.case_id'])
      ->addJoin('Contact AS contact', 'INNER', ['contact.id', '=', 'case_contact.contact_id'])
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    return [
      'id' => $id,
      'caseType' => $case['case_type_id.title'] ?? '',
      'contact' => $case['contact.display_name'] ?? '',
    ];
  }

  /**
   * Get case details required to create the activity subject.
   *
   * @param int $id
   *   The case id.
   *
   * @return array
   *   case details.
   */
  private function getCaseDetails(int $id): array {
    $caseClient = Contact::get(FALSE)
      ->addSelect('display_name')
      ->addWhere('id', '=', $_POST['client_id'] ?? 0)
      ->execute()
      ->first();

    return [
      'id' => $id,
      'caseType' => CRM_Case_BAO_Case::getCaseType($id),
      'contact' => $caseClient['display_name'] ?? '',
    ];
  }

  /**
   * Create activity subject.
   *
   * @param array $caseDetails
   *   The case details.
   * @param array $linkedToCaseDetails
   *   The linked to case details.
   *
   * @return string
   *   Activity subject.
   */
  private function getActivitySubject(array $caseDetails, array $linkedToCaseDetails): string {
    return ts('Create link between %1 - %2 (CaseID: %3) and %4 - %5 (CaseID: %6)', [
      1 => $caseDetails['contact'],
      2 => $caseDetails['caseType'],
      3 => $caseDetails['id'],
      4 => $linkedToCaseDetails['contact'],
      5 => $linkedToCaseDetails['caseType'],
      6 => $linkedToCaseDetails['id'],
    ]);
  }

}
