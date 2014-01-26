{gt text='Edit Profile' assign='templatetitle'}
{ajaxheader validation=true}

{include file='User/menu.tpl'}

<form id="modifyprofileform" class="form-horizontal" action="{modurl modname=$module type='user' func='update'}" method="post" enctype="application/x-www-form-urlencoded">
	<input type="hidden" id="csrftoken" name="csrftoken" value="{insert name="csrftoken"}" />
	<p class="alert alert-info">{gt text="Items marked with an asterisk (*) are required entries."}</p>
	{foreach from=$fieldsets key='key' item='fieldset'}
        {capture name='capture_fieldset' assign='capture_fieldset'}
            <fieldset class="{$key}">
    	        <legend>{$fieldset}</legend>
                {foreach from=$duditems item='item' key='itemlabel'}
    		        {if ($fieldset == $item.prop_fieldset)}
    		            {capture name='capture_fields' assign='capture_fields'}
    		                {duditemmodify item=$item}
                        {/capture}
                        {if ($capture_fields|trim != '')}
                            {$capture_fields}
                        {/if}
                    {/if}
                {/foreach}
            </fieldset>
        {/capture}
        {if ($capture_fields|trim != '')}
            {$capture_fieldset}
        {/if}
    {/foreach}
    <div class="col-lg-offset-3 col-lg-9">
        <button class="btn btn-success" type="submit" name="Save">{gt text="Save"}</button>
        <a class="btn btn-danger" href="{modurl modname=$module type='user' func='view'}" title="{gt text="Cancel"}">{gt text="Cancel"}</a>
    </div>
</form>

<script type="text/javascript">
    // <![CDATA[
    var valid = new Validation('modifyprofileform');
    // ]]>
</script>