<h3>Initialize website</h3>
<h4>You are about to initialize this website with the following configuration:</h4>

{form_start}
<input type="hidden" name="{$actionid}confirm0" value="1">
<div class="pageoverflow" style="height: 200px; overflow-y: scroll;"><pre style="display: block">{$config|var_dump}</pre></div>

<div class="pageoverflow">
    <p class="pagetext">Confirm operation:</p>
    <p class="pageinput">
        <input type="checkbox" id="confirm1" value="1" name="{$actionid}confirm1">
        &nbsp; <label for="confirm1">Yes, I understand that all existing data will be <strong>deleted</strong> and unrecoverable</label>
        <br/>
        <input type="checkbox" id="confirm2" value="1" name="{$actionid}confirm2">
        &nbsp; <label for="confirm2">Yes, I am absolutely sure I want to do this</label></p>
</div>

<div class="pageoverflow">
    <p class="pagetext"></p>
    <p class="pageinput">
        <input type="submit" name="{$actionid}submit" value="Submit"/>
    </p>
</div>
{form_end}