<?php

/**
 * Add Case token category class.
 */
class CRM_Civicase_Hook_Tokens_AddCaseTokenCategory {

  /**
   * Sets Case Token Category.
   *
   * @param array $tokens
   *   Available tokens.
   */
  public function run(array &$tokens) {
    if (!$this->shouldRun($tokens)) {
      return;
    }

    $this->setCaseTokenCategory($tokens);
  }

  /**
   * Sets Case Token Category.
   *
   * Without this function, the token values added in
   * AddCaseCustomFieldsTokenValues class will not be replaced.
   * Because the token values are added as part of the case category tokens
   * included within contact tokens, Civi will fetch the array keys of
   * the $tokens to use that to determine the token categories present, hence
   * the reason why we are adding the case_cf token category. No need to set
   * any tokens for this category, just the key is enough.
   *
   * @param array $tokens
   *   Available tokens.
   */
  private function setCaseTokenCategory(array &$tokens) {
    if (CIVICRM_UF === 'UnitTests') {
      // For unit tests where AddCaseCustomFieldsTokenValues might not be called
      // using an empty key breaks the code.
      return $tokens['case_cf'] = [];
    }

    $tokens['case_cf'][''] = '';
  }

  /**
   * Decides whether the hook should run or not.
   *
   * @param array $tokens
   *   Available tokens.
   *
   * @return bool
   *   Whether this hook should run or not.
   */
  private function shouldRun(array $tokens) {
    return !isset($tokens['case_cf']);
  }

}
