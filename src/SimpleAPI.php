<?php
namespace DigitalStars;

class SimpleAPI {
    private $data = [];
    private $flag = 0;
    public $answer = [];
    public $module = '';
    public $destruct_func = null;

    public function __construct() {
        if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit();
        }

        $this->data = $_POST + $_GET;

        if (!isset($this->data['module'])) {
            $this->data = json_decode(file_get_contents('php://input'), 1);
            if ($this->data != null) {
                if(!isset($this->data['module'])) {
                    $this->error('missed module');
                }
            } else
                $this->error('json invalid');
        }
        $this->module = $this->data['module'];
    }

    public function __destruct() {
        header('Content-Type: application/json');
        if (isset($this->answer['error']))
            http_response_code(501);
        if (is_callable($this->destruct_func))
            call_user_func($this->destruct_func, $this->answer);
        exit(json_encode($this->answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function error($text) {
        $this->answer['error'] = $text;
        exit();
    }

    public function params($params) {
        if(!$this->flag) {
            $this->flag = 1;
            if ($this->array_keys_exist($params))
                return $this->data;
            else
                $this->error('missed params');
        } else
            exit();
    }

    private function array_keys_exist($keys) {
        foreach ($keys as $key) {
            if ($key[0] == '?')
                continue;
            if (!array_key_exists($key, $this->data) | !isset($this->data[$key]))
                return false;
        }
        return true;
    }
}
