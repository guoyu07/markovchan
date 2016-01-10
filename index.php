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

    $pdo_writing_db = Markovchan\DatabaseConnection::openForWriting($board);
    $parse_start_time = microtime(true);
    $parse_data = Markovchan\ApiParser::parse($board, $pdo_writing_db);
    $parse_exec_time = microtime(true) - $parse_start_time;

    $template_data = [
        'parse_execution_time' => round($parse_exec_time, 3) . ' s',
        'parse_ok' => $parse_data['success'] ? 'true' : 'false',
    ];

    $pdo_reading_db = Markovchan\DatabaseConnection::openForReading($board);
    $post = Markovchan\PostGenerator::generate($board, $pdo_reading_db, $parse_data['image_data'], $template_data);

    $res->getBody()->write($post);
});

$app->error(function (\Exception $e) use ($app) {
    error_log($e->getMessage());
});

$app->run();
