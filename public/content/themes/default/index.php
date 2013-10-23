<?php
$args = ['post_type' => 'post'];
$payload = \app\models\WPObject::find($args);
var_dump($payload);