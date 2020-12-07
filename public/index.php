<?php
require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;

$pass_signature = false;

$channel_access_token = "f+u+H0Q5kZ4CBPs3iP3l4oEeSMzjs9YfquNQqABWAbbSZIqb9Eu/sDjmmGTBH3s5F+2iYt/XN815Vlw0HOODaOTfucVFcOaNhicfD8C7O6yj9UHT44LSreupSRBP894wiaHZQ7cJh7CBmzVqj47ncgdB04t89/1O/w1cDnyilFU=";
$channel_secret = "71d628bdfae6c977b8e514907f3a349b";

$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);

$app = AppFactory::create();
$app->setBasePath("/public");

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello World!");
    return $response;
});

$app->post('/webhook', function (Request $request, Response $response) use ($channel_secret, $bot, $httpClient, $pass_signature) {
    $body = $request->getBody();
    $signature = $request->getHeaderLine('HTTP_X_LINE_SIGNATURE');

    file_put_contents('php://stderr', 'Body: ' . $body);

    if ($pass_signature === false) {
        if (empty($signature)) {
            return $response->withStatus(400, 'Signature not set');
        }

        if (!SignatureValidator::validateSignature($body, $channel_secret, $signature)) {
            return $response->withStatus(400,'Invalid signature');
        }
    }
});

$app->run();