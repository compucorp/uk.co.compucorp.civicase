<?php

use Psr\SimpleCache\CacheInterface;

/**
 * Flush cached case-type information whenever a CaseType changes.
 */
class CRM_Civicase_Hook_Post_CaseTypeCache {

  private const JS_KEY     = 'civicase_js_var_case_types';
  private const PREFIX_CAT = 'civicase_case_type_for_';

  /**
   * Run the hook.
   *
   * @param string $op
   *   Operation: create|edit|delete|view.
   * @param string $objectName
   *   DAO/API entity name.
   * @param int $objectId
   *   Record ID (unused).
   * @param object $objectRef
   *   DAO reference (unused).
   */
  public function run(string $op, string $objectName, $objectId, &$objectRef): void {
    if (!$this->shouldRun($op, $objectName)) {
      return;
    }

    $cache = Civi::cache();

    // Drop JS bootstrap blob.
    $cache->delete(self::JS_KEY);

    // Remove every “case_type_for_xxx” entry.
    if (method_exists($cache, 'deleteByPattern')) {
      $cache->deleteByPattern('/^' . self::PREFIX_CAT . '/');
    }
    else {
      $this->purgeByPrefix($cache, self::PREFIX_CAT);
    }
  }

  /**
   * Delete all keys that start with a given prefix.
   *
   * @param Psr\SimpleCache\CacheInterface $cache
   *   Cache pool.
   * @param string $prefix
   *   Key prefix.
   */
  private function purgeByPrefix(CacheInterface $cache, string $prefix): void {
    if ($cache instanceof IteratorAggregate) {
      foreach ($cache->getIterator() as $key => $unused) {
        if (strpos($key, $prefix) === 0) {
          $cache->delete($key);
        }
      }
    }
    else {
      // Non-iterable backend – clear whole pool (tiny & safe).
      $cache->clear();
    }
  }

  /**
   * Decide whether this hook should act.
   *
   * @param string $op
   *   Operation name.
   * @param string $objectName
   *   Entity name.
   *
   * @return bool
   *   TRUE when the entity is CaseType and $op mutates data.
   */
  private function shouldRun(string $op, string $objectName): bool {
    return $objectName === 'CaseType'
      && in_array($op, ['create', 'edit', 'delete'], TRUE);
  }

}
