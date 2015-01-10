<?php namespace App\Services;

use App\Services\Contracts\Documentation as DocumentationInterface;
use App\Services\Documentation\Interactive;
use Illuminate\Support\Collection;

class Documentation implements DocumentationInterface {

    public    $url      = 'http://laravel.com/docs/';

    public    $version;

    public    $header;

    public    $sub;

    protected $helpFlag = false;

    private   $interactive;

    public function __construct($payload)
    {
        $this->interactive = new Interactive;
        $parts             = new Collection(explode(' ', $payload));

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
        return $this->url;
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