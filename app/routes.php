<?php

declare(strict_types=1);

use App\Application\Actions\Journal\ListJournalsAction;
use App\Application\Actions\Journal\JournalAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Journal API working fine!');
        return $response;
    });

    $app->get('/journals', ListJournalsAction::class);
    
    $app->post('/journals', [JournalAction::class, 'createJournal']);

    $app->put('/journals/{id}', [JournalAction::class, 'updateJournal']);

    $app->delete('/journals/{id}', [JournalAction::class, 'deleteJournal']);
};