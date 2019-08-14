<?php
/**
 * @file
 */

namespace Drupal\menu_hierarchy_block\Plugin\Block;

use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides a menu hierarchy sibling block.
 *
 * @Block(
 *   id = "menu_hierarchy_sibling",
 *   admin_label = @Translation("Siblings"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_hierarchy_block\Plugin\Derivative\MenuHierarchyBlock",
 * )
 */

class MenuHierarchySiblingBlock extends MenuHierarchyBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function getMenuTree() {
    // Get the menu name.
    $menu_name = $this->getDerivativeId();

    // Get the active trail for the menu hierarchy block.
    $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);

    // Get the current active menu item.
    $current = array_shift($active_trail);

    // Get the sibling.
    $parent = array_shift($active_trail);

    // Create the parameters for loading.
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($active_trail)
      ->setRoot($parent)
      ->onlyEnabledLinks()
      ->setMinDepth(1)
      ->setMaxDepth(1);

    // Load the sibling tree.
    $tree = $this->menuTree->load($menu_name, $parameters);

    return $tree;
  }

}
