<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AlunniController.php';
require __DIR__ . '/controllers/CertificazioniController.php';

$app = AppFactory::create();

$app->get('/test', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Test page");
    return $response;
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

//rotte ALUNNI
$app->get('/alunni', [AlunniController::class, 'index']);
$app->get('/alunni/{id}', [AlunniController::class, 'read']);
$app->post('/alunni', [AlunniController::class, 'create']);
$app->put('/alunni/{id}', [AlunniController::class, 'update']);
$app->delete('/alunni/{id}', [AlunniController::class, 'delete']);

//rotte CERTIFICAZIONI
$app->get('/alunni/{alunno_id}/certificazioni', [CertificazioniController::class, 'index']);
$app->get('/alunni/{alunno_id}/certificazioni/{id}', [CertificazioniController::class, 'read']);
$app->post('/alunni/{alunno_id}/certificazioni', [CertificazioniController::class, 'create']);
$app->put('/alunni/{alunno_id}/certificazioni/{id}', [CertificazioniController::class, 'update']);
$app->delete('/alunni/{alunno_id}/certificazioni/{id}', [CertificazioniController::class, 'delete']);

$app->addBodyParsingMiddleware();
$app->run();
