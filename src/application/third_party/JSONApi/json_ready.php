<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 4/10/19
 * Time: 5:39 PM
 */

namespace JSONApi;


abstract class json_ready
{
    /**
     * @var array
     */
    protected $options;

    function json_data()
    {
        $return = [];
        $options = Document::create()->options;
        foreach ($this as $label=>$data) {

            //echo "$label - ".get_class($this)."\n";

            // skip options; shoul not be printed
            if($label=="options")
                continue;

            // skip links when links output is disabled
            if($label=="links" && isset($options["nolinks"]) && $options["nolinks"])
                continue;

            switch (gettype($data)) {
                case "object":

                    if(method_exists($data,"json_data"))
                        $return[$label] = $data->json_data();
                    else
                        $return[$label] = $data;

                    break;
                case "array":
                    $return[$label] = [];
                    foreach ($data as $item) {

                        if (method_exists($item, "json_data"))
                            $return[$label][] = $item->json_data();
                        else
                            $return[$label][] = $item;

                    }

                    break;
                default:
                    //if(isset($data))
                    $return[$label] = $data;
            }
        }
        return $return;
    }

}