<?php

namespace Drupal\custom_api;

class Helper {

  public static function validateToken($token, $uid) {
    $currentDate = date('Y-m-d');
    $expectedToken = md5($uid . $currentDate . 'Interactive');
    return $token === $expectedToken;
  }

  public static function getToken($uid)
  {
    $currentDate = date('Y-m-d');
    $expectedToken = md5($uid . $currentDate . 'Interactive');
    return $expectedToken;
  }
}
