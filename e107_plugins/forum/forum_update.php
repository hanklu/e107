<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2009 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Forum upgrade routines
 *
 * $URL$
 * $Id$
 *
*/


define('e_ADMIN_AREA',true);
require_once('../../class2.php');

if (!getperms('P'))
{
	header('location:'.e_BASE.'index.php');
	exit;
}

error_reporting(E_ALL);
require_once(e_PLUGIN.'forum/forum_class.php');
require_once(e_ADMIN.'auth.php');
$forum = new e107forum;
$timestart = microtime();

$f = new forumUpgrade;
$e107 = e107::getInstance();

$upgradeNeeded = $f->checkUpdateNeeded();
$upgradeNeeded = true;
if(!$upgradeNeeded)
{
	$mes = e107::getMessage();

	$mes->addInfo("The forum is already at the most recent version, no upgrade is required");
	$ns->tablerender('Forum Upgrade', $mes->render());
	require(e_ADMIN.'footer.php');
	exit;
}

if(isset($_POST) && count($_POST))
{
	if(isset($_POST['skip_attach']))
	{
		$f->updateInfo['skip_attach'] = 1;
		$f->updateInfo['currentStep'] = 2;
		$f->setUpdateInfo();
	}

	if(isset($_POST['nextStep']))
	{
		$tmp = array_keys($_POST['nextStep']);
		$f->updateInfo['currentStep'] = $tmp[0];
		$f->setUpdateInfo();
	}
}


$currentStep = (isset($f->updateInfo['currentStep']) ? $f->updateInfo['currentStep'] : 1);
$stepParms = (isset($stepParms) ? $stepParms : '');

//echo "currentStep = $currentStep <br />";
if(function_exists('step'.$currentStep))
{
	$result = call_user_func('step'.$currentStep, $stepParms);
}

require(e_ADMIN.'footer.php');
exit;


function step1()
{
	global $f;
	$e107 = e107::getInstance();
	$mes = e107::getMessage();
	//Check attachment dir permissions
	if(!isset($f->updateInfo['skip_attach']))
	{
		$f->checkAttachmentDirs();
		if(isset($f->error['attach']))
		{
			$text = "
			<h3>ERROR:</h3>
			The following errors have occured.  These issues must be resolved if you ever want to enable attachment or image uploading in your forums. <br />If you do not ever plan on enabling this setting in your forum, you may click the 'skip' button <br /><br />
			";
			foreach($f->error['attach'] as $e)
			{
				$text .= '** '.$e.'<br />';
			}
			$text .= "
			<br />
			<form method='post' action='".e_SELF."?step=2'>
			<input class='btn' type='submit' name='retest_attach' value='Retest Permissions' />
			&nbsp;&nbsp;&nbsp;
			<input class='btn btn-success' type='submit' name='skip_attach'  value='Skip - I understand the risks' />
			</form>
			";
		}
		else
		{
			$mes->addSuccess("Attachment and attachment/thumb directories are writable");
			
			$text = "<form method='post' action='".e_SELF."?step=2'>
			<input class='btn btn-success' type='submit' name='nextStep[2]' value='Proceed to step 2' />
			</form>
			";
		}
		$e107->ns->tablerender('Step 1: Attachment directory permissions', $mes->render(). $text);
	}
}

function step2()
{
	$e107 = e107::getInstance();
	$mes = e107::getMessage();
	$ns = e107::getRender();
	
	if(!isset($_POST['create_tables']))
	{
		$text = "
		This step will create the new forum_thread, forum_post, and forum_attach tables.  It will also create a forum_new table that will become the 'real' forum table once the data from the current table is migrated.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='create_tables' value='Proceed with table creation' />
		</form>
		";
		$ns->tablerender('Step 2: Forum table creation', $text);
		return;
	}

	// FIXME - use db_verify. ??
	require_once(e_HANDLER.'db_table_admin_class.php');
	$db = new db_table_admin;

	$tabList = array('forum' => 'forum_new' , 'forum_thread' => '', 'forum_post' => '', 'forum_track' => '' ); //
	$ret = '';
	$failed = false;
	$text = '';
	foreach($tabList as $name => $rename)
	{
		$message = 'Creating table '.($rename ? $rename : $name);
		
		$result = $db->createTable(e_PLUGIN.'forum/forum_sql.php', $name, true, $rename);
		if($result === true)
		{
			$mes->addSuccess($message);
		//	$text .= 'Success <br />';
		}
		elseif($result !== true)
		{
		//	$text .= 'Failed <br />';
			$mes->addError($message);
			$failed = true;
		}
	}
	if($failed)
	{
		$mes->addError("Creation of table(s) failed.  You can not continue until these are created successfully!");
		
	}
	else
	{
			$text = "<form method='post' action='".e_SELF."?step=4'>
			<input class='btn button' type='submit' name='nextStep[4]' value='Proceed to step 3' />
			</form>";
	}
	$ns->tablerender('Step 2: Forum table creation', $mes->render(). $text);
}


