<?php namespace App\Http\Controllers\Command;

use App\Http\Controllers\Controller;
use App\Services\Slack;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;

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
        return $this->response->json($this->getUsers()->count());
    }

    private function getUsers()
    {
        return new Collection($this->slack->execute('users.list')->getBody()['members']);
    }

}