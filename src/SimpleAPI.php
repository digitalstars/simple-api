<?php
namespace DigitalStars;

class SimpleAPI {
    private $data = [];
    public $answer = [];

    public function __construct() {
        $this->data = $_POST + $_GET;
        if(!isset($this->data['module']))
            $this->exit(['error' => 'missed params']);
    }

    public function __destruct() {
        $this->exit($this->answer);
    }

    public function module($name, $params, $anon) {
        if($this->data['module'] == $name) {
            if ($this->array_keys_exist($params)) {
                $anon($this->data);
                exit();
            }
            else
                $this->exit(['error' => 'missed params']);
        }
    }

    private function exit($array) {
        header('Content-Type: application/json');
        exit(json_encode($array));
    }

    private function array_keys_exist($keys){
        foreach($keys as $key){
            if($key{0} == '?')
                continue;
            if(!array_key_exists($key, $this->data))
                return false;
        }
        return true;
    }
}
