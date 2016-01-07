<?php

require "vendor/autoload.php";
require "notorm/NotORM.php";

//initialize slim
$app = new Slim\Slim();

//initialize db handlers;
$pdo = new PDO('sqlite:test.db');
$db = new NotORM($pdo);

//secret hash key used for 2 way encryption
$secret_hash = "123ophiophagus!@#";
$encryptionMethod = "AES-256-CBC"; 

//initializing vector;
$iv = openssl_random_pseudo_bytes(16);


//login authentication
$app->post('/auth/login', function() use ($app, $db, $secret_hash, $encryptionMethod, $iv){
	//get post variables
	$all_post_vars = $app->request->post();

	$username = $all_post_vars['username'];
	$password = $all_post_vars['password'];

	//check if username and password exists in table
	$res = $db->users()->where('username = ?', $username)->where('password = ?', $password);
	
	if(count($res) == 0){
		$app->response->setStatus(401);
		echo json_encode(['message' => 'Invalid username and/or password']);
	}
	else{
		$app->response->setStatus(200);
		
		//get current timestamp and use it as seed to generate token
		$timestamp = get_current_time();
		
		$textToEncrypt = $username."-".$password."-".$timestamp;
		$token = generate_token($textToEncrypt, $encryptionMethod, $secret_hash, $iv);

		//insert token, token timestamp and initializing vector into database
		$res->update(['token' => $token, 'token_generated' => $timestamp, 'iv' => $iv]);

		echo json_encode(['token' => $token]);
	}
	
});

//log the user out
$app->get('/auth/logout', function() use ($app, $db) {
	//get the token
	$token = $app->request->headers->get('user-token');
	if(token_is_valid($token, $db)){
		$app->response->setStatus(200);

		//get token and invalidate it
		$user_data = $db->users('token = ?', $token)->fetch();
		$expired_timestamp = intval($user_data['token_generated']) - 64800;

		//update user with expired timestamp
		$user = $db->users()->where('token = ?', $token);
		$user->update(['token_generated' => $expired_timestamp]);

		echo json_encode(['message' => 'Logged out!']);
	} else {
		//$app->response->setStatus(401);
		echo json_encode(['message' => 'Invalid Token']);
	}

});

//fetch all emojis
$app->get('/emojis/', function() use ($app, $db) {
	//fetch all emojis
	$emojis = $db->emoji();

	$result = [];

	foreach($emojis as $emoji) {
		$result[] = $emoji;
	}
	$output = ['emojis' => $result];

	echo json_encode($output);
});

//fetch emoji by id
$app->get('/emojis/:id', function($id) use ($app, $db) {
	$emoji = $db->emoji('id = ?', $id)->fetch();
	echo json_encode($emoji);
});

//post emoji
$app->post(/'emojis/', function() use ($app, $db) {
	$token = $app->request->headers->get('user-token');
	if(token_is_valid($token, $db)){
		$all_post_vars = $app->request->post();
		if ($db->insert($all_post_vars)) {
			$app->response->setStatus(200);
			echo jsone_encode(['message' => 'Emoji successfully added!']);
		} else {
			$app->response->seStatus(401);
			echo jsone_encode(['message' => 'Failed!']);
		}
	} else {
			$app->response->seStatus(401);
			echo jsone_encode(['message' => 'Invalid Token!']);
	}

});



$app->run();

function generate_token($txtToEnc, $encMethod, $secHash, $iv)
{
	return openssl_encrypt($txtToEnc, $encMethod, $secHash, 0, $iv);
}

function token_is_valid($token, $db) 
{
	$result = $db->users->where('token = ?', $token);
	if (count($result) == 0) {
		return false;
	}
	else {
		//check that token has not expired
		$current_time = get_current_time();
		$user = $db->users('token = ?', $token)->fetch();
		$time_token_generated = $user['token_generated'];
		$output = (intval($current_time) - 64800) < $time_token_generated ? true : false;
		return $output;
	}
}

function get_current_time() 
{
	$date = new DateTime();
	return $date->getTimestamp();
}


?>