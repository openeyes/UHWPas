<?php

/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
$config = array(
    'import' => array(
        'application.modules.UHWPas.*',
//        'application.modules.UHWPas.components.*',
        'application.modules.UHWPas.models.*',
    ),
    'components' => array(
        'event' => array(
            //'class' => 'OEEventManager',
            'observers' => array(
                'patient_search_criteria' => array(
                    'patient_search_criteria' => array(
                        'class' => 'UHWPasPatientService',
                        'method' => 'search',
                    ),
                ),
            ),
        ),
    ),
    'params' => array(
        'pas_client_host' => !empty(getenv('UHWPAS_HOST')) ? getenv('UHWPAS_HOST') : 'localhost',
        'pas_client_port' => !empty(getenv('UHWPAS_PORT')) ? getenv('UHWPAS_PORT') : '9991',
        /* Set to true to search for patients on a NHS number. Failed
         * (local DB) searches will then try to find the patient by a PAS
         * search using the NHS number. */
        'pas_search_on_nhsnum' => 'true',
        /* Set to true to update patients on NHS number, if they exist
         * locally on a search (DB). Only used if pas_search_on_nhsnum
         * is true. */
        'pas_update_on_nhsnum' => '1',
        /* Set to true to search for patients on hospital number. Failed
         * (local DB) searches will then try to find the patient by a PAS
         * search, using the hospital number. */
        'pas_search_on_hosnum' => '1',
        /* Set to true to update patients on hospital number, if they exist
         * locally on a search (DB). Only used if pas_search_on_hosnum
         * is true. */
        'pas_update_on_hosnum' => '1',
    ),
);

return $config;

