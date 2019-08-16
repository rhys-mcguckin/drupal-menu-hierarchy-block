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
 *   context = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"), required = FALSE)
 *   }
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

    // Get the entity trail, or default to the active trail.
    $trail = array_reverse(array_values($this->getEntityTrail() ?: $active_trail));

    // Get the actual depth of trail.
    $depth = count($trail) - 1;
    if ($depth < 2) {
      return [];
    }

    // Get the parent.
    $parent = $trail[$depth - 1];

    // Get the parents parent.
    $parent_parent = $trail[$depth - 2];

    // Create the parameters for loading.
    $parameters = (new MenuTreeParameters())
      ->setActiveTrail($active_trail)
      ->setRoot($parent_parent)
      ->onlyEnabledLinks()
      ->setMinDepth(0)
      ->setMaxDepth(1);

    // Load the tree
    $tree = $this->menuTree->load($menu_name, $parameters);

    // Restrict the tree to the parent id
    return !empty($tree[$parent]) ? [$parent => $tree[$parent]] : [];
  }

}
