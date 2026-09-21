<?php

namespace Drupal\liberal_custom_content\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;

/**
 * Provides a resource to get view modes by entity and bundle.
 */
#[RestResource(
  id: "logged_actions_get_resource",
  label: new TranslatableMarkup("Logged Actions for Admin Audit Trail"),
  uri_paths: [
    "canonical" => "/api/{nid}/custom-logged-actions",
  ]
)]
class LoggedActionsGetResource extends ResourceBase {
  /**
   * A current user instance which is logged in the session.
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $loggedUser;

  /**
   * Which role is allowed to view the results
   * @var int
   */
  protected $allowedUserRole = 'liberal_content_editor';

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $config
   *   A configuration array which contains the information about the plugin instance.
   * @param string $module_id
   *   The module_id for the plugin instance.
   * @param mixed $module_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A currently logged user instance.
   */
  public function __construct(
    array $config,
    $module_id,
    $module_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($config, $module_id, $module_definition, $serializer_formats, $logger);

    $this->loggedUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container, array $config, $module_id, $module_definition) {
    return new static(
      $config,
      $module_id,
      $module_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('sample_rest_resource'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET request.
   * Returns a list of logged actions.
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * Throws exception expected.
   */
  public function get($nid = NULL) {
    // either use is admin or has the content editor role
    $loggedUserRoles = $this->loggedUser->getRoles();
    if (!$this->loggedUser->hasPermission('administer site configuration')
        || (!in_array($this->allowedUserRole, $loggedUserRoles) && !$this->loggedUser->hasPermission('administer site configuration'))) {
      throw new AccessDeniedHttpException();
    }

    $database = \Drupal::database();
    $query = $database->select('admin_audit_trail', 'at');
    $query->leftjoin('users_field_data', 'ufd', 'at.uid = ufd.uid');
    $query->condition('ref_numeric', $nid)
      ->fields('at', ['lid', 'operation', 'created'])
      ->fields('ufd', ['name']);

    $logged_actions_for_entity = $query->execute()->fetchAllAssoc('lid', \Drupal\Core\Database\Statement\FetchAs::Associative);

    // fix the data more before exposing
    foreach ($logged_actions_for_entity as $key => $logged_action) {
      $logged_actions_for_entity[$key]['created'] = \Drupal::service('date.formatter')->format($logged_action['created'], 'custom', 'd-m-Y H:i:s', "Europe/Athens");
    }

    $response = new ResourceResponse($logged_actions_for_entity);
    $response->addCacheableDependency($logged_actions_for_entity);
    return $response;
  }

}
