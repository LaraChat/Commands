<?php namespace App\Services\Slack;

use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Illuminate\Support\Collection;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SoapClient;

class RealTime implements MessageComponentInterface {

    /**
     * @var Collection
     */
    protected $clients;

    /**
     * @var string
     */
    private $slackMessageServerUrl;

    public function __construct($slackMessageServerUrl)
    {
        $this->clients               = new Collection;
        $this->slackMessageServerUrl = $slackMessageServerUrl;

        $sc = new SoapClient($this->slackMessageServerUrl);
        dd($sc);
    }

    /**
     * When a new connection is opened it will be passed to this method
     *
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     *
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->push($conn);
        var_dump($this->clients);

        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     *
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     *
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->pull($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     *
     * @param  ConnectionInterface $conn
     * @param  \Exception          $e
     *
     * @throws \Exception
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }

    /**
     * Triggered when a client sends data through the socket
     *
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string                       $msg  The message received
     *
     * @throws \Exception
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        var_dump($from);
        var_dump($msg);
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                $client->send($msg);
            }
        }
    }
}