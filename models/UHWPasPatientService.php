<?php

/**
 * This is the model class for table "ophcosdpro_session_data".
 *
 * The followings are the available columns in table 'ophcosdpro_session_data':
 * @property string $id
 * @property string $last_view_time
 * @property string $user_id
 * @property string $ip_address
 * @property string $last_modified_user_id
 * @property string $last_modified_date
 * @property string $created_user_id
 * @property string $created_date
 *
 * The followings are the available model relations:
 * @property User $lastModifiedUser
 * @property User $createdUser
 * @property User $user
 */
class UHWPasPatientService {

    /**
     * Called after a patient is retrieved via a search.
     * 
     * Update the necessary tables with the time and date the patient
     * was viewed.
     * 
     * @param type $patient the patient being viewed.
     */
    public function search($params) {
        $patient = $params['patient'];
        $nhsnum = $patient->nhs_num;
        $hosnum = $patient->hos_num;
OELog::log("HOSNUM " . $hosnum);
OELog::log("NHSNUM " . $nhsnum);
//OELog::log("params " . print_r($params));
	$toSearch = $nhsnum;
	if (isset($hosnum) && strlen($nhsnum) < 10) {
		$toSearch = $hosnum;
	}
	// ??????
	//else return new Patient();
        //?????????


	/* New patients (those not already in the local DB) are automatically
         * saved. Patients with existing local DB entries are only entered
         * if the pas update value is set. */
        $isNewPatient = false;

/*        if (strlen($toSearch) == 10 && isset($toSearch)) {
            $patient = Patient::model()->find("hos_num=\"" . $toSearch . "\"");
            if ($patient == null) {
                $patient = $this->createNewPatient();
		$patient->contact = new Contact();
	        $patient->contact->homeAddress = new Address();
                $isNewPatient = true;
OELog::log("Patient does not exist: " . $toSearch);
            } else {
OELog::log("Patient DOES exist: " . $toSearch);
OELog::log("pas_update_on_nhsnum=" . (Yii::app()->params['pas_update_on_nhsnum'] == 'false'));
//            } else if (!$isNewPatient && Yii::app()->params['pas_update_on_nhsnum'] == 'false') {
                // the patient exists, we do not want to update the details
                // so do no PAS search
                return $patient;
            }
        }
*/
	OELog::log("A " . (Yii::app()->params['pas_search_on_hosnum'] == 'true'));
	OELog::log("B " . strlen($toSearch));
	OELog::log("C " . isset($toSearch));
	if (isset($toSearch)) {
	    if(strlen($toSearch) <10){
            	$patient = Patient::model()->find("hos_num=\"" . $toSearch . "\"");
	    }else
	    {
	   	$patient = Patient::model()->find("nhs_num=\"" . $toSearch . "\"");
	    }
            if ($patient == null) {
                $patient = $this->createNewPatient();
                $isNewPatient = true;
OELog::log("Patient does not exist: " . $toSearch);
            } else {
OELog::log("Patient DOES exist: " . $toSearch);
                // the patient exists, we do not want to update the details
                // so do no PAS search
                //return $patient;
           }
        }
        $age = $patient->dob;

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $host = Yii::app()->params['pas_client_host'];
        $port = Yii::app()->params['pas_client_port'];
        if (!socket_connect($socket, $host, $port)) {
            // don't do anything
        } else {
            $dataStr = $toSearch . "\n";
OELog::log("dataStr " . $dataStr);
            $out = ""; // data read from socket
            $data = ""; // data built up as read from socket
            $ret = socket_write($socket, $dataStr, strlen($dataStr));
            while ($out = socket_read($socket, 2048)) {
                $data .= $out;
                if (strpos($out, "\n") !== false && strpos($out, "0") !== true)
                    break;
            }
            $json = json_decode($data, true);
//var_dump($json) . PHP_EOL;
            // set hos num implies we have a patient:
            if (!isset($json["hos_num"]) && !isset($json["nhs_num"])) {
                socket_close($socket);
		OELog::log("No NHS or hospital number found in the return data set!");
                return;
            }
            $attrs = $this->getAttributes($json);
//echo var_dump($attrs) . PHP_EOL;
	OELog::log("attrs: ".print_r($attrs, true));

            if (isset($attrs['hos_num']) && isset($attrs['nhs_num']) && $attrs['first_name'] && $attrs['last_name'] && $attrs['gender'] && $attrs['dob'] && $attrs['address1'] && $attrs['postcode']) {
		OELog::log("Updating patient data");
                $patient->contact = $this->updateContact($patient->contact, $attrs['first_name'], $attrs['last_name'], $attrs['title']);
                if (!($address = $patient->contact->homeAddress)) {
                    $address = new Address();
                }
                $this->updateAddress($address, $attrs['address1'], $attrs['address2'], $attrs['city'], $attrs['county'], $attrs['postcode'], $patient->contact->id);
                #$patient->gp = Gp::model()->findByAttributes(array('nat_id' => $attrs['gpcode']));
		#var_dump($patient->gp); die;
		$this->updatePatient($patient, $attrs['dob'], $attrs['nhs_num'], $attrs['hos_num'], $attrs['gender'], $attrs['gpcode']);
            }
            socket_close($socket);
        }
    }

