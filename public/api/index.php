<?php


require dirname(dirname(__DIR__)) . '/config/wordpress.php';

// header('Content-type: application/json');

# 定義 slim 的logger
$logger = new \Flynsarmy\SlimMonolog\Log\MonologWriter(array(
	'handlers' => array(
		new \Monolog\Handler\StreamHandler(dirname(dirname(__DIR__)) . '/log/development.log'),
		),
	));

// HOW TO USE LOG
// $api->log->debug(mixed $object);
// $api->log->info(mixed $object);
// $api->log->notice(mixed $object);
// $api->log->warning(mixed $object);
// $api->log->error(mixed $object);
// $api->log->critical(mixed $object);
// $api->log->alert(mixed $object);
// $api->log->emergency(mixed $object);

$api = new \Slim\Slim(array(
	'log.enabled' => true,
	'log.writer' => $logger,
	'debug' => true
	));


$api->get("/:type", function($type) use($api){
	$args = ['post_type' => $type] + $api->request()->get();
	$payload = \app\models\WPObject::find($args);

	echo json_encode($payload);
});

$api->get("/:type/random", function($type) use($api) {
	$args = ['post_type' => $type, 'posts_per_page' => 1, 'orderby' => 'random'] + $api->request()->get();
	$payload = \app\models\WPObject::find($args); 
	echo json_encode($payload);
});

$api->get("/:type/:id", function($type, $id) use($api){
	$payload = \app\models\WPObject::find($id);
	$api->log->warning(json_encode($payload));

	echo json_encode($payload);
});

$api->post("/:type", function($type) use($api) {
	$data = json_decode($api->request()->getBody(), true);
	$data['post_type'] = $type;
	$result = wp_insert_post($data);
	$location = "http://wp.local/api/index.php/{$type}/{$result}";
	$response = $api->response();
	$response->status(201);
	$responsep['Content-Type'] = "application/json";
	echo json_encode(['location' => $location]);
});

$api->map("/:type/:id(/)", function($type,$id) use($api){
	$body = $api->request()->getBody();
	$data = json_decode($body, true);
	$data['ID'] = $id;
	$return  = wp_update_post($data);
	$response = $api->response();
	$response->status(200);
	$responsep['Content-Type'] = "application/json";
	echo json_encode($return);
})->via('PUT', 'PATCH');


$api->delete("/:type/:id", function($type, $id) use($api) {
	$return  = wp_delete_post( $id );
	echo json_encode($return);
});

$api->run();

