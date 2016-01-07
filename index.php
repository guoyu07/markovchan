<?php

require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app = new Slim\App;
$app->get('/', function (Request $request, Response $response) {
    $post = Markovchan\PostGenerator::generate();
    $response->getBody()->write($post);
});

$app->run();
