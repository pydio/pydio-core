{$formstart}
<fieldset>
<legend>{$mod->Lang('ajxp_realurl')}</legend>
  <p class="pageinput">
    <input type="text" name="{$actionid}ajxp_realurl" value="{$ajxp_realurl}" size="50" maxlength="255"/>
  </p>
</fieldset>
<fieldset>
<legend>{$mod->Lang('ajxp_secret')}</legend>
  <p class="pageinput">
    <input type="text" name="{$actionid}ajxp_secret" value="{$ajxp_secret}" size="10" maxlength="25"/>
  </p>
</fieldset>
<fieldset>
<legend>{$mod->Lang('ajxp_link_text')}</legend>
  <p class="pageinput">
    <input type="text" name="{$actionid}ajxp_link_text" value="{$ajxp_link_text}" size="20" maxlength="55"/>
  </p>
</fieldset>
<fieldset>
<legend>{$mod->Lang('ajxp_auth_group')}</legend>
  <p class="pageinput">
    {$ajxp_auth_group}
  </p>
</fieldset>

<div class="pageoverflow">
  <p class="pageinput">
    <input type="submit" name="{$actionid}submit_settings" value="{$mod->Lang('submit')}"/>
  </p>
</div>
{$formend}