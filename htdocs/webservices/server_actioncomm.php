<?php
/* Copyright (C) 2006-2011 	Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012	 	Florian Henry			<florian.henry@open-concept.pro>
 * Copyright (C) 2015       Jean-François FERRY  <jfefe@aternatik.fr>
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

/**
 *       \file       htdocs/webservices/server_actioncomm.php
 *       \brief      File that is entry point to call Dolibarr WebServices
 *       \version    $Id: server_actioncomm.php,v 1.7 2010/12/19 11:49:37 eldy Exp $
 */

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once("../master.inc.php");
require_once(DOL_DOCUMENT_ROOT."/comm/action/class/wsactioncomm.class.php");


dol_syslog("Call ActionComm webservices interfaces");

// Enable and test if module web services is enabled
if (empty($conf->global->MAIN_MODULE_WEBSERVICES))
{
    $langs->load("admin");
    dol_syslog("Call Dolibarr webservices interfaces with module webservices disabled");
    print $langs->trans("WarningModuleNotActive",'WebServices').'.<br><br>';
    print $langs->trans("ToActivateModule");
    exit;
}

$wsActioncomm = new wsActioncomm();
$wsActioncomm->addClass('wsActioncomm');
$wsActioncomm->processRequest();