    /**
     * 
     * @param type $nhsnum
     * @return \Patient
     */
    private function createNewPatient() {
        // instantiate a new patient to add values to:
        $patient = new Patient();
        //$contact = new Contact();
        //$address = new Address();
        $patient->contact = new Contact();
        $patient->contact->homeAddress = new Address();
        return $patient;
    }

    /**
     * 
     * @param type $first_name
     * @param type $last_name
     * @param type $title
     * @return \Contact
     */
    private function updateContact($contact, $first_name, $last_name, $title) {
        $contact->first_name = $first_name;
        $contact->last_name = $last_name;
        if ($title) {
            $contact->title = $title;
        }
        $contact->save();
        return $contact;
    }

    /**
     * 
     * @param type $address1
     * @param type $address2
     * @param type $city
     * @param type $county
     * @param type $postcode
     * @param type $contact_id
     */
    private function updateAddress($address, $address1, $address2, $city, $county, $postcode, $contact_id) {
        $address->address1 = $address1;
        if ($address2) {
            $address->address2 = $address2;
        }
        $address->city = $city;
        $address->county = $county;
        $address->postcode = $postcode;
        $address->country_id = 1;
        $address->contact_id = $contact_id;
        $address->save();
    }

    /**
     * 
     * @param type $patient
     * @param type $dob
     * @param type $nhsnum
     * @param type $hosnum
     * @param type $gender
     * @param type $contact
     */
    private function updatePatient($patient, $dob, $nhsnum, $hosnum, $gender, $gpcode) {
        $patient->dob = $dob;
        $patient->nhs_num = $nhsnum;
        $patient->hos_num = $hosnum;
        $patient->gender = $gender;
        $patient->contact_id = $patient->contact->id;
	$gp =  Gp::model()->findByAttributes(array('nat_id' => $gpcode));
	if($gp){
		$patient->gp_id = $gp->id;
	}
        $patient->save();
    }

    private function getAttributes($json) {
        $attrs = array();
        if(isset($json['hos_num']))
	{ 
		$attrs['hos_num'] = $json["hos_num"];
	}else
	{
		$attrs['hos_num'] = "unknown";
	}
        if(isset ($json['nhs_num']))
	{
	 	$attrs['nhs_num'] = $json["nhs_num"];
	}else
	{
		$attrs['nhs_num'] = "unknown";
	}
        if (isset($json["title"])) {
            $attrs['title'] = $json["title"];
        } else {
            $attrs['title'] = null;
        }
        $attrs['first_name'] = $json["first_name"];
        $attrs['last_name'] = $json["last_name"];
        $attrs['gender'] = $json["gender"];
        $attrs['dob'] = $json["dob"];

        if (isset($json['address1'])) {
	        $attrs['address1'] = $json["address1"];
	}
        if (isset($json["address2"])) {
            $attrs['address2'] = $json["address2"];
        } else {
            $attrs['address2'] = null;
        }
        if (isset($json["city"])) {
            $attrs['city'] = $json["city"];
        } else {
            $attrs['city'] = null;
        }
        if (isset($json["county"])) {
            $attrs['county'] = $json["county"];
        } else {
            $attrs['county'] = null;
        }
        if (isset($json["postcode"])) {
            $attrs['postcode'] = $json["postcode"];
        } else {
            $attrs['postcode'] = null;
        }
	if (isset($json["gpcode"])){
		$attrs['gpcode'] = $json["gpcode"];
	}else{
		$attrs['gpcode'] = null;
	}
        return $attrs;
    }

}
