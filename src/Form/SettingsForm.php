<?php

namespace Drupal\instagram_catalogue\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\instagram_catalogue\Controller\ApiController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a text input and checkbox form for module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Shortcuts for entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Handles dir creation.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Handles api queries.
   *
   * @var \Drupal\instagram_catalogue\Controller\ApiController
   */
  protected $apiController;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Service 'config.factory'.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Service: 'entity_type.manager'.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   Service: 'file_system'.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    parent::__construct($config_factory);
    $this->entityManager = $entity_type_manager;
    $this->fileSystem = $file_system;

    $this->apiController = new ApiController($this->entityManager, $this->fileSystem, $this->logger('action'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'instagram_catalogue_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['instagram_catalogue.settings'];
  }

  /**
   * Build elements for access token and upload personal data file.
   */
  public function buildForm(array $form, FormStateInterface $form_state) : array {
    $form = parent::buildForm($form, $form_state);

    $access_token = self::config($this->getEditableConfigNames()[0])->get('access_token') ?? '';

    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('For instructions on getting an access token, follow this @link.',
        ['@link' => Link::fromTextAndUrl('link', Url::fromUri('https://www.instagram.com/developer/authentication/'))->toString()]),
      '#size' => 48,
      '#default_value' => $access_token,
    ];

    $form['delete_btn'] = [
      '#markup' => 'Delete your access token first.',
      '#type' => 'submit',
      '#value' => 'Delete Saved Posts',
      '#name' => 'delete_btn',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    switch ($form_state->getTriggeringElement()['#name']) {
      case 'delete_btn':
        $this->apiController->deleteAllPostData();
        break;

      default:
        $access_token = $form_state->getValue('access_token');
        if ($access_token) {
          $this->apiController->queryForNewPosts();
          $config = self::config($this->getEditableConfigNames()[0]);
          $config->set('access_token', $access_token)->save();
        }
        break;
    }
    parent::submitForm($form, $form_state);
  }

}
