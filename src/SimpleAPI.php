<?php
namespace DigitalStar;

class SimpleAPI {
    private $data = [];
    public function __construct() {
        $this->data = file_get_contents('php://input');
    }
}