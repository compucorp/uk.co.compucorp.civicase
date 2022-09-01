<?php

/**
 * Trait with utilities for testing emails.
 */
trait Helpers_MailHelpersTrait {

  /**
   * Get emails notification for spool table.
   *
   * @param array $emails
   *   Array with emails to look.
   *
   * @return CRM_Core_DAO
   *   Object with the results.
   */
  public function getEmailNotificationsFromDatabase(array $emails) {
    $emails = "'" . implode("','", $emails) . "'";
    $messageSpoolTable = CRM_Mailing_BAO_Spool::getTableName();
    $query = "SELECT * FROM {$messageSpoolTable} WHERE recipient_email
              IN($emails)";

    return CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Get notifications by email, with the headers separated.
   *
   * @param array $emails
   *   Array with emails to look.
   *
   * @return array
   *   Returns an array with the information parsed.
   */
  public function getNotificationsByEmail(array $emails) {
    $result = $this->getEmailNotificationsFromDatabase($emails);
    $emails = [];
    while ($result->fetch()) {
      $allHeaders = explode(PHP_EOL, $result->headers);
      $headerValues = [];
      foreach ($allHeaders as $headerLine) {
        $tmp = explode(': ', $headerLine);
        // Not all headers are separated by `: ` some headers
        // are separated by by `=`
        // e.g. boundary="=_450418403cfd293a551c46b24aeb05c6"
        // we simply ignore such header value.
        $headerValues[$tmp[0]] = $tmp[1] ?? '';
      }

      $emails[$result->recipient_email][] = array_merge(
        [
          'body' => $result->body,
          'headers' => $result->headers,
        ],
        $headerValues
      );
    }

    return $emails;
  }

  /**
   * Delete all notifications in spool table.
   */
  public function deleteEmailNotificationsInDatabase() {
    $messageSpoolTable = CRM_Mailing_BAO_Spool::getTableName();
    CRM_Core_DAO::executeQuery("DELETE FROM {$messageSpoolTable}");
  }

}
