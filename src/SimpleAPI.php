<?php

namespace DigitalStars;

class SimpleAPI {
    private $data = [];
    private $flag = 0;
    public $answer = [];
    public $module = '';

    public function __construct() {
        $this->data = $_POST + $_GET;
        if(isset($this->data['json_data']))
            $this->data = json_decode($this->data['json_data'], true);

        if (!isset($this->data['module'])) {
            $this->answer['error'] = 'missed module';
            exit();
        } else
            $this->module = $this->data['module'];
    }

    public function __destruct() {
        header('Content-Type: application/json');
        exit(json_encode($this->answer));
    }

    public function error($text) {
        $this->answer['error'] = $text;
        exit();
    }

    public function params($params) {
        if(!$this->flag) {
            $this->flag = 1;
            if ($this->array_keys_exist($params))
                return true;
            else {
                $this->answer['error'] = 'missed params';
                exit();
            }
        } else
            exit();
    }

    private function array_keys_exist($keys) {
        foreach ($keys as $key) {
            if ($key{0} == '?')
                continue;
            if (!array_key_exists($key, $this->data) | !isset($this->data[$key]))
                return false;
        }
        return true;
    }
}
