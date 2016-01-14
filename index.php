<?php

require 'vendor/autoload.php';

//initialize db handlers;

$db_config = parse_ini_file('config.ini');

$dsn = ($db_config['db_location'] == 'remote') ?
'pgsql:host='.$db_config['host'].
';dbname='.$db_config['db'].
';user='.$db_config['user'].
';password='.$db_config['pword'] : 'sqlite:test.db';
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db = new NotORM($pdo);

//initialize slim
$app = new Slim\Slim();

//set secret hash key, encryption method and intializing vector as environment variables
$secret_hash = '123ophiophagus!@#';
$encryptionMethod = 'AES-256-CBC';
$iv = openssl_random_pseudo_bytes(16);

putenv("secret_hash=$secret_hash");
putenv("encryptionMethod=$encryptionMethod");
putenv("iv=$iv");

//instantiate the appAction Object that processes our requests
$action = new app\ApiController($app, $db);

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
$app->patch('/emojis/:id/', $action->updateEmoji());

//delete an emoji
$app->delete('/emojis/:id/', $action->deleteEmoji());

//index page
$app->get('/', function () {
   echo 'Welcome to the naija emoji api homepage';
});
//run $app
$app->run();
