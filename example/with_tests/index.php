<?php

	use Psr\Http\Message\ServerRequestInterface as Request;
	use Psr\Http\Message\ResponseInterface as Response;
    require("../../vendor/autoload.php");

	$api = new \SlimAPI\SlimAPI([
		'name' => 'Separate Endpoints - ',
		'debug' => true
	]);
	
	$api->addReadme('/','./README.md');
	
	$func = require("endpoints.php");
	$api->addRoutes($func);
	
	$api->run(); 