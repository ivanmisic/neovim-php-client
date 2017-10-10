<?php
    namespace NeovimPhpClient;

    class ApiFunction
    {
        public $fname;

        /**
         * '''' array() => [0] {"name", "type"}
         * @var Array  $params
         */
        private $params = array();

        public $deprecatedSince = null;
        public $method = null;
        public $returnType = null;
        public $since = null;

        public function __construct($fname, array $params=array())
        {
            $this->fname = $fname;
            $this->params = $params;
        }

        public function getName()
        {
            return $this->fname;
        }

        /**
         * @return array Format used for message pack
         */
        public function getParams()
        {
            $params = array();

            foreach($this->params as $param)
            {
                $params[$param['id']] = $param['value'];
            }
            return $params;
        }

        public function setParam($name, $value)
        {
            foreach($this->params as &$param)
            {
                if ($param['name'] == $name)
                {
                    $param['value'] = $value;
                    break;
                }
            }
        }

        /**
         * @param array or null $params
         */
        public function setParams($params)
        {
            if (isset($params))
            {
                foreach($params as $param)
                {
                    assert (key_exists('name', $param) && key_exists('value', $param));
                    $this->setParam($param['name'], $param['value']);
                }
            }
        }

        public function parseApiPayload($payload)
        {
            if (key_exists('deprecated_since', $payload))
                $this->deprecatedSince = $payload['deprecated_since'];
            if (key_exists('method', $payload))
                $this->method = $payload['method'];
            if (key_exists('return_type', $payload))
                $this->returnType = $payload['return_type'];
            if (key_exists('since', $payload))
                $this->since = $payload['since'];
            if (key_exists('parameters', $payload))
                $this->addParams($payload['parameters']);
        }

        private function addParams($params)
        {
            for($i=0; $i < count($params); $i++)
            {
                $this->params[] = array(
                    "id" => $i,
                    "type" => $params[$i][0],
                    "name" => $params[$i][1],
                    "value" => null
                );

            }
        }

        public function isParamsFilled()
        {
            $isFilled = true;
            foreach($this->params as $param)
            {
                if (!isset($param['value']))
                {
                    $isFilled = false;
                    break;
                }
            }
            return $isFilled;
        }

    }

