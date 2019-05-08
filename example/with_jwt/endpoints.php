<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function($inst){

    $inst->addTokenGenerationPath();

    $inst->app->get('/hello', function (Request $request, Response $response, array $args){

        return $response->withJson(["Hello World"]);
	    //throw new Exception('Image could not be generated', 400);

    });

    $inst->app->get('/restricted', function (Request $request, Response $response, array $args){

        return $response->withJson(["If you can read this, you have access to this restricted endpoint"]);
	    //throw new Exception('Image could not be generated', 400);

    })->add($inst->auth());

    $inst->app->get('/encode', function (Request $request, Response $response, array $args) use ($inst) {

        return $response->withJson([
            "token" => $inst->jwt_encode($_GET["payload"])
        ]);

    });

    return $inst;
};