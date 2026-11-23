<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * Adds the page resources the "civicase" Angular module relies on.
 *
 * The ang/civicase.ang.php file used to add these, but core only runs it when
 * it rebuilds its cached Angular module list, so only the request that rebuilt
 * the cache got them. This adds them on every page that loads the module,
 * directly or as a dependency of another module.
 */
class CRM_Civicase_Event_Listener_CivicaseAngularResources {

  /**
   * Listener for `civi.region.render`.
   *
   * Registered after core's AngularLoader (priority 0), so the page's modules
   * are known, and still before the region renders what was added to it.
   *
   * @param \Civi\Core\Event\GenericHookEvent $event
   *   The event; has ->region.
   */
  public static function onRegionRender(GenericHookEvent $event): void {
    if ($event->region->_name !== 'html-header') {
      return;
    }

    $loader = Civi::service('angularjs.loader');
    if (!$loader->getModules() || !in_array('civicase', $loader->findActiveModules(), TRUE)) {
      return;
    }

    Civi::resources()->addSetting([
      'config' => [
        'enableComponents' => CRM_Core_Config::singleton()->enableComponents,
        'user_contact_id' => (int) CRM_Core_Session::getLoggedInContactID(),
      ],
    ]);

    // The affix plugin and the theme's CSS come from org.civicrm.shoreditch.
    // Without its code, adding them would throw on every page.
    if (!self::isShoreditchAvailable()) {
      return;
    }

    Civi::resources()
      ->addScriptFile('org.civicrm.shoreditch', 'base/js/affix.js', 1000, 'html-header');

    if ($loader->getPageName() === 'civicrm/case/a' && !Civi::settings()->get('customCSSURL')) {
      Civi::resources()
        ->addStyleFile('org.civicrm.shoreditch', 'css/custom-civicrm.css', 99, 'html-header');
    }
  }

  /**
   * Checks whether the org.civicrm.shoreditch extension's code is present.
   *
   * @return bool
   *   TRUE when its files can be added to the page.
   */
  private static function isShoreditchAvailable(): bool {
    try {
      CRM_Extension_System::singleton()->getMapper()->keyToBasePath('org.civicrm.shoreditch');

      return TRUE;
    }
    catch (CRM_Extension_Exception_MissingException $e) {
      return FALSE;
    }
  }

}
