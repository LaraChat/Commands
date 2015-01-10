<?php namespace App\Services;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation\Interactive;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Documentation implements DocumentationInterface {

    public    $url      = 'http://laravel.com/docs/';

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
        $this->checkFlags($parts);

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
        $this->url .= $data . $separator;

        return $this;
    }

    public function sendToSlack()
    {
        $this->slack->execute('chat.postMessage', [
            'channel' => $this->request->get('channel_id'),
            'text' => $this->url
        ]);

        return 'Success';
    }

    private function checkFlags($parts)
    {
        $helpIndex = $parts->search('-h');

        if ($helpIndex != null) {
            $this->helpFlag = true;

            $parts->forget($this->helpFlag);
        }
    }

    private function checkParts($parts)
    {
        if ($parts->first() != null) {
            $this->version = $this->getDataAppendUrl($parts);
        }
        if ($parts->first() != null) {
            $this->header = $this->getDataAppendUrl($parts, '#');
        }
        if ($parts->first() != null) {
            $this->sub = $this->getDataAppendUrl($parts, null);
        }
    }

    private function getDataAppendUrl($parts, $separator = '/')
    {
        $data = $parts->pull(0);

        $this->appendUrl($data, $separator);

        return $data;
    }
}