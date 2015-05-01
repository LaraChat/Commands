<?php namespace App\Console\Commands;

use App\Services\Slack\RealTime;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\StreamSelectLoop;
use React\Socket\Server;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class RealTimeSlack extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'slack:real-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the real time slack api server.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param Repository $config
     *
     * @return mixed
     */
    public function fire(Repository $config)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);

        $this->info('Connecting to slack API...');
        $slackApi         = $interactor->get('https://slack.com/api/rtm.start', ['token' => $config->get('services.slack.token')]);
        $messageServerUrl = $slackApi->getBody()['url'];

        $this->info('Launching ratchet server...');
        $ws = new WsServer(new RealTime($messageServerUrl));
        $ws->disableVersion(0);
        $ws->setEncodingChecks(false);

        $loop = new StreamSelectLoop;
        $socketServer = new Server($loop);
        $socketServer->listen(8080);

        $server = new IoServer(
            new HttpServer(
                new WsServer(new RealTime($messageServerUrl))
            ),
            $socketServer,
            $loop
        );
        $server->run();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['example', InputArgument::OPTIONAL, 'An example argument.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }

}
