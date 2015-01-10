<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Documentation;
use App\Services\Slack;
use DOMDocument;
use DOMXPath;
use Github\Client;
use Illuminate\Cache\Repository;
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
     * @var int
     */
    private $cacheTime = 60;

    /**
     * @var Slack
     */
    private $slack;

    public function __construct(CommonMarkConverter $markdown, Client $github, Repository $cache, Slack $slack)
    {
        parent::__construct();

        $this->markdown = $markdown;
        $this->github   = $github;
        $this->cache    = $cache;
        $this->slack    = $slack;
    }

    public function index(Request $request)
    {
        $documentation = new Documentation($request, $this->slack);

        return $documentation->handle($this->github);
    }

}