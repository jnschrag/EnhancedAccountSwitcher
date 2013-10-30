<?php
/**
 * Enhanced Account Switcher for MyBB 1.6
 * Copyright (c) 2012-2013 doylecc
 * http://mybbplugins.de.vu
 *
 * based on the Plugin:
 * Account Switcher 1.0 by Harest
 * Copyright (c) 2011 Harest
 *
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

if(!defined("IN_MYBB"))
	die("We all know this is your fault.");


//Caching templates
global $templatelist, $templates, $db;
if(isset($templatelist))
{
	$templatelist .= ',';
}
$templatelist .= 'as_header, global_pm_switch_alert';
$templates->cache($db->escape_string($templatelist));

if(my_strpos($_SERVER['PHP_SELF'], 'usercp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'as_usercp_nav, as_usercp_options, as_usercp_userbit, as_usercp_users, as_usercp';
}
if(my_strpos($_SERVER['PHP_SELF'], 'private.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'as_usercp_nav';
}


$plugins->add_hook("usercp_menu", "accountswitcher_usercpmenu", 40);
$plugins->add_hook("usercp_start", "accountswitcher_usercp");
$plugins->add_hook("global_start", "accountswitcher_header");
$plugins->add_hook("member_login", "accountswitcher_switch");
$plugins->add_hook("admin_user_groups_edit", "accountswitcher_admingroups_edit");
$plugins->add_hook("admin_user_groups_edit_commit", "accountswitcher_admingroups_commit");
$plugins->add_hook("showthread_start", "accountswitcher_post");
$plugins->add_hook("newreply_start", "accountswitcher_post");
$plugins->add_hook("newthread_start", "accountswitcher_post");
$plugins->add_hook("private_do_send_end", "accountswitcher_pm");
$plugins->add_hook("private_read_end", "accountswitcher_pm");


//Plugin info
function accountswitcher_info()
{
	global $lang, $db, $plugins_cache;

	$lang->load("accountswitcher");

	$accountswitcher_info = array(
		"name"			=> $lang->as_name,
		"description"	=> $lang->as_desc,
		"website"		=> "http://mybbplugins.de.vu",
		"author"		=> "doylecc, chainria",
		"version"		=> "1.3",
		"compatibility"	=> "16*",
		"guid"			=> ""
	);

		//Add link to settings to info
		if(accountswitcher_is_installed() && is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active']['accountswitcher'])
		{
			$result = $db->simple_select('settinggroups', 'gid', "name = 'Enhanced Account Switcher'");
			$set = $db->fetch_array($result);
			if(!empty($set))
			{
				$accountswitcher_info['description'] .= "  <br /><span style=\"float: right; padding-right: 20px;\"><img src=\"styles/default/images/icons/custom.gif\" alt=\"\" />&nbsp;&nbsp;<a href=\"index.php?module=config-settings&amp;action=change&amp;gid=".(int)$set['gid']."\">".$lang->as_name_settings."</a></span>";
			}
		}
	return $accountswitcher_info;
}

//Install the plugin
function accountswitcher_install()
{
	global $db, $mybb, $cache, $lang;

	//Avoid duplicates database columns
	if($db->field_exists("as_uid", "users"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` DROP COLUMN `as_uid`");
	}
	if($db->field_exists("as_canswitch", "usergroups"))
	{
		$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `as_canswitch`");
	}
	if($db->field_exists("as_limit", "usergroups"))
	{
		$db->query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP COLUMN `as_limit`");
	}

	//Add database columns
	$db->write_query("ALTER TABLE `".TABLE_PREFIX."users` ADD `as_uid` INT(11) NOT NULL DEFAULT '0'");
	$db->write_query("ALTER TABLE `".TABLE_PREFIX."usergroups` ADD `as_canswitch` INT(1) NOT NULL DEFAULT '0', ADD `as_limit` SMALLINT(5) NOT NULL DEFAULT '0'");

	$cache->update_forums();

	$lang->load("accountswitcher");

	//Add the new templates
	$as_template[0] = array(
		"title" 	=> "as_usercp_nav",
		"template"	=> '<tr><td class="trow1 smalltext"><a href="usercp.php?action=as_edit" class="usercp_nav_item usercp_nav_usergroups">{$lang->as_name}</a></td></tr>',
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> TIME_NOW
	);
	$as_template[1] = array(
		"title" 	=> 'as_usercp',
		"template"	=> '<html><head><title>{$mybb->settings[\\\'bbname\\\']} - {$lang->as_name}</title>{$headerinclude}</head>
						<body>
						{$header}
						<table width="100%" border="0" align="center">
							<tr>
							{$usercpnav}
								<td valign="top">
									<table border="0" cellspacing="{$theme[\\\'borderwidth\\\']}" cellpadding="{$theme[\\\'tablespace\\\']}" class="tborder">
										<tr><td class="thead" colspan="2"><strong>{$lang->as_name}</strong></td></tr>
										<tr>
											<td class="trow1" valign="top">
												<fieldset class="trow2">
													<legend><strong>{$lang->as_usercp_options}</strong></legend>
													{$as_usercp_options}
												</fieldset>
											</td>
											{$as_usercp_users}
										</tr>
									</table>
								</td>
							</tr>
						</table>
						{$footer}
						</body>
						</html>',
		"sid"		=> -1,
		"version"	=> 1.0,
		"dateline"	=> TIME_NOW
	);
	$as_template[2] = array (
		"title" 	=> 'as_usercp_options',
		"template" 	=> '<form method="post" action="usercp.php">
						<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
							{$as_usercp_input}
						</table>
						</form>',
		"sid" 		=> -1,
		"version" 	=> 1.0,
		"dateline" 	=> TIME_NOW
	);
	$as_template[3] = array (
		"title" 	=> 'as_usercp_users',
		"template" 	=> '<td class="trow1" valign="top">
							<fieldset class="trow2">
								<legend><strong>{$lang->as_usercp_users}</strong></legend>
								<table cellspacing="0" cellpadding="{$theme[\\\'tablespace\\\']}">
									<tr><td>{$lang->as_usercp_attached}</td></tr>
									{$as_usercp_userbit}
								</table>
							</fieldset>
						</td>',
		"sid" 		=> -1,
		"version" 	=> 1.0,
		"dateline" 	=> TIME_NOW
	);
	$as_template[4] = array (
		"title" 	=> 'as_usercp_userbit',
		"template" 	=> '<tr><td>
							<form method="post" action="usercp.php">
							<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
							<input type="hidden" name="action" value="as_detachuser" />
							<input type="hidden" name="uid" value="{$attachedOneUID}" />
							<table width="100%" border="0" style="border:1px solid #000;margin:2px 0;padding:3px;" class="trow1">
								<tr>
									<td>{$attachedOneName}</td>
									<td align="right">
										<input type="submit" value="{$lang->as_detachuser}" name="{$lang->as_detachuser}" class="button" />
									</td>
								</tr>
							</table>
							</form>
						</td></tr>',
		"sid" 		=> -1,
		"version" 	=> 1.0,
		"dateline" 	=> TIME_NOW
	);
	$as_template[5] = array (
		"title" 	=> 'as_header',
		"template" 	=> '<hr />{$as_header_userbit}',
		"sid" 		=> -1,
		"version" 	=> 1.0,
		"dateline" 	=> TIME_NOW
	);
	$as_template[6] = array (
		"title" 	=> 'global_pm_switch_alert',
		"template" 	=> $db->escape_string('{$privatemessage_switch_text}'),
		"sid" 		=> -1,
		"version" 	=> 1.0,
		"dateline" 	=> TIME_NOW
	);
	foreach ($as_template as $row)
	{
		$db->insert_query("templates", $row);
	}

	/**
	 *
	 * Settings
	 *
	 **/

	//Avoid duplicates
	$query_setgr = $db->simple_select('settinggroups','gid','name="Enhanced Account Switcher"');
	$ams = $db->fetch_array($query_setgr);
	$db->delete_query('settinggroups',"gid='".$ams['gid']."'");
	$db->delete_query('settings',"gid='".$ams['gid']."'");

	//Add the settings
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");

	//Add settinggroup for global settings
	$account_jumper_group = array(
		"name" => "Enhanced Account Switcher",
		"title" => $lang->as_name,
		"description" => $lang->aj_group_descr,
		"disporder" => $rows+1,
		"isdefault" => 0
	);
	$db->insert_query("settinggroups", $account_jumper_group);
	$gid = $db->insert_id();

	//Add settings for the settinggroup
	$account_jumper_1 = array(
		"name" => "aj_postjump",
		"title" => $lang->aj_postjump_title,
		"description" => $lang->aj_postjump_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 1,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_1);

	$account_jumper_2 = array(
		"name" => "aj_changeauthor",
		"title" => $lang->aj_changeauthor_title,
		"description" => $lang->aj_changeauthor_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 2,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_2);

	$account_jumper_3 = array(
		"name" => "aj_pmnotice",
		"title" => $lang->aj_pmnotice_title,
		"description" => $lang->aj_pmnotice_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 3,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_3);

	$account_jumper_4 = array(
		"name" => "aj_profile",
		"title" => $lang->aj_profile_title,
		"description" => $lang->aj_profile_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 4,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_4);

	$account_jumper_5 = array(
		"name" => "aj_away",
		"title" => $lang->aj_away_title,
		"description" => $lang->aj_away_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 6,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_5);

	$account_jumper_6 = array(
		"name" => "aj_postbit",
		"title" => $lang->aj_postbit_title,
		"description" => $lang->aj_postbit_descr,
		"optionscode" => "yesno",
		"value" => 1,
		"disporder" => 5,
		"gid" => (int)$gid
		);
	$db->insert_query("settings", $account_jumper_6);

	//Refresh settings.php
	rebuild_settings();

}

