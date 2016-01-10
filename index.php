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

    $parse_start_time = microtime(true);
    $parse_ok = Markovchan\ApiParser::parse($board);
    $parse_exec_time = microtime(true) - $parse_start_time;

    $template_data = [
        'parse_execution_time' => round($parse_exec_time, 3) . ' s',
        'parse_ok' => $parse_ok,
    ];
    $post = Markovchan\PostGenerator::generate($board, $template_data);

    $res->getBody()->write($post);
});

$app->run();
