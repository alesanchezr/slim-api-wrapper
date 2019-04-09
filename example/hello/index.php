<?php

	use Psr\Http\Message\ServerRequestInterface as Request;
	use Psr\Http\Message\ResponseInterface as Response;
    require("../../vendor/autoload.php");

	$api = new \SlimAPI\SlimAPI([
		'name' => 'BreatheCode Student Github Integration - BreatheCode Platform',
		'debug' => true
	]);
	
	$api->addReadme('/','./README.md');
	
	$api->get('/hello', function (Request $request, Response $response, array $args) use ($api) {
	        
        return $response->withJson(["Hello World"]);
    });
	
	$api->get('/hello/{name}', function (Request $request, Response $response, array $args) use ($api) {
	       
	    $name = $api->validate($args['name'])->string(0,50);
	    $age = $api->validate($args['age'])->int(0,10);
	    
        return $response->withJson([
        	"name" => $name,
        	"age" => $age
        ]);
    });
	    
	
	$api->run(); 