<?php

namespace Drupal\aqto_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Aqto Menu Block with token support.
 *
 * @Block(
 *   id = "aqto_blocks_aqto_menu_block",
 *   admin_label = @Translation("Aqto Menu"),
 *   category = @Translation("Aqto"),
 * )
 */
final class AqtoMenuBlock extends BlockBase implements ContainerFactoryPluginInterface
{

  private readonly Token $token;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    Token $token
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self
  {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('token')
    );
  }

  public function defaultConfiguration(): array
  {
    return [
      'menu_to_use' => NULL,
      'menu_title' => '',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array
  {
    $form['menu_to_use'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu to use'),
      '#options' => ['' => $this->t('Select a menu')] + array_map(
        fn ($menu) => $menu->label(),
        $this->entityTypeManager->getStorage('menu')->loadMultiple()
      ),
      '#default_value' => $this->configuration['menu_to_use'],
    ];

    $form['menu_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Menu Title'),
      '#description' => $this->t('You can use tokens like [site:name] or [user:mail].'),
      '#default_value' => $this->configuration['menu_title'],
    ];

    // Attach the token tree UI for user-friendly token selection.
    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['site', 'user'],
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void
  {
    $this->configuration['menu_to_use'] = $form_state->getValue('menu_to_use');
    $this->configuration['menu_title'] = $form_state->getValue('menu_title');
  }

  public function build(): array
  {
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
      '#menu_title' => $this->token->replace($this->configuration['menu_title'], ['user' => $this->currentUser]),
    ];
    return $build;
  }
}
