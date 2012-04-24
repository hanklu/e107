<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2012 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Cron Administration
 *
 * $URL: https://e107.svn.sourceforge.net/svnroot/e107/trunk/e107_0.8/e107_admin/cron.php $
 * $Id: cron.php 12492 2011-12-30 16:09:10Z e107steved $
 *
 */

/**
 *
 * @package     e107
 * @subpackage	frontend
 * @version     $Id: cron.php 12492 2011-12-30 16:09:10Z e107steved $
 *	Ultra-simple Image-Gallery
 */
 
 /*
  * THIS SCRIPT IS HIGHLY EXPERIMENTAL. USE AT OWN RISK. 
  * 
  */
require_once("../../class2.php");
if (!getperms("P") || !plugInstalled('gallery'))
{
	header('location:'.e_BASE.'index.php');
	exit;
}

e107::getJS()->headerFile("http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js",1);
e107::getJs()->headerPlugin('gallery', 'jslib/lightbox/js/lightbox.js');
e107::getJs()->pluginCSS('gallery', 'jslib/lightbox/css/lightbox.css');
e107::getJs()->pluginCSS('gallery', 'gallery_style.css');

//e107::getJs()->headerPlugin('gallery', 'jslib/shadowbox/shadowbox.js');
//e107::getJs()->pluginCSS('gallery', 'jslib/shadowbox/shadowbox.css');

 // e107::getJs()->headerInline('Shadowbox.init();');
/*
e107::getJs()->headerInline('
Shadowbox.init({
    handleOversize: "drag",
  	overlayOpacity: 0.9,
  	viewportPadding: 50
});
');

*/


require_once(HEADERF);

class gallery
{
	private $catList = array();
	
	function __construct()
	{
		$this->catList = e107::getMedia()->getCategories('gallery');
		
		if(($_GET['cat']) && isset($this->catList[$_GET['cat']]))
		{
			$this->showImages($_GET['cat']);	
		}
		else
		{
			$this->listCategories();		
		}
	}
	
	function listCategories()
	{
		$template 	= e107::getTemplate('gallery');	
		$sc 		= e107::getScBatch('gallery',TRUE);
		
		$text = "";		
		foreach($this->catList as $val)
		{
			$sc->setParserVars($val);	
			$text .= e107::getParser()->parseTemplate($template['CAT_ITEM'],TRUE);
		}	
		$text = $template['CAT_START'].$text.$template['CAT_END'];
		e107::getRender()->tablerender("Gallery",$text);
	}
	

	function showImages($cat)
	{
		$mes 		= e107::getMessage();	
		$tp			= e107::getParser();			
		$template 	= e107::getTemplate('gallery');
		$sc 		= e107::getScBatch('gallery',TRUE);
					
		$sc->total 	= e107::getMedia()->countImages($cat);
		$sc->amount = 9; // TODO Add Pref. 
		$sc->curCat = $cat;
		$sc->from 	= ($_GET['frm']) ? intval($_GET['frm']) : 0;
		
		$list 		= e107::getMedia()->getImages($cat,$sc->from,$sc->amount);
	
		$inner = "";	
		
		foreach($list as $row)
		{
			$sc->setParserVars($row);				
			$inner .= $tp->parseTemplate($template['LIST_ITEM'],TRUE);
		}
					
		$text = $tp->parseTemplate($template['LIST_START'],TRUE);
		$text .= $inner; 	
		$text .= $tp->parseTemplate($template['LIST_END'],TRUE);
		
		e107::getRender()->tablerender("Gallery :: ".str_replace("_"," ",$cat),$mes->render().$text);
		
	}
	
}


new gallery;

require_once(FOOTERF);
exit;


?>