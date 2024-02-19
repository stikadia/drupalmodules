<?php

namespace Drupal\custom_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\custom_api\Helper;

class GetTokenController extends ControllerBase {

  protected $currentUser;
  protected $sessionManager;

  public function __construct(AccountInterface $current_user, SessionManagerInterface $session_manager) {
    $this->currentUser = $current_user;
    $this->sessionManager = $session_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('session_manager')
    );
  }

  public function login(Request $request) {

    // Check if the request contains username and password fields.
    $username = $request->request->get('username');
    $password = $request->request->get('password');

    // Attempt user authentication using Drupal's user authentication function.
    $uid = \Drupal::service('user.auth')
      ->authenticate($username, $password);

    if ($uid) {
      // User authentication successful.
      $user = \Drupal\user\Entity\User::load($uid);
      $user_id = $user->id();
      // Log the user in (optional).
      user_login_finalize($user);

      // Return the user ID in a JSON response.
      $token = Helper::getToken($user_id);
      
      return new JsonResponse(['uid' => $user_id,'token' => $token]);
    }

    // Authentication failed.
    return new JsonResponse(['error' => 'Authentication failed'], 401);
  }

  public function getToken(Request $request)
  {
    $username = $request->query->get('uid');
  
    $currentDate = date('Y-m-d');
    $token = md5($username.$currentDate . 'Interactive');
    return new JsonResponse(['token' => $token]);
  }

}
