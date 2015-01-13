<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Documentation;
use App\Services\Slack;
use Github\Client;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use League\CommonMark\CommonMarkConverter;

class DocController extends Controller {

    /**
     * @var CommonMarkConverter
     */
    private $markdown;

    /**
     * @var Client
     */
    private $github;

    /**
     * @var Repository
     */
    private $cache;

    /**
     * @var Slack
     */
    private $slack;

    /**
     * @var ConfigRepository
     */
    private $config;

    public function __construct(CommonMarkConverter $markdown, Client $github, Repository $cache, Slack $slack,
                                ConfigRepository $config)
    {
        parent::__construct();

        $this->markdown = $markdown;
        $this->github   = $github;
        $this->cache    = $cache;
        $this->slack    = $slack;
        $this->config   = $config;
    }

    /**
     * Run the variations of the /docs command in slack
     * Usage: /docs [version] [area] [sub area] -h
     *
     * @param Request $request
     *
     * @return null|string
     */
    public function index(Request $request)
    {
        // Restrict commands from working in certain channels.
        // This is primarily done to reduce the chance of spamming in populated areas.
        if (in_array($request->get('channel_name'), $this->config->get('larabot.excludedChannels'))) {
            return 'LaraBot does not run in this channel.  Please try to run it in a different one.';
        }

        try {
            $documentation = new Documentation($request, $this->slack, $this->github, $this->cache, $this->config);

            return $documentation->handle($this->github, $this->cache);
        } catch (\InvalidArgumentException $e) {
            // Used when the user submits an invalid option.

            return $e->getMessage();
        } catch (\Exception $e) {
            throw $e;
            // Squash general errors so as to not to send a wall of html at the user.

            return 'Something went wrong.  Check your command for any spelling errors and try again.';
        }
    }

}