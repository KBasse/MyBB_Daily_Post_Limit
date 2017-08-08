<?php

/*
This plugin will allow admins to set an upper limit to how many posts users can make per day.
It also lets the admins set a variety of criteria (including having achived a specified post count, belonging to a specified group, etc.), which, if fulfilled, will allow users to circumvent the limit.
*/

//This disallows direct access to the file for security reasons.

if(!defined('IN_MYBB')){
	die("This file cannot be accessed directly.");
}

//Hooks.
$plugins->add_hook('newreply_start', 'dailypostlimit_run');
$plugins->add_hook('newreply_do_newreply_start', 'dailypostlimit_run');
$plugins->add_hook('newthread_start', 'dailypostlimit_run');
$plugins->add_hook('newthread_do_newthread_start', 'dailypostlimit_run');

function dailypostlimit_info(){
	return array(
		'name' 		=> 'Daily Post Limit',
		'description'	=> 'A plugin that lets admins set an daily post limit.',
		'website'	=> 'None',
		'author'	=> 'KBasse',
		'authorsite'	=> "I don't have one",
		'version'	=> '0.2',
		'guid'		=> ''
	);
}

//This activation function inserts the settinggroup Daily Post Limit, which contains the seven settings given, on the ACP settings tab.
function dailypostlimit_activate(){
	global $db, $mybb, $lang;

	$lang->load('dailypostlimit');

	$dailypostlimit_group = array(
		'name'		=> 'dailypostlimit',
		'title'		=> $lang->setting_group,
		'description'	=> $lang->setting_group_desc,
		'disporder'	=> '2',
		'isdefault'	=> '0',
	);

	$db->insert_query("settinggroups", $dailypostlimit_group);
	$gid = $db->insert_id();

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_enable',
		'title'		=> $lang->setting_enabled,
		'description'	=> $lang->setting_enabled_desc,
		'optionscode'	=> 'yesno',
		'value'		=> '0',
		'disporder'	=> '1',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_limit',
		'title'		=> $lang->setting_limit,
		'description'	=> $lang->setting_limit_desc,
		'optionscode'	=> 'text',
		'value'		=> '',
		'disporder'	=> '2',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_min_post',
		'title'		=> $lang->setting_min_post,
		'description'	=> $lang->setting_min_post_desc,
		'optionscode'	=> 'text',
		'value'		=> '',
		'disporder'	=> '3',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_exclude_user',
		'title'		=> $lang->setting_exclude_user,
		'description'	=> $lang->setting_exclude_user_desc,
		'optionscode'	=> 'text',
		'value'		=> '',
		'disporder'	=> '4',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_exclude_group',
		'title'		=> $lang->setting_exclude_group,
		'description'	=> $lang->setting_exclude_group_desc,
		'optionscode'	=> 'text',
		'value'		=> '',
		'disporder'	=> '5',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_exclude_forum',
		'title'		=> $lang->setting_exclude_forum,
		'description'	=> $lang->setting_exclude_forum_desc,
		'optionscode'	=> 'text',
		'value'		=> '',
		'disporder'	=> '6',
		'gid'		=> intval($gid),
	);

	$dailypostlimit_setting[] = array(
		'name'		=> 'dailypostlimit_message',
		'title'		=> $lang->setting_message,
		'description'	=> $lang->setting_message_desc,
		'optionscode'	=> 'textarea',
		'value'		=> $lang->setting_message_value,
		'disporder'	=> '7',
		'gid'		=> intval($gid)
	);

	foreach($dailypostlimit_setting as $setting){
		$db->insert_query("settings", $setting);
	}

	rebuild_settings();
}

//The deactivation function removes the settings inserted by the activation function.
function dailypostlimit_deactivate(){
	global $db, $mybb, $lang;

	$db->query("delete from " .TABLE_PREFIX. "settings WHERE name IN ('dailypostlimit_enable', 'dailypostlimit_limit', 'dailypostlimit_min_post', 'dailypostlimit_exclude_group', 'dailypostlimit_exclude_user', 'dailypostlimit_exclude_forum', 'dailypostlimit_message')");
	$db->query("delete from " .TABLE_PREFIX. "settinggroups WHERE name='dailypostlimit'");

	rebuild_settings();
}

function dailypostlimit_run(){
	global $mybb, $db, $settings;

	//Checks if the plugin is enabled.
	if($settings['dailypostlimit_enable'] != '1'){
		return;
	}

	//Checks whether the posting user is somehow excluded from the effect of the plugin using the exluded() function below.
	$excluded = excluded();

	if($excluded){
		return;
	}

	//Runs the postcount() function to determine the number of posts the user has made in the past 24 hours.
	$dailypostcount = postcount();

	//Checks whether the users has reached the daily post limit, and if so, displays a message informing the user of this.
	if($dailypostcount >= $settings['dailypostlimit_limit']){
		$parameters = array('#/limit#', '#/min_count#');
		$replace = array($settings['dailypostlimit_limit'], $settings['dailypostlimit_min_post']);
		$message = preg_replace($parameters, $replace, $settings['dailypostlimit_message']);
		error($message);
	}
}

function excluded(){
	global $mybb, $db, $settings;

	//Is the user exluded from the effect of the plugin by his/her post count?
	if($mybb->user['postnum'] >= $settings['dailypostlimit_min_post']){
		return true;
	}

	//Has this specific user been excluded from the effect of the plugin?
	if($settings['dailypostlimit_exclude_user'] != ''){
		$excluded_users = explode(',', preg_replace('/\s\s+/', '', $settings['dailypostlimit_exclude_user']));
		if(in_array($mybb->user['uid'], $excluded_users)){
			return true;
		}
	}

	//Does the user belong to a usergroup, which is excluded from the effect of the plugin?
	if($settings['dailypostlimit_exclude_group'] != ''){
		$excluded_groups = explode(',', preg_replace('/\s\s+/', '', $settings['dailypostlimit_exclude_group']));
		if(in_array($mybb->user['usergroup'], $excluded_groups)){
			return true;
		}
	}

	//Is the post being made in a subforum, which is excluded from the effect of the plugin?
	if($settings['dailypostlimit_exclude_forum'] != ''){
		$fid = intval($mybb->input['fid']);
		$tid = intval($db->escape_string($mybb->input['tid']));
		$query = $db->query("SELECT fid FROM " .TABLE_PREFIX. "threads WHERE tid = $tid");
		$forum = $db->fetch_array($query);

		$excluded_forums = explode(',', preg_replace('/\s\s+/', '', $settings['dailypostlimit_exclude_forum']));
		if(in_array($forum['fid'], $excluded_forums) OR in_array($fid, $excluded_forums)){
			return true;
		}
	}

	//Returns the result "false", if none of the above applies.
	return false;
}

function postcount(){
	global $mybb, $db, $settings;

	$excluded_forums = explode(',', preg_replace('/\s\s+/', '', $settings['dailypostlimit_exclude_forum']));

	//Checks the posts database for posts made by the user and carrying a time stamp from within the last 24 hours.
	$query = $db->simple_select("posts", "count(*) as post_count", "uid='{$mybb->user['uid']}' and dateline >='".(TIME_NOW - (60*60*24))."' and fid != '$excluded_forums[0]' and fid != '$excluded_forums[1]' and fid != '$excluded_forums[2]'");
	$postcount = $db->fetch_field($query, 'post_count');
	print_r($postcount);
	return $postcount;
}

?>
