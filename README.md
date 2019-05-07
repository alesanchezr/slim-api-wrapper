# Slim On Steroids

[![Build Status](https://travis-ci.org/alesanchezr/slim-api-wrapper.svg?branch=master)](https://travis-ci.org/alesanchezr/json-orm)
[![Coverage Status](https://coveralls.io/repos/github/alesanchezr/slim-api-wrapper/badge.svg?branch=master)](https://coveralls.io/github/alesanchezr/slim-api-wrapper?branch=master)

Just a small slim wrapper to avoid doing the same things all over again every time I start a new API.

This package is ideal for doing micro-framework architectures where your api is distributed thru several independet servers/developments.

## Instalation

```bash
$ composer require alesanchezr/slim-api-wrapper
```

If you are going to use Authorization headers you have to allow apache to use HTTP Headers in your `.htaccess`:
```
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
```

## Creating an API in 1 minute 🧐

Here is an example on how to create a simple api with just one `GET /hello` endpoint

```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
require("./vendor/autoload.php");

$api = new \SlimAPI\SlimAPI([
	'name' => 'My Super Duper API',
	'debug' => true
]);

$api->get('/hello', function (Request $request, Response $response, array $args) use ($api) {
	return $response->withJson(["Hello World"]);
});
```

## 📝 Adding a readme to the API

It is good practice to add a README.md file written in markwdown, just call the `$api->addReadme()` method to specify the URI you will want users to access yout `README.md`
```
// here users will GET to path /readme in order to read the readme
$api->addReadme('/readme');

//here users will GET to the root to read the readme file, but you can also specify the name of your readme file.
$api->addReadme('/','./INSTRUCTIONS.md');
```

## 💻 Adding more endpoints

The API uses [Slim PHP 3.0](http://www.slimframework.com/) on the background, you can add as many endpoints as you like following the [Slim documetation](http://www.slimframework.com/docs/).

💡 Here is a [list of examples you can use](https://github.com/alesanchezr/slim-api-wrapper/tree/master/example).

## 🔑 JWT Authentication

1. To create private/authenticated the endpoints just add `->add($inst->auth());` at the end of the edpoint like this:

```php

    $inst->app->get('/hello/private', function (Request $request, Response $response, array $args){
        
        return $response->withJson([
            "private" => "object"
        ]);
	    
    })->add($inst->auth()); //here I say I want this endpoint to be private

```

2. Add a secret seed to the API, this will be used as salt for the token generation and you only have to do this step once.

```php
// adding an internal seed for random private key generation
// this only has to be done once in the entire API
$api->setJWTKey("adSAD43gtterT%rtwre32@");
```

3. Add at least one client to the API, you can pick a username but the secret key has to be generated using the `generatePrivateKey` method.
```php
// pick any username you like for the JWT credentials
$clientId = "alesanchezr";

// generate a key based on that username
$clientKey = $api->generatePrivateKey($clientId);
```

4. Now you can make call any request but you have to add the key to the Request `Authorization` header or as `access_token` on the querystring:

### Using QueriString for authentication

```js
//here is an example in Javascript using QueryString autentication
fetch('https://my_api.com/path/to/endpoint?access_token=ddsfs#@$fsd3425Ds')
    .then(resp => {
        //if the token is wrong you will recive status == 403
        if(resp.status == 403) console.error("You have a wrong access_token token");
        else if(resp.ok) return resp.json()
        else console.error("Uknown problem on the API");
    })
    .then(data => console.log(data))
    .catch(err => console.error("There is a problem on the front-end or the API is down"))
```

### Using `Authorization` header for authentication

```js
//here is an example in Javascript using QueryString autentication
fetch('https://my_api.com/path/to/endpoint', {
    'method': 'POST',
    'headers': {
        'Content-Type': ''
        'Authorization': 'JWT asdA@SDad!sdASASDsd453453SDF43'
    },
    'body': JSON.stringify(data)
})
    .then(resp => {
        //if the token is wrong you will recive status == 403
        if(resp.status == 403) console.error("You have a wrong access_token token");
        else if(resp.ok) return resp.json()
        else console.error("Uknown problem on the API");
    })
    .then(data => console.log(data))
    .catch(err => console.error("There is a problem on the front-end or the API is down"))
```

## Aditional Info

Run the tests:
```sh
./vendor/bin/phpunit example/with_tests/tests.php --colors
```
