<?php
/* Copyright (C) 2015 Jean-François Ferry             <jfefe@aternatik.fr>
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
/**
 * Class for Dolibarr product webservices
 *
 * @author jfefe
 */
class wsProduct extends DolWS
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
     * Get produt or service
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id of object
     * @param	string		$ref				Ref of object
     * @param	ref_ext		$ref_ext            Ref external of object
     * @param   string      $lang               Lang to force
     * @return	mixed
     */
    function getProductOrService($authentication,$id='',$ref='',$ref_ext='',$lang='')
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getProductOrService login=".$authentication['login']." id=".$id." ref=".$ref." ref_ext=".$ref_ext);

        $langcode=($lang?$lang:(empty($conf->global->MAIN_LANG_DEFAULT)?'auto':$conf->global->MAIN_LANG_DEFAULT));
        $langs->setDefaultLang($langcode);

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

            $langcode=($lang?$lang:(empty($conf->global->MAIN_LANG_DEFAULT)?'auto':$conf->global->MAIN_LANG_DEFAULT));
            $langs->setDefaultLang($langcode);

            $fuser->getrights();

            if ($fuser->rights->produit->lire || $fuser->rights->service->lire)
            {
                $product=new Product($db);
                $result=$product->fetch($id,$ref,$ref_ext);

                if ($result > 0)
                {
                    $product->load_stock();

                    $dir = (!empty($conf->product->dir_output)?$conf->product->dir_output:$conf->service->dir_output);
                    $pdir = get_exdir($product->id,2) . $product->id ."/photos/";
                    $dir = $dir . '/'. $pdir;

                    if (! empty($product->multilangs[$langs->defaultlang]["label"]))     		$product->label =  $product->multilangs[$langs->defaultlang]["label"];
                    if (! empty($product->multilangs[$langs->defaultlang]["description"]))     	$product->description =  $product->multilangs[$langs->defaultlang]["description"];
                    if (! empty($product->multilangs[$langs->defaultlang]["note"]))     		$product->note =  $product->multilangs[$langs->defaultlang]["note"];

                    // Create
                    $objectresp = array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'product'=>array(
                            'id' => $product->id,
                            'ref' => $product->ref,
                            'ref_ext' => $product->ref_ext,
                            'label' => $product->label,
                            'description' => $product->description,
                            'date_creation' => dol_print_date($product->date_creation,'dayhourrfc'),
                            'date_modification' => dol_print_date($product->date_modification,'dayhourrfc'),
                            'note' => $product->note,
                            'status_tosell' => $product->status,
                            'status_tobuy' => $product->status_buy,
                            'type' => $product->type,
                            'barcode' => $product->barcode,
                            'barcode_type' => $product->barcode_type,
                            'country_id' => $product->country_id>0?$product->country_id:'',
                            'country_code' => $product->country_code,
                            'custom_code' => $product->customcode,

                            'price_net' => $product->price,
                            'price' => $product->price_ttc,
                            'price_min_net' => $product->price_min,
                            'price_min' => $product->price_min_ttc,
                            'price_base_type' => $product->price_base_type,
                            'vat_rate' => $product->tva_tx,
                            //! French VAT NPR
                            'vat_npr' => $product->tva_npr,
                            //! Spanish local taxes
                            'localtax1_tx' => $product->localtax1_tx,
                            'localtax2_tx' => $product->localtax2_tx,

                            'stock_real' => $product->stock_reel,
                            'stock_alert' => $product->seuil_stock_alerte,
                            'pmp' => $product->pmp,
                            'import_key' => $product->import_key,
                            'dir' => $pdir,
                            'images' => $product->liste_photos($dir,$nbmax=10)
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
        //var_dump($objectresp);exit;
        return $objectresp;
    }


    /**
     * Create an invoice
     *
     * @param	array		$authentication		Array of authentication information
     * @param	Product		$product			Product
     * @return	array							Array result
     */
    function createProductOrService($authentication,$product)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: createProductOrService login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if ($product['price_net'] > 0) $product['price_base_type']='HT';
        if ($product['price'] > 0)     $product['price_base_type']='TTC';

        if ($product['price_net'] > 0 && $product['price'] > 0)
        {
            $error++; $errorcode='KO'; $errorlabel="You must choose between price or price_net to provide price.";
        }

        if ($product['barcode'] && !$product['barcode_type'])
        {
        $errror++; $errorcode='KO' ; $errorlabel="You must set a barcode type when setting a barcode.";
        }



        if (! $error)
        {
            include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

            $newobject=new Product($db);
            $newobject->ref=$product['ref'];
            $newobject->ref_ext=$product['ref_ext'];
            $newobject->type=$product['type'];
            $newobject->libelle=$product['label'];    // TODO deprecated
            $newobject->label=$product['label'];
            $newobject->description=$product['description'];
            $newobject->note=$product['note'];
            $newobject->status=$product['status_tosell'];
            $newobject->status_buy=$product['status_tobuy'];
            $newobject->price=$product['price_net'];
            $newobject->price_ttc=$product['price'];
            $newobject->tva_tx=$product['vat_rate'];
            $newobject->price_base_type=$product['price_base_type'];
            $newobject->date_creation=$now;

        if ($product['barcode']) 
        {
            $newobject->barcode = $product['barcode'];
            $newobject->barcode_type = $product['barcode_type'];
        }

            $newobject->stock_reel=$product['stock_real'];
            $newobject->pmp=$product['pmp'];
            $newobject->seuil_stock_alert=$product['stock_alert'];

            $newobject->country_id=$product['country_id'];
            if ($product['country_code']) $newobject->country_id=getCountry($product['country_code'],3);
            $newobject->customcode=$product['customcode'];

            $newobject->canvas=$product['canvas'];
            /*foreach($product['lines'] as $line)
            {
                $newline=new FactureLigne($db);
                $newline->type=$line['type'];
                $newline->desc=$line['desc'];
                $newline->fk_product=$line['fk_product'];
                $newline->total_ht=$line['total_net'];
                $newline->total_vat=$line['total_vat'];
                $newline->total_ttc=$line['total'];
                $newline->vat=$line['vat_rate'];
                $newline->qty=$line['qty'];
                $newline->fk_product=$line['product_id'];
            }*/
            //var_dump($product['ref_ext']);
            //var_dump($product['lines'][0]['type']);

            $db->begin();

            $result=$newobject->create($fuser,0);
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
     * Update a product or service
     *
     * @param	array		$authentication		Array of authentication information
     * @param	Product		$product			Product
     * @return	array							Array result
     */
    function updateProductOrService($authentication,$product)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: updateProductOrService login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters
        if ($product['price_net'] > 0) $product['price_base_type']='HT';
        if ($product['price'] > 0)     $product['price_base_type']='TTC';

        if ($product['price_net'] > 0 && $product['price'] > 0)
        {
            $error++; $errorcode='KO'; $errorlabel="You must choose between price or price_net to provide price.";
        }


        if ($product['barcode'] && !$product['barcode_type'])
        {
            $errror++; $errorcode='KO' ; $errorlabel="You must set a barcode type when setting a barcode.";
        }

        if (! $error)
        {
            include_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

            $newobject=new Product($db);
            $newobject->fetch($product['id']);

            if (isset($product['ref']))     $newobject->ref=$product['ref'];
            if (isset($product['ref_ext'])) $newobject->ref_ext=$product['ref_ext'];
            $newobject->type=$product['type'];
            $newobject->libelle=$product['label'];    // TODO deprecated
            $newobject->label=$product['label'];
            $newobject->description=$product['description'];
            $newobject->note=$product['note'];
            $newobject->status=$product['status_tosell'];
            $newobject->status_buy=$product['status_tobuy'];
            $newobject->price=$product['price_net'];
            $newobject->price_ttc=$product['price'];
            $newobject->tva_tx=$product['vat_rate'];
            $newobject->price_base_type=$product['price_base_type'];
            $newobject->date_creation=$now;

            if ($product['barcode']) 
            {
                    $newobject->barcode = $product['barcode'];
                    $newobject->barcode_type = $product['barcode_type'];
            }

            $newobject->stock_reel=$product['stock_real'];
            $newobject->pmp=$product['pmp'];
            $newobject->seuil_stock_alert=$product['stock_alert'];

            $newobject->country_id=$product['country_id'];
            if ($product['country_code']) $newobject->country_id=getCountry($product['country_code'],3);
            $newobject->customcode=$product['customcode'];

            $newobject->canvas=$product['canvas'];
            /*foreach($product['lines'] as $line)
            {
                $newline=new FactureLigne($db);
                $newline->type=$line['type'];
                $newline->desc=$line['desc'];
                $newline->fk_product=$line['fk_product'];
                $newline->total_ht=$line['total_net'];
                $newline->total_vat=$line['total_vat'];
                $newline->total_ttc=$line['total'];
                $newline->vat=$line['vat_rate'];
                $newline->qty=$line['qty'];
                $newline->fk_product=$line['product_id'];
            }*/
            //var_dump($product['ref_ext']);
            //var_dump($product['lines'][0]['type']);

            $db->begin();

            $result=$newobject->update($newobject->id,$fuser);
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
     * Delete a product or service
     *
     * @param	array		$authentication		Array of authentication information
     * @param	string		$listofidstring		List of id with comma
     * @return	array							Array result
     */
    function deleteProductOrService($authentication,$listofidstring)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: deleteProductOrService login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);

        // User must be defined to user authenticated
        global $user;
        $user=$fuser;

        $listofid=explode(',',trim($listofidstring));
        $listofiddeleted=array();

        // Check parameters
        if (count($listofid) == 0 || empty($listofid[0]))
        {
            $error++; $errorcode='KO'; $errorlabel="List of Id of products or services to delete are required.";
        }

        if (! $error)
        {
            $firsterror='';

            $db->begin();

            foreach($listofid as $key => $id)
            {
                $newobject=new Product($db);
                $result=$newobject->fetch($id);

                if ($result == 0)
                {
                    $error++;
                    $firsterror='Product or service with id '.$id.' not found';
                    break;
                }
                else
                {
                    $result=$newobject->delete();
                    if ($result <= 0)
                    {
                        $error++;
                        $firsterror=$newobject->error;
                        break;
                    }

                    $listofiddeleted[]=$id;
                }
            }

            if (! $error)
            {
                $db->commit();
                //$objectresp=array('result'=>array('result_code'=>'OK', 'result_label'=>''), 'listofid'=>$listofiddeleted);
                $objectresp=array('result'=>array('result_code'=>'OK', 'result_label'=>''), 'nbdeleted'=>count($listofiddeleted));
            }
            else
            {
                $db->rollback();
                $error++;
                $errorcode='KO';
                $errorlabel=$firsterror;
            }
        }

        if ($error)
        {
            //$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel), 'listofid'=>$listofiddeleted);
            $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel), 'nbdeleted'=>0);
        }
        else if (count($listofiddeleted) == 0)
        {
            //$objectresp=array('result'=>array('result_code'=>'NOT_FOUND', 'result_label'=>'No product or service with id '.join(',',$listofid).' found'), 'listofid'=>$listofiddeleted);
            $objectresp=array('result'=>array('result_code'=>'NOT_FOUND', 'result_label'=>'No product or service with id '.join(',',$listofid).' found'), 'nbdeleted'=>0);
        }

        return $objectresp;
    }


    /**
     * getListOfProductsOrServices
     *
     * @param	array		$authentication		Array of authentication information
     * @param	array		$filterproduct		Filter fields
     * @return	array							Array result
     */
    function getListOfProductsOrServices($authentication,$filterproduct)
    {
        global $db,$conf,$langs;

        $now=dol_now();

        dol_syslog("Function: getListOfProductsOrServices login=".$authentication['login']);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        // Init and check authentication
        $objectresp=array();
        $arrayproducts=array();
        $errorcode='';$errorlabel='';
        $error=0;
        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
        // Check parameters

        if (! $error)
        {
            $sql ="SELECT rowid, ref, ref_ext";
            $sql.=" FROM ".MAIN_DB_PREFIX."product";
            $sql.=" WHERE entity=".$conf->entity;
            foreach($filterproduct as $key => $val)
            {
                if ($key == 'type' && $val >= 0)   	$sql.=" AND fk_product_type = ".$db->escape($val);
                if ($key == 'tosell') 				$sql.=" AND to_sell = ".$db->escape($val);
                if ($key == 'tobuy')  				$sql.=" AND to_buy = ".$db->escape($val);
            }
            $resql=$db->query($sql);
            if ($resql)
            {
                $num=$db->num_rows($resql);

                $i=0;
                while ($i < $num)
                {
                    $obj=$db->fetch_object($resql);
                    $arrayproducts[]=array('id'=>$obj->rowid,'ref'=>$obj->ref,'ref_ext'=>$obj->ref_ext);
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
                'products'=>$arrayproducts
            );
        }
        else
        {
            $objectresp = array(
                'result'=>array('result_code' => 'OK', 'result_label' => ''),
                'products'=>$arrayproducts
            );
        }

        return $objectresp;
    }


    /**
     * Get list of products for a category
     *
     * @param	array		$authentication		Array of authentication information
     * @param	array		$id					Category id
     * @param	$lang		$lang				Force lang
     * @return	array							Array result
     */
    function getProductsForCategory($authentication,$id,$lang='')
    {
        global $db,$conf,$langs;

        $langcode=($lang?$lang:(empty($conf->global->MAIN_LANG_DEFAULT)?'auto':$conf->global->MAIN_LANG_DEFAULT));
        $langs->setDefaultLang($langcode);

        dol_syslog("Function: getProductsForCategory login=".$authentication['login']." id=".$id);

        if ($authentication['entity']) $conf->entity=$authentication['entity'];

        $objectresp=array();
        $errorcode='';$errorlabel='';
        $error=0;

        $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);


        if (! $error && !$id)
        {
            $error++;
            $errorcode='BAD_PARAMETERS'; $errorlabel="Parameter id must be provided.";
        }


        if (! $error)
        {
            $langcode=($lang?$lang:(empty($conf->global->MAIN_LANG_DEFAULT)?'auto':$conf->global->MAIN_LANG_DEFAULT));
            $langs->setDefaultLang($langcode);

            $fuser->getrights();

            if ($fuser->rights->produit->lire)
            {
                $categorie=new Categorie($db);
                $result=$categorie->fetch($id);
                if ($result > 0)
                {
                    $table = "product";
                    $field = "product";
                    $sql  = "SELECT fk_".$field." FROM ".MAIN_DB_PREFIX."categorie_".$table;
                    $sql .= " WHERE fk_categorie = ".$id;
                    $sql .= " ORDER BY fk_".$field." ASC" ;


                    dol_syslog("getProductsForCategory get id of product into category sql=".$sql);
                    $res  = $db->query($sql);
                    if ($res)
                    {
                        while ($rec = $db->fetch_array($res))
                        {
                            $obj = new Product($db);
                            $obj->fetch($rec['fk_'.$field]);
                            if($obj->status > 0 )
                            {
                                $dir = (!empty($conf->product->dir_output)?$conf->product->dir_output:$conf->service->dir_output);
                                $pdir = get_exdir($obj->id,2) . $obj->id ."/photos/";
                                $dir = $dir . '/'. $pdir;

                                $products[] = array(
                                    'id' => $obj->id,
                                    'ref' => $obj->ref,
                                    'ref_ext' => $obj->ref_ext,
                                    'label' => ! empty($obj->multilangs[$langs->defaultlang]["label"]) ? $obj->multilangs[$langs->defaultlang]["label"] : $obj->label,
                                    'description' => ! empty($obj->multilangs[$langs->defaultlang]["description"]) ? $obj->multilangs[$langs->defaultlang]["description"] : $obj->description,
                                    'date_creation' => dol_print_date($obj->date_creation,'dayhourrfc'),
                                    'date_modification' => dol_print_date($obj->date_modification,'dayhourrfc'),
                                    'note' => ! empty($obj->multilangs[$langs->defaultlang]["note"]) ? $obj->multilangs[$langs->defaultlang]["note"] : $obj->note,
                                    'status_tosell' => $obj->status,
                                    'status_tobuy' => $obj->status_buy,
                                    'type' => $obj->type,
                                    'barcode' => $obj->barcode,
                                    'barcode_type' => $obj->barcode_type,
                                    'country_id' => $obj->country_id>0?$obj->country_id:'',
                                    'country_code' => $obj->country_code,
                                    'custom_code' => $obj->customcode,

                                    'price_net' => $obj->price,
                                    'price' => $obj->price_ttc,
                                    'vat_rate' => $obj->tva_tx,

                                    'price_base_type' => $obj->price_base_type,

                                    'stock_real' => $obj->stock_reel,
                                    'stock_alert' => $obj->seuil_stock_alerte,
                                    'pmp' => $obj->pmp,
                                    'import_key' => $obj->import_key,
                                    'dir' => $pdir,
                                    'images' => $obj->liste_photos($dir,$nbmax=10)
                                );
                            }

                        }

                        // Retour
                        $objectresp = array(
                        'result'=>array('result_code'=>'OK', 'result_label'=>''),
                        'products'=> $products
                        );

                    }
                    else
                    {
                        $errorcode='NORECORDS_FOR_ASSOCIATION'; $errorlabel='No products associated'.$sql;
                        $objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
                        dol_syslog("getProductsForCategory:: ".$c->error, LOG_DEBUG);

                    }
                }
                else
                {
                    $error++;
                    $errorcode='NOT_FOUND'; $errorlabel='Object not found for id='.$id;
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

    
    
}
