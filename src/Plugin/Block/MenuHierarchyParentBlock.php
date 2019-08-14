<?php
/**
 * @file
 */

namespace Drupal\menu_hierarchy_block\Plugin\Block;

use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides a menu hierarchy parent block.
 *
 * @Block(
 *   id = "menu_hierarchy_parent",
 *   admin_label = @Translation("Parent"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_hierarchy_block\Plugin\Derivative\MenuHierarchyBlock",
 * )
 */

class MenuHierarchyParentBlock extends MenuHierarchyBlockBase {

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

    // Get the parent.
    $parent = array_shift($active_trail);

    // Get the parents parent.
    $parent_parent = array_shift($active_trail);

    // Create the parameters for loading.
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($active_trail)
      ->setRoot($parent_parent)
      ->onlyEnabledLinks()
      ->setMaxDepth(1);

    // Load the tree
    $tree = $this->menuTree->load($menu_name, $parameters);

    // Restrict the tree to the parent id
    return !empty($tree[$parent]) ? [$parent => $tree[$parent]] : [];
  }

}
