{**
 * suppFile.tpl
 *
 * Copyright (c) 2003-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Add/edit a supplementary file.
 *
 * $Id$
 *}
{include file="common/header.tpl"}

{literal}
<script type="text/javascript">
$(document).ready(function() {
   $('#fileType').change(function(){
        var isOtherSelected = false;
        $.each($('#fileType').val(), function(key, value){
            if(value == "{/literal}{translate key="common.other"}{literal}") {
                isOtherSelected = true;
            }
        });
        if(isOtherSelected) {
            $('#divOtherFileType').show();
        } else {
            $('#divOtherFileType').hide();
        }
    });
});
</script>
{/literal}

{strip}
{if $suppFileId}
	{assign var="pageTitle" value="author.submit.editSupplementaryFile"}
{else}
       {* Comment out, AIM, June 1, 2011
	{assign var="pageTitle" value="author.submit.addSupplementaryFile"}
        *}
        {if $type == "Progress Report"}
            {assign var="pageTitle" value="author.submit.addProgressReport"}
        {elseif $type == "Completion Report"}
            {assign var="pageTitle" value="author.submit.addCompletionReport"}
        {elseif $type == "Extension Request"}
            {assign var="pageTitle" value="author.submit.addExtensionRequest"}
        {elseif $type == "Raw Data File"}
        	{assign var="pageTitle" value=author.submit.addRawDataFile}
        {else}
            {assign var="pageTitle" value="author.submit.addSupplementaryFile"}
        {/if}
{/if}

{*
{assign var="pageCrumbTitle" value="submission.supplementaryFiles"}
*}

{if $type == "Progress Report"}
    {assign var="pageCrumbTitle" value="submission.progressReports"}
{elseif $type == "Completion Report"}
    {assign var="pageCrumbTitle" value="submission.completionReports"}
{elseif $type == "Extension Request"}
    {assign var="pageCrumbTitle" value="submission.extensionRequest"}
{elseif $type == "Raw Data File"}
	{assign var="pageCrumbTitle" value="submission.rawDataFile"}
{else}
    {assign var="pageCrumbTitle" value="submission.supplementaryFiles"}
{/if}


{/strip}

<form name="suppFile" method="post" action="{url page=$rolePath op="saveSuppFile" path=$suppFileId}" enctype="multipart/form-data">
<input type="hidden" name="articleId" value="{$articleId|escape}" />
<input type="hidden" name="from" value="{$from|escape}" />
<input type="hidden" name="type" value="{$type|escape}" /> <!-- Added by AIM, June 15 2011 -->
{include file="common/formErrors.tpl"}

<!--
{*
{if count($formLocales) > 1}
<div id="locale">
<table width="100%" class="data">
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="formLocale" key="form.formLanguage"}</td>
		<td width="80%" class="value">
			{if $suppFileId}{url|assign:"formUrl" op="editSuppFile" path=$articleId|to_array:$suppFileId from=$from escape=false}
			{else}{url|assign:"formUrl" op="addSuppFile" path=$articleId from=$from escape=false}
			{/if}
			{form_language_chooser form="suppFile" url=$formUrl}
			<span class="instruct">{translate key="form.formLanguage.description"}</span>
		</td>
	</tr>
</table>
</div>
{/if}


<div id="supplementaryFileData">
<h3>{translate key="author.submit.supplementaryFileData"}</h3>
<p>{translate key="author.submit.supplementaryFileDataDescription"}</p>

<table width="100%" class="data">
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="title" required="true" key="common.title"}</td>
		<td width="80%" class="value"><input type="text" id="title" name="title[{$formLocale|escape}]" value="{$title[$formLocale]|escape}" size="50" maxlength="255" class="textField" /></td>
	</tr>
	{if $enablePublicSuppFileId}
	<tr valign="top">
		<td width="20%" class="label">{fieldLabel name="publicSuppFileId" key="author.suppFile.publicSuppFileIdentifier"}</td>
		<td width="80%" class="value"><input type="text" id="publicSuppFileId" name="publicSuppFileId" value="{$publicSuppFileId|escape}" size="20" maxlength="255" class="textField" /></td>
	</tr>
	{/if}
	<tr valign="top">
		<td class="label">{fieldLabel name="creator" key="author.submit.suppFile.createrOrOwner"}</td>
		<td class="value"><input type="text" id="creator" name="creator[{$formLocale|escape}]" value="{$creator[$formLocale]|escape}" size="50" maxlength="255" class="textField" /></td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="subject" key="common.subject"}</td>
		<td class="value"><input type="text" name="subject[{$formLocale|escape}]" id="subject" value="{$subject[$formLocale]|escape}" size="50" maxlength="255" class="textField" /></td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="type" key="common.type"}</td>
		<td class="value"><select name="type" size="1" id="type" class="selectMenu">{html_options_translate output=$typeOptionsOutput values=$typeOptionsValues translateValues="true" selected=$type}</select><br />{translate key="author.submit.suppFile.specifyOtherType"}: <input type="text" name="typeOther[{$formLocale|escape}]" value="{$typeOther[$formLocale]|escape}" size="45" maxlength="255" class="textField" /></td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="description" key="author.submit.suppFile.briefDescription"}</td>
		<td class="value"><textarea name="description[{$formLocale|escape}]" id="description" rows="5" cols="50" class="textArea">{$description[$formLocale]|escape}</textarea></td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="publisher" key="common.publisher"}</td>
		<td class="value">
			<input type="text" name="publisher[{$formLocale|escape}]" id="publisher" value="{$publisher[$formLocale]|escape}" size="50" maxlength="255" class="textField" />
			<br />
			<span class="instruct">{translate key="author.submit.suppFile.publisherDescription"}</span>
		</td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="sponsor" key="author.submit.suppFile.contributorOrSponsor"}</td>
		<td class="value"><input id="sponsor" type="text" name="sponsor[{$formLocale|escape}]" value="{$sponsor[$formLocale]|escape}" size="50" maxlength="255" class="textField" /></td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="dateCreated" key="common.date"}</td>
		<td class="value">
			<input type="text" id="dateCreated" name="dateCreated" value="{$dateCreated|escape}" size="11" maxlength="10" class="textField" /> {translate key="submission.date.yyyymmdd"}
			<br />
			<span class="instruct">{translate key="author.submit.suppFile.dateDescription"}</span>
		</td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="source" key="common.source"}</td>
		<td class="value">
			<input type="text" id="source" name="source[{$formLocale|escape}]" value="{$source[$formLocale]|escape}" size="50" maxlength="255" class="textField" />
			<br />
			<span class="instruct">{translate key="author.submit.suppFile.sourceDescription"}</span>
		</td>
	</tr>
	<tr valign="top">
		<td class="label">{fieldLabel name="language" key="common.language"}</td>
		<td class="value">
			<input type="text" id="language" name="language" value="{$language|escape}" size="5" maxlength="10" class="textField" />
			<br />
			<span class="instruct">{translate key="author.submit.languageInstructions"}</span>
		</td>
	</tr>
