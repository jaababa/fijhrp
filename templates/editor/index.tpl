{**
 * index.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Editor index.
 *
 * $Id$
 *}
{strip}
{assign var="pageTitle" value="editor.home"}
{assign var="pageCrumbTitle" value="user.role.editor"}
{include file="common/header.tpl"}
{/strip}
<div id="articleSubmissions">
<h3>{translate key="article.submissions"}</h3>
<ul class="plain">
	<li>&#187; <a href="{url op="submissions" path="submissionsUnassigned"}">{translate key="common.queue.short.submissionsUnassigned"}</a>&nbsp;({if $submissionsCount[0]}{$submissionsCount[0]}{else}0{/if})</li>
	<li>&#187; <a href="{url op="submissions" path="submissionsInReview"}">{translate key="common.queue.short.submissionsInReview"}</a>&nbsp;({if $submissionsCount[1]}{$submissionsCount[1]}{else}0{/if})</li>
	<!--{*	 *
	<li>&#187; <a href="{url op="submissions" path="submissionsInEditing"}">{translate key="common.queue.short.submissionsInEditing"}</a>&nbsp;({if $submissionsCount[2]}{$submissionsCount[2]}{else}0{/if})</li>
	 **}-->
	<li>&#187; <a href="{url op="submissions" path="submissionsArchives"}">{translate key="common.queue.short.submissionsArchives"}</a></li>
	{call_hook name="Templates::Editor::Index::Submissions"}
</ul>
</div>

{**
 * Added section for meetings in editor home page
 * Last Updated: 7/5/2011
 *}
 
<div class="separator">&nbsp;</div>
<div id="meetings">
<h3>{translate key="editor.meetings"}</h3>
<ul class="plain">
	<li>&#187; <a href="{url op="meetings"}">{translate key="editor.meetings}</a></li>
	<li>&#187; <a href="{url op="minutes"}">{translate key="editor.meetings.uploadMinutes"}</a></li>
</ul>
</div>

<div class="separator">&nbsp;</div>

<div id="issues">
<h3>{translate key="editor.navigation.issues"}</h3>

<ul class="plain">
	<li>&#187; <a href="{url op="createIssue"}">{translate key="editor.navigation.createIssue"}</a></li>
	<li>&#187; <a href="{url op="notifyUsers"}">{translate key="editor.notifyUsers"}</a></li>
	<li>&#187; <a href="{url op="futureIssues"}">{translate key="editor.navigation.futureIssues"}</a></li>
	<li>&#187; <a href="{url op="backIssues"}">{translate key="editor.navigation.issueArchive"}</a></li>
	{call_hook name="Templates::Editor::Index::Issues"}
</ul>
</div>

{call_hook name="Templates::Editor::Index::AdditionalItems"}

{include file="common/footer.tpl"}

