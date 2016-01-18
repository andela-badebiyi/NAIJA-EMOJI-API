<?php
namespace app\middleware;

use app\auth\Token;

class Validate extends \Slim\Middleware
{
  private $db;

  public function __construct($db)
  {
      $this->db = $db;
  }

  public function call()
  {
    $app = $this->app;
    $url = $app->request()->getResourceUri();

    //do not perform any authentication for this routes
    if ($this->noAuth($url, $app)) {
        $this->next->call();
    } else {
      $token = $app->request->headers->get('user-token');
      $app->response->headers->set('Content-Type', 'application/json');

      //authentication for logout and create endpoint
      if ( $this->isLogoutOrCreate($url, $app) ){
        //set the response header content type and retrieve the user token
        if (!Token::isValid($token, $this->db)) {
          $app->response->setStatus(401);
          echo json_encode(['message' => 'Invalid Token']);
        } else {
          $this->next->call();
        }
      }

      //authentication for update and delete endpoint
      if( $this->isUpdateOrDelete($app) ){
        $id = $this->getId($app);
        if(
        Token::isValid($token, $this->db)
        && $this->user_owns_emoji($token, $id)
        && $this->emoji_exists($id) ){
          $this->next->call();
        } else {
          $this->app->response->setStatus(401);
          echo json_encode(['message' => 'At least one of the authentication requirements failed']);
        }

      }
    }
  }

  private function noAuth($url, $app)
  {
    if ($url == '/auth/login' || $url == '/register') {
      return true;
    } else if ($url == '/emojis' && strtolower($app->request->getMethod()) == 'get' ) {
      return true;
    } else if (strpos($url,'/emojis/') !== false&& strtolower($app->request->getMethod()) == 'get'){
      return true;
    } else {
      return false;
    }
  }

  private function isLogoutOrCreate($url, $app)
  {
    if ($url == '/auth/logout') {
      return true;
    } elseif($url == '/emojis' && strtolower($app->request->getMethod()) == 'post' ) {
      return true;
    } else {
      return false;
    }
  }

  private function isUpdateOrDelete($app)
  {
    if (
    strtolower($app->request->getMethod()) == 'put' ||
    strtolower($app->request->getMethod()) == 'patch' ||
    strtolower($app->request->getMethod()) == 'delete'){
      return true;
    } else {
      return false;
    }
  }

  /**
   * Checks if the user is the owner of an emoji.
   *
   * @param string $token    User token
   * @param int    $emoji_id the id of the emoji whose ownership we want to confirm
   *
   * @return bool returns true if user does own the emoji and false if he doesn't
   */
  private function user_owns_emoji($token, $emoji_id)
  {
      //fetch username of the current logged in user
      $user = Token::owner($token, $this->db);

      //fetch emoji data
      $emoji = $this->db->emoji('id = ?', $emoji_id)->fetch();

      //check that the username of the logged user is equal to the user that created the emoji
      return $user == $emoji['created_by'] ? true : false;
  }

  /**
   * Check if an emmoji exists.
   *
   * @param int $emoji_id id of the emoji whose existence we want to confirm
   *
   * @return bool returns true if emoji exists and false if it doesn't
   */
  private function emoji_exists($emoji_id)
  {
      return $this->db->emoji('id = ?', $emoji_id)->fetch() == null ? false : true;
  }

  private function getId($app)
  {
    $result = explode("/", $app->request->getResourceUri());
    return $result[count($result) - 1];
  }

}
?>
