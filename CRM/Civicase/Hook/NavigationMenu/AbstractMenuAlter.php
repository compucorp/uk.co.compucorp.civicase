<?php

/**
 * Class AbstractMenuAlter.
 */
abstract class CRM_Civicase_Hook_NavigationMenu_AbstractMenuAlter {

  /**
   * Modifies the navigation menu.
   *
   * @param array $menu
   *   Menu Array.
   */
  abstract public function run(array &$menu);

  /**
   * Allow a menu item to be inserted into the position of an existing item.
   *
   * @param array $menus
   *   Array of menu items.
   * @param string $menuBefore
   *   Unique name of the menuitem to be moved down.
   *
   * @return int
   *   Weight of the menu item that was moved down.
   */
  protected function moveMenuDown(array &$menus, string $menuBefore) {
    $weight = $desiredWeight = 0;
    $moveDown = FALSE;

    foreach ($menus as $key => &$value) {
      if ($value['attributes']['name'] === $menuBefore) {
        $weight = $desiredWeight = (int) $value['attributes']['weight'];
        $moveDown = TRUE;
      }

      if ($moveDown) {
        $value['attributes']['weight'] = ++$weight;
      }
    }

    return $desiredWeight;
  }

}
