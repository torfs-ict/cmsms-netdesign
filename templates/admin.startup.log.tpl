<h3>Initialize website</h3>
<h4>Operational log:</h4>

<div class="pageoverflow">
    <ul>
        {foreach from=$log item="item"}
            <li>{$item.caption}: {$item.status}</li>
        {/foreach}
    </ul>
</div>