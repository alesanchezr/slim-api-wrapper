<?php

namespace SlimAPI;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Aws\Ses\SesClient;
use Aws\Ses\Exception\SesException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Respect\Validation\Validator as v;
use \Firebase\JWT\JWT;
use Exception;

class SlimAPI{

    public static $INFO = 'info';
    public static $NOTICE = 'notice';
    public static $WARNING = 'warning';
    public static $ERROR = 'error';
    private $logPaths = [
        'activity' => 'logs/api_activity.log'
    ];

    var $app = null;
    var $appName = null;
    var $db = [];
    var $logger = null;
    var $debug = false;
    var $authPath = '/token/generate';
    var $jwtKey = null;
    var $jwtClients = [];
    var $allowedURLs = [
            'all',
//            'https://replbox.repl.it'
        ];
    var $allowedMethods = ['GET','POST','PUT','DELETE','OPTIONS'];

    function __construct($settings=null){
        $this->logger = new Logger('activity');
        if(!empty($settings['name'])) $this->appName = $settings['name'];
        if(!empty($settings['debug'])) $this->debug = $settings['debug'];
        if(!empty($settings['jwt_key'])) $this->jwtKey = $settings['jwt_key'];
        if(!empty($settings['jwt_clients'])) $this->jwtClients = $settings['jwt_clients'];
        if(empty($settings['settings'])) $settings['settings'] = [];
        if(!empty($settings['allowedMethods'])){
            if(!is_array($settings['allowedMethods'])) throw new Exception('allowedMethods must be an array of methods: ["GET","POST", ...]');
            $this->allowedMethods = $settings['allowedMethods'];
        }
        if(!empty($settings['allowedURLs']) && $this->debug){
            if(is_array($settings['allowedURLs']))
                $this->allowedURLs = array_push($settings['allowedURLs'], $this->allowedURLs);
            else if($settings['allowedURLs'] == 'all') $this->allowedURLs = ['all'];
            else throw new Exception('Invalid setting value for allowedURLs');
        }

    	$c = new \Slim\Container([
    	    'settings' => array_merge([
    	        'displayErrorDetails' => $this->debug
    	    ], $settings['settings']),
    	]);
    	if(!$this->debug){
        	$c['errorHandler'] = function ($c) {
        	    return function ($request, $response, $exception) use ($c) {

        	        //$this->log(self::$ERROR, $exception->getMessage());

        	        $code = $exception->getCode();
                    if(!in_array($code, [500,400,301,302,401,404,403])) $code = 500;

        	        return $c['response']->withStatus($code)
        	                             ->withHeader('Content-Type', 'application/json')
        	                             ->withHeader('Access-Control-Allow-Origin', '*')
        	                             ->write( json_encode(['msg' => $exception->getMessage()]));
        	    };
        	};
        	$c['phpErrorHandler'] = function ($c) {
        	    return function ($request, $response, $exception) use ($c) {

        	        //$this->log(self::$ERROR, $exception->getMessage());
        	        $code = $exception->getCode();
                    if(!in_array($code, [500,400,301,302,401,404])) $code = 500;
        	        return $c['response']->withStatus($code)
        	                             ->withHeader('Content-Type', 'application/json')
        	                             ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        	                             ->withHeader('Access-Control-Allow-Origin', '*')
        	                             ->write( json_encode(['msg' => $exception->getMessage()]));
        	    };
        	};
        	//$c['notFoundHandler'] = $phpErrorHandler;
    	}
    	else{
        	$c['phpErrorHandler'] = $c['errorHandler'] = function ($c) {
        	    return function ($request, $response, $exception) use ($c) {

        	        $code = $exception->getCode();
                    if(!in_array($code, [500,400,301,302,401,404,403])) $code = 500;
        	        return $c['response']->withStatus($code)
        	                             ->withHeader('Content-Type', 'application/json')
        	                             ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        	                             ->withHeader('Access-Control-Allow-Origin', '*')
        	                             ->write( json_encode([
        	                                 'msg' => $exception->getMessage(),
        	                                 'trace' => array_map(function($trace){
        	                                     if(!isset($trace['file'])) $trace['file'] = '';
        	                                     if(!isset($trace['line'])) $trace['line'] = '';
        	                                     if(!isset($trace['class'])) $trace['class'] = '';
        	                                     if(!isset($trace['function'])) $trace['function'] = '';
        	                                     return sprintf("\n%s:%s %s::%s", $trace['file'], $trace['line'], $trace['class'], $trace['function']);
        	                                 },debug_backtrace())
    	                                 ]));
        	    };
        	};
    	}

	    $this->app = new \Slim\App($c);

	    $allowedURLs = $this->allowedURLs;
	    $allowedMethods = $this->allowedMethods;
        if(isset($_SERVER['HTTP_ORIGIN']) || in_array('all', $allowedURLs)){
            foreach($allowedURLs as $o)
                if((isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] == $o) || $o == 'all')
                {
                    $this->app->add(function ($req, $res, $next) use ($allowedURLs,$allowedMethods) {
                        $response = $next($req, $res);
                        return $response
                                ->withHeader('Access-Control-Allow-Origin', '*')
                                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-PINGOTHER')
                                ->withHeader('Access-Control-Allow-Methods', implode(",",$allowedMethods))
                                ->withHeader('Allow', implode(",",$allowedMethods));
                    });
                }
        }
    }

    public function addDB($key, $connector){
	    $this->db[$key] = $connector;
    }

    public function setJwtClients($clients){
        if(!is_array($clients) || count($clients) == 0) throw new Exception("JWT Clients must be a non-empty array");
	    $this->jwtClients = $clients;
    }

    public function setJWTKey($key){
        if(empty($key)) throw new Exception('Invalid JWT key');
        $this->jwtKey = $key;
    }
    public function generatePrivateKey($clientId){
        $this->jwtClients[$clientId] = $this->jwt_encode($clientId);
        return $this->jwtClients[$clientId];
    }

    public function get($path, $callback){
        return $this->app->get($path, $callback);
    }
    public function post($path, $callback){
        return $this->app->post($path, $callback);
    }
    public function put($path, $callback){
        return $this->app->put($path, $callback);
    }
    public function delete($path, $callback){
        return $this->app->delete($path, $callback);
    }
    public function addReadme($path='/', $markdownPath='./README.md'){
        $slimAPI = $this;
        $this->app->get($path, function (Request $request, Response $response, array $args) use ($slimAPI, $markdownPath){
            return $response->write($slimAPI->_readmeTemplate($markdownPath));
    	});
    }

    public function auth(){

        $privateKey = $this->jwtKey;
        $authPath = $this->authPath;

        return function ($request, $response, $next) use ($privateKey, $authPath) {

            $path = $request->getUri()->getPath();
            if($path != $authPath){
                $token = '';
                $query = $request->getQueryParams();
                if(isset($query['access_token'])){
                    $parts = explode('.', $query['access_token']);
                    if(count($parts)!=3) throw new Exception('Invalid access token', 403);
                    $token = $query['access_token'];
                }
                else{
                    $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
                    if(!empty($authHeader)){
                        if(strpos($authHeader,"JWT") === false) throw new Exception('Authorization header must contain JWT', 403);
                        $authHeader = str_replace("JWT ", "", $authHeader);
                        $parts = explode('.', $authHeader);
                        if(count($parts)!=3) throw new Exception('Invalid access token', 403);
                        $token = $authHeader;

                    }
                    else throw new Exception('Invalid access token', 403);
                }

            	$decoded = JWT::decode($token, $privateKey, array('HS256'));
            }

        	$response = $next($request, $response);

        	return $response;
        };
    }

    public function addRoutes($func){
        if(!is_callable($func)) throw new \InvalidArgumentException('AddRoutes expects a callabel function or object but '.gettype($func).' found');

        $this->app = $func($this)->app;
    }

    public function addTokenGenerationPath($path = '/token/generate'){

        if(empty($this->jwtKey)) throw new Exception('No jwt_key has been set to the api', 500);

        $clients = $this->jwtClients;
        $privateKey = $this->jwtKey;
        $this->authPath = $path;
        $this->app->post($this->authPath, function (Request $request, Response $response, array $args) use ($clients, $privateKey){

            $ct = $request->getHeader('Content-Type');
            $clientId = '';
            $cliendPass = '';

            if($ct[0] == 'application/json'){
                $parsedBody = $request->getParsedBody();
                if(empty($parsedBody['client_id']) || empty($parsedBody['client_pass']))
                     throw new Exception('MISSING credentials: client_id and client_pass');
                $clientId = $parsedBody['client_id'];
                $cliendPass = $parsedBody['client_pass'];
            }
            else
            {
                if(empty($_POST['client_id']) || empty($_POST['client_pass']))
                    throw new Exception('MISSING credentials: client_id and client_pass');
                $clientId = $_POST['client_id'];
                $cliendPass = $_POST['client_pass'];

            }

            if(empty($clients[$clientId]) || $clients[$clientId] != $cliendPass)
                throw new Exception('INVALID credentials: client_id ('.$clientId.') and client_pass');

    		$token = array(
    		    "clientId" => $clientId,
    		    "iat" => time(),
    		    "exp" => time() + 31556952000 // plus one year in miliseconds
    		);

    		$token['access_token'] = JWT::encode($token,  $privateKey);
            return $response->withJson($token);
    	});
    }

    public function jwt_encode($payload, $expiration=31556952000){

		$token = array(
		    "clientId" => $payload,
		    "iat" => time(),
		    "exp" => time() + $expiration
		);

		return JWT::encode($token, $this->jwtKey);
    }

    public function jwt_decode($payload){

		return JWT::decode($payload, $this->jwtKey, array('HS256'));
    }

    public function run(){
        return $this->app->run();
    }

    // Get an instance of the application.
    public function getContainer(){
        return $this->app->getContainer();
    }

    private function _readmeTemplate($readmePath){
        if(!file_exists($readmePath)) throw new Exception('Readme not found in path '.$readmePath, 404);
        if(!$this->appName) throw new Exception('You need to set a name for the API in order to use the Readme Generator');
        return '
    <!DOCTYPE html>
    <html>
        <head>
            <title>'.$this->appName.'</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-markdown-css/2.10.0/github-markdown.min.css" type="text/css" />
        </head>
        <body>
            <style type="text/css">
                img{max-height: 25px;}
                .markdown-body{ max-width: 800px; margin: 0 auto;}
            </style>
            <div class="markdown-body"></div>
            <script type="text/javascript">
                window.onload = function(){
                    fetch("'.$readmePath.'")
                    .then(resp => resp.text())
                    .then(function(text){
                        var converter = new showdown.Converter();
                        converter.setFlavor("github");
                        const html      = converter.makeHtml(text);
                        document.querySelector(".markdown-body").innerHTML = html;
                    }).catch(function(error){
                        console.error(error);
                    });
                }
            </script>
            <script type="text/javascript" src="https://cdn.rawgit.com/showdownjs/showdown/1.8.6/dist/showdown.min.js"></script>
        </body>
    </html>
        ';
    }

    public function sendMail($to,$subject,$message){

        $loader = new \Twig_Loader_Filesystem('../');
        $twig = new \Twig_Environment($loader);

        $template = $twig->load('email_template.html');

        $client = SesClient::factory(array(
            'version'=> 'latest',
            'region' => 'us-west-2',
            'credentials' => [
                'key'    => S3_KEY,
                'secret' => S3_SECRET,
            ]
        ));

        try {
             $result = $client->sendEmail([
            'Destination' => [
                'ToAddresses' => [
                    $to,
                ],
            ],
            'Message' => [
                'Body' => [
                    'Html' => [
                        'Charset' => 'UTF-8',
                        'Data' => $template->render(['subject' => $subject, 'message' => $message]),
                    ],
        			'Text' => [
                        'Charset' => 'UTF-8',
                        'Data' => $message,
                    ],
                ],
                'Subject' => [
                    'Charset' => 'UTF-8',
                    'Data' => $subject,
                ],
            ],
            'Source' => 'info@breatheco.de',
            //'ConfigurationSetName' => 'ConfigSet',
        ]);
             $messageId = $result->get('MessageId');
             return true;

        } catch (SesException $error) {
            throw new Exception("The email was not sent. Error message: ".$error->getAwsErrorMessage()."\n", 500);
        }
    }

    public function log($level, $msg, $data=null){

        if(empty($level) || empty($msg)) throw new Exception('Mising level or message');
        // create a log channel
        if(!file_exists($this->logPaths['activity'])) throw new Exception('Activity log not found: '.$this->logPaths['activity']);
        $this->logger->pushHandler(new StreamHandler($this->logPaths['activity'], Logger::DEBUG));

        // add records to the log
        if($data) $msg .= json_encode($data);
        switch($level){
            case self::$NOTICE: $this->logger->notice($msg); break;
            case self::$ERROR: $this->logger->error($msg); break;
            case self::$WARNING: $this->logger->warning($msg); break;
            case self::$INFO: $this->logger->info($msg); break;
        }
    }

    function validate($value, $key=null){
        $val = new Validator($value, $key);
        return $val;
    }
    function optional($value, $key=null){
        $val = new ValidatorOptional($value, $key);
        return $val;
    }

}
class ArgumentException extends Exception{
    protected $code = 400;
}
class Validator{
    var $value = null;
    var $key = null;
    var $optional = false;

