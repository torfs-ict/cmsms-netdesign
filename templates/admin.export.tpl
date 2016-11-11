{if $success eq true}
	<p class="information">Your export has been successfully created.</p>
{else}
	{form_start}
	<input type="hidden" name="{$actionid}confirm" value="1">

	<p>Please note that exporting your website might take a while, depending on the size of the data.</p>

	<div class="pageoverflow">
		<p class="pagetext"></p>
		<p class="pageinput">
			<input type="submit" name="{$actionid}submit" value="Continue"/>
		</p>
	</div>
	{form_end}
{/if}