<?php

declare(strict_types=1);

namespace Drupal\aqto_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an aqto admin dashboard example block.
 *
 * @Block(
 *   id = "aqto_blocks_aqto_menu_block",
 *   admin_label = @Translation("Aqto Menu"),
 *   category = @Translation("Aqto"),
 * )
 */
final class AqtoMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'menu_to_use' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    // Lets do a 'menu_to_use' that is a select list of menus.
    $form['menu_to_use'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu to use'),
      '#options' => ['' => $this->t('Select a menu')] + array_map(
        fn ($menu) => $menu->label(),
        $this->entityTypeManager->getStorage('menu')->loadMultiple()
      ),
      '#default_value' => $this->configuration['menu_to_use'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['menu_to_use'] = $form_state->getValue('menu_to_use');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Load up the chosen menu, and lets massage it into an array of title -> url.
    $build = [];
    if (!$this->configuration['menu_to_use']) {
      return [];
    }
    $menu = $this->entityTypeManager->getStorage('menu')->load($this->configuration['menu_to_use']);
    if (!$menu) {
      return [];
    }
    $menu_links = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu->id()]);
    $menu_links = array_map(
      fn ($menu_link) => [
        'title' => $menu_link->label(),
        'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($menu_link->link->uri),
      ],
      $menu_links
    );

    $build['content'] = [
      '#theme' => 'aqto_menu',
      '#menu_to_use' => $menu_links,
    ];
    return $build;
  }

}
