<?php

/* Copyright (C) 2006-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012-2015 JF FERRY             <jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


require_once DOL_DOCUMENT_ROOT . '/core/class/DolWS.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';



/**
 * Class for Dolibarr other webservices
 *
 * @author jfefe
 */
class wsOther extends DolWS
{

    /**
     * Constructor method
     * 
     */
    function __construct()
    {
        parent::__construct();
    }


    /**
     * Get Dolibarr version
     * 
     * @global type $db
     * @global type $conf
     * @global type $langs
     * @param type $authentication
     * @return array
     */
    function getVersions($authentication)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getVersions login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters


        if (! $error)
        {
            $objectresp['result']=array('result_code'=>'OK', 'result_label'=>'');
            $objectresp['dolibarr']=version_dolibarr();
            $objectresp['os']=version_os();
            $objectresp['php']=version_php();
            $objectresp['webserver']=version_webserver();
        }

        if ($error)
        {
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }

    /**
     * Method to get a document by webservice
     *
     * @param 	array	$authentication		Array with permissions
     * @param 	string	$modulepart		 	Properties of document
     * @param	string	$file				Relative path
     * @param	string	$refname			Ref of object to check permission for external users (autodetect if not provided)
     * @return	void
     */
    function getDocument($authentication, $modulepart, $file, $refname='')
    {
        global $db,$conf,$langs,$mysoc;

        dol_syslog("Function: getDocument login=".$authentication['login'].' - modulepart='.$modulepart.' - file='.$file);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;

        // Properties of doc
        $original_file = $file;
        $type=dol_mimetype($original_file);
        //$relativefilepath = $ref . "/";
        //$relativepath = $relativefilepath . $ref.'.pdf';

        $accessallowed=0;

        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if ($fuser->societe_id) $socid=$fuser->societe_id;

        // Check parameters
        if (! $error && ( ! $file || ! $modulepart ) )
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter file and modulepart must be both provided.";
        }

        if (! $error)
        {
            $fuser->getrights();

            // Suppression de la chaine de caractere ../ dans $original_file
            $original_file = str_replace("../","/", $original_file);

            // find the subdirectory name as the reference
            if (empty($refname)) $refname=basename(dirname($original_file)."/");

            // Security check
            $check_access = dol_check_secure_access_document($modulepart,$original_file,$conf->entity,$fuser,$refname);
            $accessallowed              = $check_access['accessallowed'];
            $sqlprotectagainstexternals = $check_access['sqlprotectagainstexternals'];
            $original_file              = $check_access['original_file'];

            // Basic protection (against external users only)
            if ($fuser->societe_id > 0)
            {
                if ($sqlprotectagainstexternals)
                {
                    $resql = $db->query($sqlprotectagainstexternals);
                    if ($resql)
                    {
                        $num=$db->num_rows($resql);
                        $i=0;
                        while ($i < $num)
                        {
                            $obj = $db->fetch_object($resql);
                            if ($fuser->societe_id != $obj->fk_soc)
                            {
                                $accessallowed=0;
                                break;
                            }
                            $i++;
                        }
                    }
                }
            }

            // Security:
            // Limite acces si droits non corrects
            if (! $accessallowed)
            {
                $errorcode='NOT_PERMITTED';
                $errorlabel='Access not allowed';
                $error++;
            }

            // Security:
            // On interdit les remontees de repertoire ainsi que les pipe dans
            // les noms de fichiers.
            if (preg_match('/\.\./',$original_file) || preg_match('/[<>|]/',$original_file))
            {
                dol_syslog("Refused to deliver file ".$original_file);
                $errorcode='REFUSED';
                $errorlabel='';
                $error++;
            }

            clearstatcache();

            if(!$error)
            {
                if(file_exists($original_file))
                {
                    dol_syslog("Function: getDocument $original_file $filename content-type=$type");

                    $file=$fileparams['fullname'];
                    $filename = basename($file);

                    $f = fopen($original_file,'r');
                    $content_file = fread($f,filesize($original_file));

                    $objectret = array(
                        'filename' => basename($original_file),
                        'mimetype' => dol_mimetype($original_file),
                        'content' => base64_encode($content_file),
                        'length' => filesize($original_file)
                    );

                    // Create return object
                    $objectresp = array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'document'=>$objectret
                    );
                }
                else
                {
                    dol_syslog("File doesn't exist ".$original_file);
                    $errorcode='NOT_FOUND';
                    $errorlabel='';
                    $error++;
                }
            }
        }

        if ($error)
        {
            $objectresp = array(
            'result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel)
            );
        }

        return $objectresp;
    }
}
