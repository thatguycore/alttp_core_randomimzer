<?php
/**
 * Shuffle the contents of an array using mt_rand
 *
 * @param array $array array to shuffle
 *
 * @return array
 */
function mt_shuffle(array $array) {
	$new_array = [];
	while(count($array)) {
		$pull_key = mt_rand(0, count($array) - 1);
		$new_array = array_merge($new_array, array_splice($array, $pull_key, 1));
	}
	return $new_array;
}

/**
 * Sort array by key recursively (this is basically an extension of ksort)
 * @see http://php.net/manual/en/function.ksort.php
 *
 * @param array &$array array to sort
 * @param int $sort_flags optional sort flags see PHP's sort() function for details
 *
 * @return bool
 */
function ksortr(array &$array, int $sort_flags = SORT_REGULAR) {
	if (!is_array($array)) {
		return false;
	}
	ksort($array, $sort_flags);

	foreach ($array as &$sub_array) {
		if (is_array($sub_array)) {
			ksortr($sub_array, $sort_flags);
		}
	}

	return true;
}

/**
 * Take our patch format and merge it down to a more compact version
 *
 * @param array $patch_left Left side of patch
 * @param array $patch_right patch to add to left side
 *
 * @return array
 */
function patch_merge_minify(array $patch_left, array $patch_right = []) {
	$write_array = [];
	// decompose left
	foreach ($patch_left as $wri) {
		foreach ($wri as $seek => $bytes) {
			for ($i = 0; $i < count($bytes); $i++) {
				$write_array[$seek + $i] = [$bytes[$i]];
			}
		}
	}
	// decompose right and overwrite
	foreach ($patch_right as $wri) {
		foreach ($wri as $seek => $bytes) {
			for ($i = 0; $i < count($bytes); $i++) {
				$write_array[$seek + $i] = [$bytes[$i]];
			}
		}
	}
	$out = $write_array;
	ksort($out);

	$backwards = array_reverse($out, true);

	// merge down
	foreach ($backwards as $off => $value) {
		if (isset($backwards[$off - 1])) {
			$backwards[$off - 1] = array_merge($backwards[$off - 1], $backwards[$off]);
			unset($backwards[$off]);
		}
	}

	$forwards = array_reverse($backwards, true);

	array_walk($forwards, function(&$write, $address) {
		$write = [$address => $write];
	});

	return array_values($forwards);
}
