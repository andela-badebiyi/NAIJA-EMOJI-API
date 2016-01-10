<?php
namespace app;

/**
 * ApiController class that resolves request made to each endpoint of the api
 * Each public method in this class handles the request made to each endpoint in the api
 * and returns a closure 
 * 
 * @author Adebiyi Bodunde
 */
class ApiController {
	//handles for the app and db
	private $app;
	private $db;
	
	//parameters required for the token generation
	private $secret_hash;
	private $encryptionMethod;
	private $iv;

	/**
	 * Initializes the slim app object and the Db object
	 * @param Slim   $app       slim object that handles request and response made to and from the api
	 * @param NotOrm $db        orm object used to interact with the database
	 * @param string $secHash   secret hash key used in generating token with php open_ssl_encryption
	 * @param string $encMethod encryption method used by the open_ssl_encryption method
	 * @param string $iv        initializing vector used during encryptio
	 */
	public function __construct($app, $db, $secHash, $encMethod, $iv)
	{
		$this->app = $app;
		$this->db = $db;
		$this->secret_hash = $secHash;
		$this->encryptionMethod = $encMethod;
		$this->iv = $iv;
	}

	/**
	 * Controller action that processes the request made to the login endpoint
	 * @return closure token generated is returned in json format
	 */
	public function authLogin(){
		return function()
		{
			$this->app->response->headers->set('Content-Type', 'application/json');
			//get post variables
			$all_post_vars = $this->app->request->post();

			$username = $all_post_vars['username'];
			$password = $all_post_vars['password'];

			//check if username and password exists in table
			$res = $this->db->users()->where('username = ?', $username)->where('password = ?', $password);
			
			if(count($res) == 0){
				$this->app->response->setStatus(401);
				echo json_encode(['message' => 'Invalid username and/or password']);
			}
			else{
				$this->app->response->setStatus(200);
				
				//get current timestamp and use it as seed to generate token
				$timestamp = $this->get_current_time();
				
				$textToEncrypt = $username."-".$password."-".$timestamp;
				$token = $this->generate_token(
					$textToEncrypt, $this->encryptionMethod, $this->secret_hash, $this->iv
				);

				//insert token, token timestamp and initializing vector into database
				$res->update(['token' => $token, 'token_generated' => $timestamp, 'iv' => $this->iv]);

				echo json_encode(['token' => $token]);
			}
		};
	}

	/**
	 * Controller action that processes the request made to the logout endpoint
	 * @return closure response message indicating success or failure in json format
	 */
	public function authLogout(){
		return function()
		{
			//set the response header content type and retrieve the user token
			$this->app->response->headers->set('Content-Type', 'application/json');
			$token = $this->app->request->headers->get('user-token');

			//check if token is valid and then proceed to process request
			if ($this->token_is_valid($token)) {
				$this->app->response->setStatus(200);

				//get token and invalidate it by making it older than a day
				$user_data = $this->db->users('token = ?', $token)->fetch();
				$expired_timestamp = intval($user_data['token_generated']) - 86400;

				//update user with expired timestamp
				$user = $this->db->users()->where('token = ?', $token);
				$user->update(['token_generated' => $expired_timestamp]);

				echo json_encode(['message' => 'Logged out!']);
			} else {
				$this->app->response->setStatus(401);
				echo json_encode(['message' => 'Invalid Token']);
			}
		};
	}

