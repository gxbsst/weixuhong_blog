<?php


namespace app\models;

// require dirname(__DIR__) . '/traits/PropertyExtract.php';

/**
* 
*/
class WPObject 
{
	use \app\traits\PropertyExtract;

	public $ID;
	public $title;
	public $content;
	public $status;
	public $type;
	public $date;

	function __construct($wp_post_array)
	{
		$this->extract($wp_post_array, [
			'key' => function($k, $v) {
				return (substr($k, 0, 5) === "post_") ? substr($k, 5) :$k;
			}
			]);
	}

	public static function find($args) {
		if (is_numeric($args)) {
			return static::find_one($args);
		} else if(is_array($args)){
			return static::find_many($args);
		}
	}

	protected static function find_one($id){
		$object = get_post( $id );
		$wpobject = new static($object);
		return $wpobject;
	}

	protected static function find_many($args) {
		return array_map(function($object){ 
			return new static($object);
		}, get_posts($args));
	}


	public static function create($data)
	{
		unset($data['ID']);
		$object = static::find_one(wp_insert_post($data ));
		return $object;
	}

	public function update($data) {
		foreach ($data as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		$data['ID'] = $this->ID;
		wp_update_post( $data );
		return $this;
	}

	public function delete() {
		wp_delete_post( $this->ID );
		return $this; 
	}
}