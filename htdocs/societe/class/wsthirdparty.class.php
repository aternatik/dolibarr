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
require_once DOL_DOCUMENT_ROOT . '/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * Class for Dolibarr product webservices
 *
 * @author jfefe
 */
class wsThirdparty extends DolWS
{
    /**
     * Constructor method
     * 
     */
    function __construct()
    {
        parent::__construct();
    }


    // Full methods code
    function getThirdParty($authentication,$id='',$ref='',$ref_ext='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getThirdParty login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if (! $error && (($id && $ref) || ($id && $ref_ext) || ($ref && $ref_ext)))
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
        }

        if (! $error)
        {
            $fuser->getrights();

            if ($fuser->rights->societe->lire)
            {
                $thirdparty=new Societe($db);
                $result=$thirdparty->fetch($id,$ref,$ref_ext);
                if ($result > 0)
                {

                    $thirdparty_result_fields=array(
                            'id' => $thirdparty->id,
                            'ref' => $thirdparty->name,
                            'ref_ext' => $thirdparty->ref_ext,
                            'status' => $thirdparty->status,
                            'client' => $thirdparty->client,
                            'supplier' => $thirdparty->fournisseur,
                            'customer_code' => $thirdparty->code_client,
                            'supplier_code' => $thirdparty->code_fournisseur,
                            'customer_code_accountancy' => $thirdparty->code_compta,
                            'supplier_code_accountancy' => $thirdparty->code_compta_fournisseur,
                            'fk_user_author' => $thirdparty->fk_user_author,
                            'date_creation' => dol_print_date($thirdparty->date_creation,'dayhourrfc'),
                            'date_modification' => dol_print_date($thirdparty->date_modification,'dayhourrfc'),
                            'address' => $thirdparty->address,
                            'zip' => $thirdparty->zip,
                            'town' => $thirdparty->town,
                            'province_id' => $thirdparty->state_id,
                            'country_id' => $thirdparty->country_id,
                            'country_code' => $thirdparty->country_code,
                            'country' => $thirdparty->country,
                            'phone' => $thirdparty->phone,
                            'fax' => $thirdparty->fax,
                            'email' => $thirdparty->email,
                            'url' => $thirdparty->url,
                            'profid1' => $thirdparty->idprof1,
                            'profid2' => $thirdparty->idprof2,
                            'profid3' => $thirdparty->idprof3,
                            'profid4' => $thirdparty->idprof4,
                            'profid5' => $thirdparty->idprof5,
                            'profid6' => $thirdparty->idprof6,
                            'capital' => $thirdparty->capital,
                            'barcode' => $thirdparty->barcode,
                            'vat_used' => $thirdparty->tva_assuj,
                            'vat_number' => $thirdparty->tva_intra,
                            'note_private' => $thirdparty->note_private,
                            'note_public' => $thirdparty->note_public);

                    //Retreive all extrafield for thirdsparty
                    // fetch optionals attributes and labels
                    $extrafields=new ExtraFields($db);
                    $extralabels=$extrafields->fetch_name_optionals_label('societe',true);
                    //Get extrafield values
                    $thirdparty->fetch_optionals($thirdparty->id,$extralabels);

                    foreach($extrafields->attribute_label as $key=>$label)
                    {
                        $thirdparty_result_fields=array_merge($thirdparty_result_fields,array('options_'.$key => $thirdparty->array_options['options_'.$key]));
                    }

                    // Create
                    $objectresp = array(
                        'result'=>parent::array_to_object(array('result_code'=>'OK', 'result_label'=>'')),
                        'thirdparty' => parent::array_to_object($thirdparty_result_fields)
                    );
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
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }



    /**
     * Create a thirdparty
     *
     * @param	array		$authentication		Array of authentication information
     * @param	Societe		$thirdparty		    Thirdparty
     * @return	array							Array result
     */
    function createThirdParty($authentication,$thirdparty)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: createThirdParty login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if (empty($thirdparty['ref']))
        {
            $error++; $errorcode='KO'; $errorlabel="Name is mandatory.";
        }


        if (! $error)
        {
            include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

            $newobject=new Societe($db);
            $newobject->ref=$thirdparty['ref'];
            $newobject->name=$thirdparty['ref'];
            $newobject->ref_ext=$thirdparty['ref_ext'];
            $newobject->status=$thirdparty['status'];
            $newobject->client=$thirdparty['client'];
            $newobject->fournisseur=$thirdparty['supplier'];
            $newobject->code_client=$thirdparty['customer_code'];
            $newobject->code_fournisseur=$thirdparty['supplier_code'];
            $newobject->code_compta=$thirdparty['customer_code_accountancy'];
            $newobject->code_compta_fournisseur=$thirdparty['supplier_code_accountancy'];
            $newobject->date_creation=$now;
            $newobject->note_private=$thirdparty['note_private'];
            $newobject->note_public=$thirdparty['note_public'];
            $newobject->address=$thirdparty['address'];
            $newobject->zip=$thirdparty['zip'];
            $newobject->town=$thirdparty['town'];

            $newobject->country_id=$thirdparty['country_id'];
            if ($thirdparty['country_code']) $newobject->country_id=getCountry($thirdparty['country_code'],3);
            $newobject->province_id=$thirdparty['province_id'];
            //if ($thirdparty['province_code']) $newobject->province_code=getCountry($thirdparty['province_code'],3);

            $newobject->phone=$thirdparty['phone'];
            $newobject->fax=$thirdparty['fax'];
            $newobject->email=$thirdparty['email'];
            $newobject->url=$thirdparty['url'];
            $newobject->idprof1=$thirdparty['profid1'];
            $newobject->idprof2=$thirdparty['profid2'];
            $newobject->idprof3=$thirdparty['profid3'];
            $newobject->idprof4=$thirdparty['profid4'];
            $newobject->idprof5=$thirdparty['profid5'];
            $newobject->idprof6=$thirdparty['profid6'];

            $newobject->capital=$thirdparty['capital'];

            $newobject->barcode=$thirdparty['barcode'];
            $newobject->tva_assuj=$thirdparty['vat_used'];
            $newobject->tva_intra=$thirdparty['vat_number'];

            $newobject->canvas=$thirdparty['canvas'];
            $newobject->particulier=$thirdparty['individual'];

            //Retreive all extrafield for thirdsparty
            // fetch optionals attributes and labels
            $extrafields=new ExtraFields($db);
            $extralabels=$extrafields->fetch_name_optionals_label('societe',true);
            foreach($extrafields->attribute_label as $key=>$label)
            {
                $key='options_'.$key;
                $newobject->array_options[$key]=$thirdparty[$key];
            }

            $db->begin();

            $result=$newobject->create($fuser);
            if ($newobject->particulier && $result > 0) {
                $newobject->firstname = $thirdparty['firstname'];
                $newobject->name_bis = $thirdparty['lastname'];
                $result = $newobject->create_individual($fuser);
            }
            if ($result <= 0)
            {
                $error++;
            }

            if (! $error)
            {
                $db->commit();
                $objectresp=array('result'=>array('result_code'=>'OK', 'result_label'=>''),'id'=>$newobject->id,'ref'=>$newobject->ref);
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
     * Update a thirdparty
     *
     * @param	array		$authentication		Array of authentication information
     * @param	Societe		$thirdparty		    Thirdparty
     * @return	array							Array result
     */
    function updateThirdParty($authentication,$thirdparty)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: updateThirdParty login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if (empty($thirdparty['id']))	{
            $error++; $errorcode='KO'; $errorlabel="Thirdparty id is mandatory.";
        }

        if (! $error)
        {
            $objectfound=false;

            include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

            $object=new Societe($db);
            $result=$object->fetch($thirdparty['id']);

            if (!empty($object->id)) {

                $objectfound=true;

                $object->ref=$thirdparty['ref'];
                $object->name=$thirdparty['ref'];
                $object->ref_ext=$thirdparty['ref_ext'];
                $object->status=$thirdparty['status'];
                $object->client=$thirdparty['client'];
                $object->fournisseur=$thirdparty['supplier'];
                $object->code_client=$thirdparty['customer_code'];
                $object->code_fournisseur=$thirdparty['supplier_code'];
                $object->code_compta=$thirdparty['customer_code_accountancy'];
                $object->code_compta_fournisseur=$thirdparty['supplier_code_accountancy'];
                $object->date_creation=$now;
                $object->note_private=$thirdparty['note_private'];
                $object->note_public=$thirdparty['note_public'];
                $object->address=$thirdparty['address'];
                $object->zip=$thirdparty['zip'];
                $object->town=$thirdparty['town'];

                $object->country_id=$thirdparty['country_id'];
                if ($thirdparty['country_code']) $object->country_id=getCountry($thirdparty['country_code'],3);
                $object->province_id=$thirdparty['province_id'];
                //if ($thirdparty['province_code']) $newobject->province_code=getCountry($thirdparty['province_code'],3);

                $object->phone=$thirdparty['phone'];
                $object->fax=$thirdparty['fax'];
                $object->email=$thirdparty['email'];
                $object->url=$thirdparty['url'];
                $object->idprof1=$thirdparty['profid1'];
                $object->idprof2=$thirdparty['profid2'];
                $object->idprof3=$thirdparty['profid3'];
                $object->idprof4=$thirdparty['profid4'];
                $object->idprof5=$thirdparty['profid5'];
                $object->idprof6=$thirdparty['profid6'];

                $object->capital=$thirdparty['capital'];

                $object->barcode=$thirdparty['barcode'];
                $object->tva_assuj=$thirdparty['vat_used'];
                $object->tva_intra=$thirdparty['vat_number'];

                $object->canvas=$thirdparty['canvas'];

                //Retreive all extrafield for thirdsparty
                // fetch optionals attributes and labels
                $extrafields=new ExtraFields($db);
                $extralabels=$extrafields->fetch_name_optionals_label('societe',true);
                foreach($extrafields->attribute_label as $key=>$label)
                {
                    $key='options_'.$key;
                    $object->array_options[$key]=$thirdparty[$key];
                }

                $db->begin();

                $result=$object->update($thirdparty['id'],$fuser);
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
                $errorlabel='Thirdparty id='.$thirdparty['id'].' cannot be found';
            }
        }

        if ($error)
        {
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }



    /**
     * getListOfThirdParties
     *
     * @param	array		$authentication		Array of authentication information
     * @param	array		$filterthirdparty	Filter fields
     * @return	array							Array result
     */
    function getListOfThirdParties($authentication,$filterthirdparty)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: getListOfThirdParties login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $arraythirdparties=array();

        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters

        if (! $error)
        {
            $sql ="SELECT s.rowid as socRowid, s.nom as ref, s.ref_ext, s.address, s.zip, s.town, p.libelle as country, s.phone, s.fax, s.url, extra.*";
            $sql.=" FROM ".MAIN_DB_PREFIX."societe as s";
            $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_pays as p ON s.fk_pays = p.rowid';
            $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as extra ON s.rowid=fk_object";

            $sql.=" WHERE entity=".$conf->entity;
            foreach($filterthirdparty as $key => $val)
            {
                if ($key == 'client'   && $val != '')  $sql.=" AND s.client = ".$db->escape($val);
                if ($key == 'supplier' && $val != '')  $sql.=" AND s.fournisseur = ".$db->escape($val);
                if ($key == 'category'   && $val != '')  $sql.=" AND s.rowid IN (SELECT fk_societe FROM ".MAIN_DB_PREFIX."categorie_societe WHERE fk_categorie=".$db->escape($val).") ";
            }
            dol_syslog("Function: getListOfThirdParties sql=".$sql);

            $extrafields=new ExtraFields($db);
            $extralabels=$extrafields->fetch_name_optionals_label('societe',true);


            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);

                $i=0;
                while ($i < $num)
                {
                    $extrafieldsOptions=array();
                    $obj=$db->fetch_object($resql);
                    foreach($extrafields->attribute_label as $key=>$label)
                    {
                        $extrafieldsOptions['options_'.$key] = $obj->{$key};
                    }
                    $arraythirdparties[]=array('id'=>$obj->socRowid,
                        'ref'=>$obj->ref,
                        'ref_ext'=>$obj->ref_ext,
                        'adress'=>$obj->adress,
                        'zip'=>$obj->zip,
                        'town'=>$obj->town,
                        'country'=>$obj->country,
                        'phone'=>$obj->phone,
                        'fax'=>$obj->fax,
                        'url'=>$obj->url
                    );
                    $arraythirdparties[$i] = array_merge($arraythirdparties[$i],$extrafieldsOptions);

                    $i++;
                }
            }
            else
            {
                $error++;
                $errorcode=$db->lasterrno();
                $errorlabel=$db->lasterror();
            }
        }

        if ($error)
        {
            $objectresp = array(
                'result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel),
                'thirdparties'=>$arraythirdparties
            );
        }
        else
        {
            $objectresp = array(
                'result'=>array('result_code' => 'OK', 'result_label' => ''),
                'thirdparties'=>$arraythirdparties
            );
        }

        return $objectresp;
    }
}
