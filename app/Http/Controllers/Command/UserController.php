<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Slack;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserController extends Controller {

    /**
     * @var int
     */
    private $cacheTime = 60;

    /**
     * @var Slack
     */
    private $slack;

    /**
     * @var ResponseFactory
     */
    private $response;

    public function __construct(Slack $slack, ResponseFactory $response)
    {
        parent::__construct();

        $this->slack    = $slack;
        $this->response = $response;
    }

    public function find($name)
    {
        if (substr($name, 0, 1) == 'U' && strlen($name) == 9) {
            return $this->response->json($this->getUsers()->where('id', $name));
        }

        return $this->response->json($this->getUsers()->where('name', $name));
    }

    public function all()
    {
        return $this->response->json($this->getUsers()->toArray());
    }

    public function count()
    {
        $userCount = $this->getUsers()->count();

        if ($userCount === null) {
            return response()->json('Unable to gather user details at this time.', 503);
        }

        return $this->response->json($this->getUsers()->count());
    }

    private function getUsers()
    {
        ini_set('memory_limit', '20M');
        return new Collection($this->slack->execute('users.list')->getBody()['members']);
    }

}