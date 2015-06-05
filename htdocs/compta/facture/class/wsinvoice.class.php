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

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';


/**
 * Class for Dolibarr invoice webservices
 *
 * @author jfefe
 */
class wsInvoice extends DolWS
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
     * Get invoice from id, ref or ref_ext.
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id
     * @param	string		$ref				Ref
     * @param	string		$ref_ext			Ref_ext
     * @return	array							Array result
     */
    function getInvoice($authentication,$id='',$ref='',$ref_ext='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getInvoice login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

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

            if ($fuser->rights->facture->lire)
            {
                $invoice=new Facture($db);
                $result=$invoice->fetch($id,$ref,$ref_ext);
                if ($result > 0)
                {
                    $linesresp=array();
                    $i=0;
                    foreach($invoice->lines as $line)
                    {
                        //var_dump($line); exit;
                        $linesresp[]=array(
                            'id'=>$line->rowid,
                            'type'=>$line->product_type,
                            'desc'=>dol_htmlcleanlastbr($line->desc),
                            'total_net'=>$line->total_ht,
                            'total_vat'=>$line->total_tva,
                            'total'=>$line->total_ttc,
                            'vat_rate'=>$line->tva_tx,
                            'qty'=>$line->qty,
                            'product_ref'=>$line->product_ref,
                            'product_label'=>$line->product_label,
                            'product_desc'=>$line->product_desc,
                        );
                        $i++;
                    }

                    // Create invoice
                    $objectresp = array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'invoice'=>array(
                            'id' => $invoice->id,
                            'ref' => $invoice->ref,
                            'ref_ext' => $invoice->ref_ext?$invoice->ref_ext:'',   // If not defined, field is not added into soap
                            'fk_user_author' => $invoice->user_author?$invoice->user_author:'',
                            'fk_user_valid' => $invoice->user_valid?$invoice->user_valid:'',
                            'date' => $invoice->date?dol_print_date($invoice->date,'dayrfc'):'',
                            'date_creation' => $invoice->date_creation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
                            'date_validation' => $invoice->date_validation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
                            'date_modification' => $invoice->datem?dol_print_date($invoice->datem,'dayhourrfc'):'',
                            'type' => $invoice->type,
                            'total_net' => $invoice->total_ht,
                            'total_vat' => $invoice->total_tva,
                            'total' => $invoice->total_ttc,
                            'note_private' => $invoice->note_private?$invoice->note_private:'',
                            'note_public' => $invoice->note_public?$invoice->note_public:'',
                            'status'=> $invoice->statut,
                            'close_code' => $invoice->close_code?$invoice->close_code:'',
                            'close_note' => $invoice->close_note?$invoice->close_note:'',
                            'lines' => $linesresp
                        ));
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
     * Get list of invoices for third party
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$idthirdparty		Id thirdparty
     * @return	array							Array result
     */
    function getInvoicesForThirdParty($authentication,$idthirdparty)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getInvoicesForThirdParty login=".$authentication['login']." idthirdparty=".$idthirdparty);

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
            $linesinvoice=array();

            $sql.='SELECT f.rowid as facid, facnumber as ref, ref_ext, type, fk_statut as status, total_ttc, total, tva';
            $sql.=' FROM '.MAIN_DB_PREFIX.'facture as f';
            $sql.=" WHERE f.entity = ".$conf->entity;
            if ($idthirdparty != 'all' ) $sql.=" AND f.fk_soc = ".$db->escape($idthirdparty);

            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);
                $i=0;
                while ($i < $num)
                {
                    // En attendant remplissage par boucle
                    $obj=$db->fetch_object($resql);

                    $invoice=new Facture($db);
                    $invoice->fetch($obj->facid);

                    // Sécurité pour utilisateur externe
                    if( $socid && ( $socid != $invoice->socid) )
                    {
                        $error++;
                        $errorcode='PERMISSION_DENIED'; $errorlabel=$invoice->socid.' User does not have permission for this request';
                    }

                    if(!$error)
                    {
                        // Define lines of invoice
                        $linesresp=array();
                        foreach($invoice->lines as $line)
                        {
                            $linesresp[]=array(
                                'id'=>$line->rowid,
                                'type'=>$line->product_type,
                                'total_net'=>$line->total_ht,
                                'total_vat'=>$line->total_tva,
                                'total'=>$line->total_ttc,
                                'vat_rate'=>$line->tva_tx,
                                'qty'=>$line->qty,
                                'product_ref'=>$line->product_ref,
                                'product_label'=>$line->product_label,
                                'product_desc'=>$line->product_desc,
                            );
                        }

                        // Now define invoice
                        $linesinvoice[]=array(
                            'id' => $invoice->id,
                            'ref' => $invoice->ref,
                            'ref_ext' => $invoice->ref_ext?$invoice->ref_ext:'',   // If not defined, field is not added into soap
                            'fk_user_author' => $invoice->user_author?$invoice->user_author:'',
                            'fk_user_valid' => $invoice->user_valid?$invoice->user_valid:'',
                            'date' => $invoice->date?dol_print_date($invoice->date,'dayrfc'):'',
                            'date_due' => $invoice->date_lim_reglement?dol_print_date($invoice->date_lim_reglement,'dayrfc'):'',
                            'date_creation' => $invoice->date_creation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
                            'date_validation' => $invoice->date_validation?dol_print_date($invoice->date_creation,'dayhourrfc'):'',
                            'date_modification' => $invoice->datem?dol_print_date($invoice->datem,'dayhourrfc'):'',
                            'type' => $invoice->type,
                            'total_net' => $invoice->total_ht,
                            'total_vat' => $invoice->total_tva,
                            'total' => $invoice->total_ttc,
                            'note_private' => $invoice->note_private?$invoice->note_private:'',
                            'note_public' => $invoice->note_public?$invoice->note_public:'',
                            'status'=> $invoice->statut,
                            'close_code' => $invoice->close_code?$invoice->close_code:'',
                            'close_note' => $invoice->close_note?$invoice->close_note:'',
                            'lines' => $linesresp
                        );
                    }

                    $i++;
                }

                $objectresp=array(
                    'result'=>array('result_code'=>'OK', 'result_label'=>''),
                    'invoices'=>$linesinvoice

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
     * Create an invoice
     *
     * @param	array		$authentication		Array of authentication information
     * @param	Facture		$invoice			Invoice
     * @return	array							Array result
     */
    function createInvoice($authentication,$invoice)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: createInvoiceForThirdParty login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        if (! $error)
        {
            $newobject=new Facture($db);
            $newobject->socid=$invoice['thirdparty_id'];
            $newobject->type=$invoice['type'];
            $newobject->ref_ext=$invoice['ref_ext'];
            $newobject->date=dol_stringtotime($invoice['date'],'dayrfc');
            $newobject->note_private=$invoice['note_private'];
            $newobject->note_public=$invoice['note_public'];
            $newobject->statut=0;	// We start with status draft
            $newobject->fk_project=$invoice['project_id'];
            $newobject->date_creation=$now;

            // Trick because nusoap does not store data with same structure if there is one or several lines
            $arrayoflines=array();
            if (isset($invoice['lines']['line'][0])) $arrayoflines=$invoice['lines']['line'];
            else $arrayoflines=$invoice['lines'];

            foreach($arrayoflines as $key => $line)
            {
                // $key can be 'line' or '0','1',...
                $newline=new FactureLigne($db);
                $newline->product_type=$line['type'];
                $newline->desc=$line['desc'];
                $newline->fk_product=$line['fk_product'];
                $newline->tva_tx=$line['vat_rate'];
                $newline->qty=$line['qty'];
                $newline->subprice=$line['unitprice'];
                $newline->total_ht=$line['total_net'];
                $newline->total_tva=$line['total_vat'];
                $newline->total_ttc=$line['total'];
                $newline->date_start=dol_stringtotime($line['date_start']);
                $newline->date_end=dol_stringtotime($line['date_end']);
                $newline->fk_product=$line['product_id'];
                $newobject->lines[]=$newline;
            }
            //var_dump($newobject->date_lim_reglement); exit;
            //var_dump($invoice['lines'][0]['type']);

            $db->begin();

            $result=$newobject->create($fuser,0,dol_stringtotime($invoice['date_due'],'dayrfc'));
            if ($result < 0)
            {
                $error++;
            }

            if ($invoice['status'] == 1)   // We want invoice to have status validated
            {
                $result=$newobject->validate($fuser);
                if ($result < 0)
                {
                    $error++;
                }
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


}
