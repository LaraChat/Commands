<?php
use Frlnc\Slack\Http\SlackResponseFactory;
use Ratchet\Server\IoServer;
use App\Services\Slack\RealTime;
use Frlnc\Slack\Http\CurlInteractor;

require './vendor/autoload.php';

$app = require './bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$interactor = new CurlInteractor;
$interactor->setResponseFactory(new SlackResponseFactory);

$slackApi = $interactor->get('https://slack.com/api/rtm.start', ['token' => Config::get('services.slack.token')]);
$messageServerUrl = $slackApi->getBody()['url'];

$server = IoServer::factory(
    new RealTime($messageServerUrl),
    8080
);

$server->run();