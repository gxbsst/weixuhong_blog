<?php


namespace app\traits;



trait PropertyExtract {

	protected function extract($properties, $modifiers = [], $ifExists = true) {
		foreach ((array)$properties as $key => $value) {
			
			if (!empty($modifiers['key']) && is_callable($modifiers['key'])) {
				$key = $modifiers['key']($key, $value);
			}

			if (!empty($modifiers['value']) && is_callable($modifiers['value'])) {
				$key = $modifiers['value']($key, $value);
			}

			if (!$ifExists || ($ifExists && property_exists($this, $key))) {
				$this->$key = $value;
			}
		}
	}
}

