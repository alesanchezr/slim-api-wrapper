<?php

	use Psr\Http\Message\ServerRequestInterface as Request;
	use Psr\Http\Message\ResponseInterface as Response;
    require("../../vendor/autoload.php");

	$api = new \SlimAPI\SlimAPI([
		'name' => 'Separate Endpoints - ',
		'debug' => true,
        'jwt_key'=> '345345f6543g24!!!@@@ds34',
        'jwt_clients' => [
            'bobylant' => "2d3CD3gv432423gv434@4dstgjk45&@SDsd23v6t!dfg&vf#",
        ]
	]);

	$api->addReadme('/','./README.md');

	$api->addRoutes(require("endpoints.php"));

	$api->run();