    function __construct($value, $key=null){
        //if there is a key, the $value is an object a we need to grab the value inside of it
        if($key){
            $value = (array) $value;
            if(isset($value[$key])) $value = $value[$key];
            else $value = null;
        }

        $this->value = $value;
        $this->key = $key;
    }
    function smallString($min=1, $max=255){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::stringType()->length($min, $max)->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid value: '.$for." was expecting string between $min and $max");
        return $this->value;
    }
    function string($min=1, $max=255){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::stringType()->length($min, $max)->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid value: '.$for." was expecting string between $min and $max");
        return $this->value;
    }
    function text($min=1, $max=4000){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::stringType()->length($min, $max)->validate($this->value);

        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid value: '.$for." was expecting string between $min and $max");
        return $this->value;
    }

    function slug(){
        if(empty($this->value) && $this->optional) return null;

        $validator = preg_match('/^([a-zA-z])(-|_|[a-z0-9])*$/', $this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid value: '.$for);
        return $this->value;
    }
    function enum($options=[]){
        if(empty($this->value) && $this->optional) return null;

        $validator = in_array($this->value, $options);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        $for .= ' it has to match one of the following: '.implode($options,",");
        if(!$validator) throw new ArgumentException('Invalid value: '.$for);
        return $this->value;
    }
    function int(){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::intVal()->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid integer value: '.$for);
        return $this->value;
    }
    function email(){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::email()->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid email value: '.$for);
        return $this->value;
    }
    function url(){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::url()->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid URL value: '.$for);
        return $this->value;
    }
    function date(){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::date()->validate($this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : $this->value;
        if(!$validator) throw new ArgumentException('Invalid date value: '.$for);
        return $this->value;
    }
    function bool(){
        if(empty($this->value) && $this->optional) return null;

        $validator = v::boolType()->validate((bool) $this->value);
        $for = ($this->key) ? $this->value.' for '.$this->key : '';
        if(!$validator) throw new ArgumentException('Invalid boolean value: '.$for);
        return $this->value;
    }
}
class ValidatorOptional extends Validator{
    public function __construct($value, $key=null){
        parent::__construct($value, $key);
        $this->optional = true;
    }
}
class SlimAPITestCase extends \PHPUnit\Framework\TestCase {

    var $routesAdded = false;
    /**
     * Default preparation for each test
     */
    public function setUp()
    {
        parent::setUp();
        $this->createVirtualAPI();
        if(!defined('RUNING_TEST')) define('RUNING_TEST',true);
    }

    /**
     * Migrates the database and set the mailer to 'pretend'.
     * This will cause the tests to run quickly.
     */
    private function createVirtualAPI(){
    	$this->app = new SlimAPI([
    		'debug' => true,
            'settings' => [
                'authenticate' => false,
                'displayErrorDetails' => true,
                'determineRouteBeforeAppMiddleware' => false,
                'addContentLengthHeader' => false
            ]
    	]);
    }

    /**
     * To add the routs being tested
     */
    public function addRoutes($func){
        $this->routesAdded = true;
        $this->app = $func($this)->app;
    }

    public function mockGET($url){
        $parts = explode('?',$url);

        $params = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $parts[0]
        ];

        if(!empty($parts[1])) $params['QUERY_STRING'] = $parts[1];

        return $this->mockRequest($params);
    }

    public function mockPOST($url, $bod=null){
        return $this->mockRequest([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $url
        ], $body);
    }

    public function mockPUT($url, $bod=null){
        return $this->mockRequest([
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => $url
        ], $body);
    }

    public function mockDELETE($url, $bod=null){
        return $this->mockRequest([
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => $url
        ]);
    }

    /**
       [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/catalog/countries/',
       ]
     */
    protected function mockRequest($params, $body=null){

        $env = \Slim\Http\Environment::mock($params);

        $req = \Slim\Http\Request::createFromEnvironment($env);
        $bodyStream = $req->getBody();
        $bodyStream->write(json_encode($body));
        $bodyStream->rewind();
        $req = $req->withBody($bodyStream);
        $req = $req->withHeader('Content-Type', 'application/json');

        $this->app->getContainer()["environment"] = $env;
        $this->app->getContainer()["request"] = $req;
        $response = $this->app->run();
        $responseBody = $response->getBody();
        $responseObj = json_decode($responseBody);

        $assertion =  new AssertResponse($this, $response, $responseObj);
        //$this->_logTest($params, $response, $responseObj, $assertion);
        return $assertion;
    }
    function log($msg){
        if(DEBUG){
            echo "\033[33m";
            print_r($msg);
            echo "\033[0m";
        }
    }
    function _logTest($params, $response, $responseObj, $assertion=null){
        if(DEBUG){
            $code = $response->getStatusCode();
            $expected = (!$assertion) ? '' : $assertion->getExpectedRespCode();
            if($code != 200 && $code != 400 && $code != 404){
                if(!empty($responseObj)){
                    $logEntry = "\n \n [ \n".
                    "   [code]     => \033[33m".$responseObj->code."\033[0m \n".
                    "   [msg]      => \033[31m".$responseObj->msg."\033[0m \n".
                    "]\n \n";
                    echo "\033[31m \n ****    FOUND SOME MISMATCHES:    **** \n \033[0m";
                    print_r($logEntry);
                }
                else {
                    echo "\033[31m \n ****    FOUND SOME MISMATCHES:    **** \n \033[0m";
                    echo "   [request]  => \033[36m".$params['REQUEST_METHOD'].": ".$params['REQUEST_URI']."\033[0m \n";
                    echo "   [details]  => \033[33m No details or response was provided \033[0m \n \n";
                }
            }
        }
    }
}
class AssertResponse{
    private $test;
    private $response;
    private $expectedRespCode = null;
    private $responseObj;
    function __construct($test, $response, $responseObj){
        $this->test = $test;
        $this->response = $response;
        $this->responseObj = $responseObj;
    }
    function getExpectedRespCode(){ return $this->expectedRespCode; }
    function expectSuccess($code=200){
        $this->test->assertSame($this->response->getStatusCode(), 200);
        if(isset($this->responseObj->code)) $this->test->assertSame($this->responseObj->code, 200);
        $this->expectedRespCode = $code;
        return new AssertResponse($this->test, $this->response, $this->responseObj);
    }
    function expectFailure($code=400){
        $this->test->assertSame($this->response->getStatusCode(), $code);
        if(isset($this->responseObj->code)) $this->test->assertSame($this->responseObj->code, $code);
        $this->expectedRespCode = $code;
        return new AssertResponse($this->test, $this->response, $this->responseObj);
    }
    function withProperties($properties){
        $hasProperties = true;
        $respData = isset($this->responseObj->code) ? $this->responseObj->data : $this->responseObj;
        foreach($properties as $key => $val){
            $this->test->assertObjectHasAttribute($key, $respData);
        }
        return new AssertResponse($this->test, $this->response, $this->responseObj);
    }
    function withPropertiesAndValues($properties){
        $hasProperties = true;
        $respData = isset($this->responseObj->code) ? $this->responseObj->data : $this->responseObj;
        foreach($properties as $key => $value){
            $this->test->assertObjectHasAttribute($key, $respData);
            if(property_exists($respData, $key)){
                $data = (array) $respData;
                $this->test->assertSame($data[$key], $value);
            }
        }

        return new AssertResponse($this->test, $this->response, $this->responseObj);
    }
    function getParsedBody(){
        return $this->responseObj;
    }
}