</table>
</div>
*}
<div class="separator"></div>
-->


<div id="supplementaryFileUpload">
<!--
<h3>{translate key="author.submit.supplementaryFileUpload"}</h3>
-->

{if $type == "Progress Report"}
    <h3>{translate key="submission.progressReports"}</h3>
{elseif $type == "Completion Report"}
    <h3>{translate key="submission.completionReports"}</h3>
{elseif $type == "Extension Request"}
    <h3>{translate key="submission.extensionRequest"}</h3>
{elseif $type == "Raw Data File"}
    <h3>{translate key="submission.rawDataFile"}</h3>
{elseif $type == "Other Supplementary Research Output"}
    <h3>{translate key="submission.otherSuppResearchOutput"}</h3>
{else}
    <h3>{translate key="author.submit.supplementaryFileUpload"}</h3>
{/if}

<table id="suppFile" class="data">
{if $suppFile}
	<tr valign="top">
		<td width="20%" class="label">{translate key="common.fileName"}</td>
		<td width="80%" class="data"><a href="{url op="downloadFile" path=$articleId|to_array:$suppFile->getFileId()}">{$suppFile->getFileName()|escape}</a></td>
	</tr>
	<tr valign="top">
		<td class="label">{translate key="common.originalFileName"}</td>
		<td class="value">{$suppFile->getOriginalFileName()|escape}</td>
	</tr>
	<tr valign="top">
		<td class="label">{translate key="common.fileSize"}</td>
		<td class="value">{$suppFile->getNiceFileSize()}</td>
	</tr>
	<tr>
		<td class="label">{translate key="common.dateUploaded"}</td>
		<td class="value">{$suppFile->getDateUploaded()|date_format:$dateFormatShort}</td>
	</tr>
</table>
<!--
{*
<table width="100%"  class="data">
	<tr valign="top">
		<td width="5%" class="label"><input type="checkbox" name="showReviewers" id="showReviewers" value="1"{if $showReviewers==1} checked="checked"{/if} /></td>
		<td width="95%" class="value"><label for="showReviewers">{translate key="author.submit.suppFile.availableToPeers"}</label></td>
	</tr>
</table>
*}
-->
{else}
	<tr valign="top">
		<td colspan="2" class="nodata">{* translate key="author.submit.suppFile.noFile" *}</td>
	</tr>
{/if}

<br />

<table id="showReviewers" width="100%" class="data">
        <!--Start Edit Jan 30 2012-->
        {if $type == "Supp File"}
            <tr>
                <td width="30%" class="label">Select file type(s)<br />(Hold down the CTRL button to select multiple options.)</td>
                <td width="35%" class="value">
                        <select name="fileType[]" id="fileType" multiple="multiple" size="10" class="selectMenu">
                            {html_options_translate options=$typeOptions translateValues="true" selected=$fileType}
                        </select>
                </td>
                <td style="vertical-align: bottom;">
                        <div id="divOtherFileType" style="display: none;">
                            <span class="label" style="font-style: italic;">Specify "Other" file type</span> <br />
                            <input type="text" name="otherFileType" id="otherFileType" size="20" /> <br />
                        </div>
                </td>
            </tr>
        {/if}
        <!--End Edit -->
	<tr valign="top">
		<td class="label">
			{if $suppFile}
				{fieldLabel name="uploadSuppFile" key="common.replaceFile"}
			{else}
				{fieldLabel name="uploadSuppFile" key="common.upload"}
			{/if}
		</td>
		<td class="value" colspan="2"><input type="file" name="uploadSuppFile" id="uploadSuppFile" class="uploadField" />&nbsp;&nbsp;{translate key="author.submit.supplementaryFiles.saveToUpload"}</td>
	</tr>
        <!--
        {*
	{if not $suppFile}
	<tr valign="top">
		<td>&nbsp;</td>
		<td class="value">
			<input type="checkbox" name="showReviewers" id="showReviewers" value="1"{if $showReviewers==1} checked="checked"{/if} />&nbsp;
			<label for="showReviewers">{translate key="author.submit.suppFile.availableToPeers"}</label>
		</td>
	</tr>
	{/if}
        *}
        -->
</table>
</div>

<div class="separator"></div>


<p><input type="submit" value="{translate key="common.save"}" class="button defaultButton" /> <input type="button" value="{translate key="common.cancel"}" class="button" onclick="history.go(-1)" /></p>

<p><span class="formRequired">{translate key="common.requiredField"}</span></p>

</form>

{include file="common/footer.tpl"}

