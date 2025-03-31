<?php

$plugin_info = array(
  'pi_name' => 'Remove Paragraph Tags',
  'pi_version' => '1.0',
  'pi_author' => 'Zac Gordon',
  'pi_author_url' => 'http://wideskydesigns.com/',
  'pi_description' => 'Removes paragraph tags',
  'pi_usage' => Removepar::usage()
  );

class Removepar
{

var $return_data = "";

  function Removepar()
  {
    global $TMPL;

	$this->return_data = preg_replace("/(<p>)|(<\/p>)/", "", $TMPL->tagdata);

  }

  // ----------------------------------------
  //  Plugin Usage
  // ----------------------------------------

  // This function describes how the plugin is used.
  //  Make sure and use output buffering

  function usage()
  {
  ob_start(); 
  ?>
	This plugin the paragraph tags between the {exp:removepar}{field_name}{/exp:removepar}
  <?php
  $buffer = ob_get_contents();
	
  ob_end_clean(); 

  return $buffer;
  }
  // END

}

?>