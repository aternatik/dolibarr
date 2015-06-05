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

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';



/**
 * Class for Dolibarr supplier invoice webservices
 *
 * @author jfefe
 */
class wsSupplierInvoice extends DolWS
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
     * Get invoice from id, ref or ref_ext
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id
     * @param	string		$ref				Ref
     * @param	string		$ref_ext			Ref_ext
     * @return	array							Array result
     */
    function getSupplierInvoice($authentication,$id='',$ref='',$ref_ext='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getSupplierInvoice login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

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

            if ($fuser->rights->fournisseur->facture->lire)
            {
                $invoice=new FactureFournisseur($db);
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
                            'total_net'=>$line->total_ht,
                            'total_vat'=>$line->total_tva,
                            'total'=>$line->total_ttc,
                            'vat_rate'=>$line->tva_tx,
                            'qty'=>$line->qty
                        );
                        $i++;
                    }

                    // Create invoice
                    $objectresp = array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'invoice'=>array(
                            'id' => $invoice->id,
                            'ref' => $invoice->ref,
                            'ref_supplier'=>$invoice->ref_supplier,
                            'ref_ext' => $invoice->ref_ext,
                            'fk_user_author' => $invoice->fk_user_author,
                            'fk_user_valid' => $invoice->fk_user_valid,
                            'fk_thirdparty' => $invoice->fk_soc,
                            'type'=>$invoice->type,
                            'status'=>$invoice->statut,
                            'total_net'=>$invoice->total_ht,
                            'total_vat'=>$invoice->total_tva,
                            'total'=>$invoice->total_ttc,
                            'date_creation'=>dol_print_date($invoice->datec,'dayhourrfc'),
                            'date_modification'=>dol_print_date($invoice->tms,'dayhourrfc'),
                            'date_invoice'=>dol_print_date($invoice->date,'dayhourrfc'),
                            'date_term'=>dol_print_date($invoice->date_echeance,'dayhourrfc'),
                            'label'=>$invoice->libelle,
                            'paid'=>$invoice->paye,
                            'note_private'=>$invoice->note_private,
                            'note_public'=>$invoice->note_public,
                            'close_code'=>$invoice->close_code,
                            'close_note'=>$invoice->close_note,

                            'lines' => $linesresp
    //					        'lines' => array('0'=>array('id'=>222,'type'=>1),
    //				        				 '1'=>array('id'=>333,'type'=>1))

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
     *
     */
    function getSupplierInvoicesForThirdParty($authentication,$idthirdparty)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getSupplierInvoicesForThirdParty login=".$authentication['login']." idthirdparty=".$idthirdparty);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if (! $error && empty($idthirdparty))
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel='Parameter id is not provided';
        }

        if (! $error)
        {
            $linesinvoice=array();

            $sql.='SELECT f.rowid as facid';
            $sql.=' FROM '.MAIN_DB_PREFIX.'facture_fourn as f';
            //$sql.=', '.MAIN_DB_PREFIX.'societe as s';
            //$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON pt.fk_product = p.rowid';
            //$sql.=" WHERE f.fk_soc = s.rowid AND nom = '".$db->escape($idthirdparty)."'";
            //$sql.=" WHERE f.fk_soc = s.rowid AND nom = '".$db->escape($idthirdparty)."'";
            $sql.=" WHERE f.entity = ".$conf->entity;
            if ($idthirdparty != 'all') $sql.=" AND f.fk_soc = ".$db->escape($idthirdparty);

            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);
                $i=0;
                while ($i < $num)
                {
                    // En attendant remplissage par boucle
                    $obj=$db->fetch_object($resql);

                    $invoice=new FactureFournisseur($db);
                    $result=$invoice->fetch($obj->facid);
                    if ($result < 0)
                    {
                        $error++;
                        $errorcode=$result; $errorlabel=$invoice->error;
                        break;
                    }

                    // Define lines of invoice
                    $linesresp=array();
                    foreach($invoice->lines as $line)
                    {
                        $linesresp[]=array(
                            'id'=>$line->rowid,
                            'type'=>$line->product_type,
                            'desc'=>dol_htmlcleanlastbr($line->description),
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
                        'id'=>$invoice->id,
                        'ref'=>$invoice->ref,
                        'ref_supplier'=>$invoice->ref_supplier,
                        'ref_ext'=>$invoice->ref_ext,
                        'fk_user_author' => $invoice->fk_user_author,
                        'fk_user_valid' => $invoice->fk_user_valid,
                        'fk_thirdparty' => $invoice->fk_soc,
                        'type'=>$invoice->type,
                        'status'=>$invoice->statut,
                        'total_net'=>$invoice->total_ht,
                        'total_vat'=>$invoice->total_tva,
                        'total'=>$invoice->total_ttc,
                        'date_creation'=>dol_print_date($invoice->datec,'dayhourrfc'),
                        'date_modification'=>dol_print_date($invoice->tms,'dayhourrfc'),
                        'date_invoice'=>dol_print_date($invoice->date,'dayhourrfc'),
                        'date_term'=>dol_print_date($invoice->date_echeance,'dayhourrfc'),
                        'label'=>$invoice->libelle,
                        'paid'=>$invoice->paye,
                        'note_private'=>$invoice->note_private,
                        'note_public'=>$invoice->note_public,
                        'close_code'=>$invoice->close_code,
                        'close_note'=>$invoice->close_note,

                        'lines' => $linesresp
                    );

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

}
