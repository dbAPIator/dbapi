<?php
/**
 * @param $data
 * @return string
 */
function to_php_code($data,$addBegining=false)
{
//    $json = json_encode($data,JSON_PRETTY_PRINT);
//    print_r($json);
//    $str =  preg_replace(["/\{/","/\}/","/\:/"],["[","]","=>"],$json).";";
//    $str = str_replace('"',"'",$str);

    return ($addBegining ? "<?php\nreturn " : "").var_export($data,true).";";
}

/**
 * @param $db_struct
 * @param $target_struct
 * @return array
 */
function compute_struct_diff($db_struct, $target_struct) {
    if(is_array($db_struct) xor is_array($target_struct)) {
        return $target_struct;
    }
    if(!is_array($target_struct)) {
        return $target_struct;
    }

    $diff = [];
    foreach ($target_struct as $key=>$val) {
        if(!isset($db_struct[$key])) {
            $diff[$key] = $val;
            continue;
        }
        if($val==$db_struct[$key]) {
            continue;
        }
        $diff[$key] = compute_struct_diff($db_struct[$key],$val);
    }
    return $diff;
}


/**
 * @param $arr1
 * @param $arr2
 * @return bool
 */
function smart_array_merge_recursive($arr1,$arr2) {
    if(!is_array($arr1) || !is_array($arr2) )
        return $arr1;

    foreach ($arr2 as $key=>$val) {
        if(is_null($val)) {
            unset($arr1[$key]);
            continue;
        }
        if(!array_key_exists($key,$arr1)) {
            $arr1[$key] = $val;
            continue;
        }
        if(is_array($val) && is_array($arr1[$key])) {
            $arr1[$key] = smart_array_merge_recursive($arr1[$key],$val);
            continue;
        }
        $arr1[$key] = $val;

    }
    return  $arr1;
}

function remove_dir_recursive($fsEntry) {
    if(!is_dir($fsEntry)) {
        if(!unlink($fsEntry)) throw new Exception("Could not remove $fsEntry");
        return true;
    }

    $dir = opendir($fsEntry);
    while ($entry = readdir($dir)) {
        if(in_array($entry,[".",".."])) continue;
        remove_dir_recursive($fsEntry."/".$entry);
    }
    closedir($dir);
    if(!rmdir($fsEntry)) throw new Exception("Could not remove $fsEntry");
    return true;
}

/**
 * Recursively sanitizes array values to prevent code injection
 * @param mixed $data Input array or value to sanitize
 * @return mixed Sanitized array or value
 */
function sanitize_data($data) {
    if (is_array($data)) {
        $clean = [];
        foreach ($data as $key => $value) {
            // Sanitize the key
            $clean_key = htmlspecialchars(strip_tags($key), ENT_QUOTES, 'UTF-8');
            // Recursively sanitize the value
            $clean[$clean_key] = sanitize_data($value);
        }
        return $clean;
    } else {
        // For non-array values, apply sanitization
        if (is_string($data)) {
            // Remove any potential PHP tags
            $data = preg_replace('/<\?(php)?|\?>/', '', $data);
            // Convert special characters to HTML entities
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
        // Return non-string values unchanged
        return $data;
    }
}


function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
