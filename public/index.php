<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;

$pass_signature = $_ENV['APP_ENV'] == 'local';

$channel_access_token = $_ENV['LINE_ACCESS_TOKEN'];
$channel_secret = $_ENV['LINE_SECRET'];

$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);

$app = AppFactory::create();
$app->setBasePath("/public");
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, false, false);

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

    $data = json_decode($body, true);
    if (isset($data)) {
        if (is_array($data['events'])) {
            foreach ($data['events'] as $event) {
                if ($event['type'] == 'message') {
                    $responseMessage = "";
                    if ($event['source']['type'] == 'group' || $event['source']['type'] == 'room') {
                        $userId = $event['source']['userId'];
                        $getProfile = $bot->getProfile($userId);
                        $profile = $getProfile->getJSONDecodedBody();
                        $responseMessage = new TextMessageBuilder('Hello, ' . $profile['displayName']);
                    } else {
                        switch ($event['message']['type']) {
                            case 'text':
                                switch (strtolower($event['message']['text'])) {
                                    case 'flex message':
                                        $flexTemplate = file_get_contents(__DIR__ . "/flex_message.json");
                                        $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                                            'replyToken' => $event['replyToken'],
                                            'messages' => [
                                                [
                                                    'type' => 'flex',
                                                    'altText' => 'Test Flex Message',
                                                    'contents' => json_decode($flexTemplate)
                                                ]
                                            ]
                                        ]);
                                        $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                                        return $response
                                            ->withHeader('Content-Type', 'application/json')
                                            ->withStatus($result->getHTTPStatus());
                                    default:
                                        $responseMessage = new TextMessageBuilder($event['message']['text']);
                                }
                                break;
                            case 'sticker':
                                $packageId = $event['message']['packageId'];
                                $stickerId = $event['message']['stickerId'];
                                $responseMessage = new StickerMessageBuilder($packageId, $stickerId);
                                break;
                            case 'image':
                            case 'video':
                            case 'audio':
                            case 'file':
                                $contentUrl = $_ENV['APP_URL'] . "/public/content/" . $event['message']['id'];
                                $contentType = ucfirst($event['message']['type']);
                                $responseMessage = new TextMessageBuilder($contentType . " can access from:\n" . $contentUrl);
                                break;
                            default:
                                $multiMessage1 = new TextMessageBuilder('Message type not support');
                                $multiMessage2 = new StickerMessageBuilder(1, 17);
                                $responseMessage = new MultiMessageBuilder();
                                $responseMessage->add($multiMessage1);
                                $responseMessage->add($multiMessage2);
                        }
                    }

                    $result = $bot->replyMessage($event['replyToken'], $responseMessage);
                    $response->getBody()->write(json_encode($result->getJSONDecodedBody()));
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($result->getHTTPStatus());
                }
            }
        }
    }

    return $response->withStatus(400);
});

$app->post('/pushmessage', function (Request $req, Response $res) use ($bot) {
    $textMessageBuilder = new TextMessageBuilder('Hallo, this is a push message');
    $result = $bot->pushMessage($_ENV['LINE_USER_ID'], $textMessageBuilder);

    $res->getBody()->write('Push message sent!');
    return $res
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->post('/pushsticker', function (Request $req, Response $res) use ($bot) {
    $stickerMessageBuilder = new StickerMessageBuilder(1, 1);
    $result = $bot->pushMessage($_ENV['LINE_USER_ID'], $stickerMessageBuilder);

    $res->getBody()->write('Push sticker sent!');
    return $res
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/profile', function (Request $req, Response $res) use ($bot) {
    $result = $bot->getProfile($_ENV['LINE_USER_ID']);

    $res->getBody()->write(json_encode($result->getJSONDecodedBody()));
    return $res
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/profile/{userId}', function (Request $req, Response $res, $args) use ($bot) {
    $result = $bot->getProfile($args['userId']);
    $res->getBody()->write(json_encode($result->getJSONDecodedBody()));
    return $res
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/content/{messageId}', function (Request $req, Response $res, $args) use ($bot) {
    $result = $bot->getMessageContent($args['messageId']);
    $res->getBody()->write($result->getRawBody());
    return $res
        ->withHeader('Content-Type', $result->getHeader('Content-Type'))
        ->withStatus($result->getHTTPStatus());
});

$app->run();