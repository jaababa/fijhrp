{include file="sectionEditor/minutes/menu.tpl"}

<h4>{translate key="editor.minutes.proposalsForInitialReview"}</h4>
<br/>
{if count($submissions) == 0 }
	{translate key="editor.minutes.noProposalsForInitialReview"}
{else}
	
	<div id="selectInitialReview">
		<table class="data">
			<form method="POST" action="{url op="selectInitialReview" path=$meeting->getId()}">			
			<tr>
				<td class="label">
					{fieldLabel name="articleId" required="true" key="editor.minutes.selectProposal"}													
				</td>		
				<td class="value">
					<select name="articleId" id="articleId" class="selectMenu">
						<option value="none">Choose One</option>
						{foreach from=$submissions item=submission}
							<option value="{$submission->getArticleId()}">{$submission->getLocalizedWhoId()}: {$submission->getLocalizedTitle()|strip_unsafe_html}</option>						
						{/foreach}
					</select>
					&nbsp;&nbsp;&nbsp;<input type="submit" class="button" name="selectProposal" value="Select Proposal"/></td>	
			</tr>
			</form>	
			<tr>
				<td colspan="6">
					<br/>				
				</td>
			</tr>
		</table>
	</div>
{/if}	
	
<br/><input type="button" class="button defaultButton" id="complete" name="complete" value="{translate key="editor.minutes.completeInitialReviews"}" onclick="document.location.href='{url op="completeInitialReviews" path=$meeting->getId()}'"/>
<input type="button" class="button" onclick="document.location.href='{url op="uploadMinutes" path=$meeting->getId()}'" value="{translate key="common.back"}" />	