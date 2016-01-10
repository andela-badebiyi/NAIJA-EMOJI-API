<?php

require "vendor/autoload.php";

//initialize db handlers;
$pdo = new PDO('sqlite:test.db');
$db = new NotORM($pdo);

//initialize slim
$app = new Slim\Slim();


//secret hash key and the encryption method
$secret_hash = "123ophiophagus!@#";
$encryptionMethod = "AES-256-CBC"; 

//initializing vector;
$iv = openssl_random_pseudo_bytes(16);

//instantiate the appAction Object that processes our requests
$action = new app\ApiController($app, $db, $secret_hash, $encryptionMethod, $iv);

//log the user in
$app->post('/auth/login', $action->authLogin());

//log the user out
$app->get('/auth/logout', $action->authLogout());

//fetch all emojis
$app->get('/emojis/', $action->fetchAllEmojis());

//fetch emoji by id 
$app->get('/emojis/:id', $action->fetchEmoji());

//create a new emoji
$app->post('/emojis/', $action->createEmoji());

//update an emoji using put
$app->put('/emojis/:id/', $action->updateEmoji());

//update an emoji using patch
$app->patch('/emojis/:id/',$action->updateEmoji());

//delete an emoji
$app->delete('/emojis/:id/',$action->deleteEmoji());

//run $app
$app->run();

?>