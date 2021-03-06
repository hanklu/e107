<?php
/*
* e107 website system
*
* Copyright (c) 2008-2009 e107 Inc (e107.org)
* Released under the terms and conditions of the
* GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
*
* Custom Featurebox install/uninstall/update routines
*
* $Source: /cvs_backup/e107_0.8/e107_plugins/featurebox/featurebox_setup.php,v $
* $Revision$
* $Date$
* $Author$
*
*/

if (!defined('e107_INIT')) { exit; }

class featurebox_setup
{
/*	
 	function install_pre($var)
	{
		// print_a($var);
		// echo "custom install 'pre' function<br /><br />";
	}
*/
	function install_post($var)
	{
		e107::includeLan(e_PLUGIN.'featurebox/languages/'.e_LANGUAGE.'_admin_featurebox.php');
		$mes = e107::getMessage();
		
		$query = array();
		$query['fb_category_id'] = 0;
		$query['fb_category_title'] = FBLAN_INSTALL_04;
		$query['fb_category_template'] = 'default';
		$query['fb_category_random'] = 0;
		$query['fb_category_class'] = e_UC_PUBLIC;
		$query['fb_category_limit'] = 1;
		$inserted = e107::getDb()->db_Insert('featurebox_category', $query);
		
		$query['fb_category_id'] = 0;
		$query['fb_category_title'] = FBLAN_INSTALL_03;
		$query['fb_category_template'] = 'unassigned';
		$query['fb_category_random'] = 0;
		$query['fb_category_class'] = e_UC_NOBODY;
		$query['fb_category_limit'] = 0;
		$inserted1 = e107::getDb()->db_Insert('featurebox_category', $query);
		$status = $inserted && $inserted1 ? E_MESSAGE_SUCCESS : E_MESSAGE_ERROR; 
		$mes->add(FBLAN_INSTALL_01, $status);
		
		if($inserted)
		{
			$query = array();
			$query['fb_id'] = 0;
			$query['fb_category'] = $inserted;
			$query['fb_title'] = 'Default Title';
			$query['fb_text'] = 'Default Message';
			$query['fb_mode'] = 0;
			$query['fb_class'] = e_UC_PUBLIC;
			$query['fb_rendertype'] = 0;
			$query['fb_template'] = 'default';
			$query['fb_order'] = 0;
			$query['fb_image'] = '';
			$query['fb_imageurl'] = '';
			$status = e107::getDb('sql2')->db_Insert('featurebox', $query) ? E_MESSAGE_SUCCESS : E_MESSAGE_ERROR; 
		}
		else 
		{
			$status = E_MESSAGE_ERROR;
		}
		$mes->add(FBLAN_INSTALL_02, $status);
	}
/*	
	function uninstall_options()
	{
	
	}


	function uninstall_post($var)
	{
		// print_a($var);
	}
*/	
	function upgrade_post($var)
	{
		$sql = e107::getDb();
		$currentVersion = $var->current_plug['plugin_version'];
		//$newVersion = $var->plug_vars['@attributes']['version'];
		if($currentVersion == '1.0')
		{
			$query = array();
			$query['fb_category_id'] = 0;
			$query['fb_category_title'] = FBLAN_INSTALL_03;
			$query['fb_category_template'] = 'unassigned';
			$query['fb_category_random'] = 0;
			$query['fb_category_class'] = e_UC_NOBODY;
			$query['fb_category_limit'] = 0;
			$inserted = $sql->db_Insert('featurebox_category', $query);
			$status = $inserted ? E_MESSAGE_SUCCESS : E_MESSAGE_ERROR; 
			e107::getMessage()->add(FBLAN_INSTALL_01, $status);
			if($sql->getLastErrorNumber())
			{
				e107::getMessage()->addDebug($sql->getLastErrorText().'<br /><pre>'.$sql->getLastQuery().'</pre>');
			}
		}
	}
}
