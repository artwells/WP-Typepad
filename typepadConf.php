<?php
if($_REQUEST['_wpnonce']
   && check_admin_referer('typepad-admin')){
    //process the inputs and present the valid ones for prefill
    $cleaned=$this->_processConfigChange($_REQUEST);
}

if(! $antispam_val=$cleaned['antispam_key']){
    $antispam_val=$this->_antispamKey;
}

if(! $leaderboard_val=$cleaned['leaderboard_code']){
    $leaderboard_val=$this->_leaderboardCode;
}

if(! $media_code=$cleaned['media_code']){
    $media_code=$this->_mediaCode;
}


//toggle defaults
if($this->_enabledMedia){
    $media_enabled_yes='checked="checked"';  
}
else{
    $media_enabled_no='checked="checked"';
    //hide unneeded rows
    $jqueryConditional.='
       $(\'.mediaRow\').hide();
'; 
}

//toggle defaults
if($this->_enabledAntiSpam){
    $antispam_enabled_yes='checked="checked"';  
}
else{
    $antispam_enabled_no='checked="checked"';
    //hide unneeded rows
    $jqueryConditional.='
       $(\'.antispamRow\').hide();
'; 

}


if($this->_antispamTrash){
    $antispam_trash_yes='checked="checked"';
}
else{
    $antispam_trash_no='checked="checked"';
}

?>



<div class="wrap">
<?php if(!empty($this->_formSuccess)){ 
    /* simple messaging--should be moved in the main class */
?>
<div class="updated"><p><strong><?php echo implode(', ',$this->_formSuccess)?> Changed Successfully</strong></p></div>

<?php } ?>
<?php if(!empty($this->_formFailure)){ 
?>
<div class="error"><strong><p><?php echo implode(', ',$this->_formFailure)?> Changes Failed</strong></p></div>
<?php } ?>


<div id="icon-plugins" class="icon32"><br/></div>
<h2>TypePad Services for WordPress</h2>
<form method="post" action="<?php echo TY_SUPER_MENU; ?>?page=<?php echo TY_PAGE; ?>">


<?php wp_nonce_field('typepad-admin'); ?>

<?php /* not loving the tables, but since wp uses them */ 
/** 
 *  For each service we will check for the installed and enabled before showing options
 *  with appropriate prompts for action.
 */
?>



<table class="form-table">
<tr valign="top" class="tyMedia">
<tr valign="top" class="typeAntiSpam">
  <td colspan="2"> <h3>TypePad AntiSpam</h3>
<a href="http://antispam.typepad.com/">Visit the TypePad AntiSpam homepage</a> </td>
</tr>
<tr valign="top" class="typeAntiSpam">
  <th scope="row" class="antispamCell">AntiSpam Enabled</th>
  <td class="antispamCell"><input type="radio" name="antispam_enabled" value="1" id="antispamEnabledYes" class="antispamEnabled" <?php echo $antispam_enabled_yes; ?> /><label for="antispamEnabledYes">Yes</label>
     <input type="radio" name="antispam_enabled" value="0" id="antispamEnabledNo" class="antispamEnabled" <?php echo $antispam_enabled_no; ?> /><label for="antispamEnabledNo">No</label>
 </td>
</tr>
<tr valign="top" class="antispamRow">
<th scope="row" class="antispamCell">TypePad AntiSpam Key</th>
<td class="antispamCell"><input type="text" name="antispam_key"  value="<?php echo $antispam_val; ?>" size="32" /> 

<?php 
if(!$this->_antispamKey){ 
    echo '<a href="'.TY_KEY_URL.'">Get Key</a>';
} 
?>

</td>
</tr>
<tr valign="top" class="antispamRow">
  <th scope="row" class="antispamCell">Throw Out Spam After A Month?</th>
  <td class="antispamCell"><input type="radio" name="antispam_trash" value="1" id="antispamTrashYes" class="antispamTrash" <?php echo $antispam_trash_yes; ?> /><label for="antispamTrashYes">Yes</label>
     <input type="radio" name="antispam_trash" value="0" id="antispamTrashNo" class="antispamTrash" <?php echo $antispam_trash_no; ?> /><label for="antispamTrashNo">No</label>
 </td>
</tr>


<tr valign="top" class="tyMedia">
  <td><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</td>
</tr>

<tr valign="top" class="tyMedia">
  <td><h3>Six Apart Media</h3>
<a href="http://sixapart.com/advertising">Visit the Six Apart Media homepage</a>
</td>
</tr>

<tr valign="top" class="tyMedia">
  <th scope="row" class="mediaCell">Media Enabled</th>
  <td class="mediaCell"><input type="radio" name="media_enabled" value="1" id="mediaEnabledYes" class="mediaEnabled" <?php echo $media_enabled_yes; ?> /><label for="mediaEnabledYes">Yes</label>
     <input type="radio" name="media_enabled" value="0" id="mediaEnabledNo" class="mediaEnabled" <?php echo $media_enabled_no; ?> /><label for="mediaEnabledNo">No</label>
 </td>
</tr>
<tr valign="top" class="mediaRow">
<th scope="row"  class="mediaCell">Media Javascript</th>
<td class="mediaCell"><input type="text" name="leaderboard_code" value="<?php echo htmlentities($leaderboard_val); ?>" style="width:40em;"/><br />
Log in <a href="http://app.adify.com/MemberPages/SiteCreationFlow/GetAdSpaceTags.aspx" target="_blank">here</a> and get the value for 'Ad Tag for Leaderboard'
</td>
</tr>
<?php
    if(!empty($media_code)){
?>
<tr valign="top" class="mediaRow">
<td colspan="2"  class="mediaCell">
<h3 id="installLeader">To install standard leaderboard-style ads</h3>
</td></tr>

<tr valign="top" class="mediaRow subLeader">
<td colspan="2"  class="mediaCell">
Add the following to <a href="/wp-admin/theme-editor.php?file=/themes/<?php echo get_current_theme(); ?>/header.php"  target="_blank" ><?php echo get_template_directory(); ?>/header.php</a> right after the "&lt;div id=&quot;page&quot;&gt;"<br />
<textarea style="width: 95%">&lt;script type=&quot;text/javascript&quot; src=&quot;http://ads.sixapart.com/custom?id=<?php echo $media_code; ?>&amp;width=728&amp;height=90&amp;js=1&quot;&gt;&lt;/script&gt;</textarea>
<p>
OR<br />
Enter the code <b><?php echo TY_FILTER_PREFIX.$media_code; ?>_LEADER</b> into a post or page.
</p>
<p>
OR<br />
Put this in your theme: <b>&lt;?php echo addTypePadAd('<?php echo $media_code; ?>','LEADER'); ?&gt;</b>
</p>

<h4>Example</h4>
<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id=<?php echo $media_code; ?>&width=728&height=90&js=1"></script>

</td>
</tr>
<tr valign="top" class="mediaRow">
<td colspan="2"  class="mediaCell">
<h3 id="installSidebar">To install standard sidebar ads</h3>
</td></tr>

<tr valign="top" class="mediaRow subSidebar">
<td colspan="2"  class="mediaCell">
If you have a 'widget-aware' theme.    
<ul>
<li>  Go to Widgets</li>
<li> Click Add text</li>
<li>Save Changes</li>
<li>Click Edit Text</li>
<li>Copy and paste the following into text box</li>
</ul>

<textarea style="width: 95%">&lt;script type=&quot;text/javascript&quot; src=&quot;http://ads.sixapart.com/custom?id=<?php echo ($media_code+200); ?>&amp;width=160&amp;height=600&amp;js=1&gt;&lt;/script&gt;</textarea>
<p>
OR<br />
Enter the code <b><?php echo TY_FILTER_PREFIX.($media_code+200); ?>_SIDEBAR</b> into a post or page.
</p>
<p>
OR<br />
Put this in your theme: <b>&lt;?php echo addTypePadAd('<?php echo ($media_code+200); ?>','SIDEBAR'); ?&gt;</b>
</p>
<h4>Example</h4>
<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id=<?php echo ($media_code+200); ?>&width=160&height=600&js=1"></script>
</td>
</tr>
<tr valign="top" class="mediaRow">
<td colspan="2"  class="mediaCell">
<h3 id="installRectangle">To install rectangle ads</h3>
</td></tr>

<tr valign="top" class="mediaRow subRectangle">
<td colspan="2"  class="mediaCell">
Place the following code in your theme.

<textarea style="width: 95%">
&lt;script type=&quot;text/javascript&quot; src=&quot;http://ads.sixapart.com/custom?id=<?php echo ($media_code+100); ?>&amp;width=300&amp;height=250&amp;js=1&gt;&lt;/script&gt;</textarea>
<p>
OR<br />
Enter the code <b><?php echo TY_FILTER_PREFIX.($media_code+100); ?>_RECTANGLE</b> into a post or page.
</p>
<p>
OR<br />
Put this in your theme: <b>&lt;?php echo addTypePadAd('<?php echo ($media_code+100); ?>','RECTANGLE'); ?&gt;</b>
</p>
<h4>Example</h4>
<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id=<?php echo ($media_code+100); ?>&width=300&height=250&js=1"></script>
</td>
</tr>

<tr valign="top" class="mediaRow">
<td colspan="2"  class="mediaCell">
<h3 id="installBadge">To install badge ads</h3>
</td></tr>
<tr valign="top" class="mediaRow subBadge">
<td colspan="2"  class="mediaCell">
Place the following code in your theme.

<textarea style="width: 95%">
&lt;script type=&quot;text/javascript&quot; src=&quot;http://ads.sixapart.com/custom?id=<?php echo ($media_code+300); ?>&amp;width=160&amp;height=90&amp;js=1&gt;&lt;/script&gt;</textarea>
<p>
OR<br />
Enter the code <b><?php echo TY_FILTER_PREFIX.($media_code+300); ?>_BADGE</b> into a post or page.
</p>
<p>
OR<br />
Put this in your theme: <b>&lt;?php echo addTypePadAd('<?php echo ($media_code+300); ?>','BADGE'); ?&gt;</b>
</p>
<h4>Example</h4>
<script language="javascript" type="text/javascript" src="http://ads.sixapart.com/custom?id=<?php echo ($media_code+300); ?>&width=160&height=90&js=1"></script>
</td>
</tr>
<?php
}
?>
</table>

<input type="hidden" name="action" value="update" />
<!-- <input type="hidden" name="page_options" value="new_option_name,some_other_option,option_etc" /> -->

<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div>
<?php
/* jQuery below just handles the click-n-show of the varies part
 * but wouldn't be great to handle some ajax here?  wouldn't it?
 */
?>

<script type="text/javascript">
jQuery.noConflict();
jQuery(function($) {
  $(document).ready(function() {
      $('#installLeader').css('cursor','pointer');
      $('#installSidebar').css('cursor','pointer');
      $('#installRectangle').css('cursor','pointer');
      $('#installBadge').css('cursor','pointer');
<?php 
            echo  $jqueryConditional;
?>
      $('.mediaEnabled').bind('change',function(){
          if ($('#mediaEnabledYes:checked').val()){
              $('.mediaRow').fadeIn('slow');
          }
          else{
              $('.mediaRow').fadeOut('slow');
          }
                                                 }
                             );
      $('.antispamEnabled').bind('change',function(){
          if ($('#antispamEnabledYes:checked').val()){
              $('.antispamRow').fadeIn('slow');
          }
          else{
              $('.antispamRow').fadeOut('slow');
          }
                                                 }
                             );
      $('#installLeader').bind('click',function(){
          $('.subLeader').toggle();
      });
      $('#installSidebar').bind('click',function(){
          $('.subSidebar').toggle();
      });
      $('#installRectangle').bind('click',function(){
          $('.subRectangle').toggle();
      });
      $('#installBadge').bind('click',function(){
          $('.subBadge').toggle();
      });
                              }
                    );


                  }
      );

</script>