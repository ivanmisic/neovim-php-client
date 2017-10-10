<?php
    namespace NeovimPhpClient;

    use React\EventLoop\Factory;
    use React\Socket\ConnectionInterface;
    use React\Socket\TcpConnector;
    use React\Socket\UnixConnector;
    use Evenement\EventEmitter;

    /**
     * The logic to connect to neovim instance and send communicate
    */
    class Session extends EventEmitter
    {
        const TCP_SCHEME = 0;
        const UNIX_SCHEME = 1;

        /**
         * @var React\Promise $promise
         */
        protected $promise;

        protected $eventLoop;

        public $uri;

        /**
         * @var React\Connection $connection
         */
        public $connection;

        public $transport;

        public function __construct()
        {
            $this->eventLoop = Factory::create();
        }

        /**
         * @param String $uri
         *
         * @return React\Promise
         */
        protected function tcp($uri)
        {
            $connector = new TcpConnector($this->eventLoop);
            return $connector->connect($uri);
        }

        /**
         * @param String $uri
         *
         * @return React\Promise
         */
        protected function unix($uri)
        {
            $connector = new UnixConnector($this->eventLoop);
            return $connector->connect($uri);
        }

        /**
         * @param String $uri
         */
        public function connect($uri=null)
        {
            if (!is_null($uri))
            {
                $this->uri = $uri;
            }
            else
            {
                if (key_exists("NVIM_LISTEN_ADDRESS", $_ENV))
                {
                    $this->uri = $_ENV["NVIM_LISTEN_ADDRESS"];
                }
                else
                {
                    throw new \Exception("Unknown URL Address to connect to Neovim");
                }
            }

            $parsedUrl = parse_url($this->uri);

            if (
                key_exists("scheme", $parsedUrl) && $parsedUrl["scheme"] == "tcp" ||
                key_exists("host", $parsedUrl)
            )
            {
                $defered = $this->tcp($this->uri);
            }
            else if (
                key_exists("scheme", $parsedUrl) && $parsedUrl["scheme"] == "unix" ||
                key_exists("path", $parsedUrl)
            )
            {
                $defered = $this->unix($this->uri);
            }
            else
            {
                throw new \Exception("Bad URI : " . $this->uri);
            }

            $this->promise = $defered->then(
                function (ConnectionInterface $connectionStream)
                {
                    $this->transport = new Transport($connectionStream); 


                    $connectionStream->on('data',function($data) 
                    {
                        $this->transport->handleData($data);
                    });

                    $connectionStream->on('error', function (\Exception $e) {
                        echo 'error: ' . $e->getMessage();
                    });


                    $this->emit("open");
                }
            )->otherwise(function ($reason)
            {
                echo $reason;
            });
        }

        public function run()
        {
            $this->eventLoop->run();
        }

        public function stop()
        {
            $this->eventLoop->stop();
        }
    }