// DEPRECATED - Done automatically via plugin-class. 
/*
function step3()
{
	$e107 = e107::getInstance();
	$stepCaption = 'Step 3: Extended user field creation';
	if(!isset($_POST['create_extended']))
	{
		$text = "
		This step will create the new extended user fields required for the new forum code: <br />
		* user_plugin_forum_posts (to track number of posts for each user)<br />
		* user_plugin_forum_viewed (to track threads viewed by each user<br />
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='create_extended' value='Proceed with field creation' />
		</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}
	require_once(e_HANDLER.'user_extended_class.php');
	$ue = new e107_user_extended;
	$fieldList = array(
	'plugin_forum_posts' => EUF_INTEGER,
	'plugin_forum_viewed' => EUF_TEXTAREA
	);
	$failed = false;
	foreach($fieldList as $fieldName => $fieldType)
	{
		$text .= 'Creating extended user field user_'.$fieldName.' -> ';
		$result = $ue->user_extended_add_system($fieldName, $fieldType);
		if($result)
		{
			$text .= 'Success <br />';
		}
		else
		{
			$text .= 'Failed <br />';
			$failed = true;
		}
	}
	if($failed)
	{
		$text .= '
		<br /><br />
		Creation of extended field(s) failed.  You can not continue until these are create successfully!
		';
	}
	else
	{
			$text .= "
			<br /><br />
			<form method='post'>
			<input class='btn button' type='submit' name='nextStep[4]' value='Proceed to step 4' />
			</form>
			";
	}
	$e107->ns->tablerender($stepCaption, $text);

}
*/
function step4()
{
	global $pref;
	$e107 = e107::getInstance();
	$mes = e107::getMessage();
	$ns = e107::getRender();
	
	$stepCaption = 'Step 4: Move user specific forum data and forum prefs';
	if(!isset($_POST['move_user_data']))
	{
		$text = "
		This step will move the main forum preferences into its own table row.  It will also move all user_viewed data from user table into the user extended table.<br />
		The user_forum field data will not be moved, as it will be recalculated later.<br />
		<br />
		Depending on the size of your user table, this step could take a while.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='move_user_data' value='Proceed with user data move' />
		</form>
		";
		$ns->tablerender($stepCaption, $text);
		return;
	}

	/** Convert forum prefs to their own row **/
	$fconf = e107::getPlugConfig('forum', '', false);
	$coreConfig = e107::getConfig();
	$old_prefs = array();
	foreach($pref as $k => $v)
	{
		if(substr($k, 0, 6) == 'forum_')
		{
			$nk = substr($k, 6);
			echo "Converting $k to $nk<br />";
			$old_prefs[$nk] = $v;
			$coreConfig->remove($k);
		}
	}
	$forumPrefList = array(
		'reported_post_email', 'email_notify', 'email_notify_on'
	);

	foreach($forumPrefList as $_fp) 
	{
		$mes->addDebug( "converting $_fp to $_fp" );
		$old_prefs[$_fp] = $coreConfig->get($_fp);
		$coreConfig->remove($_fp);
	}

	$fconf->setPref($old_prefs)->save(false, true);
	$coreConfig->save(false, true);

	$result = array(
	'usercount' => 0,
	'viewcount' => 0,
	'trackcount' => 0
	);
	$db = new db;
	if($db->select('user', 'user_id, user_viewed, user_realm',"user_viewed != '' OR user_realm != ''"))
	{
		require_once(e_HANDLER.'user_extended_class.php');
		$ue = new e107_user_extended;

		while($row = $db->fetch(MYSQL_ASSOC))
		{
			$result['usercount']++;
			$userId = (int)$row['user_id'];

			$viewed = $row['user_viewed'];
			$viewed = trim($viewed, '.');
			$tmp = preg_split('#\.+#', $viewed);
			$viewed = implode(',', $tmp);


			$realm = $row['user_realm'];
			$realm = str_replace('USERREALM', '', $realm);
			$realm = trim($realm, '-.');
			$trackList = preg_split('#\D+#', $realm);

//			echo 'user_id = '.$userId.'<br />';
//			echo 'viewed = '.$viewed.'<br />';
//			echo 'realm = '.$realm.'<br />';
//			echo 'tracking = ' . implode(',', $trackList).'<br />';
//			print_a($trackList);
//			echo "<br /><br />";

			if($viewed != '')
			{
				$ue->user_extended_setvalue($userId, 'plugin_forum_viewed', mysql_real_escape_string($viewed));
				$result['viewcount']++;
			}

			if(is_array($trackList) && count($trackList))
			{
				foreach($trackList as $threadId)
				{
					$result['trackcount']++;
					$threadId = (int)$threadId;
					if($threadId > 0)
					{
						$tmp = array();
						$tmp['track_userid'] = $userId;
						$tmp['track_thread'] = $threadId;

						$e107->sql->db_Insert('forum_track', $tmp);
					}
				}
			}
		}
	}

	$mes->addSuccess( "User data move results:
	<ul>
	<li>Number of users processed: {$result['usercount']} </li>
	<li>Number of viewed data processed: {$result['viewcount']} </li>
	<li>Number of tracked records added: {$result['trackcount']} </li>
	</ul>
	");
	
	$text = "<form method='post' action='".e_SELF."?step=5'>
	<input class='btn button' type='submit' name='nextStep[5]' value='Proceed to step 5' />
	</form>";

	$ns->tablerender($stepCaption, $mes->render().$text);

}

function step5()
{
	$e107 = e107::getInstance();
	
	$sql = e107::getDb();
	$ns = e107::getRender();
	$mes = e107::getMessage();
	
	$stepCaption = 'Step 5: Migrate forum data';
	if(!isset($_POST['move_forum_data']))
	{
		$mes->addInfo("This step will copy all of your forum configuration from the `forum` table into the `forum_new` table.<br />
		Once the information is successfully copied, the existing 1.0 forum table will be renamed `forum_old` and the newly created `forum_new` table will be renamed `forum`.<br />
		");
		$text = "
		<form method='post'>
		<input class='btn button' type='submit' name='move_forum_data' value='Proceed with forum data move' />
		</form>
		";
		$ns->tablerender($stepCaption, $mes->render().$text);
		return;
	}

	$counts = array('parens' => 0, 'forums' => 0, 'subs' => 0); //XXX Typo on 'parents' ?

	if($sql->select('forum'))
	{
		$forumList = $sql->db_getList();
		foreach($forumList as $forum)
		{
			if($forum['forum_parent'] == 0)
			{
				$counts['parents']++;
			}
			elseif($forum['forum_sub'] != 0)
			{
				$counts['subs']++;
			}
			else
			{
				$counts['forums']++;
			}

			$tmp = $forum;
			$tmp['forum_threadclass'] = $tmp['forum_postclass'];
			$tmp['forum_options'] = '_NULL_';
//			$tmp['_FIELD_TYPES'] = $ftypes['_FIELD_TYPES'];
			$sql->insert('forum_new', $tmp);
		}

		$mes->addInfo ( "
		Forum data move results:
		<ul>
		<li>Number of forum parents processed: {$counts['parents']} </li>
		<li>Number of forums processed: {$counts['forums']} </li>
		<li>Number of sub forums processed: {$counts['subs']} </li>
		</ul>
		");

		$result = $sql->gen('RENAME TABLE `#forum`  TO `#forum_old` ') ? e_MESSAGE_SUCCESS : E_MESSAGE_ERROR;
		$mes->add("Renaming forum to forum_old",$result);

		$result = $sql->gen('RENAME TABLE `#forum_new`  TO `#forum` ') ? e_MESSAGE_SUCCESS : E_MESSAGE_ERROR;
		$mes->add("Renaming forum_new to forum",$result);
		

		$text = "
		<form method='post' action='".e_SELF."?step=6'>
		<input class='btn button' type='submit' name='nextStep[6]' value='Proceed to step 6' />
		</form>
		";

		$ns->tablerender($stepCaption, $mes->render(). $text);

	}
}

function step6()
{
	global $f;
	$e107 = e107::getInstance();
	$ns = e107::getRender();
	$mes = e107::getMessage();
	$sql = e107::getDb();
	
	$stepCaption = 'Step 6: Thread and post data';
	$threadLimit = varset($_POST['threadLimit'], 1000);
	$lastThread = varset($f->updateInfo['lastThread'], 0);
	$maxTime = ini_get('max_execution_time');

	if(!isset($_POST['move_thread_data']))
	{
		$count = $sql->count('forum_t', '(*)', "WHERE thread_parent = 0 AND thread_id > {$lastThread}");
		$limitDropdown = createThreadLimitDropdown($count);
		$text = "
		<form method='post'>
		This step will copy all of your existing forum threads and posts into the new `forum_thread` and `forum_post` tables.<br /><br />
		Depending on your forum size and speed of server, this could take some time.  This routine will attempt to do it in steps in order to
		reduce the possibility of data loss and server timeouts.<br />
		<br />
		Your current timeout appears to be set at {$maxTime} seconds.  This routine will attempt to extend this time in order to process all threads,
		success will depend on your server configuration.  If you get a timeout while performing this function, return to this page and select fewer threads
		to process.
		<br /><br />
		There are {$count} forum threads to convert, we will be doing it in steps of: {$limitDropdown}
		<br /><br />
		<input class='btn button' type='submit' name='move_thread_data' value='Begin thread data move' />
		</form>
		";
		$ns->tablerender($stepCaption, $mes->render(). $text);
		return;
	}

	$count = $sql->count('forum_t', '(*)', "WHERE thread_parent=0 AND thread_id>{$lastThread}");
	if($count === false)
	{
		echo "error: Unable to determine last thread id";
		exit;
	}
	$done = false;

	$qry = "
	SELECT thread_id FROM `#forum_t`
	WHERE thread_parent = 0
	AND thread_id > {$lastThread}
	ORDER BY thread_id ASC
	LIMIT 0, {$threadLimit}
	";
	if($sql->gen($qry))
	{
		$postCount = 0;
		$threadList = $sql->db_getList();
		$text = '';
		foreach($threadList as $t)
		{
			set_time_limit(30);
			$id = (int)$t['thread_id'];
			$result = $f->migrateThread($id);
			if($result === false)
			{
				echo "ERROR! Failed to migrate thread id: {$id}<br />";
			}
			else
			{
				$postCount += ($result-1);
				$f->updateInfo['lastThread'] = $id;
				$f->setUpdateInfo();
			}
		}
		$text .= '<br />Successfully converted '.count($threadList)." threads and {$postCount} replies.<br />";
		$text .= "Last thread id = {$t['thread_id']}<br />";


		$count = $sql->count('forum_t', '(*)', "WHERE thread_parent = 0	AND thread_id > {$f->updateInfo['lastThread']}");
		if($count)
		{
			$limitDropdown = createThreadLimitDropdown($count);
			$text .= "
			<form method='post'>
			We still have {$count} threads remaining to convert, do them in steps of {$limitDropdown}
			<br /><br />
			<input class='btn btn-success' type='submit' name='move_thread_data' value='Continue thread data move' />
			</form>
			";
			$ns->tablerender($stepCaption, $mes->render(). $text);
		}
		else
		{
			$done = true;
		}
	}
	else
	{
		$done = true;
	}
	if($done)
	{
		$mes->addSuccess("Thread migration is complete!!");
		$text = "<form method='post' action='".e_SELF."?step=7'>
		<input class='btn button' type='submit' name='nextStep[7]' value='Proceed to step 7' />
		</form>";
		
		$ns->tablerender($stepCaption, $mes->render(). $text);
	}

}


function step7()
{
	$e107 = e107::getInstance();
	$stepCaption = 'Step 7: Calculate user post counts';
	if(!isset($_POST['calculate_usercounts']))
	{
		$text = "
		This step will calculate post count information for all users, as well as recount all for thread and reply counts.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='calculate_usercounts' value='Proceed with post count calculation' />
		</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}

	global $forum;
	require_once(e_HANDLER.'user_extended_class.php');
	$ue = new e107_user_extended;

	$counts = $forum->getUserCounts();
	foreach($counts as $uid => $count)
	{
		$ue->user_extended_setvalue($uid, 'user_plugin_forum_posts', $count, 'int');
	}
	$forum->forumUpdateCounts('all', true);


//	var_dump($counts);

	$text .= "
	Successfully recalculated forum posts for ".count($counts)." users.
	<br /><br />
	<form method='post' action='".e_SELF."?step=8'>
	<input class='btn button' type='submit' name='nextStep[8]' value='Proceed to step 8' />
	</form>
	";
	$e107->ns->tablerender($stepCaption, $text);
}

function step8()
{
	$e107 = e107::getInstance();
	$stepCaption = 'Step 8: Calculate last post information';
	if(!isset($_POST['calculate_lastpost']))
	{
		$text = "
		This step will recalculate all thread and forum lastpost information
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='calculate_lastpost' value='Proceed with lastpost calculation' />
		</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}

	global $forum;

	$forum->forumUpdateLastpost('forum', 'all', true);


//	$forum->forumUpdateLastpost('thread', 84867);


	$text .= "
	Successfully recalculated lastpost information for all forums and threads.
	<br /><br />
	<form method='post' action='".e_SELF."?step=9'>
	<input class='btn button' type='submit' name='nextStep[9]' value='Proceed to step 9' />
	</form>
	";
	$e107->ns->tablerender($stepCaption, $text);
}

function step9()
{
	$e107 = e107::getInstance();
	$stepCaption = 'Step 9: Migrate poll information';
	if(!isset($_POST['migrate_polls']))
	{
		$text = "
		This step will recalculate all poll information that has been entered in the forums.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='migrate_polls' value='Proceed with poll migration' />
		</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}

	$qry = "
	SELECT t.thread_id, p.poll_id FROM `#polls` AS p
	LEFT JOIN `#forum_thread` AS t ON t.thread_id =  p.poll_datestamp
	WHERE t.thread_id IS NOT NULL
	";
	if($e107->sql->db_Select_gen($qry))
	{
		while($row = $e107->sql->db_Fetch(MYSQL_ASSOC))
		{
			$threadList[] = $row['thread_id'];
		}
		foreach($threadList as $threadId)
		{
			if($e107->sql->db_Select('forum_thread', 'thread_options', 'thread_id = '.$threadId, 'default'))
			{
				$row = $e107->sql->db_Fetch(MYSQL_ASSOC);
				if($row['thread_options'])
				{
					$opts = unserialize($row['thread_options']);
					$opts['poll'] = 1;
				}
				else
				{
					$opts = array('poll' => 1);
				}
				$tmp = array();
				$tmp['thread_options'] = serialize($opts);
				$tmp['WHERE'] = 'thread_id = '.$threadId;
//				$tmp['_FIELD_TYPES']['thread_options'] = 'escape';
				$e107->sql->db_Update('forum_thread', $tmp);
			}
		}
	}
	else
	{
		$text = 'No threads found! <br />';
	}

	$text .= "
	Successfully migrated forum poll information for ".count($threadList)." thread poll(s).
	<br /><br />
	<form method='post' action='".e_SELF."?step=10'>
	<input class='btn button' type='submit' name='nextStep[10]' value='Proceed to step 10' />
	</form>
	";
	$e107->ns->tablerender($stepCaption, $text);
}

function step10()
{
	$e107 = e107::getInstance();
	$sql = e107::getDb();
	$ns = e107::getRender();
	
	global $f;
	$stepCaption = 'Step 10: Migrate forum attachments';
	
	//FIXME - Files should be moved to e107_media/files/forum/
	if(!isset($_POST['migrate_attachments']))
	{
		$text = "
		This step will migrate all forum attachment information.<br />
		All files will be moved from the e107_files/public directory into the e107_plugins/forum/attachment directory and related posts will be updated accordingly.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='migrate_attachments' value='Proceed with attachment migration' />
		</form>
		";
		$ns->tablerender($stepCaption, $text);
		return;
	}

	$qry = "
	SELECT post_id, post_entry FROM `#forum_post`
	WHERE post_entry REGEXP '_[[:digit:]]+_FT'
	";
	
	if($sql->gen($qry))
	{
		while($row = $sql->fetch(MYSQL_ASSOC))
		{
			$postList[] = $row;
		}
		$i = 0;
		$pcount = 0;
		$f->log("Found ".count($postList). " posts with attachments");
		
		//XXX Run post through $tp->toHtml() and then use $tp->getTag() to find images or files.? 
		foreach($postList as $post)
		{
//			echo htmlentities($post['post_entry'])."<br />";
			$i++;
//			if($pcount++ > 10) { die('here 10'); }
			$attachments = array();
			$foundFiles = array();

//			echo $post['post_entry']."<br /><br />";

			//[link={e_FILE}public/1230091080_1_FT0_julia.jpg][img:width=60&height=45]{e_FILE}public/1230091080_1_FT0_julia_.jpg[/img][/link][br]
			//Check for images with thumbnails linking to full size
			if(preg_match_all('#\[link=(.*?)\]\[img.*?\]({e_FILE}.*?)\[/img\]\[/link\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					$att = array();
					$att['thread_id'] = $post['thread_id'];
					$att['type'] = 'img';
					$att['html'] = $match[0];
					$att['name'] = $match[1];
					$att['thumb'] = $match[2];
					$attachments[] = $att;
					$foundFiles[] = $match[1];
					$foundFiles[] = $match[2];
				}
			}

			if(preg_match_all('#\[link=(.*?)\]\[img.*?\](\.\./\.\./e107_files/public/.*?)\[/img\]\[/link\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					$att = array();
					$att['thread_id'] = $post['thread_id'];
					$att['type'] = 'img';
					$att['html'] = $match[0];
					$att['name'] = $match[1];
					$att['thumb'] = $match[2];
					$attachments[] = $att;
					$foundFiles[] = $match[1];
					$foundFiles[] = $match[2];
				}
			}


			//<div class=&#039;spacer&#039;>[img:width=604&height=453]{e_FILE}public/1229562306_1_FT0_julia.jpg[/img]</div>
			//Check for attached full-size images
			if(preg_match_all('#\[img.*?\]({e_FILE}.*?_FT\d+_.*?)\[/img\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					//Ensure it hasn't already been handled above
					if(!in_array($match[1], $foundFiles))
					{
						$att = array();
						$att['thread_id'] = $post['thread_id'];
						$att['type'] = 'img';
						$att['html'] = $match[0];
						$att['name'] = $match[1];
						$att['thumb'] = '';
						$attachments[] = $att;
					}
				}
			}

			if(preg_match_all('#\[img.*?\](\.\./\.\./e107_files/public/.*?_FT\d+_.*?)\[/img\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					//Ensure it hasn't already been handled above
					if(!in_array($match[1], $foundFiles))
					{
						$att = array();
						$att['thread_id'] = $post['thread_id'];
						$att['type'] = 'img';
						$att['html'] = $match[0];
						$att['name'] = $match[1];
						$att['thumb'] = '';
						$attachments[] = $att;
					}
				}
			}

			//[file={e_FILE}public/1230090820_1_FT0_julia.zip]julia.zip[/file]
			//Check for attached file (non-images)
			if(preg_match_all('#\[file=({e_FILE}.*?)\](.*?)\[/file\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					$att = array();
					$att['thread_id'] = $post['thread_id'];
					$att['type'] = 'file';
					$att['html'] = $match[0];
					$att['name'] = $match[1];
					$att['thumb'] = '';
					$attachments[] = $att;
				}
			}

			if(preg_match_all('#\[file=(\.\./\.\./e107_files/public/.*?)\](.*?)\[/file\]#ms', $post['post_entry'], $matches, PREG_SET_ORDER))
			{
				foreach($matches as $match)
				{
					$att = array();
					$att['thread_id'] = $post['thread_id'];
					$att['type'] = 'file';
					$att['html'] = $match[0];
					$att['name'] = $match[1];
					$att['thumb'] = '';
					$attachments[] = $att;
				}
			}

			if(count($attachments))
			{
				$f->log("found ".count($attachments)." attachments");
				$newValues = array();
				$info = array();
				$info['post_entry'] = $post['post_entry'];
				foreach($attachments as $attachment)
				{
					$error = '';
					$f->log($attachment['name']);
					if($f->moveAttachment($attachment, $post['post_id'], $error))
					{
						$fInfo = pathinfo($attachment['name']);
//						$_file = split('/', $attachment['name']);
						$newval = $attachment['type'].'*'.$fInfo['basename'];
						switch($attachment['type'])
						{
							//If file, add real name to entry
							case 'file':
								$tmp = explode('_', $fInfo['basename'], 4);
								$newval .= '*'.$tmp[3];
								break;

							//If image and it has a thumb, add thumb filename to entry
							case 'img':
								if($attachment['thumb'])
								{
									$fInfo = pathinfo($attachment['thumb']);
									$newval .= '*'.$fInfo['basename'];
								}
								break;
						}
						$newValues[] = $newval;
//						echo "Newval = $newval <br />";
//						echo "Removing from post:".htmlentities($attachment['html'])."<br />";
						$info['post_entry'] = str_replace($attachment['html'], '', $info['post_entry']);
					}
					else
					{
						$errorText .= "Failure processing post {$post['post_id']} - file {$attachment['name']} - {$error}<br />";
						$f->log("Failure processing post {$post['post_id']} - file {$attachment['name']} - {$error}");
					}
				}
//				echo $errorText."<br />";

				// Did we make any changes at all?
				if(count($newValues))
				{
					$info['WHERE'] = 'post_id = '.$post['post_id'];
					$info['post_attachments'] = implode(',', $newValues);
//					print_a($info);
					$sql->db_Update('forum_post', $info);
				}
//				echo $post['thread_thread']."<br />";
//				print_a($newValues);
//				echo $info['newpost']."<br />--------------------------------------<br />";
//			Update db values now
			}
		}
	}
	else
	{
		$text = 'No forum attachments found! <br />';
	}

//	$forum->forumUpdateLastpost('thread', 84867);

	$text .= "
	Successfully migrated forum attachment information for ".count($postList)." post(s).
	<br /><br />
	<form method='post' action='".e_SELF."?step=11'>
	<input class='btn button' type='submit' name='nextStep[11]' value='Proceed to step 11' />
	</form>
	";
	$e107->ns->tablerender($stepCaption, $text);
}

function step11()
{
	$e107 = e107::getInstance();
	$stepCaption = 'Step 11: Delete old attachments';
	if(!isset($_POST['delete_orphans']))
	{
		$text = "
		The previous versions of the forum had difficulty deleting attachment files when posts or threads were deleted.
		<br />
		As a result of this, there is a potential for numerous files to exist that do not point to anything. In this step
		we will try to identify these files and delete them.
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='delete_orphans' value='Proceed with attachment deletion' />
		</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}

	global $forum;
	require_once(e_HANDLER.'file_class.php');
	$f = new e_file;

	$flist = $f->get_files(e_FILE.'public', '_\d+_FT\d+_');
	$numFiles = count($flist);

	if($numFiles)
	{
		if($_POST['delete_orphans'] == 'Delete files')
		{
			//Do the deletion
			$success = 0;
			$failText = '';
			foreach($flist as $file)
			{
				$fileName = e_FILE.'public/'.$file['fname'];
				$r = unlink($fileName);
				if($r) {
					$success++;
				}
				else
				{
					$failText .= "Deletion failed: {$file['fname']}<br />";
				}
			}
			if($failText)
			{
				$failText = "<br /><br />The following failures occured: <br />".$failText;
			}
			$text .= "
				Successfully removed {$success} orphaned files <br />
				{$failText}
				<br /><br />
				<form method='post' action='".e_SELF."?step=12'>
				<input class='btn button' type='submit' name='nextStep[12]' value='Proceed to step 12' />
				</form>
			";
			$e107->ns->tablerender($stepCaption, $text);
			return;
		}
		$text = "There were {$numFiles} orphaned files found<br /><br />";
		if($_POST['delete_orphans'] == 'Show files' || $numFiles < 31)
		{
			$i=1;
			foreach($flist as $file)
			{
				$text .= $i++.') '.$file['fname'].'<br />';
			}
			$extra = '';
		}
		else
		{
			$extra = "<input class='btn button' type='submit' name='delete_orphans' value='Show files' />&nbsp; &nbsp; &nbsp; &nbsp;";
		}
		$text .= "
			<br /><br />
			<form method='post'>
			{$extra}
			<input class='btn button' type='submit' name='delete_orphans' value='Delete files' />
			</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}
	else
	{
		$text .= "
			There were no orphaned files found <br />
			<br /><br />
			<form method='post' action='".e_SELF."?step=12'>
			<input class='btn button' type='submit' name='nextStep[12]' value='Proceed to step 12' />
			</form>
		";
		$e107->ns->tablerender($stepCaption, $text);
		return;
	}
}

function step12()
{
	$sql 	= e107::getDb();
	$ns 	= e107::getRender();
	$mes 	= e107::getMessage();
	
	$f 		= new forumUpgrade;
		
	$stepCaption = 'Step 12: Delete old forum data';
	
	if(!isset($_POST['delete_old']))
	{
		$text = "
		The forum upgrade should now be complete.<br />  During the upgrade process the old forum tables were
		retained, it is now time to remove the tables.<br /><br />
		We will also be marking the forum upgrade as completed!
		<br /><br />
		<br /><br />
		<form method='post'>
		<input class='btn button' type='submit' name='delete_old' value='Remove old forum tables' />
		</form>
		";
		$ns->tablerender($stepCaption, $text);
		return;
	}

	$qryArray = array(
		"DROP TABLE `#forum_old`",
		"DROP TABLE `#forum_t",
		"DELETE * FROM `#generic` WHERE gen_type = 'forumUpgrade'"
	);
	
	foreach($qryArray as $qry)
	{
		$sql->gen($qry);
	}
	
	$ret = $f->setNewVersion();

	$mes->addSuccess("Congratulations, the forum upgrade is now completed!<br /><br />{$ret}");
	$ns->tablerender($stepCaption,$mes->render(). $text);
	return;
}






class forumUpgrade
{
	var $newVersion = '2.0';
	var $error = array();
	public $updateInfo;
	private $attachmentData;
	private $logf;

	public function __construct()
	{
		$this->updateInfo['lastThread'] = 0;
		$this->attachmentData = array();
		$this->logf = e_MEDIA.'files/forum_upgrade.txt';
		$this->getUpdateInfo();
	}

	public function log($msg, $append=true)
	{
//		echo "logf = ".$this->logf."<br />";
		$txt = sprintf("%s - %s\n", date('m/d/Y H:i:s'), $msg);
//		echo $txt."<br />";
		$flag = ($append ? FILE_APPEND : '');
		file_put_contents($this->logf, $txt, $flag);
	}

	public function checkUpdateNeeded()
	{
		return true; 
	//	include_once(e_PLUGIN.'forum/forum_update_check.php');
	//	$needed = update_forum_08('check');
	//	return !$needed;
	}

	function checkAttachmentDirs()
	{
		$dirs = array(
		e_MEDIA.'files/plugins/forum/attachments/',
		e_MEDIA.'files/plugins/forum/attachments/thumb'
		);

		foreach($dirs as $dir)
		{
			if(!file_exists($dir))
			{
				if(!mkdir($dir, 0777, true))
				{
					$this->error['attach'][] = "Directory '{$dir}' does not exist and I was unable to create it";
				}
			}
			else
			{
				if(!is_writable($dir))
				{
					$this->error['attach'][] = "Directory '{$dir}' exits, but is not writeable";
				}
			}
		}
	}

	function getUpdateInfo()
	{
		$e107 = e107::getInstance();
		if($e107->sql->db_Select('generic', '*', "gen_type = 'forumUpgrade'"))
		{
			$row = $e107->sql->db_Fetch(MYSQL_ASSOC);
			$this->updateInfo = unserialize($row['gen_chardata']);
		}
		else
		{
			$qry = "INSERT INTO `#generic` (gen_type) VALUES ('forumUpgrade')";
			$e107->sql->db_Select_gen($qry);
			$this->updateInfo = array();
		}
	}

	function setUpdateInfo()
	{
		$e107 = e107::getInstance();
		$info = mysql_real_escape_string(serialize($this->updateInfo));
		$qry = "UPDATE `#generic` Set gen_chardata = '{$info}' WHERE gen_type = 'forumUpgrade'";
		$e107->sql->db_Select_gen($qry);
	}

	function setNewVersion()
	{
		$pref = e107::getPref();
		$e107 = e107::getInstance();
		$sql = e107::getDb();
		
		$sql->update('plugin',"plugin_version = '{$this->newVersion}' WHERE plugin_name='Forum'");
		$pref['plug_installed']['forum'] = $this->newVersion;
		save_prefs();
		return "Forum Version updated to version: {$this->newVersion} <br />";
	}

	function migrateThread($threadId)
	{
		global $forum;
		$e107 = e107::getInstance();
		$threadId = (int)$threadId;
		if($e107->sql->db_Select('forum_t', '*', "thread_parent = {$threadId} OR thread_id = {$threadId}", 'default'))
		{
			$threadData = $e107->sql->db_getList();
			foreach($threadData as $post)
			{
				if($post['thread_parent'] == 0)
				{
					$result = $this->addThread($post);
					if($result)
					{
						$result = $this->addPost($post);
					}
				}
				else
				{
					$result = $this->addPost($post);
				}
			}
			return ($result ? count($threadData) : false);
		}
		return false;
	}

	function addThread(&$post)
	{
		global $forum;
		$e107 = e107::getInstance();
		$thread = array();
		$thread['thread_id'] = $post['thread_id'];
		$thread['thread_name'] = $post['thread_name'];
		$thread['thread_forum_id'] = $post['thread_forum_id'];
		$thread['thread_datestamp'] = $post['thread_datestamp'];
		$thread['thread_views'] = $post['thread_views'];
		$thread['thread_active'] = $post['thread_active'];
		$thread['thread_sticky'] = $post['thread_s'];
		$userInfo = $this->getUserInfo($post['thread_user']);
		$thread['thread_user'] = $userInfo['user_id'];
		$thread['thread_user_anon'] = $userInfo['anon_name'];
		//  If thread marked as 'tracked by starter', we must convert to using forum_track table
		if($thread['thread_active'] == 99 && $thread['thread_user'] > 0)
		{
			$forum->track('add', $thread['thread_user'], $thread['thread_id'], true);
			$thread['thread_active'] = 1;
		}

//		$thread['_FIELD_TYPES'] = $forum->fieldTypes['forum_thread'];
//		$thread['_FIELD_TYPES']['thread_name'] = 'escape'; //use escape to prevent double entities

		$result =  $e107->sql->db_Insert('forum_thread', $thread);
		return $result;

//		return $e107->sql->db_Insert('forum_thread', $thread);
//		print_a($thread);
	}

	function addPost(&$post)
	{
		global $forum;
		$e107 = e107::getInstance();
		$newPost = array();
		$newPost['post_id'] = $post['thread_id'];
		$newPost['post_thread'] = ($post['thread_parent'] == 0 ? $post['thread_id'] : $post['thread_parent']);
		$newPost['post_entry'] = $post['thread_thread'];
		$newPost['post_forum'] = $post['thread_forum_id'];
		$newPost['post_datestamp'] = $post['thread_datestamp'];
		$newPost['post_edit_datestamp'] = ($post['thread_edit_datestamp'] ? $post['thread_edit_datestamp'] : '_NULL_');

		$userInfo = $this->getUserInfo($post['thread_user']);
		$newPost['post_user'] = $userInfo['user_id'];
		$newPost['post_user_anon'] = $userInfo['anon_name'];
		$newPost['post_ip'] = $userInfo['user_ip'];

//		$newPost['_FIELD_TYPES'] = $forum->fieldTypes['forum_post'];
//		$newPost['_FIELD_TYPES']['post_entry'] = 'escape'; //use escape to prevent double entities
//		print_a($newPost);
//		exit;
		$result =$e107->sql->db_Insert('forum_post', $newPost);
//		exit;
		return $result;

	}

	function getUserInfo(&$info)
	{
		$e107 = e107::getInstance();
		$tmp = explode('.', $info);
		$ret = array(
		'user_id' => 0,
		'user_ip' => '_NULL_',
		'anon_name' => '_NULL_'
		);

		if(count($tmp) == 2)
		{
			$id = (int)$tmp[0];
			if($id == 0) //Anonymous post
			{
				$_tmp = explode(chr(0), $tmp[1]);
				if(count($_tmp) == 2)  //Ip address exists
				{
					$ret['user_ip'] = $e107->ipEncode($_tmp[1]);
					$ret['anon_name'] = $_tmp[0];
				}
			}
			else
			{
				$ret['user_id'] = $id;
			}
		}
		else
		{
			if(is_numeric($info) && $info > 0)
			{
				$ret['user_id'] = $info;
			}
			else
			{
				$ret['anon_name'] = 'Unknown';
			}
		}
		return $ret;
	}

	function moveAttachment($attachment, $post_id, &$error)
	{
		set_time_limit(30);
//		$tmp = explode('/', $attachment['name']);
		$attachment['name'] = str_replace(array(' ', "\n", "\r"), '', $attachment['name']);
		$old = str_replace('{e_FILE}', e_FILE, $attachment['name']);
		$fileInfo = pathinfo($attachment['name']);
		$new = e_MEDIA.'files/plugins/forum/attachments/'.$fileInfo['basename'];
		$hash = md5($new);
		if(!file_exists($old))
		{
			if(isset($this->attachmentData[$hash]))
			{
				$error = "Post {$post_id} - Attachment already migrated with post: ".$this->attachmentData[$hash];
			}
			else
			{
				$error = 'Original attachment not found (orphaned?)';
			}
			return false;
		}
		if(!file_exists($new))
		{
			$this->log("Copying [{$old}] -> [{$new}]");
			$r = copy($old, $new);
			$this->attachmentData[$hash] = $post_id;
//			$r = true;
		}
		else
		{
			//File already exists, show some sort of error
			if(isset($this->attachmentData[$hash]))
			{
				$error = "Post {$post_id} - Attachment already migrated with post: ".$this->attachmentData[$hash];
			}
			else
			{
				$error = 'Attachment file already exists';
			}
			return false;
		}
		if(!$r)
		{
			//File copy failed!
			$error = 'Copy of attachments failed';
			return false;
		}

		$oldThumb = '';
		if($attachment['thumb'])
		{
			$tmp = explode('/', $attachment['thumb']);
			$fileInfo = pathinfo($attachment['thumb']);

			$oldThumb = str_replace('{e_FILE}', e_FILE, $attachment['thumb']);
//			$newThumb = e_PLUGIN.'forum/attachments/thumb/'.$tmp[1];
			$newThumb = e_MEDIA.'files/plugins/forum/attachments/thumb/'.$fileInfo['basename'];
			$hash = md5($newThumb);
			if(!file_exists($newThumb))
			{
				$r = copy($oldThumb, $newThumb);
//				$r = true;
			}
			else
			{
				//File already exists, show some sort of error
				if(isset($this->attachmentData[$hash]))
				{
					$error = "Post {$post_id} - Thumb already migrated with post: ".$this->attachmentData[$hash];
				}
				else
				{
				$error = 'Thumb file already exists';
				}
				return false;
			}
			if(!$r)
			{
				//File copy failed
				$error = 'Copy of thumb failed';
				return false;
			}
		}

		//Copy was successful, let's delete the original files now.
//		$r = true;
		$r = unlink($old);
		if(!$r)
		{
			$error = 'Was unable to delete old attachment: '.$old;
			return false;
		}
		if($oldThumb)
		{
//			$r = true;
			$r = unlink($oldThumb);
			if(!$r)
			{
				$error = 'Was unable to delete old thumb: '.$oldThumb;
				return false;
			}
		}
		return true;
	}


}

function createThreadLimitDropdown($count)
{
	$ret = "
	<select class='tbox' name='threadLimit'>
	";
	$last = min($count, 10000);
	if($count < 2000) {
		$ret .= "<option value='{$count}'>{$count}</option>";
	}
	else
	{
		for($i=2000; $i<$count; $i+=2000)
		{
			$ret .= "<option value='{$i}'>{$i}</option>";
		}
		if($count < 10000)
		{
			$ret .= "<option value='{$count}'>{$count}</option>";
		}
	}
	$ret .= '</select>';
	return $ret;
}


function forum_update_adminmenu()
{		
		$action = 1;

		$var[1]['text'] = '1 - Permissions';
		$var[1]['link'] = e_SELF;

		$var[2]['text'] = '2 - Create new tables';
		$var[2]['link'] = '#';

		$var[3]['text'] = '3 - Create extended fields';
		$var[3]['link'] = '#';

		$var[4]['text'] = '4 - Move user data';
		$var[4]['link'] = '#';

		$var[5]['text'] = '5 - Migrate forum config';
		$var[5]['link'] = '#';

		$var[6]['text'] = '6 - Migrate threads/replies';
		$var[6]['link'] = '#';

		$var[7]['text'] = '7 - Recalc all counts';
		$var[7]['link'] = '#';

		$var[8]['text'] = '8 - Calc lastpost data';
		$var[8]['link'] = '#';

		$var[9]['text'] = '9 - Migrate any poll data';
		$var[9]['link'] = '#';

		$var[10]['text'] = '10 - Migrate any attachments';
		$var[10]['link'] = '#';

		$var[11]['text'] = '11 - Delete old attachments';
		$var[11]['link'] = '#';

		$var[12]['text'] = '12 - Delete old forum data';
		$var[12]['link'] = '#';

		
		if(isset($_GET['step']))
		{
		//	$action = key($_POST['nextStep']);		
			$action = intval($_GET['step']);	
		}

		show_admin_menu('Forum Upgrade', $action, $var);
}

?>