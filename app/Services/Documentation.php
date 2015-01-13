<?php namespace App\Services;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation\Interactive;
use Github\Client;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Documentation implements DocumentationInterface {

    /**
     * @var string
     */
    public $url = 'http://laravel.com/docs';

    /**
     * @var  string|null
     */
    public $version;

    /**
     * @var  string|null
     */
    public $header;

    /**
     * @var  string|null
     */
    public $sub;

    /**
     * @var bool
     */
    protected $helpFlag = false;

    /**
     * @var Interactive
     */
    private $interactive;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Slack
     */
    private $slack;

    /**
     * @param Request          $request
     * @param Slack            $slack
     * @param Client           $github
     * @param Repository       $cache
     * @param ConfigRepository $config
     */
    public function __construct(Request $request, Slack $slack, Client $github, Repository $cache, ConfigRepository $config)
    {
        $this->request     = $request;
        $this->slack       = $slack;
        $this->interactive = new Interactive($this, $github, $cache, $config);
        $parts             = new Collection(explode(' ', $this->request->get('text')));

        // Check for helper flag
        $parts = $this->checkFlags($parts);

        // Handle the parts
        $this->checkParts($parts);
    }

    /**
     * Handle the two paths the command can take.  Interactive or direct post.
     *
     * @return null
     */
    public function handle()
    {
        if ($this->helpFlag == true) {
            return $this->interactive->start();
        }

        return $this->sendToSlack();
    }

    /**
     * Append data to the url.
     *
     * @param        $data
     * @param string $separator
     *
     * @return $this
     */
    public function appendUrl($data, $separator = '/')
    {
        $this->url .= $separator . $data;

        return $this;
    }

    /**
     * Send a message to a slack channel, group or DM.
     *
     * @return null
     */
    public function sendToSlack()
    {
        $this->slack->execute('chat.postMessage', [
            'channel'      => $this->request->get('channel_id'),
            'text'         => $this->url . "\t(" . $this->request->get('user_name') . ')',
            'username'     => 'LaraBot',
            'icon_url'     => 'https://s3-us-west-2.amazonaws.com/slack-files2/avatars/2015-01-05/3336795461_0e48d3ea4de9fe7693fc_132.jpg',
            'unfurl_links' => false
        ]);

        return null;
    }

    /**
     * Check for the existence of any recognized flags for the command.
     *
     * Available Flags:
     * -h: Help.  Changes the command to interactive mode to give the user available options.
     *
     * @param $parts
     *
     * @return mixed
     */
    private function checkFlags($parts)
    {
        $parts = $this->setFlag('helpFlag', '-h', $parts);

        return $parts;
    }

    /**
     * Handle setting the flags and modifying the parts.
     *
     * @param $property
     * @param $flag
     * @param $parts
     *
     * @return mixed
     */
    private function setFlag($property, $flag, $parts)
    {
        $result = $parts->search($flag);

        // If the property exists, set the flag on the class
        // and remove it from the parts collection.
        if ($result !== false) {
            $this->{$property} = true;

            $parts->forget($result);
        }

        return $parts;
    }

    /**
     * Add to the URL based on what exists in the command.
     *
     * @param $parts
     */
    private function checkParts($parts)
    {
        // Check for the existence of a version and verify it is valid.
        if ($parts->has(0) && $parts->get(0) != null) {
            $this->verifyVersion($parts->get(0));
            list($parts, $this->version) = $this->getDataAppendUrl(0, $parts);
        }

        // Check for the existence of a header and verify it is valid.
        if ($parts->has(1) && $parts->get(1) != null) {
            $this->verifyHeader($parts->get(1));
            list($parts, $this->header) = $this->getDataAppendUrl(1, $parts);
        }

        // Check for the existence of a sub header and verify it is valid.
        if ($parts->has(2) && $parts->get(2) != null) {
            $this->verifySub($parts->get(2));
            list($parts, $this->sub) = $this->getDataAppendUrl(2, $parts, '#');
        }
    }

    /**
     * Get the information from the collection, remove it and append it to the URL.
     *
     * @param        $index
     * @param        $parts
     * @param string $separator
     *
     * @return array
     */
    private function getDataAppendUrl($index, $parts, $separator = '/')
    {
        $data = $parts->pull($index);

        $this->appendUrl($data, $separator);

        return [$parts, $data];
    }

    /**
     * Make sure the version supplied exists.
     *
     * @param null|string $version
     */
    public function verifyVersion($version = null)
    {
        $cacheKey = 'docs.versions';

        // Get the current version being called.
        $version = $version == null ? $this->version : $version;

        // Get a collection of valid versions.
        $versions = $this->interactive->getCachedVersions($cacheKey);

        // Make sure the supplied version is in the available versions.
        // Throw an exception if not.
        if ($versions->search($version) === false) {
            $versions = $this->interactive->getVersions();
            throw new \InvalidArgumentException('The version [' . $version . "] does not seem to exist.\n" . $versions);
        }
    }

    /**
     * Make sure the header supplied exists.
     *
     * @param null|string $header
     */
    public function verifyHeader($header = null)
    {
        $cacheKey = 'docs.' . $this->version . '.headers';

        // Get the current header being called.
        $header = $header == null ? $this->header : $header;

        // Get a collection of valid headers.
        $headers = $this->interactive->getCachedHeaders($cacheKey);

        // Make sure the supplied header is in the available headers.
        // Throw an exception if not.
        if ($headers->search($header) === false) {
            $headers = $this->interactive->getHeaders();
            throw new \InvalidArgumentException('The section [' . $header . "] does not seem to exist.\n" . $headers);
        }
    }

    /**
     * Make sure the sub header supplied exists.
     *
     * @param null|string $sub
     */
    public function verifySub($sub = null)
    {
        $cacheKey = 'docs.' . $this->version . '.' . $this->header . '.subs';

        // Get the current sub header being called.
        $sub = $sub == null ? $this->sub : $sub;

        // Get a collection of valid sub headers.
        $subs = $this->interactive->getCachedSubs($cacheKey);

        // Make sure the supplied sub header is in the available sub headers.
        // Throw an exception if not.
        if ($subs->search($sub) === false) {
            $subs = $this->interactive->getSubs();
            throw new \InvalidArgumentException('The sub section [' . $sub . "] does not seem to exist.\n" . $subs);
        }
    }
}