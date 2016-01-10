<?php

require 'vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

srand(microtime(true));

$app = new Slim\App;

$app->get('/', function (Request $req, Response $res) {
    return $res->withRedirect('/boards/g', 302);
});

$app->get('/boards/{board}', function (Request $req, Response $res) {
    $board = $req->getAttribute('board');

    $parse_ok = Markovchan\ApiParser::parse($board);
    $template_data = ['parse_ok' => $parse_ok];
    $post = Markovchan\PostGenerator::generate($board, $template_data);

    $res->getBody()->write($post);
});

$app->get('/parse', function (Request $req, Response $res) {
    return $res->withStatus(204);
});

$app->run();
