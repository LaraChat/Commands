<?php namespace App\Services;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation\Interactive;
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

    public function __construct(Request $request, Slack $slack)
    {
        $this->request     = $request;
        $this->slack       = $slack;
        $this->interactive = new Interactive;
        $parts             = new Collection(explode(' ', $this->request->get('text')));

        // Check for helper flag
        $parts = $this->checkFlags($parts);

        // Handle the parts
        $this->checkParts($parts);
    }

    public function handle()
    {
        if ($this->helpFlag == true) {
            return $this->interactive->start($this);
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
            'text' => $this->url
        ]);

        return null;
    }

    private function checkFlags($parts)
    {
        $helpIndex = $parts->search('-h');

        if ($helpIndex != null) {
            $this->helpFlag = true;

            $parts->forget($this->helpFlag);
        }

        return $parts;
    }

    private function checkParts($parts)
    {
        if ($parts->has(0) != null) {
            list($parts, $this->version) = $this->getDataAppendUrl(0, $parts);
        }
        if ($parts->has(1) != null) {
            list($parts, $this->header) = $this->getDataAppendUrl(1, $parts);
        }
        if ($parts->has(2) != null) {
            list($parts, $this->sub) = $this->getDataAppendUrl(2, $parts, '#');
        }
    }

    private function getDataAppendUrl($index, $parts, $separator = '/')
    {
        $data = $parts->pull($index);

        $this->appendUrl($data, $separator);

        return [$parts, $data];
    }
}