<?php
namespace DigitalStars;

class SimpleAPI {
    private $data;

    public function __construct() {
        if(!isset($this->data['module']))
            $this->exit(['error' => 'missed params']);
        $this->data = $_POST + $_GET;
    }

    public function module($name, $params, $anon) {
        if($this->data['module'] == $name & $this->array_keys_exist($params))
            $anon($this->data);
        else
            $this->exit(['error' => 'missed params']);
    }

    public function exit($array) {
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
