<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Config $config
 * @property CI_Loader $load
 * @property CI_Input $input
 * @property Utilities $utilities
 */
trait DbapiInputTrait
{
    private function get_input_data($input=null,bool $no_validation=false)
    {
        if(!is_null($input)) {
            return  $input;
        }

        if(!isset($_SERVER["CONTENT_TYPE"])) {
            throw new Exception("Missing Content-Type",400);
        }

        $contentType = explode(";",$_SERVER["CONTENT_TYPE"]);

        if(in_array("application/x-www-form-urlencoded",$contentType)) {
            $inputData = $this->input->post();
        }
        elseif(in_array("application/vnd.api+json",$contentType)) {
            $inputData = json_decode($this->input->raw_input_stream);
        }
        elseif(in_array("application/json",$contentType)) {
            $inputData = json_decode($this->input->raw_input_stream);
        }
        else {
            $inputData = json_decode($this->input->raw_input_stream);
//            throw new Exception("Invalid Content-Type",400);
        }

        if($no_validation) {
            return $inputData;
        }
        validate_body_data($inputData);
        return $inputData;
    }

    /**
     * Update multiple records with a single call
     * @param string $configName
     * @param string $resourceName
     * @param array|null $paras
     * @throws \dbAPI\API\Exception
     * @todo to be implemented
     */
    private function get_req_parameters($resName, $input=null)
    {
        if(is_null($input))
            $input = $this->input;

        $queryParas = $input->get();

        // get include
        if($input->get("include")) {
            $queryParas["includes"] = $input->get("include");
        }

        if($input->get("filter_advanced")) {
            $this->load->helper("where");
            $queryParas["custom_where"] = $input->get("filter_advanced");
            if(!is_array($queryParas["custom_where"])) {
                $queryParas["custom_where"] = [
                    $resName => $queryParas["custom_where"]
                ];
            }
        }

        // get sparse fieldset fields
        if($flds = $input->get("fields")) {
            if(is_array($flds))
                $queryParas["fields"] = $flds;
        }

        $queryParas["paging"] = [];

        // get paging fieldset fields
        $paging = $input->get("page");
        
        if(isset($paging[0])) {
            $paging[$resName]  = $paging[0];
            if(isset($paging[1])) {
                $paging[$resName] = array_merge($paging[$resName],$paging[1]);
            }
        }

        if(is_array($paging))
            $queryParas["paging"] = $paging;

        if(!isset($queryParas["paging"][$resName])) {
            $queryParas["paging"][$resName] = [];
        }

        foreach ($queryParas["paging"] as $key=>$val) {
            if(isset($val["limit"]) && !preg_match("/^\d+$/",$val["limit"])) {
                unset($queryParas["paging"][$key]["limit"]);
            }

            if(isset($val["offset"]) && !preg_match("/^\d+$/",$val["offset"])) {
                unset($queryParas["paging"][$key]["offset"]);
            }
        }

        if(!isset($queryParas["paging"][$resName]))
            $queryParas["paging"][$resName] = ["offset"=>0];

        // get filter
        if($filterStr = $input->get("filter")) {
            $queryParas["filter"] = get_filter($filterStr, $resName);
        }

        // get sort
        if($sortQry = $input->get("sort"))
            $queryParas["order"] = getSort($sortQry,$resName);

        // get onduplicate behaviour and fields to update
        if($ondupe = $input->get("onduplicate")) {
            if(!in_array($ondupe,["update","ignore","error"]))
                $ondupe = "error";
            $queryParas["onduplicate"] = $ondupe;

            $updateFields = $input->get("update");
            if($ondupe=="update" && $updateFields && is_array($updateFields)) {
                $queryParas["update"] = $updateFields;
            }
        }
        return $queryParas;
    }


    /**
     * Process request GET parameters and build the structure to be used for xtracting the data
     * @param string $fqResName fully qualified resource name
     * @param array|null $inputs
     * @return DBAPIRequest
     * @throws \dbAPI\API\Exception
     */
    private function normalize_object($obj)
    {
        if(isset($obj->relationships)) {
            if(isset($obj->relationships->data)) {
                $obj->relationships = $obj->relationships->data;
            }

            foreach ($obj->relationships as $relName=>$relData) {
                $obj->relationships->$relName = $this->normalize_input_data($relData);
            }
        }
        return $obj;
    }

    private function normalize_input_data($data) {
        if(!$data)
            return $data;

        $resp = $data->data;
        if(is_object($resp)) {
            $resp = $this->normalize_object($resp);
        }

        if(is_array($resp)) {
            for($i=0;$i<count($resp);$i++) {
                $resp[$i] = $this->normalize_object($resp[$i]);
            }
        }
        return $resp;
    }

    /**
     * Insert records recursively
     * @param $configName
     * @param $resourceName
     * @param null $input
     * @return null|void
     * TODO: add some limitation for maximum records to insert at a time
     * @throws Exception
     */
}
