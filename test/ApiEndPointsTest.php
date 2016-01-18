<?php

namespace app\tests;

use GuzzleHttp\Client;


class ApiEndPointsTest extends \PHPUnit_Framework_TestCase
{
    public $client;
    public $dbHandler;

    public function setUp()
    {
        $this->client = new Client(['base_url' => 'http://bd-naijaemoji.herokuapp.com/']);
        //$this->client = new Client(['base_url' => 'http://localhost:3000/']);

    }

    public function testValidConnection()
    {
        $this->assertNotNull($this->client);
        $this->assertInstanceOf('GuzzleHttp\Client', $this->client);
    }

    public function testRegister()
    {
      //test with a username that already exists
      $response = $this->client->post('register', [
          'body' => [
                  'username' => 'admin',
                  'password' => 'invalid_password',
              ],
          'exceptions' => false,
      ]);

      //confirm the error code and the error message
      $this->assertEquals(400, $response->getStatusCode());
      $this->assertEquals('This username already exists', $response->json()['message']);

      //test with a uique username

      //test with a username that already exists
      $response = $this->client->post('register', [
          'body' => [
                  'username' => $this->randomText(),
                  'password' => $this->randomText(),
              ],
          'exceptions' => false,
      ]);

      //confirm the error code and the error message
      $this->assertEquals(200, $response->getStatusCode());
      $this->assertEquals('User has been successfully registered', $response->json()['message']);

    }

    public function testAuthLogin()
    {
        //test login with invalid username and password
        $response = $this->client->post('auth/login', [
            'body' => [
                    'username' => 'invalid_username',
                    'password' => 'invalid_password',
                ],
            'exceptions' => false,
        ]);

        //confirm the error code and the error message
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid username and/or password', $response->json()['message']);

        //test login endpoint with valid username and password
        $response = $this->client->post('auth/login', [
            'body' => [
                    'username' => 'admin',
                    'password' => 'pass1234',
                ],
            'exceptions' => false,
        ]);

        //confirm the error code and that token is returned
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->json()['token']);

        //pass token to the authlogout test
        return $response->json()['token'];
    }

    /**
     * @depends testAuthLogin
     */
    public function testAuthLogout($token)
    {
        //log a user out with an invalid token
        $response = $this->client->get('auth/logout', [
            'headers' => [
                'user-token' => 'invalid token',
            ],
            'exceptions' => false,
        ]);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid Token', $response->json()['message']);

        //log a user out with a valid token
        $response = $this->client->get('auth/logout', [
            'headers' => [
                'user-token' => $token,
            ],
            'exceptions' => false,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Logged out!', $response->json()['message']);
    }

    public function testFetchAllEmojis()
    {
        //connect to app and send a request to fetch all emojis
        $response = $this->client->get('emojis');

        $this->assertInternalType('array', $response->json()['emojis']);
    }

    public function testFetchEmojiById()
    {
        //fetch an emoji that isnt in the database
        $response = $this->client->get('emojis/30000', [
          'exceptions' => false
        ]);
        $this->assertEquals(404, $response->getStatusCode());

        //fetch an emoji that is in the database
        $response = $this->client->get('emojis/1');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->json());
        $this->assertInternalType('array', $response->json());
    }

    public function testCreateEmoji()
    {
        //log user in and get a valid token
        $token = $this->log_user_in();

        //create emoji with an incorrect token
        $response = $this->client->post('emojis', [
            'headers' => ['user-token' => 'invalid_token'],
            'body'    => [
                'name'     => 'love_smiley',
                'smiley'   => 'link_to_image',
                'category' => 'people',
                'keywords' => 'love, emotion, mushy, sappy, awwnnn',
            ],
            'exceptions' => false,
        ]);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Invalid Token', $response->json()['message']);

        //create emoji with a correct token
        $response = $this->client->post('emojis', [
            'headers' => ['user-token' => $token],
            'body'    => [
                'name'     => 'love_smiley',
                'smiley'   => 'link_to_image',
                'category' => 'people',
                'keywords' => 'love, emotion, mushy, sappy, awwnnn',
            ],
            'exceptions' => false,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Emoji successfully added!', $response->json()['message']);

        //log user out
        $this->log_user_out($token);

        return $response->json()['emoji'];
    }

    /**
     * @depends testCreateEmoji
     */
    public function testUpdateEmoji($emoji)
    {
        //log user in and get a valid token
        $token = $this->log_user_in();

        $new_name = 'new_name_for_smiley';

        //try to update with invalid token
        $response = $this->client->put('emojis/'.$emoji['id'], [
            'headers'    => ['user-token' => 'invalid_token'],
            'body'       => ['name' => $new_name],
            'exceptions' => false,
        ]);

        $this->assertEquals(401, $response->getStatusCode());

        //update the name of the emoji via put request
        $response = $this->client->put('emojis/'.$emoji['id'], [
            'headers'    => ['user-token' => $token],
            'body'       => ['name' => $new_name],
            'exceptions' => false,
        ]);

        //fetch emoji and check if it updated correctly
        $fetched_emoji = $this->client->get('emojis/'.$emoji['id'])->json();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($new_name, $fetched_emoji['name']);

        //update the name of the emoji via a patch request
        $response = $this->client->patch('emojis/'.$emoji['id'], [
            'headers'    => ['user-token' => $token],
            'body'       => ['name' => $new_name],
            'exceptions' => false,
        ]);

        //fetch emoji and check if it updated correctly
        $fetched_emoji = $this->client->get('emojis/'.$emoji['id'])->json();
        $this->assertEquals($response->getStatusCode(), 200);
        $this->assertEquals($new_name, $fetched_emoji['name']);

        //log user out
        $this->log_user_out($token);

        return $fetched_emoji;
    }

    /**
     * @depends testUpdateEmoji
     */
    public function testDeleteEmoji($emoji)
    {
        $token = $this->log_user_in();

        //try to delete with invalid token
        $response = $this->client->delete('emojis/'.$emoji['id'], [
            'headers'    => ['user-token' => 'invalid_token'],
            'exceptions' => false,
        ]);

        $this->assertEquals(401, $response->getStatusCode());

        //delete user with a valid token
        $response = $this->client->delete('emojis/'.$emoji['id'], [
            'headers'    => ['user-token' => $token],
            'exceptions' => false,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('successfully Deleted!', $response->json()['message']);
    }

    private function log_user_in()
    {
        $response = $this->client->post('auth/login', [
            'body' => [
                    'username' => 'admin',
                    'password' => 'pass1234',
                ],
            'exceptions' => false,
        ]);

        return $response->json()['token'];
    }

    private function log_user_out($token)
    {
        //log a user out with a valid token
        $response = $this->client->get('auth/logout', [
            'headers' => [
                'user-token' => $token,
            ],
            'exceptions' => false,
        ]);
    }

    private function randomText($length = 10)
    {
      $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
      }
      return $randomString;
    }
}
