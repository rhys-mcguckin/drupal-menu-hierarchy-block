<?php
/**
 * @file
 */

namespace Drupal\menu_hierarchy_block\Plugin\Block;

use Drupal\Core\Menu\MenuTreeParameters;

/**
 * Provides a menu hierarchy next block.
 *
 * @Block(
 *   id = "menu_hierarchy_next",
 *   admin_label = @Translation("Next"),
 *   category = @Translation("Menus"),
 *   deriver = "Drupal\menu_hierarchy_block\Plugin\Derivative\MenuHierarchyBlock",
 *   context = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"), required = FALSE)
 *   }
 * )
 */

class MenuHierarchyNextBlock extends MenuHierarchyBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function getMenuTree() {
    // Get the menu name.
    $menu_name = $this->getDerivativeId();

    // Get the active trail for the menu hierarchy block.
    $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
    array_shift($active_trail);

    // Get the entity trail, or default to the active trail.
    $trail = array_reverse(array_values($this->getEntityTrail() ?: $active_trail));

    // Get the actual depth of trail.
    $depth = count($trail) - 1;
    if ($depth < 0) {
      return [];
    }

    // Get the current active menu item.
    $current = $trail[$depth];

    // Get the parent.
    $parent = $trail[$depth - 1];

    // Create the parameters for loading.
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($active_trail)
      ->setRoot($parent)
      ->onlyEnabledLinks()
      ->setMinDepth(0)
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
    foreach (array_reverse($tree) as $item) {
      if ($item->link->getPluginId() === $current) {
        $found = TRUE;
        break;
      }
      $last = $item;
    }

    return !empty($last) && !empty($found) ? [$current => $last] : [];
  }

}
