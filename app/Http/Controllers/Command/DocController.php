<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Documentation;
use App\Services\Slack;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function index(Request $request)
    {
        if (in_array($request->get('channel_name'), $this->config->get('services.larabot.excludedChannels'))) {
            return 'LaraBot does not run in this channel.  Please try to run it in a different one.';
        }

        try {
            $documentation = new Documentation($request, $this->slack, $this->github, $this->cache);

            return $documentation->handle($this->github, $this->cache);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        } catch (\Exception $e) {
            return 'Something went wrong.  Check your command for any spelling errors and try again.';
        }
    }

}