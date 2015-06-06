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
require_once(DOL_DOCUMENT_ROOT."/core/lib/ws.lib.php");
require_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * Class for Dolibarr order webservices
 *
 * @author jfefe
 */
class wsOrder extends DolWS
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
     * Get order from id, ref or ref_ext.
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id
     * @param	string		$ref				Ref
     * @param	string		$ref_ext			Ref_ext
     * @return	array							Array result
     */
    function getOrder($authentication,$id='',$ref='',$ref_ext='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getOrder login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;

        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if ($fuser->societe_id) $socid=$fuser->societe_id;

        // Check parameters
        if (! $error && (($id && $ref) || ($id && $ref_ext) || ($ref && $ref_ext)))
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter id, ref and ref_ext can't be both provided. You must choose one or other but not both.";
        }

        if (! $error)
        {
            $fuser->getrights();

            if ($fuser->rights->commande->lire)
            {
                $order=new Commande($db);
                $result=$order->fetch($id,$ref,$ref_ext);
                if ($result > 0)
                {
                    // Security for external user
                    if( $socid && ( $socid != $order->socid) )
                    {
                        $error++;
                        $errorcode='PERMISSION_DENIED'; $errorlabel=$order->socid.'User does not have permission for this request';
                    }

                    if(!$error)
                    {

                        $linesresp=array();
                        $i=0;
                        foreach($order->lines as $line)
                        {
                            //var_dump($line); exit;
                            $linesresp[]=array(
                            'id'=>$line->rowid,
                            'fk_commande'=>$line->fk_commande,
                            'fk_parent_line'=>$line->fk_parent_line,
                            'desc'=>$line->desc,
                            'qty'=>$line->qty,
                            'price'=>$line->price,
                            'unitprice'=>$line->subprice,
                            'vat_rate'=>$line->tva_tx,
                            'remise'=>$line->remise,
                            'remise_percent'=>$line->remise_percent,
                            'product_id'=>$line->fk_product,
                            'product_type'=>$line->product_type,
                            'total_net'=>$line->total_ht,
                            'total_vat'=>$line->total_tva,
                            'total'=>$line->total_ttc,
                            'date_start'=>$line->date_start,
                            'date_end'=>$line->date_end,
                            'product_ref'=>$line->product_ref,
                            'product_label'=>$line->product_label,
                            'product_desc'=>$line->product_desc
                            );
                            $i++;
                        }

                        // Create order
                        $objectresp = array(
                            'result'=> (object) array('result_code'=>'OK', 'result_label'=>''),
                            'order'=> array(
                                'id' => $order->id,
                                'ref' => $order->ref,
                                'ref_client' => $order->ref_client,
                                'ref_ext' => $order->ref_ext,
                                'ref_int' => $order->ref_int,
                                'thirdparty_id' => $order->socid,
                                'status' => $order->statut,

                                'total_net' => $order->total_ht,
                                'total_vat' => $order->total_tva,
                                'total_localtax1' => $order->total_localtax1,
                                'total_localtax2' => $order->total_localtax2,
                                'total' => $order->total_ttc,
                                'project_id' => $order->fk_project,

                                'date' => $order->date?dol_print_date($order->date,'dayhourrfc'):  dol_now(),
                               
                                'date_validation' => $order->date_validation?dol_print_date($order->date_validation,'dayhourrfc'):'',
                                //'date_modification' => $order->datem?dol_print_date($order->datem,'dayhourrfc'):'',

                                'remise' => $order->remise,
                                'remise_percent' => $order->remise_percent,
                                'remise_absolue' => $order->remise_absolue,

                                'source' => $order->source,
                                'facturee' => $order->facturee,
                                'note_private' => $order->note_private,
                                'note_public' => $order->note_public,
                                'cond_reglement_id' => $order->cond_reglement_id,
                                'cond_reglement_code' => $order->cond_reglement_code,
                                'cond_reglement' => $order->cond_reglement,
                                'mode_reglement_id' => $order->mode_reglement_id,
                                'mode_reglement_code' => $order->mode_reglement_code,
                                'mode_reglement' => $order->mode_reglement,

                                'date_livraison' => $order->date_livraison,
                                'fk_delivery_address' => $order->fk_delivery_address,

                                'demand_reason_id' => $order->demand_reason_id,
                                'demand_reason_code' => $order->demand_reason_code,

                                'lines' => $linesresp
                            )
                        );
                    }
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
            $objectresp = array('result'=> (object) array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }


    /**
     * Get list of orders for third party
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$idthirdparty		Id of thirdparty
     * @return	array							Array result
     */
    function getOrdersForThirdParty($authentication,$idthirdparty)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getOrdersForThirdParty login=".$authentication['login']." idthirdparty=".$idthirdparty);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if ($fuser->societe_id) $socid=$fuser->societe_id;

        // Check parameters
        if (! $error && empty($idthirdparty))
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel='Parameter id is not provided';
        }

        if (! $error)
        {
            $linesorders=array();

            $sql.='SELECT c.rowid as orderid';
            $sql.=' FROM '.MAIN_DB_PREFIX.'commande as c';
            $sql.=" WHERE c.entity = ".$conf->entity;
            if ($idthirdparty != 'all' ) $sql.=" AND c.fk_soc = ".$db->escape($idthirdparty);


            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);
                $i=0;
                while ($i < $num)
                {
                    // En attendant remplissage par boucle
                    $obj=$db->fetch_object($resql);

                    $order=new Commande($db);
                    $order->fetch($obj->orderid);

                    // Sécurité pour utilisateur externe
                    if( $socid && ( $socid != $order->socid) )
                    {
                        $error++;
                        $errorcode='PERMISSION_DENIED'; $errorlabel=$order->socid.' User does not have permission for this request';
                    }

                    if(!$error)
                    {

                        // Define lines of invoice
                        $linesresp=array();
                        foreach($order->lines as $line)
                        {
                            $linesresp[]=array(
                            'id'=>$line->rowid,
                            'type'=>$line->product_type,
                            'fk_commande'=>$line->fk_commande,
                            'fk_parent_line'=>$line->fk_parent_line,
                            'desc'=>$line->desc,
                            'qty'=>$line->qty,
                            'price'=>$line->price,
                            'unitprice'=>$line->subprice,
                            'tva_tx'=>$line->tva_tx,
                            'remise'=>$line->remise,
                            'remise_percent'=>$line->remise_percent,
                            'total_net'=>$line->total_ht,
                            'total_vat'=>$line->total_tva,
                            'total'=>$line->total_ttc,
                            'date_start'=>$line->date_start,
                            'date_end'=>$line->date_end,
                            'product_id'=>$line->fk_product,
                            'product_ref'=>$line->product_ref,
                            'product_label'=>$line->product_label,
                            'product_desc'=>$line->product_desc
                            );
                        }

                        // Now define invoice
                        $linesorders[]=array(
                        'id' => $order->id,
                        'ref' => $order->ref,
                        'ref_client' => $order->ref_client,
                        'ref_ext' => $order->ref_ext,
                        'ref_int' => $order->ref_int,
                        'socid' => $order->socid,
                        'status' => $order->statut,

                        'total_net' => $order->total_ht,
                        'total_vat' => $order->total_tva,
                        'total_localtax1' => $order->total_localtax1,
                        'total_localtax2' => $order->total_localtax2,
                        'total' => $order->total_ttc,
                        'project_id' => $order->fk_project,

                        'date' => $order->date?dol_print_date($order->date,'dayrfc'):'',

                        'remise' => $order->remise,
                        'remise_percent' => $order->remise_percent,
                        'remise_absolue' => $order->remise_absolue,

                        'source' => $order->source,
                        'facturee' => $order->facturee,
                        'note_private' => $order->note_private,
                        'note_public' => $order->note_public,
                        'cond_reglement_id' => $order->cond_reglement_id,
                        'cond_reglement' => $order->cond_reglement,
                        'cond_reglement_doc' => $order->cond_reglement_doc,
                        'cond_reglement_code' => $order->cond_reglement_code,
                        'mode_reglement_id' => $order->mode_reglement_id,
                        'mode_reglement' => $order->mode_reglement,
                        'mode_reglement_code' => $order->mode_reglement_code,

                        'date_livraison' => $order->date_livraison,

                        'demand_reason_id' => $order->demand_reason_id,
                        'demand_reason_code' => $order->demand_reason_code,

                        'lines' => $linesresp
                        );
                    }
                    $i++;
                }

                $objectresp=array(
                'result'=>parent::array_to_object(array('result_code'=>'OK', 'result_label'=>'')),
                'orders'=>$linesorders

                );
            }
            else
            {
                $error++;
                $errorcode=$db->lasterrno(); $errorlabel=$db->lasterror();
            }
        }

        if ($error)
        {
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }


    /**
     * Create order
     *
     * @param	array		$authentication		Array of authentication information
     * @param	array		$order				Order info
     * @return	int								Id of new order
     */
    function createOrder($authentication,$order)
    {
        global $db,$conf,$langs;

        require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

        $now=dol_now();

        dol_syslog("Function: createOrder login=".$authentication['login']." socid :".$order['socid']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        // Check parameters


        if (! $error)
        {
            $newobject=new Commande($db);
            $newobject->socid=$order['socid'];
            $newobject->type=$order['type'];
            $newobject->ref_ext=$order['ref_ext'];
            $newobject->date=$order['date'];
            $newobject->date_lim_reglement=$order['date_due'];
            $newobject->note_private=$order['note_private'];
            $newobject->note_public=$order['note_public'];
            $newobject->statut=0;	// We start with status draft
            $newobject->facturee=$order['facturee'];
            $newobject->fk_project=$order['project_id'];
            $newobject->fk_delivery_address=$order['fk_delivery_address'];
            $newobject->cond_reglement_id=$order['cond_reglement_id'];
            $newobject->demand_reason_id=$order['demand_reason_id'];

            // Trick because nusoap does not store data with same structure if there is one or several lines
            $arrayoflines=array();
            if (isset($order['lines']['line'][0])) $arrayoflines=$order['lines']['line'];
            else $arrayoflines=$order['lines'];

            foreach($arrayoflines as $key => $line)
            {
                // $key can be 'line' or '0','1',...
                $newline=new OrderLine($db);

                $newline->type=$line['type'];
                $newline->desc=$line['desc'];
                $newline->fk_product=$line['product_id'];
                $newline->tva_tx=$line['vat_rate'];
                $newline->qty=$line['qty'];
                $newline->price=$line['price'];
                $newline->date_start=$line['date_start'];
                $newline->date_end=$line['date_end'];
                $newline->subprice=$line['unitprice'];
                $newline->total_ht=$line['total_net'];
                $newline->total_tva=$line['total_vat'];
                $newline->total_ttc=$line['total'];
                $newobject->lines[]=$newline;
            }


            $db->begin();
            dol_syslog("Webservice server_order:: order creation start", LOG_DEBUG);
            $result=$newobject->create($fuser);
            dol_syslog('Webservice server_order:: order creation done with $result='.$result, LOG_DEBUG);
            if ($result < 0)
            {
                dol_syslog("Webservice server_order:: order creation failed", LOG_ERR);
                $error++;

            }

            if ($order['status'] == 1)   // We want order to have status validated
            {
                dol_syslog("Webservice server_order:: order validation start", LOG_DEBUG);
                $result=$newobject->valid($fuser);
                if ($result < 0)
                {
                    dol_syslog("Webservice server_order:: order validation failed", LOG_ERR);
                    $error++;
                }
            }

            if ($result >= 0)
            {
                dol_syslog("Webservice server_order:: order creation & validation succeeded, commit", LOG_DEBUG);
                $db->commit();
                $objectresp=array('result'=>(object) array('result_code'=>'OK', 'result_label'=>''),'id'=>$newobject->id,'ref'=>$newobject->ref);
            }
            else
            {
                dol_syslog("Webservice server_order:: order creation or validation failed, rollback", LOG_ERR);
                $db->rollback();
                $error++;
                $errorcode='KO';
                $errorlabel=$newobject->error;
            }

        }

        if ($error)
        {
            $objectresp = array('result'=>(object) array('result_code' => $errorcode, 'result_label' => $errorlabel));
        }

        return $objectresp;
    }


    /**
     * Valid an order
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id of order to validate
     * @return	array							Array result
     */
    function validOrder($authentication,$id='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: validOrder login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        if ($authentication['entity']) $conf->entity=$authentication['entity'];
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if (! $error)
        {
            $fuser->getrights();

            if ($fuser->rights->commande->lire)
            {
                $order=new Commande($db);
                $result=$order->fetch($id,$ref,$ref_ext);

                $order->fetch_thirdparty();
                $db->begin();
                if ($result > 0)
                {
                    $result=$order->valid($fuser);

                    if ($result	>= 0)
                    {
                        // Define output language
                        $outputlangs = $langs;
                        commande_pdf_create($db, $order, $order->modelpdf, $outputlangs, 0, 0, 0);

                    }
                    else
                    {
                        $db->rollback();
                        $error++;
                        $errorcode='KO';
                        $errorlabel=$newobject->error;
                    }
                }
                else
                {
                    $db->rollback();
                    $error++;
                    $errorcode='KO';
                    $errorlabel=$newobject->error;
                }

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
        else
        {
            $db->commit();
            $objectresp= array('result'=>array('result_code'=>'OK', 'result_label'=>''));
        }

        return $objectresp;
    }



}
