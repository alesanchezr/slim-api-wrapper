<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimAPI\SlimAPI;

return function($api){
		
    $api->app->get('/hello', function (Request $request, Response $response, array $args) use ($api) {
        
        $api->log(SlimAPI::$NOTICE, 'Everything cool');
        
        return $response->withJson(["Hello World"]);
	    //throw new Exception('Image could not be generated', 400);
	    
    });
    
    return $api;
};