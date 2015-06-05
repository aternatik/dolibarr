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

require_once(DOL_DOCUMENT_ROOT."/comm/action/class/actioncomm.class.php");
require_once(DOL_DOCUMENT_ROOT."/comm/action/class/cactioncomm.class.php");
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';


/**
 * Class for Dolibarr category webservices
 *
 * @author jfefe
 */
class wsActioncomm extends DolWS
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
     * Get ActionComm
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id of object
     * @return	mixed
     */
    function getActionComm($authentication,$id)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getActionComm login=".$authentication['login']." id=".$id);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if ($error || (! $id))
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
        }

        if (! $error)
        {
            $fuser->getrights();

            if ($fuser->rights->agenda->allactions->read)
            {
                $actioncomm=new ActionComm($db);
                $result=$actioncomm->fetch($id);
                if ($result > 0)
                {

                    $actioncomm_result_fields=array(
                            'id' => $actioncomm->id,
                            'ref'=> $actioncomm->ref,
                            'ref_ext'=> $actioncomm->ref_ext,
                            'type_id'=> $actioncomm->type_id,
                            'type_code'=> $actioncomm->type_code,
                            'type'=> $actioncomm->type,
                            'label'=> $actioncomm->label,
                            'datep'=> dol_print_date($actioncomm->datep,'dayhourrfc'),
                            'datef'=> dol_print_date($actioncomm->datef,'dayhourrfc'),
                            'datec'=> dol_print_date($actioncomm->datec,'dayhourrfc'),
                            'datem'=> dol_print_date($actioncomm->datem,'dayhourrfc'),
                            'note'=> $actioncomm->note,
                            'percentage'=> $actioncomm->percentage,
                            'author'=> $actioncomm->author->id,
                            'usermod'=> $actioncomm->usermod->id,
                            'usertodo'=> $actioncomm->usertodo->id,
                            'userdone'=> $actioncomm->userdone->id,
                            'priority'=> $actioncomm->priority,
                            'fulldayevent'=> $actioncomm->fulldayevent,
                            'location'=> $actioncomm->location,
                            'socid'=> $actioncomm->societe->id,
                            'contactid'=> $actioncomm->contact->id,
                            'projectid'=> $actioncomm->fk_project,
                            'fk_element'=> $actioncomm->fk_element,
                            'elementtype'=> $actioncomm->elementtype);

                            //Retreive all extrafield for actioncomm
                            // fetch optionals attributes and labels
                            $extrafields=new ExtraFields($db);
                            $extralabels=$extrafields->fetch_name_optionals_label('actioncomm',true);
                            //Get extrafield values
                            $actioncomm->fetch_optionals($actioncomm->id,$extralabels);

                            foreach($extrafields->attribute_label as $key=>$label)
                            {
                                $actioncomm_result_fields=array_merge($actioncomm_result_fields,array('options_'.$key => $actioncomm->array_options['options_'.$key]));
                            }

                    // Create
                    $objectresp = array(
                        'result'=>parent::array_to_object(array('result_code'=>'OK', 'result_label'=>'')),
                        'actioncomm'=>$actioncomm_result_fields);
                }
                else
                {
                    $error++;
                    $errorcode='NOT_FOUND'; $errorlabel='Object not found for id='.$id.' nor ref='.$ref.' nor ref_ext='.$ref_ext;
                }
            }
            else
            {
                $error++;
                $errorcode='PERMISSION_DENIED'; $errorlabel='User does not have permission for this request';
            }
        }

        if ($error)
        {
            $objectresp = parent::array_to_object(array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel)));
        }

        return $objectresp;
    }


    /**
     * Get getListActionCommType
     *
     * @param	array		$authentication		Array of authentication information
     * @return	mixed
     */
    function getListActionCommType($authentication)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getListActionCommType login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if (! $error)
        {
            $fuser->getrights();

            if ($fuser->rights->agenda->myactions->read)
            {
                $cactioncomm=new CActionComm($db);
                $result=$cactioncomm->liste_array('','code');
                if ($result > 0)
                {
                    $resultarray=array();
                    foreach($cactioncomm->liste_array as $code=>$libeller) {
                        $resultarray[]=array('code'=>$code,'libelle'=>$libeller);
                    }

                     $objectresp = array(
                        'result'=>parent::array_to_object(array('result_code'=>'OK', 'result_label'=>'')),
                        'actioncommtypes'=>$resultarray);

                }
                else
                {
                    $error++;
                    $errorcode='NOT_FOUND'; $errorlabel='Object not found for id='.$id.' nor ref='.$ref.' nor ref_ext='.$ref_ext;
                }
            }
            else
            {
                $error++;
                $errorcode='PERMISSION_DENIED'; $errorlabel='User does not have permission for this request';
            }
        }

        if ($error)
        {
            $objectresp = parent::array_to_object(array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel)));
        }

        return $objectresp;
    }


    /**
     * Create ActionComm
     *
     * @param	array		$authentication		Array of authentication information
     * @param	ActionComm	$actioncomm		    $actioncomm
     * @return	array							Array result
     */
    function createActionComm($authentication,$actioncomm)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: createActionComm login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if (! $error)
        {
            $newobject=new ActionComm($db);

            $newobject->datep=$actioncomm['datep'];
            $newobject->datef=$actioncomm['datef'];
            $newobject->type_code=$actioncomm['type_code'];
            $newobject->societe->id=$actioncomm['socid'];
            $newobject->fk_project=$actioncomm['projectid'];
            $newobject->note=$actioncomm['note'];
            $newobject->contact->id=$actioncomm['contactid'];
            $newobject->usertodo->id=$actioncomm['usertodo'];
            $newobject->userdone->id=$actioncomm['userdone'];
            $newobject->label=$actioncomm['label'];
            $newobject->percentage=$actioncomm['percentage'];
            $newobject->priority=$actioncomm['priority'];
            $newobject->fulldayevent=$actioncomm['fulldayevent'];
            $newobject->location=$actioncomm['location'];
            $newobject->fk_element=$actioncomm['fk_element'];
            $newobject->elementtype=$actioncomm['elementtype'];

            //Retreive all extrafield for actioncomm
            // fetch optionals attributes and labels
            $extrafields=new ExtraFields($db);
            $extralabels=$extrafields->fetch_name_optionals_label('actioncomm',true);
            foreach($extrafields->attribute_label as $key=>$label)
            {
                $key='options_'.$key;
                $newobject->array_options[$key]=$actioncomm[$key];
            }

            $db->begin();

            $result=$newobject->add($fuser);
            if ($result <= 0)
            {
                $error++;
            }

            if (! $error)
            {
                $db->commit();
                $objectresp=array('result'=>array('result_code'=>'OK', 'result_label'=>''),'id'=>$newobject->id);
            }
            else
            {
                $db->rollback();
                $error++;
                $errorcode='KO';
                $errorlabel=$newobject->error;
            }
        }

        if ($error)
        {
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }

    /**
     * Create ActionComm
     *
     * @param	array		$authentication		Array of authentication information
     * @param	ActionComm	$actioncomm		    $actioncomm
     * @return	array							Array result
     */
    function updateActionComm($authentication,$actioncomm)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: updateActionComm login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if (empty($actioncomm['id']))	{
            $error++; $errorcode='KO'; $errorlabel="Actioncomm id is mandatory.";
        }

        if (! $error)
        {
            $objectfound=false;

            $object=new ActionComm($db);
            $result=$object->fetch($actioncomm['id']);

            if (!empty($object->id)) {

                $objectfound=true;

                $object->datep=$actioncomm['datep'];
                $object->datef=$actioncomm['datef'];
                $object->type_code=$actioncomm['type_code'];
                $object->societe->id=$actioncomm['socid'];
                $object->fk_project=$actioncomm['projectid'];
                $object->note=$actioncomm['note'];
                $object->contact->id=$actioncomm['contactid'];
                $object->usertodo->id=$actioncomm['usertodo'];
                $object->userdone->id=$actioncomm['userdone'];
                $object->label=$actioncomm['label'];
                $object->percentage=$actioncomm['percentage'];
                $object->priority=$actioncomm['priority'];
                $object->fulldayevent=$actioncomm['fulldayevent'];
                $object->location=$actioncomm['location'];
                $object->fk_element=$actioncomm['fk_element'];
                $object->elementtype=$actioncomm['elementtype'];

                //Retreive all extrafield for actioncomm
                // fetch optionals attributes and labels
                $extrafields=new ExtraFields($db);
                $extralabels=$extrafields->fetch_name_optionals_label('actioncomm',true);
                foreach($extrafields->attribute_label as $key=>$label)
                {
                    $key='options_'.$key;
                    $object->array_options[$key]=$actioncomm[$key];
                }

                $db->begin();

                $result=$object->update($fuser);
                if ($result <= 0) {
                    $error++;
                }
            }

            if ((! $error) && ($objectfound))
            {
                $db->commit();
                $objectresp=array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'id'=>$object->id
                );
            }
            elseif ($objectfound)
            {
                $db->rollback();
                $error++;
                $errorcode='KO';
                $errorlabel=$object->error;
            } else {
                $error++;
                $errorcode='NOT_FOUND';
                $errorlabel='Actioncomm id='.$actioncomm['id'].' cannot be found';
            }
        }

        if ($error)
        {
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }
}
