<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function($inst){

    $inst->app->get('/hello/fail', function (Request $request, Response $response, array $args){

        throw new Exception('Missing something on request', 400);
        return $response->withJson(["Hello World"]);

    });

    $inst->app->get('/hello/success', function (Request $request, Response $response, array $args){

        return $response->withJson(["Hello World"]);

    });

    $inst->app->get('/hello/object_success', function (Request $request, Response $response, array $args){

        return $response->withJson([
            "foo" => "bar"
        ]);

    });

    $inst->app->get('/hello/private', function (Request $request, Response $response, array $args){

        return $response->withJson([
            "private" => "object"
        ]);

    })->add($inst->auth());


    $inst->app->get('/encode', function (Request $request, Response $response, array $args) use ($inst) {

        return $response->withJson([
            "token" => $inst->jwt_encode(23)
        ]);

    });

    return $inst;
};