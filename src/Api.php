<?php
    namespace NeovimPhpClient;

    use Evenement\EventEmitter;

    class Api extends EventEmitter
    {
        public $session;
        /**
         * Hosts api function names generated in runtime, received by
         * nvim_get_api_info()
         *
         * @var Array $apiFunctionNames
         */
        public $apiFunctionNames = array();

        /**
         * Hosts api function closures received by nvim_get_api_info()
         *
         * @var Array $apiFunctionClosures
         */
        public $apiFunctionClosures = array();

        /**
         * If api functions are generated it will be ready
         *
         * @var Bool $ready
         */
        public $ready = false;

        public function __construct(&$session)
        {
            $this->session = $session;
            $this->ready = false;
        }

        public function printMethods()
        {
            if ($this->ready)
            {
                foreach ($this->apiFunctionClosures as $fun)
                {
                    $str = $fun->fname;
                    $str .= "   " . (!is_null($fun->deprecatedSince)? "*" : "");
                    echo $str . PHP_EOL;
                }
            }
            else
            {
                echo "Api not yet generated" . PHP_EOL;
            }
        }

        /**
         * @param Array? $payload
         */
        public function generateMethods($payload)
        {
            foreach( $payload[3][1]['functions'] as $fun )
            {
                $this->addMethod($fun);
            }

            $this->ready = true;
            $this->emit("api.ready");
        }

        public function addMethod($function) {

            /*
             * Closure which is invoked when generated api function is called
             */
            $fun = function ($args = null)
            {
                // Obtaining function name hack
                $apiName = array_pop($args);

                $md = $this->apiFunctionClosures[$apiName];
                $md->setParams($args);

                if($md->isParamsFilled())
                {
                    $this->session->request (
                        $md,
                        function($data)
                        {
                            var_dump($data);
                        }
                    );
                }
                else
                {
                    throw new \Exception('No parameters given to ' . $apiName);
                }
            };
            $name = $function['name'];
            $this->apiFunctionNames[$name] = \Closure::bind($fun, $this, get_class());

            $apiFun = new ApiFunction($name);
            $apiFun->parseApiPayload($function);
            $this->apiFunctionClosures[$name] = $apiFun; 
        }

        public function __call($functionName, $args) {
            if(is_callable($this->apiFunctionNames[$functionName]))
            {
                if (!is_null($this->apiFunctionClosures[$functionName]->deprecatedSince))
                {
                    throw new \Exception
                    (
                        'The api function ' . $functionName . ' has been deprecated since ' . 
                        $this->apiFunctionClosures[$functionName]->deprecatedSince
                    );
                }

                array_push($args, $functionName);
                $newArgs = array($args);

                return call_user_func_array($this->apiFunctionNames[$functionName], $newArgs);
            }
        }

    }

