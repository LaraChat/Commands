<?php namespace App\Services;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation\Interactive;
use Github\Client;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Documentation implements DocumentationInterface {

    public    $url      = 'http://laravel.com/docs';

    public    $version;

    public    $header;

    public    $sub;

    protected $helpFlag = false;

    private   $interactive;

    private   $request;

    private   $slack;

    public function __construct(Request $request, Slack $slack, Client $github, Repository $cache)
    {
        $this->request     = $request;
        $this->slack       = $slack;
        $this->interactive = new Interactive($this, $github, $cache);
        $parts             = new Collection(explode(' ', $this->request->get('text')));

        // Check for helper flag
        $parts = $this->checkFlags($parts);

        // Handle the parts
        $this->checkParts($parts);
    }

    public function handle($github, $cache)
    {
        if ($this->helpFlag == true) {
            return $this->interactive->start();
        }

        return $this->sendToSlack();
    }

    public function appendUrl($data, $separator = '/')
    {
        $this->url .= $separator . $data;

        return $this;
    }

    public function sendToSlack()
    {
        $this->slack->execute('chat.postMessage', [
            'channel' => $this->request->get('channel_id'),
            'text' => '<'. $this->url .'|Laravel Documentation :laravel:>',
            'username' => 'LaraBot',
            'icon_url' => 'https://s3-us-west-2.amazonaws.com/slack-files2/avatars/2015-01-05/3336795461_0e48d3ea4de9fe7693fc_132.jpg'
        ]);

        return null;
    }

    private function checkFlags($parts)
    {
        $helpIndex = $parts->search('-h');

        if ($helpIndex !== false) {
            $this->helpFlag = true;

            $parts->forget($helpIndex);
        }

        return $parts;
    }

    private function checkParts($parts)
    {
        if ($parts->has(0) != null) {
            $this->verifyVersion($parts->get(0));
            list($parts, $this->version) = $this->getDataAppendUrl(0, $parts);
        }
        if ($parts->has(1) != null) {
            $this->verifyHeader($parts->get(1));
            list($parts, $this->header) = $this->getDataAppendUrl(1, $parts);
        }
        if ($parts->has(2) != null) {
            $this->verifySub($parts->get(2));
            list($parts, $this->sub) = $this->getDataAppendUrl(2, $parts, '#');
        }
    }

    private function getDataAppendUrl($index, $parts, $separator = '/')
    {
        $data = $parts->pull($index);

        $this->appendUrl($data, $separator);

        return [$parts, $data];
    }

    public function verifyVersion($version = null)
    {
        $cacheKey = 'docs.versions';

        $version = $version == null ? $this->version : $version;

        $versions = $this->interactive->getCachedVersions($cacheKey);

        if ($versions->search($version) === false) {
            $versions = $this->interactive->getVersions();
            throw new \InvalidArgumentException('The version [' . $version . "] does not seem to exist.\n" . $versions);
        }
    }

    public function verifyHeader($header = null)
    {
        $cacheKey = 'docs.' . $this->version . '.headers';

        $header = $header == null ? $this->header : $header;

        $headers = $this->interactive->getCachedHeaders($cacheKey);

        if ($headers->search($header) === false) {
            $headers = $this->interactive->getHeaders();
            throw new \InvalidArgumentException('The section [' . $header . "] does not seem to exist.\n" . $headers);
        }
    }

    public function verifySub($sub = null)
    {
        $cacheKey = 'docs.' . $this->version . '.' . $this->header . '.subs';

        $sub = $sub == null ? $this->sub : $sub;

        $subs = $this->interactive->getCachedSubs($cacheKey);

        if ($subs->search($sub) === false) {
            $subs = $this->interactive->getSubs();
            throw new \InvalidArgumentException('The sub section [' . $sub . "] does not seem to exist.\n" . $subs);
        }
    }
}