	/**
	 * Controller action that processes the request made to the fetchall emojis endpoint
	 * @return closure all the emojis found in the database in json format
	 */
	public function fetchAllEmojis()
	{
	 	return function()
		{
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
	 * Controller action that processes the request made to the fetch emoji endpoint
	 * @return closure outputs a single emoji in json format
	 */
	public function fetchEmoji()
	{
		return function($id)
		{
			//set the response header content-type and fetch emoji
			$this->app->response->headers->set('Content-Type', 'application/json');
			$emoji = $this->db->emoji('id = ?', $id)->fetch();

			//if emoji not found return error message else return emoji
			if ($emoji == null) {
				$this->app->response->setStatus(204);
				echo json_encode(['message' => 'Emoji not found']);
			} else {
				echo json_encode($emoji);
			}
		};
	}

	/**
	 * Controller action that processes the request made to the create emoji endpoint
	 * @return json 
	 */
	public function createEmoji()
	{
	 	return function()
		{	
			//set response header content-type and retrieve user token
			$this->app->response->headers->set('Content-Type', 'application/json');
			$token = $this->app->request->headers->get('user-token');

			//initialize the emoji db model
			$emoji = $this->db->emoji();

			//check if token is valid then proceed or return an error message
			if ($this->token_is_valid($token)) {
				//get post data and make some additions
				$all_post_vars = $this->app->request->post();
				$all_post_vars['date_created'] = $this->get_current_time('Y-m-d H:i:s');
				$all_post_vars['date_modified'] = $this->get_current_time('Y-m-d H:i:s');
				$all_post_vars['created_by'] = $this->get_username($token);
				
				//insert record 
				$emoji->insert($all_post_vars);

				//return result of this request operation and the inserted emoji object
				echo json_encode([
					'message' => 'Emoji successfully added!', 
					'emoji' => $emoji->where('id = ?', $emoji->insert_id())->fetch()
					]);
			} else {
					$this->app->response->setStatus(401);
					echo json_encode(['message' => 'Invalid Token!']);
			}
		};
	}

	//update an emoji via put/patch request
	public function updateEmoji()
	{ 
		return function($id)
		{
			$this->app->response->headers->set('Content-Type', 'application/json');
			$token = $this->app->request->headers->get('user-token');

			if ($this->token_is_valid($token) && $this->user_owns_emoji($token, $id) && $this->emoji_exists($id)) {
				$all_post_vars = $this->app->request->post();
				$all_post_vars['date_modified'] = $this->get_current_time('Y-m-d H:i:s');
				
				$emoji = $this->db->emoji('id = ?', $id);

				if ($emoji->update($all_post_vars)) {
					echo json_encode(['message' => 'Smiley updated successfully']);
				} else {
					$this->app->response->setStatus(304);
					echo json_encode(['message' => 'Smiley could not be updated']);
				}
			} else {
				$this->app->response->setStatus(401);
				echo json_encode(['message' => 'At least one of the authentication requirements failed']);
			}
		};
	}

	//delete an emoji via the delete request
	public function deleteEmoji()
	{ 
		return function($id)
		{
			$this->app->response->headers->set('Content-Type', 'application/json');
			$token = $this->app->request->headers->get('user-token');
			if ($this->token_is_valid($token) && $this->user_owns_emoji($token, $id) && $this->emoji_exists($id)) {
				$this->db->emoji('id = ?', $id)->delete();
				echo json_encode(['message' => 'successfully Deleted!']);
			} else {
				$this->app->response->setStatus(401);
				echo json_encode(['message' => 'Oops something went wrong!']);
			}
		};
	}

	//private functions
	private function generate_token($txtToEnc, $encMethod, $secHash, $iv)
	{
		return openssl_encrypt($txtToEnc, $encMethod, $secHash, 0, $iv);
	}

	private function token_is_valid($token) 
	{	
		//check that token exists in database then check if it has expired
		$result = $this->db->users->where('token = ?', $token);
		if (count($result) == 0) {
			return false;
		} else {
			//get current time and the time token was generated
			$current_time = $this->get_current_time();
			$user = $this->db->users('token = ?', $token)->fetch();
			$time_token_generated = $user['token_generated'];

			//if token is more than a day old return false if not return true
			return (intval($current_time) - 86400) < $time_token_generated ? true : false;
		}
	}

	private function get_current_time($format = null) 
	{
		$date = new \DateTime();
		$result = ( $format == null ? $date->getTimestamp() : gmdate($format, $date->getTimestamp()) );
		return $result;
	}

	private function get_username($token)
	{
		$user = $this->db->users('token = ?', $token)->fetch();
		return $user['username'];
	}

	private function user_owns_emoji($token, $emoji_id)
	{
		//fetch username of the current logged in user
		$user = $this->get_username($token);

		//fetch emoji data
		$emoji = $this->db->emoji('id = ?', $emoji_id)->fetch();

		//check that the username of the logged user is equal to the user that created the emoji
		return $user == $emoji['created_by'] ? true : false;

	}

	private function emoji_exists($emoji_id)
	{
		return $this->db->emoji("id = ?", $emoji_id)->fetch() == null ? false : true;
	}

}

?>