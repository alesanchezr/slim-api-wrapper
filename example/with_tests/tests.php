<?php
require('./src/SlimAPI.php');
use PHPUnit\Framework\TestCase;
use SlimAPI\SlimAPITestCase;
use \Firebase\JWT\JWT;

class GeneralTests extends SlimAPITestCase
{
    private $credentials = [
        "clientId" => "my_super_id",
        "clientKey" => null
    ];

    public function setUp(){
        parent::setUp();

        //adding an internal seed for random private key generation
        $this->app->setJWTKey("adSAD43gtterT%rtwre32@");

        //generating credentials for one client.
        $this->credentials['clientKey'] = $this->app->generatePrivateKey($this->credentials['clientId']);

        $this->app->addRoutes(require(__DIR__.'/endpoints.php'));
    }

    public function testInvalidRoutes(){
        $this->expectException(InvalidArgumentException::class);
        $this->app->addRoutes('hello');
    }

    public function testForFailure(){
        $this->mockGET('/hello/fail')->expectFailure(); //expects 400
    }
    public function testForSuccess(){
        $this->mockGET('/hello/success')->expectSuccess(); //expects 200
    }
    public function testForProperties(){
        //expects 200 and a resp object with property 'foo'
        $this->mockGET('/hello/object_success')->expectSuccess()->withProperties(['foo' => 'bar']);
        //expects 200 and a resp object with property 'foo' and value 'bar'
        $this->mockGET('/hello/object_success')->expectSuccess()->withPropertiesAndValues(['foo' => 'bar']);
    }

    public function testForAuthentication(){
        //expects failure with 403 code
        //$this->mockGET('/hello/private')->expectFailure(403);
        $this->mockGET('/hello/private?access_token='.$this->credentials['clientKey'])->expectSuccess();

        //forcing an error with invalid key
        $this->mockGET('/hello/private?access_token=asdasdads')->expectFailure(403);
    }


    public function testForEncoding(){
        //expects failure with 403 code
        //$this->mockGET('/hello/private')->expectFailure(403);
        $body = $this->mockGET('/encode')->getParsedBody();
        $this->mockGET('/hello/private?access_token='.$body->token)->expectSuccess();
    }


}