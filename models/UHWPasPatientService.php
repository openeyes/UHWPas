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
class UHWPasPatientService
{

    /**
     * Called after a patient is retrieved via a search.
     *
     * Update the necessary tables with the time and date the patient
     * was viewed.
     *
     * @param type $patient the patient being viewed.
     */
    public function search($params)
    {
        $patient = $params['patient'];
        $nhsnum = $patient->nhs_num;
        $hosnum = $patient->hos_num;
        // OELog::log("\nHOSNUM " . $hosnum . "'\nNHSNUM " . $nhsnum);

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
        // OELog::log("\n'pas_search_on_hosnum'=" . Yii::app()->params['pas_search_on_hosnum']);
        // OELog::log("\nString lenth to search= " . strlen($toSearch));
        if (isset($toSearch)) {
            if (strlen($toSearch) < 10) {
                $patient = Patient::model()->find("hos_num=\"" . $toSearch . "\"");
                // OELog::log("\nSearched for hos_num, returned: " . print_r($patient, true));
            } else {
                $patient = Patient::model()->find("nhs_num=\"" . $toSearch . "\"");
                // OELog::log("\nSearched for nhs_num, returned: " . print_r($patient, true));
            }
            if ($patient == null) {
                OELog::log("\nPatient does not exist: " . $toSearch);
                $patient = $this->createNewPatient();
                $isNewPatient = true;
            } else {
                OELog::log("\nPatient DOES exist: " . $toSearch);
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
            // OELog::log("\ndataStr " . $dataStr);
            $out = ""; // data read from socket
            $data = ""; // data built up as read from socket
            $ret = socket_write($socket, $dataStr, strlen($dataStr));
            while ($out = socket_read($socket, 2048)) {
                $data .= $out;
                if (strpos($out, "\n") !== false && strpos($out, "0") !== true) {
                    break;
                }
            }
            $json = json_decode($data, true);
            OELog::log("Returned Data = \n" . print_r($json, true));
            // set hos num implies we have a patient:
            if (!isset($json["hos_num"]) && !isset($json["nhs_num"])) {
                socket_close($socket);
                OELog::log("\nNo NHS or hospital number found in the return data set!");
                return;
            }
            $attrs = $this->getAttributes($json);
            OELog::log("\nattrs: \n" . print_r($attrs, true));

            if (isset($attrs['hos_num']) && isset($attrs['nhs_num']) && $attrs['first_name'] && $attrs['last_name'] && $attrs['gender'] && $attrs['dob'] && $attrs['address1'] && $attrs['postcode']) {
                OELog::log("\nUpdating patient data");
                $patient->contact = $this->updateContact($patient->contact, $attrs['first_name'], $attrs['last_name'], $attrs['title']);
                $address = $patient->contact->homeAddress;
                if (!isset($address)) {
                    OELog::log("\nCreating new Address record:\n");
                    $address = new Address();
                }
                $this->updateAddress($address, $attrs['address1'], $attrs['address2'], $attrs['city'], $attrs['county'], $attrs['postcode'], $patient->contact->id);
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
    private function createNewPatient()
    {
        OELog::log("Creating a new patient record");
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
    private function updateContact($contact, $first_name, $last_name, $title)
    {
        $contact->first_name = $first_name;
        $contact->last_name = $last_name;
        if (!empty($title)) {
            $contact->title = $title;
        }
        $contact->save(false);
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
    private function updateAddress($address, $address1, $address2, $city, $county, $postcode, $contact_id)
    {
        $address->address1 = $address1;
        if ($address2) {
            $address->address2 = $address2;
        }
        $address->city = $city;
        $address->county = $county;
        $address->postcode = $postcode;
        $address->country_id = 1;
        $address->contact_id = $contact_id;
        $address->save(false);
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
    private function updatePatient($patient, $dob, $nhsnum, $hosnum, $gender, $gpcode)
    {
        $patient->dob = $dob;
        $patient->nhs_num = $nhsnum;
        $patient->hos_num = $hosnum;
        $patient->gender = $gender;
        $patient->contact_id = $patient->contact->id;
        $gp = Gp::model()->findByAttributes(array('nat_id' => $gpcode));
        if ($gp) {
            $patient->gp_id = $gp->id;
        }
        $patient->save();
    }

    private function getAttributes($json)
    {
        $attrs = array();
        $attrs['hos_num'] = $json["hos_num"] ?? "unknown";
        $attrs['nhs_num'] = $json['nhs_num'] ?? "unknown";
        $attrs['title'] = $json['title'] ?? null;
        $attrs['first_name'] = $json["first_name"];
        $attrs['last_name'] = $json["last_name"];
        $attrs['gender'] = $json["gender"];
        $attrs['dob'] = $json["dob"];
        $attrs['address1'] = $json["address1"];
        $attrs['address2'] = $json["address2"] ?? null;
        $attrs['city'] = $json["city"] ?? null;
        $attrs['county'] = $json["county"] ?? null;
        $attrs['postcode'] = $json["postcode"] ?? null;
        $attrs['gpcode'] = $json["gpcode"] ?? null;

        return $attrs;
    }
}
