<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function($inst){

    $inst->app->get('/hello', function (Request $request, Response $response, array $args){

        return $response->withJson(["Hello World"]);
	    //throw new Exception('Image could not be generated', 400);

    });

    return $inst;
};