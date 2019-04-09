<?php

	use Psr\Http\Message\ServerRequestInterface as Request;
	use Psr\Http\Message\ResponseInterface as Response;
    require("../../vendor/autoload.php");

	$api = new \SlimAPI\SlimAPI([
		'name' => 'Logs - ',
		'debug' => true
	]);
	
	$api->addReadme('/','./README.md');
	$api->addRoutes(require("endpoints.php"));
	
	$api->run(); 