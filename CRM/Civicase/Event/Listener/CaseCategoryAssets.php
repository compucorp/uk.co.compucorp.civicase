<?php

use Civi\Core\Event\GenericHookEvent;
use CRM_Civicase_Helper_CaseUrl as CaseUrlHelper;
use CRM_Civicase_Hook_Helper_CaseTypeCategory as CaseTypeCategoryHelper;

/**
 * Builds the Angular strings bundle per case category.
 *
 * The angular-modules.json bundle contains translated strings, and case
 * categories change some of those strings through word replacements. Instead
 * of resetting the site-wide resource cache code whenever a user switches
 * category (which forces every bundle to be rebuilt for everyone), the
 * category is added to the bundle parameters. The asset builder includes the
 * parameters in the bundle's digest, so each category gets its own cached
 * bundle, and only that category's word replacements (on top of the site's
 * own) are applied while the bundle is rendered.
 */
class CRM_Civicase_Event_Listener_CaseCategoryAssets {

  /**
   * Bundles whose content includes translated strings.
   *
   * The angular-modules.js bundle holds code only and is identical for every
   * category, so it is deliberately not listed.
   */
  const ASSETS = ['angular-modules.json'];

  /**
   * Name of the bundle parameter that carries the case category.
   */
  const PARAM = 'civicaseCategory';

  /**
   * Word replacement state saved while a bundle is rendered.
   *
   * @var array
   */
  private static $savedLocaleStrings = [];

  /**
   * Listener for hook_civicrm_getAssetUrl.
   *
   * Adds the case category of the current request (same source as
   * CRM_Civicase_Settings::getAll(): the case_type_category URL parameter,
   * defaulting to the standard Cases category) to the bundle parameters.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   Hook event with `asset` and `params` (by reference).
   */
  public static function onGetAssetUrl(GenericHookEvent $event) {
    if (!in_array($event->asset, self::ASSETS, TRUE)) {
      return;
    }
    try {
      [, $caseCategoryName] = CaseUrlHelper::getCategoryParamsFromUrl();
    }
    catch (CRM_Core_Exception $e) {
      // Unknown case_type_category in the URL: use the default Cases category,
      // so the bundle is still built from the site's own word replacements.
      $caseCategoryName = CRM_Civicase_Helper_CaseCategory::CASE_TYPE_CATEGORY_NAME;
    }
    if ($caseCategoryName) {
      $event->params[self::PARAM] = $caseCategoryName;
    }
  }

  /**
   * Listener for hook_civicrm_buildAsset, registered before core's builders.
   *
   * Renders the bundle with the site's own word replacements plus those of the
   * bundle's case category only, so replacements applied earlier in the same
   * request (for example by a form's PreProcess hook) cannot leak into a
   * bundle that is then cached for every user.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   Hook event with `asset`, `params`, `mimeType` and `content`.
   */
  public static function onBuildAssetBefore(GenericHookEvent $event) {
    if (!in_array($event->asset, self::ASSETS, TRUE) || empty($event->params[self::PARAM])) {
      return;
    }
    $locale = CRM_Core_I18n::getLocale();
    self::$savedLocaleStrings[] = [$locale, Civi::$statics[CRM_Core_I18n::class][$locale] ?? NULL];
    Civi::$statics[CRM_Core_I18n::class][$locale] = CRM_Core_BAO_WordReplacement::getLocaleCustomStrings($locale);
    CaseTypeCategoryHelper::applyWordReplacements($event->params[self::PARAM]);
  }

  /**
   * Listener for hook_civicrm_buildAsset, registered after core's builders.
   *
   * Restores the request's word replacements saved by onBuildAssetBefore().
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   Hook event with `asset`, `params`, `mimeType` and `content`.
   */
  public static function onBuildAssetAfter(GenericHookEvent $event) {
    if (!in_array($event->asset, self::ASSETS, TRUE) || empty($event->params[self::PARAM]) || !self::$savedLocaleStrings) {
      return;
    }
    [$locale, $saved] = array_pop(self::$savedLocaleStrings);
    if ($saved === NULL) {
      unset(Civi::$statics[CRM_Core_I18n::class][$locale]);
    }
    else {
      Civi::$statics[CRM_Core_I18n::class][$locale] = $saved;
    }
  }

}
