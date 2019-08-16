<?php
/**
 * @file
 */

namespace Drupal\menu_hierarchy_block\Plugin\Block;


use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide the base block definition for menu hierarchy blocks.
 */
abstract class MenuHierarchyBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new MenuHierarchyBlockBase.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MenuLinkTreeInterface $menu_tree,
    MenuLinkManagerInterface $menu_link_manager,
    MenuActiveTrailInterface $menu_active_trail,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->menuLinkManager = $menu_link_manager;
    $this->menuActiveTrail = $menu_active_trail;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.active_trail'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $form['show_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show empty'),
      '#description' => $this->t('Allows the block to still show even with no links.'),
      '#default_value' => $config['show_empty'],
    ];

    // View modes selection.
    $form['overrides'] = [
      '#type' => 'details',
      '#title' => $this->t('Overrides'),
      '#description' => $this->t('Allow overrides of the link.'),
      '#open' => !empty($config['title']),
      '#process' => [[get_class(), 'processParents']],
    ];

    $form['overrides']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link Title'),
      '#description' => $this->t('Overrides the link title when displaying the menu link.'),
      '#default_value' => $config['title'],
    ];

    // View modes selection.
    $form['view_modes'] = [
      '#type' => 'details',
      '#title' => $this->t('View modes'),
      '#description' => $this->t('View modes to be used when submenu items are displayed as content entities'),
      '#open' => !empty($config['view_modes']),
    ];

    // A select list of view modes for each entity type.
    foreach ($this->getEntityTypes() as $entity_type) {
      $view_modes = $this->entityDisplayRepository->getViewModeOptions($entity_type);
      $form['view_modes'][$entity_type] = [
        '#title' => $this->entityTypeManager->getDefinition($entity_type)->getLabel(),
        '#type' => 'select',
        '#options' => ['' => $this->t('None')] + $view_modes,
        '#default_value' => !empty($config['view_modes'][$entity_type]) ? $config['view_modes'][$entity_type] : '',
      ];
    }

    return $form;
  }

  /**
   * Form API callback: Processes the field elements with parents.
   *
   * Adjusts the #parents of forms to save its children at the top level.
   */
  public static function processParents(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['show_empty'] = $form_state->getValue('show_empty');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['view_modes'] = array_filter($form_state->getValue('view_modes'));
  }

  /**
   * Get the menu tree that is to be displayed for this block.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The menu items to display for the hierarchy block.
   */
  abstract protected function getMenuTree();

  /**
   * Process the tree to remove any inaccessible items.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The transformed menu tree.
   */
  protected function performTransform(array $tree) {
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    return $this->menuTree->transform($tree, $manipulators);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tree = $this->getMenuTree();
    $tree = $this->performTransform($tree);

    // Generate the built menu.
    $build = $this->menuTree->build($tree);

    if (!empty($build['#theme'])) {
      // Add the configuration for use in menu_hierarchy_block_theme_suggestions_menu().
      $build['#menu_hierarchy_block_configuration'] = $this->configuration + ['plugin_id' => str_replace(':', '__', $this->getPluginId())];
      // Remove the menu name-based suggestion so we can control its precedence
      // better in menu_hierarchy_block_theme_suggestions_menu().
      $build['#theme'] = 'menu';
    }

    $build['#contextual_links']['menu'] = [
      'route_parameters' => ['menu' => $this->getDerivativeId()],
    ];

    // Convert any of the links using the configuration settings.
    if (!empty($build['#items'])) {
      $build['#items'] = $this->convertLinks($build['#items']);
      return $build;
    }

    // Configured to show empty block.
    return !empty($this->configuration['show_empty']) ? $build : [];
  }

  /**
   * Converts the link using configuration.
   *
   * @param array $item
   *   The link item to be rendered as part of the menu.
   */
  protected function convertLink(array &$item) {
    $config = $this->configuration;

    // Override the link title.
    if (!empty($config['title'])) {
      $item['title'] = $config['title'];
    }

    // Match the uri against the entity canonical route, and grab the entity.
    if (preg_match('/^entity\.(.*?)\.canonical$/', $item['original_link']->getRouteName(), $match)) {
      $entity_type = $match[1];
      $entity_id = $item['original_link']->getRouteParameters()[$entity_type];

      // Should confirm whether the entity display is supported.
      if (!empty($config['view_modes'][$entity_type])) {
        $item['entity'] = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        $item['view_mode'] = $config['view_modes'][$entity_type];
        $build = $this->entityTypeManager->getViewBuilder($entity_type)->view($item['entity'], $item['view_mode']);
        $item['rendered_entity'] = render($build);
      }
    }
  }

  /**
   * Performs conversion of links within based on the configuration settings.
   *
   * @param array[] $tree
   *   The built menu links from the tree.
   *
   * @return array[]
   *   The built menu link tree.
   */
  public function convertLinks(array $tree) {
    foreach ($tree as $key => &$item) {
      // Reprocess any subtree information.
      if (!empty($item['below'])) {
        $item['below'] = $this->convertLinks($item['below']);
      }

      // Allow the conversion of link information.
      $this->convertLink($item);
    }
    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_empty' => FALSE,
      'title' => '',
      'view_modes' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Even when the menu block renders to the empty string for a user, we want
    // the cache tag for this menu to be set: whenever the menu is changed, this
    // menu block must also be re-rendered for that user, because maybe a menu
    // link that is accessible for that user has been added.
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.' . $this->getDerivativeId();
    // TODO: This probably needs some cache tags relative to entity types and display modes.
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // TODO: This may need information regarding entity ids/display modes, etc.
    // ::build() uses MenuLinkTreeInterface::getCurrentRouteMenuTreeParameters()
    // to generate menu tree parameters, and those take the active menu trail
    // into account. Therefore, we must vary the rendered menu by the active
    // trail of the rendered menu.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    $menu_name = $this->getDerivativeId();
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . $menu_name]);
  }

  /**
   * Returns a list of valid entity types.
   *
   * @return array
   *   Valid entity type names.
   */
  protected function getEntityTypes() {
    $entity_types = ['node'];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $definition) {
      if ($entity_type != 'node' && $this->isValidEntity($definition)) {
        $entity_types[] = $entity_type;
      }
    }

    return $entity_types;
  }

  /**
   * Filters entities based on their view builder handlers.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $definition
   *   The entity type definition.
   *
   * @return bool
   *   TRUE if the entity has the correct view builder handler, FALSE if the
   *   entity doesn't have the correct view builder handler.
   */
  protected function isValidEntity(EntityTypeInterface $definition) {
    return $definition->get('field_ui_base_route') && $definition->hasViewBuilderClass();
  }

  /**
   * Get the menu trail for the context entity.
   *
   * @return null|array
   *   The menu trail ids based on the entity context.
   */
  protected function getEntityTrail() {
    // Get the menu name.
    $menu_name = $this->getDerivativeId();

    // Get the entity context, and check if it exists. As 'node' is currently
    // a special case, checking if the entity is fieldable also cuts out the
    // unnecessary items.
    $entity = $this->getContextValue('entity');
    if (!$entity || !($entity instanceof FieldableEntityInterface)) {
      return NULL;
    }

    // Check if the entity is a node.
    if ($entity->getEntityTypeId() === 'node' && function_exists('menu_ui_get_menu_link_defaults')) {
      $links = menu_ui_get_menu_link_defaults($entity);
      if (($links['menu_name'] === $menu_name) && $links['id']) {
        $trail = ['' => ''];
        if ($parents = $this->menuLinkManager->getParentIds($links['id'])) {
          $trail = $parents + $trail;
        }
        return $trail;
      }
    }

    // Check for entity support via the menu_link module.
    $field_definitions = $entity->getFieldDefinitions();
    foreach ($field_definitions as $field_name => $definition) {
      if ($definition->getType() === 'menu_link' && $entity->get($field_name)->offsetExists(0)) {
        /** @var \Drupal\menu_link\Plugin\Field\FieldType\MenuLinkItem $item */
        $item = $entity->get($field_name)->get(0);
        $id = $item->getMenuPluginId();

        // Get the link definition from menu_link item.
        if ($this->menuLinkManager->hasDefinition($id)) {
          $link = $this->menuLinkManager->getDefinition($id);

          // Only process this field for a trail if the link matches the correct menu.
          if ($link['menu_name'] === $menu_name) {
            $trail = ['' => ''];
            if ($parents = $this->menuLinkManager->getParentIds($id)) {
              $trail = $parents + $trail;
            }
            return $trail;
          }
        }
      }
    }

    // We have not located a valid method for provide an entity menu link.
    return NULL;
  }


}
