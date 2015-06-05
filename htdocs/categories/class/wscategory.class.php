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

require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");


/**
 * Class for Dolibarr category webservices
 *
 * @author jfefe
 */
class wsCategory extends DolWS
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
     * Get category infos and children
     *
     * @param	array		$authentication		Array of authentication information
     * @param	int			$id					Id of object
     * @return	mixed
     */
    function getCategory($authentication,$id)
    {
        global $db,$conf,$langs;

        dol_syslog("Function: getCategory login=".$authentication['login']." id=".$id);

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
            $fuser->getrights();

            if ($fuser->rights->categorie->lire)
            {
                $categorie=new Categorie($db);
                $result=$categorie->fetch($id);
                if ($result > 0)
                {
                        $dir = (!empty($conf->categorie->dir_output)?$conf->categorie->dir_output:$conf->service->dir_output);
                        $pdir = get_exdir($categorie->id,2) . $categorie->id ."/photos/";
                        $dir = $dir . '/'. $pdir;

                        $cat = array(
                            'id' => $categorie->id,
                            'id_mere' => $categorie->id_mere,
                            'label' => $categorie->label,
                            'description' => $categorie->description,
                            'socid' => $categorie->socid,
                            //'visible'=>$categorie->visible,
                            'type' => $categorie->type,
                            'dir' => $pdir,
                            'photos' => $categorie->liste_photos($dir,$nbmax=10)
                        );

                        $cats = $categorie->get_filles();
                        if (count($cats) > 0)
                        {
                            foreach($cats as $fille)
                            {
                                $dir = (!empty($conf->categorie->dir_output)?$conf->categorie->dir_output:$conf->service->dir_output);
                                $pdir = get_exdir($fille->id,2) . $fille->id ."/photos/";
                                $dir = $dir . '/'. $pdir;
                                $cat['filles'][] = array(
                                    'id'=>$fille->id,
                                    'id_mere' => $categorie->id_mere,
                                    'label'=>$fille->label,
                                    'description'=>$fille->description,
                                    'socid'=>$fille->socid,
                                    //'visible'=>$fille->visible,
                                    'type'=>$fille->type,
                                    'dir' => $pdir,
                                    'photos' => $fille->liste_photos($dir,$nbmax=10)
                                );

                            }

                        }

                    // Create
                    $objectresp = array(
                        'result'=>parent::array_to_object(array('result_code'=>'OK', 'result_label'=>'')),
                        'categorie'=> $cat
                   );
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