//Is the plugin installed?
function accountswitcher_is_installed()
{
	global $db;

	if($db->field_exists("as_uid", "users") && $db->field_exists("as_canswitch", "usergroups") && $db->field_exists("as_limit", "usergroups")) {
		return true;
	}
	else
	{
		return false;
	}
}

//Activate the plugin
function accountswitcher_activate()
{
	global $db, $mybb, $lang;

	$lang->load("accountswitcher");

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('header_welcomeblock_member', '#\<!--\sAccountSwitcher\s--\>(.+)\<!--\s/AccountSwitcher\s--\>#is', '', '', 0);
	find_replace_templatesets('newreply', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('newthread', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('showthread_quickreply', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('newreply', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets('newthread', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets('showthread_', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$pm_switch_notice}')."#i", '', '', 0);

	find_replace_templatesets('header_welcomeblock_member', '#{\$lang->welcome_pms_usage}#', '{\$lang->welcome_pms_usage}<!-- AccountSwitcher -->{$as_header}<!-- /AccountSwitcher -->');
	find_replace_templatesets('newreply', "#".preg_quote('<input type="submit" class="button" name="submit"')."#s", '{$as_post}&nbsp;<input type="submit" class="button" name="submit"');
	find_replace_templatesets('newthread', "#".preg_quote('<input type="submit" class="button" name="submit"')."#s", '{$as_post}&nbsp;<input type="submit" class="button" name="submit"');
	find_replace_templatesets('showthread_quickreply', "#".preg_quote('<input type="submit" class="button" value="{$lang->post_reply}')."#s", '{$as_post}&nbsp;<input type="submit" class="button" value="{$lang->post_reply}');
	find_replace_templatesets('newreply', "#".preg_quote('{$loginbox}')."#s", '<a name="switch" id="switch"></a>{$loginbox}');
	find_replace_templatesets('newthread', "#".preg_quote('{$loginbox}')."#s", '<a name="switch" id="switch"></a>{$loginbox}');
	find_replace_templatesets('showthread', "#".preg_quote('{$quickreply}')."#s", '<a name="switch" id="switch"></a>{$quickreply}');
	find_replace_templatesets("header", "#".preg_quote('{$pm_notice}')."#i", '{$pm_notice} {$pm_switch_notice}');

	accountswitcher_cache();

	//If we are upgrading...add the new settings
	$query = $db->simple_select("settings", "*", "name='aj_postjump'");
	$result = $db->num_rows($query);

	if(!$result)
	{
		$query2 = $db->simple_select("settinggroups", "COUNT(*) as rows");
		$rows = $db->fetch_field($query2, "rows");

		//Add settinggroup for the settings
		$account_jumper_group = array(
			"name" => "Enhanced Account Switcher",
			"title" => $lang->as_name,
			"description" => $lang->aj_group_descr,
			"disporder" => $rows+1,
			"isdefault" => 0
		);
		$db->insert_query("settinggroups", $account_jumper_group);
		$gid = $db->insert_id();

		//Add settings for the settinggroup
		$account_jumper_1 = array(
			"name" => "aj_postjump",
			"title" => $lang->aj_postjump_title,
			"description" => $lang->aj_postjump_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 1,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_1);

		$account_jumper_2 = array(
			"name" => "aj_changeauthor",
			"title" => $lang->aj_changeauthor_title,
			"description" => $lang->aj_changeauthor_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 2,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_2);

		$account_jumper_3 = array(
			"name" => "aj_pmnotice",
			"title" => $lang->aj_pmnotice_title,
			"description" => $lang->aj_pmnotice_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 3,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_3);

		$account_jumper_4 = array(
			"name" => "aj_profile",
			"title" => $lang->aj_profile_title,
			"description" => $lang->aj_profile_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 4,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_4);

		$account_jumper_5 = array(
			"name" => "aj_away",
			"title" => $lang->aj_away_title,
			"description" => $lang->aj_away_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 6,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_5);

		$account_jumper_6 = array(
			"name" => "aj_away",
			"title" => $lang->aj_postbit_title,
			"description" => $lang->aj_postbit_descr,
			"optionscode" => "yesno",
			"value" => 1,
			"disporder" => 5,
			"gid" => (int)$gid
			);
		$db->insert_query("settings", $account_jumper_6);
	}

	//Refresh settings.php
	rebuild_settings();

	//If we are upgrading...add the new template
	$query3 = $db->simple_select('templates','*','title="global_pm_switch_alert"');
	$result_template = $db->num_rows($query3);

	if(!$result_template)
	{
			$template = array (
			"title" 	=> 'global_pm_switch_alert',
			"template" 	=> $db->escape_string('{$privatemessage_switch_text}'),
			"sid" 		=> -1,
			"version" 	=> 1.0,
			"dateline" 	=> TIME_NOW
		);
		$db->insert_query("templates", $template);
	}

}

//Deactivate the plugin
function accountswitcher_deactivate()
{
	accountswitcher_cache(true);
}

//Uninstall the plugin
function accountswitcher_uninstall()
{
	global $db, $cache;

	//Delete templates
	$db->delete_query("templates", "`title` = 'as_usercp_nav'");
	$db->delete_query("templates", "`title` = 'as_usercp'");
	$db->delete_query("templates", "`title` = 'as_usercp_users'");
	$db->delete_query("templates", "`title` = 'as_usercp_userbit'");
	$db->delete_query("templates", "`title` = 'as_header'");
	$db->delete_query("templates", "title = 'global_pm_switch_alert'");

	//Undo template changes
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('header_welcomeblock_member', '#\<!--\sAccountSwitcher\s--\>(.+)\<!--\s/AccountSwitcher\s--\>#is', '', '', 0);
	find_replace_templatesets('newreply', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('newthread', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('showthread_quickreply', "#".preg_quote('{$as_post}&nbsp;')."#s", '', '', 0);
	find_replace_templatesets('newreply', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets('newthread', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets('showthread_', "#".preg_quote('<a name="switch" id="switch"></a>')."#s", '', '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$pm_switch_notice}')."#i", '', '', 0);

	//Delete table columns
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` DROP `as_uid`");
	$db->query("ALTER TABLE `".TABLE_PREFIX."usergroups` DROP `as_canswitch`, DROP `as_limit`");

	//Delete settings
	$query_setgr = $db->simple_select('settinggroups','gid','name="Enhanced Account Switcher"');
	$ams = $db->fetch_array($query_setgr);
	$db->delete_query('settinggroups',"gid='".$ams['gid']."'");
	$db->delete_query('settings',"gid='".$ams['gid']."'");

	$cache->update_forums();

  /**
   *
   * Delete cache
   *
   **/
	if(is_object($cache->handler))
	{
		$cache->handler->delete('accountswitcher');
	}
	//Delete database cache
	$db->delete_query("datacache", "title='accountswitcher'");
}


//########## FUNCTIONS ##########

//Add to header panel a list of users attached to the account
function accountswitcher_header()
{
	global $db, $mybb, $lang, $templates, $theme, $as_header, $as_3d_menu, $cache;

	if($mybb->user['uid'] != "0")
	{
		$lang->load("accountswitcher");

		//Get number of users attached to this account
		$count = 0;
		$as_header_userbit = '';

		//Get index.php for redirecting
		if ($mybb->input['action'] != 'login')
		{
			session_start();
			$_SESSION['page'] = THIS_SCRIPT;
		}

		//If there are users attached and current user can use the Enhanced Account Switcher...
		if($mybb->usergroup['as_canswitch'] == "1")
		{
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					$attachedOne['uid'] = (int)$account['uid'];
					$attachedOne['username'] = htmlspecialchars_uni($account['username']);
					$attachedOne['as_uid'] = (int)$account['as_uid'];
					if($attachedOne['as_uid'] == $mybb->user['uid'])
					{
						$count++;
						if($count > 0)
						{
							$as_header_userbit.= "&nbsp;&bull;&nbsp;<a href='{$mybb->settings['bburl']}/member.php?action=login&amp;do=switch&amp;uid={$attachedOne['uid']}&amp;my_post_key={$mybb->post_code}'>{$attachedOne['username']}</a>";
							$as_3d_menu.= "<li><a href='{$mybb->settings['bburl']}/member.php?action=login&amp;do=switch&amp;uid={$attachedOne['uid']}&amp;my_post_key={$mybb->post_code}'>{$attachedOne['username']}</a></li>";
						}
					}
				}
				eval("\$as_header = \"".$templates->get('as_header')."\";");
			}
		}

		//If there are no users attached to the current account but the current account is attached to another user
		if($count == 0 && $mybb->user['as_uid'] != '0')
		{
			//Get the master account
			$master = get_user($mybb->user['as_uid']);
			//Get the masters permission
			$permission = user_permissions($master['uid']);

			//If the master has permission to use the Enhanced Account Switcher, get the userlist
			if($permission['as_canswitch'] == "1")
			{
				//Create link to the master
				$as_header_userbit.= "&nbsp;&bull;&nbsp;<a href='".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".(int)$master['uid']."&amp;my_post_key={$mybb->post_code}'>".htmlspecialchars_uni($master['username'])."</a>";
				$as_3d_menu.= "<li><a href='".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".(int)$master['uid']."&amp;my_post_key={$mybb->post_code}'>".htmlspecialchars_uni($master['username'])."</a></li>";

				//Get all users attached to master from the cache
				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOne['uid'] = (int)$account['uid'];
						$attachedOne['username'] = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						//Leave current user out
						if($attachedOne['uid'] == "{$mybb->user['uid']}") continue;
						if($attachedOne['as_uid'] == $master['uid'])
						{
							$as_header_userbit.= "&nbsp;&bull;&nbsp;<a href='{$mybb->settings['bburl']}/member.php?action=login&amp;do=switch&amp;uid={$attachedOne['uid']}&amp;my_post_key={$mybb->post_code}'>{$attachedOne['username']}</a>";
							$as_3d_menu.= "<li><a href='{$mybb->settings['bburl']}/member.php?action=login&amp;do=switch&amp;uid={$attachedOne['uid']}&amp;my_post_key={$mybb->post_code}'>{$attachedOne['username']}</a></li>";
						}
					}
					eval("\$as_header = \"".$templates->get('as_header')."\";");
				}
			}
		}
	}
}


//Add a select box with the users attached to the account on the left of the post button
function accountswitcher_post()
{
	global $db, $mybb, $templates, $as_post, $cache;

	if($mybb->settings['aj_postjump'] == 1)
	{
		if($mybb->user['uid'] != "0")
		{

			//Get number of users attached to this account
			$count = 0;
			$as_post_userbit = '';

			//If there are users attached and the current user can use the Enhanced Account Switcher...
			if($mybb->usergroup['as_canswitch'] == "1")
			{
				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOne['uid'] = (int)$account['uid'];
						$attachedOne['username'] = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						if($attachedOne['as_uid'] == $mybb->user['uid'])
						{
							$count++;
							if($count > 0)
							{
								$as_post_userbit.= "<option value=\"".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</option>
								";
							}
						}
					}
						$as_post = '<select name="userswitch" onchange="window.location=(\'member.php?action=login&amp;do=switch&amp;uid=\'+this.options[this.selectedIndex].value)">
	<option value="#">'.htmlspecialchars_uni($mybb->user['username']).'</option>
	'.$as_post_userbit.'
	</select>';
				}
			}

			//If there are no users attached to the current account but the current account is attached to another user
			if($count == 0 && $mybb->user['as_uid'] != '0')
			{
				//Get the master account
				$master = get_user($mybb->user['as_uid']);
				//Get the masters permission
				$permission = user_permissions($master['uid']);

				//If master has permission to use the Enhanced Account Switcher, get the userlist
				if($permission['as_canswitch'] == "1")
				{
					//Create link to master
					$as_post_userbit.= "<option value=\"".(int)$master['uid']."&amp;my_post_key=".$mybb->post_code."\">".htmlspecialchars_uni($master['username'])."</option>
					";

					//Get all users attached to master from the cache
					$accounts = $cache->read('accountswitcher');
					if(is_array($accounts))
					{
						foreach ($accounts as $key => $account)
						{
							$attachedOne['uid'] = (int)$account['uid'];
							$attachedOne['username'] = htmlspecialchars_uni($account['username']);
							$attachedOne['as_uid'] = (int)$account['as_uid'];
							//Leave current user out
							if($attachedOne['uid'] == "{$mybb->user['uid']}") continue;
							if($attachedOne['as_uid'] == $master['uid'])
							{
								$as_post_userbit.= "<option value=\"".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</option>
								";
							}
						}
					$as_post = '<select name="userswitch" onchange="window.location=(\'member.php?action=login&amp;do=switch&amp;uid=\'+this.options[this.selectedIndex].value)">
	<option value="#">'.htmlspecialchars_uni($mybb->user['username']).'</option>
	'.$as_post_userbit.'
	</select>';
					}
				}
			}
		}
	}
}


//The switch function
function accountswitcher_switch()
{
	global $db, $mybb, $lang;

	if($mybb->user['uid'] != "0")
	{
		//Get permissions for this user
		$userPermission = user_permissions($mybb->user['uid']);

		//Get permissions for the master. First get the master
		$master = get_user($mybb->user['as_uid']);

		//Get his permissions
		$masterPermission = user_permissions($master['uid']);

		//If one of both has the permission allow to switch
		if($userPermission['as_canswitch'] == "1" || $masterPermission['as_canswitch'] == "1")
		{
			$lang->load("accountswitcher");

			verify_post_check($mybb->input['my_post_key']);

			//Get the uid sanitized
			$user = get_user((int)$mybb->input['uid']);

			//Check if user exists
			if(!$user) error($lang->as_invaliduser);

			//Get the last page for redirecting
			$ret_page = '';
			$redirect_url = $mybb->settings['bburl'].'/index.php';
			if($_SERVER['HTTP_REFERER'] && strpos($_SERVER['HTTP_REFERER'], "action=login") === false)
			{
				if (!empty($_SESSION['page']) && $_SESSION['page'] == 'index.php')
				{
					$ret_page = 'index.php';
				}
				else
				{
					$ret_page = htmlentities(basename($_SERVER['HTTP_REFERER']));
				}
				$redirect_url = $mybb->settings['bburl'].'/'.$ret_page;
			}
			$redirect_url = str_replace('&amp;processed=1', '', $redirect_url);
			//Make the switch!
			my_unsetcookie('mybbuser');
			my_setcookie('mybbuser', (int)$user['uid'].'_'.htmlspecialchars_uni($user['loginkey']), null, true);

			//Redirect to page and anchor
			redirect($redirect_url.'#switch', "");
			exit;
		}
	}
}


//Add button to the usercp navigation
function accountswitcher_usercpmenu()
{
	global $db, $mybb, $lang, $templates, $theme, $usercpmenu;

	//Show the button if the user can use the Enhanced Account Switcher or the user is attached to an account
	if($mybb->usergroup['as_canswitch'] == "1" || $mybb->user['as_uid'] != "0")
	{
		$lang->load("accountswitcher");

		eval("\$usercpmenu .= \"".$templates->get("as_usercp_nav")."\";");
	}
}


//Get the usercp Enhanced Account Switcher page and handle all actions
function accountswitcher_usercp()
{
	global $db, $mybb, $lang, $cache, $templates, $theme, $headerinclude, $header, $usercpnav, $usercpmenu, $as_usercp, $as_usercp_options, $as_usercp_users, $as_usercp_userbit, $footer;

	$lang->load("accountswitcher");

	//Get the master account of the current user
	$master = get_user($mybb->user['as_uid']);

	//If the user has no master...
	//Get the number of attached ones
	$count = $db->fetch_array($db->simple_select("users", "COUNT(as_uid) AS number", "as_uid='".(int)$mybb->user['uid']."'"));

	//Get limit for users group
	$limit = (int)$mybb->usergroup['as_limit'];
	$as_usercp_input = '';

	//Check if user can use the Enhanced Account Switcher or is attached to an account. If yes grant access to the page
	if($mybb->input['action'] == "as_edit" && ($mybb->usergroup['as_canswitch'] == "1" || $mybb->user['as_uid'] != "0"))
	{
		add_breadcrumb($lang->nav_usercp, "usercp.php");
		add_breadcrumb($lang->as_name);
		//If the user is attached to an account he only can detach himself
		if($mybb->user['as_uid'] != "0")
		{
			$lang->as_isattached = $lang->sprintf($lang->as_isattached, htmlspecialchars($master['username']));

			//Build the detach button
			$as_usercp_input.= "<input type='hidden' name='action' value='as_detach' />
								<table width='100%' border='0'>
									<tr><td>{$lang->as_isattached}</td></tr>
									<tr><td>
										<input type='submit' value='{$lang->as_detach}' name='{$lang->as_detach}' class='button' />
									</td></tr>";
			eval("\$as_usercp_options = \"".$templates->get('as_usercp_options')."\";");
		}
		//If user is free
		else
		{
			//If limit is set to 0 = unlimited
			if($limit['as_limit'] != "0") $lang->as_usercp_attached = $lang->sprintf($lang->as_usercp_attached, (int)$count['number'], $limit['as_limit']);
			if($limit['as_limit'] == "0") $lang->as_usercp_attached = $lang->sprintf($lang->as_usercp_attached, (int)$count['number'], $lang->as_unlimited);

			//If there are no users attached grant full acccess
			if($count['number'] == "0")
			{
				$as_usercp_input.= "<input type='hidden' name='action' value='as_attach' />
									<table width='100%' border='0'>
										<tr><td><input type='radio' name='select' value='attachuser' checked='ckeched' />&nbsp;{$lang->as_attachuser}<br /></td></tr>
										<tr><td><input type='radio' name='select' value='attachme' />&nbsp;{$lang->as_attachme}</td></tr>
										<tr><td><span class='smalltext'>{$lang->as_username}</span></td></tr>
										<tr><td><input type='text' name='username' size='30' /></td></tr>
										<tr><td><span class='smalltext'>{$lang->as_password}</span></td></tr>
										<tr><td><input type='password' name='password' size='30' /></td></tr>
										<tr><td>
											<input type='submit' value='{$lang->as_attach}' name='{$lang->as_attach}' class='button' />
										</td></tr>";
				eval("\$as_usercp_options = \"".$templates->get('as_usercp_options')."\";");
			}
			//If there are users attached allow only user attachment
			if($count['number'] != "0")
			{
				$as_usercp_input.= "<input type='hidden' name='action' value='as_attach' />
									<input type='hidden' name='select' value='attachuser' />
									<table width='100%' border='0'>
										<tr><td>{$lang->as_attachuser}</td></tr>
										<tr><td><span class='smalltext'>{$lang->as_username}</span></td></tr>
										<tr><td><input type='text' name='username' size='30' /></td></tr>
										<tr><td><span class='smalltext'>{$lang->as_password}</span></td></tr>
										<tr><td><input type='password' name='password' size='30' /></td></tr>
										<tr><td>
											<input type='submit' value='{$lang->as_attach}' name='{$lang->as_attach}' class='button' />
										</td></tr>";
				eval("\$as_usercp_options = \"".$templates->get('as_usercp_options')."\";");

				//Get attached ones from the cache
				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOneUID = (int)$account['uid'];
						$attachedOneName = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						if($attachedOne['as_uid'] == $mybb->user['uid'])
						{
							eval("\$as_usercp_userbit .= \"".$templates->get('as_usercp_userbit')."\";");
						}
					}
					eval("\$as_usercp_users = \"".$templates->get('as_usercp_users')."\";");
				}
			}
		}
		eval("\$as_usercp = \"".$templates->get('as_usercp')."\";");
		output_page($as_usercp);
		die();
	}

//########## ACTIONS ##########
	//Attach current user to another account
	if($mybb->input['action'] == "as_attach" && $mybb->input['select'] == "attachme" && $mybb->request_method == "post")
	{
		verify_post_check($mybb->input['my_post_key']);

		//Check if current user is already attached
		if($mybb->user['as_uid'] != "0") error($lang->as_alreadyattached);

		//Validate input
		$select = $db->escape_string($mybb->input['select']);
		$username = $db->escape_string($mybb->input['username']);
		$password = $db->escape_string($mybb->input['password']);

		//Get the target
		$target = $db->fetch_array($db->simple_select("users", "uid, usergroup", "username='{$username}'"));
		//Check targets permission and limit
		$permission = $db->fetch_array($db->simple_select("usergroups", "as_canswitch, as_limit", "gid='".(int)$target['usergroup']."'"));
		//Count number of attached accounts
		$count = $db->fetch_array($db->simple_select("users", "COUNT(as_uid) AS number", "as_uid='".(int)$target['uid']."'"));

		//If target has permission
		if($permission['as_canswitch'] == "0") error($lang->as_usercp_nopermission);
		if($permission['as_limit'] != "0" && $count['number'] == $permission['as_limit']) error($lang->as_limitreached);

		//Set uid of the new master
		$as_uid = array("as_uid" => (int)$target['uid']);

		//Update database
		$db->update_query("users", $as_uid, "uid='".(int)$mybb->user['uid']."'");
		accountswitcher_cache();
		redirect("usercp.php?action=as_edit", "Account hat been attached successfully!");
	}
	//Detach current user from master
	if($mybb->input['action'] == "as_detach" && $mybb->request_method == "post")
	{
		verify_post_check($mybb->input['my_post_key']);

		//Reset master uid
		$as_uid = array("as_uid" => "0");

		//Update database
		if($db->update_query("users", $as_uid, "uid='".(int)$mybb->user['uid']."'"))
		{
			accountswitcher_cache();
			//If user can use Enhanced Account Switcher stay here
			if($mybb->usergroup['as_canswitch'] == "1") redirect("usercp.php?action=as_edit", "Account Switcher updated successfully!");

			//Else redirect to usercp
				redirect("usercp.php", "Account detached successfully!");
		}
	}
	//Attach an user to the current account
	if($mybb->input['action'] == "as_attach" && $mybb->input['select'] == "attachuser" && $mybb->request_method == "post" && $mybb->user['as_uid'] == "0")
	{
		verify_post_check($mybb->input['my_post_key']);
		//Validate input
		$select = $db->escape_string($mybb->input['select']);
		$username = $db->escape_string($mybb->input['username']);
		$password = $db->escape_string($mybb->input['password']);

		//Get the chosen one
		$chosenOne = $db->fetch_array($db->simple_select("users", "*", "username='{$username}'"));

		//Check if user exists and password is correct
		$user = get_user((int)$chosenOne['uid']);
		if(!$user) error($lang->as_invaliduser);
		if(validate_password_from_uid($chosenOne['uid'], $password) == false) error($lang->as_invaliduser);

		//Check allowed limit
		if($limit['as_limit'] != "0" && $limit['as_limit'] == $count['number']) error($lang->as_limitreached);

		//Set his new masters uid
		$as_uid = array("as_uid" => (int)$mybb->user['uid']);

		//Update database
		$db->update_query("users", $as_uid, "uid='".(int)$chosenOne['uid']."'");
		accountswitcher_cache();
		redirect("usercp.php?action=as_edit", "User hat been attached successfully!");
	}
	//Detach user from current account
	if($mybb->input['action'] == "as_detachuser" && $mybb->request_method == "post")
	{
		verify_post_check($mybb->input['my_post_key']);
		//Validate input
		if(!is_numeric($mybb->input['uid'])) die("We all know this is your fault.");

		//Reset master uid
		$as_uid = array("as_uid" => "0");

		$db->update_query("users", $as_uid, "uid='".(int)$mybb->input['uid']."'");
		accountswitcher_cache();
		redirect("usercp.php?action=as_edit", "User has been detached successfully!");
	}
}

// ##### Admin CP functions #####
function accountswitcher_admingroups_edit()
{
	global $plugins;

	//Add new hook
	$plugins->add_hook("admin_formcontainer_end", "accountswitcher_admingroups_editform");
}

function accountswitcher_admingroups_editform()
{
	global $mybb, $lang, $form, $form_container;

	$lang->load("accountswitcher");

	//Create the input fields
	if($form_container->_title == $lang->misc)
	{
		$as_group_can = array(
			$form->generate_check_box("as_canswitch", 1, $lang->as_admin_canswitch, array("checked" => $mybb->input['as_canswitch']))
		);
		$as_group_limit = "<div class=\"group_settings_bit\">".$lang->as_admin_limit."<br />".$form->generate_text_box("as_limit", $mybb->input['as_limit'], array('class' => 'field50'))."</div>";
		$form_container->output_row($lang->as_name, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $as_group_can)."</div>".$as_group_limit);
	}
}

function accountswitcher_admingroups_commit()
{
	global $mybb, $updated_group;

	$updated_group['as_canswitch'] = (int)$mybb->input['as_canswitch'];
	$updated_group['as_limit'] = (int)$mybb->input['as_limit'];
}


//########## Cache, post-edit, profile functions ################

//Update cache when reading and sending pm's
function accountswitcher_pm()
{
	accountswitcher_cache();
}

//Build and empty cache
function accountswitcher_cache($clear=false)
{
	global $cache;
	if($clear==true)
	{
		$cache->update('accountswitcher',false);
	}
	else
	{
		global $db;
		$switchers = array();
		$query=$db->simple_select('users','uid,username,as_uid,pmnotice,unreadpms','as_uid != 0');
		while($switcher = $db->fetch_array($query))
		{
			$switchers[$switcher['uid']] = $switcher;
		}
		$cache->update('accountswitcher', $switchers);
	}
}

//Change post author on edit
$plugins->add_hook("editpost_action_start", "accountswitcher_author");

function accountswitcher_author()
{
	global $mybb, $pid, $cache, $post, $db, $theme, $headerinclude, $lang;

	if($mybb->input['changeauthor'] == 1 && $mybb->settings['aj_changeauthor'] == 1)
	{
		if($mybb->user['uid'] != $post['uid'])
		{
			error_no_permission();
		}

		$lang->load("accountswitcher");

		//Get the attached users
		if($mybb->user['uid'] != "0")
		{
			//Get the number of users attached to this account
			$count = 0;

			//If there are users attached and the current user can use the Enhanced Account Switcher...
			if($mybb->usergroup['as_canswitch'] == "1")
			{
				$as_author_userbit.= "<option value=\"".(int)$mybb->user['uid']."\" selected=\"selected\">".htmlspecialchars_uni($mybb->user['username'])."</option>
				";

				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOne['uid'] = (int)$account['uid'];
						$attachedOne['username'] = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						if($attachedOne['as_uid'] == $mybb->user['uid'])
						{
							$count++;
							if($count > 0)
							{
								$as_author_userbit.= "<option value=\"".$attachedOne['uid']."\">".$attachedOne['username']."</option>
								";
							}
						}
					}
				}
			}

			//If there are no users attached to current account but the current account is attached to another user
			if($count == 0 && $mybb->user['as_uid'] != '0')
			{
				//Get the master
				$master = get_user($mybb->user['as_uid']);
				//Get masters permissions
				$permission = user_permissions($master['uid']);

				//If the master has permission to use the Enhanced Account Switcher, get the userlist
				if($permission['as_canswitch'] == "1")
				{
					//Create link to master
					$as_author_userbit.= "<option value=\"".(int)$master['uid']."\">".htmlspecialchars_uni($master['username'])."</option>
					";

					//Get all users attached to master from the cache
					$accounts = $cache->read('accountswitcher');
					if(is_array($accounts))
					{
						foreach ($accounts as $key => $account)
						{
							$attachedOne['uid'] = (int)$account['uid'];
							$attachedOne['username'] = htmlspecialchars_uni($account['username']);
							$attachedOne['as_uid'] = (int)$account['as_uid'];
							//Leave current user out
							if($attachedOne['uid'] == $mybb->user['uid'])
							{
								continue;
							}
							if($attachedOne['as_uid'] == $master['uid'])
							{
								$as_author_userbit.= "<option value=\"".$attachedOne['uid']."\">".$attachedOne['username']."</option>
								";
							}
						}
					}
				}
			}
		}



		//Build the site
		$author .='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html xml:lang="de" lang="de" xmlns="http://www.w3.org/1999/xhtml">
				<head>
					<title>'.$lang->aj_changeauthor_headline.'
					</title>
					'.$headerinclude.'
				</head>
				<body>
					<form action="editpost.php?pid='.$pid.'" method="post">
					<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
					<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
					<tr><td align="center">
					<table width="200px" border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
						<tr>
							<td class="thead" align="center">
								<strong>'.$lang->aj_changeauthor_headline2.'</strong>
							</td>
						</tr>
					</table>
					<table width="200px" height="300px" border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
						<tr>
							<td class="trow1" style="vertical-align:top;" align="center">
								<select name="authorswitch">
								'.$as_author_userbit.'
								</select>
							</td>
						</tr>
					</table>
					<table width="200px" style="margin-bottom: 30px;" border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
						<tr>
							<td class="trow2" align="center">
								<input type="hidden" name="action" value="do_cancel" />
								<input type="submit" class="button" name="submit" value="'.$lang->aj_changeauthor_cancel.'" onclick="window.close();" />
							</td>
							<td class="trow2" align="center">
								<input type="hidden" name="action" value="do_author" />
								<input type="submit" class="button" name="submit" value="'.$lang->aj_changeauthor_submit.'" />
							</td>
						</tr>
					</table>
					</td></tr>
					</table>
					</form>
				</body>
			</html>';
		output_page($author);
	}
}


//Change the author of the post
$plugins->add_hook("editpost_start", "accountswitcher_author_change");

function accountswitcher_author_change()
{
	global $mybb, $db, $forum, $cache;

	//Change action
	if ($mybb->input['action'] == "do_author" && $mybb->request_method == "post" && $mybb->settings['aj_changeauthor'] == 1)
	{
		//Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		//Get the current author of the post
		$pid = (int)$mybb->input['pid'];
		$query = $db->simple_select("posts", "*", "pid='$pid'");
		$post = $db->fetch_array($query);
		$tid = (int)$post['tid'];

		//Get the new user
		$newuid = (int)$mybb->input['authorswitch'];
		$newauthor = get_user($newuid);

		//Subtract from the users post count
		//Update the post count if this forum allows post counts to be tracked
		if($forum['usepostcounts'] != 0)
		{
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum-1 WHERE uid='".(int)$post['uid']."'");
			$db->write_query("UPDATE ".TABLE_PREFIX."users SET postnum=postnum+1 WHERE uid='".(int)$newauthor['uid']."'");
		}
		$updated_record = array(
			"uid" => (int)$newauthor['uid'],
			"username" => $db->escape_string($newauthor['username'])
		);
		if($db->update_query("posts", $updated_record, "pid='".(int)$post['pid']."'"))
		{
			update_thread_data($tid);
			update_forum_lastpost((int)$post['fid']);

			echo '<script type="text/javascript">
<!--
opener.location.reload();
window.close();
// -->
</script>';
		}
	}
	else {
		return;
	}
}

//Show link for changing the author of the post
$plugins->add_hook("postbit", "accountswitcher_author_change_button", 50);

function accountswitcher_author_change_button(&$post)
{
	global $mybb, $theme, $lang;

	if($mybb->user['uid'] != 0 && $mybb->user['uid'] == $post['uid'] && $mybb->usergroup['as_canswitch'] == 1 && $mybb->settings['aj_changeauthor'] == 1)
	{
		$lang->load("accountswitcher");

		$post['pid'] = (int)$post['pid'];
		$post['onlinestatus'] .= "<span id=\"changeauthor_{$post['pid']}\">
		<img border=\"0\" style=\"margin-top: 2px;margin-left: 10px;\" src=\"{$theme['imgdir']}/arrow_down.gif\"/></span>
		<div id=\"changeauthor_{$post['pid']}_popup\" class=\"popup_menu\" style=\"display: none; color: #000000; width: 160px;\">
		<div class=\"popup_item_container\"><a class=\"popup_item\" href=\"#pid{$post['pid']}\" onclick=\"MyBB.popupWindow('{$mybb->settings['bburl']}/editpost.php?pid={$post['pid']}&amp;changeauthor=1', 'changeauthor', 420, 420);\">{$lang->aj_changeauthor_postbit}</a></div>
		</div>
		<script type=\"text/javascript\">
		// <!--
		if(use_xmlhttprequest == '1')
		{
		new PopupMenu(\"changeauthor_{$post['pid']}\");
		}
		// -->
		</script>";
	}
}

$plugins->add_hook('global_start', 'accountswitcher_pm_notice');

//Show PM notices for all attached accounts
function accountswitcher_pm_notice()
{
	global $db, $mybb, $lang, $cache, $user_cache, $plugins_cache, $templates, $theme, $pm, $pm_switch_notice, $privatemessage_switch_text;

	if($mybb->user['uid'] != "0" && $mybb->settings['aj_pmnotice'] == 1)
	{
		$lang->load("accountswitcher");

		//Get the number of users attached to this account
		$count = 0;

		//If there are users attached and the current user can use the Enhanced Account Switcher...
		if($mybb->usergroup['as_canswitch'] == "1")
		{
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					if($account['as_uid'] == $mybb->user['uid'])
					{
						$count++;
						if($count > 0)
						{
							$attachedUser['uid'] = (int)$account['uid'];
							$attachedUser['username'] = htmlspecialchars_uni($account['username']);
							$attachedUser['unreadpms'] = (int)$account['unreadpms'];
							$attachedUser['pmnotice'] = (int)$account['as_uid'];
							$attachedUser['as_uid'] = (int)$account['pmnotice'];
							//Check if this user has a new private message.
							if($attachedUser['unreadpms'] > 0 && $mybb->settings['enablepms'] != 0 && ($current_page != "private.php" || $mybb->input['action'] != "read"))
							{
								$privatemessage_switch.= $lang->sprintf($lang->aj_newpm_switch_notice_one, $attachedUser['username']);
								$privatemessage_switch_text = '<div class="pm_alert" id="pm_switch_notice">
								<div>'.$privatemessage_switch.'</div>
								</div>
								<br />';
							}
						}
					}
				}
				eval("\$pm_switch_notice = \"".$templates->get("global_pm_switch_alert")."\";");
			}
		}

		//If there are no users attached to the current account but the current account is attached to another user
		if($count == 0 && $mybb->user['as_uid'] != '0')
		{
			//Get the master
			$master = get_user($mybb->user['as_uid']);

			//Check if this user has a new private message.
			if($master['unreadpms'] > 0 && $mybb->settings['enablepms'] != 0 && ($current_page != "private.php" || $mybb->input['action'] != "read"))
			{
					$privatemessage_switch.= $lang->sprintf($lang->aj_newpm_switch_notice_one, htmlspecialchars_uni($master['username']));
					$privatemessage_switch_text = '<div class="pm_alert" id="pm_switch_notice">
					<div>'.$privatemessage_switch.'</div>
					</div>
					<br />';
			}

			//Get all users attached to the master
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					//Leave current user out
					if($account['uid'] == "{$mybb->user['uid']}") continue;
					if($account['as_uid'] == $master['uid'])
					{
						$attachedUser['uid'] = (int)$account['uid'];
						$attachedUser['username'] = htmlspecialchars_uni($account['username']);
						$attachedUser['unreadpms'] = (int)$account['unreadpms'];
						$attachedUser['pmnotice'] = (int)$account['pmnotice'];
						$attachedUser['as_uid'] = (int)$account['as_uid'];
						//Check if this user has a new private message.
						if($attachedUser['pmnotice'] == 2 && $attachedUser['unreadpms'] > 0 && $mybb->settings['enablepms'] != 0 && ($current_page != "private.php" || $mybb->input['action'] != "read"))
						{
							$privatemessage_switch.= $lang->sprintf($lang->aj_newpm_switch_notice_one, $attachedUser['username']);
							$privatemessage_switch_text = '<div class="pm_alert" id="pm_switch_notice">
							<div>'.$privatemessage_switch.'</div>
							</div>
							<br />';
						}
					}
				}
				eval("\$pm_switch_notice = \"".$templates->get("global_pm_switch_alert")."\";");
			}
		}
	}
}

//Show attached accounts in user profile
$plugins->add_hook('member_profile_end', 'accountswitcher_profile');

function accountswitcher_profile()
{
	global $mybb, $cache, $db, $memprofile, $theme, $profilefields, $lang;

	//Get the attached users
	if($memprofile['uid'] != "0" && $mybb->settings['aj_profile'] == 1)
	{
		//Get usergroup permissions
		$permissions = user_permissions((int)$memprofile['uid']);

		//Get the number of users attached to this account
		$count = 0;
		$as_profile_userbit = '';

		//If there are users attached and the current user can use the Enhanced Account Switcher...
		if($permissions['as_canswitch'] == "1")
		{
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					$attachedOne['uid'] = (int)$account['uid'];
					$attachedOne['username'] = htmlspecialchars_uni($account['username']);
					$attachedOne['as_uid'] = (int)$account['as_uid'];
					if($attachedOne['as_uid'] == $memprofile['uid'])
					{
						$count++;
						if($count > 0)
						{
							if($memprofile['uid'] == $mybb->user['uid'])
							{
								$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</a></li>";
							}
							else
							{
								$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$attachedOne['uid']."\" alt=\"\">".$attachedOne['username']."</a></li>";
							}
						}
					}
				}
			}
		}

		//If there are no users attached to current account but the current account is attached to another user
		if($count == 0 && $memprofile['as_uid'] != '0')
		{
			//Get the master
			$master = get_user((int)$memprofile['as_uid']);
			//Get masters permissions
			$permission = user_permissions((int)$master['uid']);

			//If master has permission to use the Enhanced Account Switcher, get the userlist
			if($permission['as_canswitch'] == "1")
			{
				//Create link to master
				if($memprofile['uid'] == $mybb->user['uid'])
				{
					$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".(int)$master['uid']."&amp;my_post_key=".$mybb->post_code."\" alt=\"\" title=\"Master Account\"><strong>".htmlspecialchars_uni($master['username'])."</strong></a></li>";
				}
				else
				{
					$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".(int)$master['uid']."\" alt=\"\" title=\"Master Account\"><strong>".htmlspecialchars_uni($master['username'])."</strong></a></li>";
				}
				//Get all users attached to master from the cache
				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOne['uid'] = (int)$account['uid'];
						$attachedOne['username'] = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						//Leave current user out
						if($attachedOne['uid'] == $memprofile['uid'])
						{
							continue;
						}
						if($attachedOne['as_uid'] == $master['uid'])
						{
							if($memprofile['uid'] == $mybb->user['uid'])
							{
								$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</a></li>";
							}
							else
							{
								$as_profile_userbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$attachedOne['uid']."\" alt=\"\">".$attachedOne['username']."</a></li>";
							}
						}
					}
				}
			}
		}

		if($count > 0 || $count == 0 && $memprofile['as_uid'] != '0')
		{
			$lang->load('accountswitcher');
			$profilefields .= '<br />
					<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
						<tr>
							<td class="thead"><strong>'.$lang->aj_profile.'</strong></td>
						</tr>
						<tr>
							<td class="trow1">
							<ul>
							'.$as_profile_userbit.'
							</ul>
							</td>
						</tr>
					</table>';
		}
	}
}

//Show attached accounts in postbit
$plugins->add_hook('postbit', 'accountswitcher_postbit');

function accountswitcher_postbit(&$post)
{
	global $mybb, $cache, $db, $theme, $lang;

	//Get the attached users
	if($post['uid'] != "0" && $mybb->settings['aj_postbit'] == 1)
	{
		//Get usergroup permissions
		$permissions = user_permissions((int)$post['uid']);

		//Get the number of users attached to this account
		$count = 0;
		$as_postbit = '';

		//If there are users attached and the current user can use the Enhanced Account Switcher...
		if($permissions['as_canswitch'] == "1")
		{
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					$attachedOne['uid'] = (int)$account['uid'];
					$attachedOne['username'] = htmlspecialchars_uni($account['username']);
					$attachedOne['as_uid'] = (int)$account['as_uid'];
					if($attachedOne['as_uid'] == $post['uid'])
					{
						$count++;
						if($count > 0)
						{
							if($post['uid'] == $mybb->user['uid'])
							{
								$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</a></li>";
							}
							else
							{
								$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$attachedOne['uid']."\" alt=\"\">".$attachedOne['username']."</a></li>";
							}
						}
					}
				}
			}
		}

		//If there are no users attached to current account but the current account is attached to another user
		if($count == 0 && $post['as_uid'] != '0')
		{
			//Get the master
			$master = get_user((int)$post['as_uid']);
			//Get masters permissions
			$permission = user_permissions((int)$master['uid']);

			//If master has permission to use the Enhanced Account Switcher, get the userlist
			if($permission['as_canswitch'] == "1")
			{
				//Create link to master
				if($post['uid'] == $mybb->user['uid'])
				{
					$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".(int)$master['uid']."&amp;my_post_key=".$mybb->post_code."\" alt=\"\" title=\"Master Account\"><strong>".htmlspecialchars_uni($master['username'])."</strong></a></li>";
				}
				else
				{
					$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".(int)$master['uid']."\" alt=\"\" title=\"Master Account\"><strong>".htmlspecialchars_uni($master['username'])."</strong></a></li>";
				}
				//Get all users attached to master from the cache
				$accounts = $cache->read('accountswitcher');
				if(is_array($accounts))
				{
					foreach ($accounts as $key => $account)
					{
						$attachedOne['uid'] = (int)$account['uid'];
						$attachedOne['username'] = htmlspecialchars_uni($account['username']);
						$attachedOne['as_uid'] = (int)$account['as_uid'];
						//Leave current user out
						if($attachedOne['uid'] == $post['uid'])
						{
							continue;
						}
						if($attachedOne['as_uid'] == $post['uid'])
						{
							if($memprofile['uid'] == $mybb->user['uid'])
							{
								$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=login&amp;do=switch&amp;uid=".$attachedOne['uid']."&amp;my_post_key=".$mybb->post_code."\">".$attachedOne['username']."</a></li>";
							}
							else
							{
								$as_postbit.= "<li><a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$attachedOne['uid']."\" alt=\"\">".$attachedOne['username']."</a></li>";
							}
						}
					}
				}
			}
		}

		if($count > 0 || $count == 0 && $post['as_uid'] != '0')
		{
			$lang->load('accountswitcher');
			$post['user_details'] .= '<br />
					<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder">
						<tr>
							<td class="thead"><strong>'.$lang->aj_profile.'</strong></td>
						</tr>
						<tr>
							<td class="trow1">
							<ul>
							'.$as_postbit.'
							</ul>
							</td>
						</tr>
					</table>';
		}
	}
}

//Set all attached accounts to away when master account status is set to away
$plugins->add_hook('usercp_do_profile_end', 'accountswitcher_set_away');

function accountswitcher_set_away()
{
	global $db, $mybb, $cache, $returndate, $awaydate;

	if($mybb->user['uid'] != "0" && $mybb->settings['aj_away'] == 1)
	{

		//Get the number of users attached to this account
		$count = 0;

		//If there are users attached and the current user can use the Enhanced Account Switcher...
		if($mybb->usergroup['as_canswitch'] == "1")
		{
			$accounts = $cache->read('accountswitcher');
			if(is_array($accounts))
			{
				foreach ($accounts as $key => $account)
				{
					$attachedOne['uid'] = (int)$account['uid'];
					$attachedOne['username'] = htmlspecialchars_uni($account['username']);
					$attachedOne['as_uid'] = (int)$account['as_uid'];
					if($attachedOne['as_uid'] == $mybb->user['uid'])
					{
						$count++;
						if($count > 0)
						{
							$updated_record = array(
								"away" => (int)$mybb->input['away'],
								"awaydate" => $db->escape_string($awaydate),
								"returndate" => $db->escape_string($returndate),
								"awayreason" => $db->escape_string($mybb->input['awayreason'])
							);
							$db->update_query("users", $updated_record, "uid='".(int)$attachedOne['uid']."'");
						}
					}
				}
			}
		}
	}
}

//Remove attached users if master user gets deleted
$plugins->add_hook("admin_user_users_delete_commit", "accountswitcher_del_user");

function accountswitcher_del_user()
{
	global $db, $user;

	$updated_record = array(
		"as_uid" => 0
	);
	$db->update_query('users', $updated_record, "as_uid='".(int)$user['uid']."'");

	accountswitcher_cache();
}

?>
