<?php
    namespace NeovimPhpClient;

    use MessagePack\Packer;
    use MessagePack\BufferUnpacker;

    class Transport
    {
        const REQUEST = 0;
        const NOTIFICATION = 1;

        protected $requestId = 0;

        protected $lastRequestId = 0;

        public $api;

        public $handleRequests = array();

        public function __construct($connection)
        {
            $this->connection = $connection;
            $this->api = new Api($this);
        }

        /**
         * @param ApiFunction $apifun
         */
        protected function packApiMessage($apifun, $requestType)
        {
            $packer = new Packer();
            return $packer->pack(
                array(
                    $requestType,
                    $this->requestId,
                    $apifun->getName(),
                    $apifun->getParams()
                )
            );
        }

        /**
         * Same as request but doesn't wait on the response
         */
        // @TODO In progress
        public function notification(ApiFunction $apifun)
        {
            $this->request($apifun, null, self::NOTIFICATION);
        }

        public function handleData($data)
        {
            $unpacker = new BufferUnpacker();
            $unpacker->append($data);
            $unpackedBlocks = $unpacker->tryUnpack();

            $endIt = false;
            foreach($unpackedBlocks as $ub)
            {
                // $ub[1] - requestId
                $this->handleRequests[$ub[1]]($ub);

                if ($this->lastRequestId == $ub[1])
                {
                    $endIt = true;
                }

                unset($this->handleRequests[$ub[1]]);
            }

            if ($endIt)
            {
                $this->connection->end();
            }
        }

        public function request(
            ApiFunction $apifun,
            callable $onData=null,
            $requestType=self::REQUEST
        )
        {
            if ($this->requestId == 1 << 32) $this->requestId = 0;
            $this->requestId += 1;


            $this->lastRequestId = $this->requestId;

            $this->handleRequests[$this->requestId] = $onData;

            $packedMessage = $this->packApiMessage($apifun, $requestType);
            $this->connection->write($packedMessage);
        }

        public function genApi(){

            $handleData = function($response)
            {
                $this->api->generateMethods($response);
            };

            $this->request(new ApiFunction('nvim_get_api_info'), $handleData);

        }

        public function close()
        {
            $this->connection->close();
        }

    }

