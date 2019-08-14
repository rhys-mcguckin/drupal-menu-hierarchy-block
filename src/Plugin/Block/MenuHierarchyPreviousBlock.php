<?php
/**
 * @file
 */

namespace Drupal\menu_hierarchy_block\Plugin\Block;

use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides a menu hierarchy previous block.
 *
 * @Block(
 *   id = "menu_hierarchy_previous",
 *   admin_label = @Translation("Previous"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_hierarchy_block\Plugin\Derivative\MenuHierarchyBlock",
 * )
 */

class MenuHierarchyPreviousBlock extends MenuHierarchyBlockBase {

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

    // Get the previous.
    $parent = array_shift($active_trail);

    // Create the parameters for loading.
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($active_trail)
      ->setRoot($parent)
      ->onlyEnabledLinks()
      ->setMinDepth(1)
      ->setMaxDepth(1);

    // Load the siblings tree.
    $tree = $this->menuTree->load($menu_name, $parameters);

    // Perform basic transforms, so that we get the correct ordering.
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);

    // Cycle through tree locate the current.
    $last = NULL;
    foreach ($tree as $item) {
      if ($item->link->getPluginId() === $current) {
        $found = TRUE;
        break;
      }
      $last = $item;
    }

    return !empty($last) && !empty($found) ? [$current => $last] : [];
  }

}
