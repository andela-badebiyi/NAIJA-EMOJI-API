<?php

namespace app;

use app\auth\Token;

/**
 * ApiController class that resolves request made to each endpoint of the api
 * Each public method in this class handles the request made to each endpoint in the api
 * and returns a closure.
 *
 * @author Adebiyi Bodunde
 */
class ApiController
{
    //handles for the app and db
    private $app;
    private $db;

    /**
     * Initializes the slim app object and the Db object.
     *
     * @param Slim   $app slim object that handles request and response made to and from the api
     * @param NotOrm $db  orm object used to interact with the database
     */
    public function __construct($app, $db)
    {
        $this->app = $app;
        $this->db = $db;
    }

    /**
     * Controller action that reigisters a new user.
     *
     * @return closure returns the username and password after creating new user
     */
    public function register()
    {
        return function () {
        $this->app->response->headers->set('Content-type', 'application/json');
        if ($this->userExist(2)) {
            $this->app->response->setStatus(400);
            echo json_encode(['message' => 'This username already exists']);
        } else {
            $this->app->response->setStatus(200);

          //fetch username and password
          $all_post_vars = $this->app->request->post();

          //create new record and store in database
          $user = $this->db->users();
            $user->insert($all_post_vars);

          //output success message
          echo json_encode([
            'message'            => 'User has been successfully registered',
            'user credentionals' => $all_post_vars,
          ]);
        }
      };
    }

    /**
     * Controller action that processes the request made to the login endpoint.
     *
     * @return closure token generated is returned in json format
     */
    public function authLogin()
    {
        return function () {
          //set headers
          $this->app->response->headers->set('Content-type', 'application/json');
          //if user exists log in, return error message if he doesn't
            if (!$this->userExist()) {
                $this->app->response->setStatus(401);
                echo json_encode(['message' => 'Invalid username and/or password']);
            } else {
                $this->app->response->setStatus(200);

                //get post variables
                $all_post_vars = $this->app->request->post();

                $username = urldecode($all_post_vars['username']);
                $password = urldecode($all_post_vars['password']);

                //get current timestamp and use it as seed to generate token
                $timestamp = $this->get_current_time();

                $textToEncrypt = $username.'-'.$password.'-'.$timestamp;
                $token = Token::generate($textToEncrypt);

                $res = $this->db->users()->where('username = ?', $username)->where('password = ?', $password);

                //insert token and time it was generated into database
                $res->update(['token_generated' => $timestamp, 'token' => $token]);

                echo json_encode(['token' => $token]);
            }
        };
    }

    /**
     * Controller action that processes the request made to the logout endpoint.
     *
     * @return closure response message indicating success or failure in json format
     */
    public function authLogout()
    {
        return function () {
          //fetch token and set http status code
          $token = $this->app->request->headers->get('user-token');
          $this->app->response->setStatus(200);

          //get token and invalidate it by making it older than a day
          $user_data = $this->db->users('token = ?', $token)->fetch();
          $expired_timestamp = intval($user_data['token_generated']) - 86400;

          //insert expired token in the users table
          $user = $this->db->users()->where('token = ?', $token);
          $user->update(['token_generated' => $expired_timestamp]);

          echo json_encode(['message' => 'Logged out!']);
        };
    }

    /**
     * Controller action that processes the request made to the fetchall emojis endpoint.
     *
     * @return closure all the emojis found in the database in json format
     */
    public function fetchAllEmojis()
    {
        return function () {
            $this->app->response->headers->set('Content-Type', 'application/json');

            //fetch all emojis
            $emojis = $this->db->emoji();

            //initialize empty result array and populate it
            $result = [];
            foreach ($emojis as $emoji) {
                $result[] = $emoji;
            }

            //set up json response
            $output = ['emojis' => $result];
            echo json_encode($output);
        };
    }

    /**
     * Controller action that processes the request made to the fetch emoji endpoint.
     *
     * @return closure outputs a single emoji in json format
     */
    public function fetchEmoji()
    {
        return function ($id) {
            //set the response header content-type and fetch emoji
            $this->app->response->headers->set('Content-Type', 'application/json');
            $emoji = $this->db->emoji('id = ?', $id)->fetch();

            //if emoji not found return error message else return emoji
            if ($emoji == null) {
                $this->app->response->setStatus(404);
                echo json_encode(['message' => 'Emoji not found']);
            } else {
                echo json_encode($emoji);
            }
        };
    }

    /**
     * Controller action that processes the request made to the create emoji endpoint.
     *
     * @return closure outputs a status message and emoji created in json format
     */
    public function createEmoji()
    {
        return function () {
          //set response header content-type and retrieve user token
          $this->app->response->headers->set('Content-Type', 'application/json');
          $token = $this->app->request->headers->get('user-token');

          //initialize the emoji db model
          $emoji = $this->db->emoji();

          //get post data and make some additions
          $all_post_vars = $this->app->request->post();
          $all_post_vars['date_created'] = $this->get_current_time('Y-m-d H:i:s');
          $all_post_vars['date_modified'] = $this->get_current_time('Y-m-d H:i:s');
          $all_post_vars['created_by'] = Token::owner($token, $this->db);

          //insert record
          $res = $emoji->insert($all_post_vars);
          $timestamp = $res['date_created'];

          //return result of this request operation and the inserted emoji
          echo json_encode([
              'message' => 'Emoji successfully added!',
              'emoji'   => $emoji->where('date_created = ?', $timestamp)->fetch(),
              ]);
        };
    }

    /**
     * Controller action that processes the request made to the update endpoint via either put or patch.
     *
     * @return closure outputs a status message
     */
    public function updateEmoji()
    {
        return function ($id) {
          //set headers and get user token
          $this->app->response->headers->set('Content-Type', 'application/json');
          $token = $this->app->request->headers->get('user-token');
          
          //fetch record
          $emoji = $this->db->emoji('id = ?', $id);

          //set date modified
          $emoji->update(['date_modified' => $this->get_current_time('Y-m-d H:i:s')]);

          //update data
          if ($emoji->update($this->app->request->post())) {
              echo json_encode(['message' => 'Smiley updated successfully']);
          } else {
              $this->app->response->setStatus(304);
              echo json_encode(['message' => 'Smiley could not be updated']);
          }
        };
    }

    /**
     * Controller action that processes the request made to the delete endpoint.
     *
     * @return closure outputs a status message
     */
    public function deleteEmoji()
    {
        return function ($id) {
            //set headers
            $this->app->response->headers->set('Content-Type', 'application/json');

            //fetch emoji and delete from database
            $this->db->emoji('id = ?', $id)->delete();
            echo json_encode(['message' => 'successfully Deleted!']);
        };
    }

    /**
     * Fetches the current time.
     *
     * @param  string [$format = null] the format in which the time should be returned in
     *
     * @return string the time
     */
    private function get_current_time($format = null)
    {
        $date = new \DateTime();
        $result = ($format == null ? $date->getTimestamp() : gmdate($format, $date->getTimestamp()));

        return $result;
    }

     /**
      * Checks if user credentials exists in database.
      *
      * @return bool returns true if user credentials already exist and false if not
      */
     private function userExist($option = 1)
     {
         //get post variables
       $all_post_vars = $this->app->request->post();

         $username = urldecode($all_post_vars['username']);
         $password = urldecode($all_post_vars['password']);

         if ($option == 1) {
             $res = $this->db->users()->where('username = ?', $username)->where('password = ?', $password);
         } else {
             $res = $this->db->users()->where('username = ?', $username);
         }

       //check if username and password exists in table
       if (count($res) == 0) {
           return false;
       } else {
           return true;
       }
     }
}
