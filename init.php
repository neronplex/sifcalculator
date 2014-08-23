<?php
// lib.phpの呼び出し
require_once (dirname(__FILE__).'/calculator.php');

$controller = (new Controller)->calculator($_POST)->echoJSON();
