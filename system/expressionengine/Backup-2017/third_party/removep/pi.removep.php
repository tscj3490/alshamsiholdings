<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
						'pi_name'			=> 'Remove para',
						'pi_version'		=> '1.0',
						'pi_author'			=> 'sreehari',
						'pi_author_url'		=> 'http://tektide.com/',
						'pi_description'	=> 'paragraph tag remove',
						'pi_usage'			=> Removep::usage()
					);

/**
 * Xml_encode Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			tektide Dev Team
 * @copyright		Copyright (c) 2011 - 2012
 * @link			http://tektide.com
 */

class Removep
{


  function Removep()
  {
	  $this->EE =& get_instance();
	 
	$this->return_data = preg_replace("/(<p>)|(<\/p>)/", "", trim($this->EE->TMPL->tagdata));

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
	  
	<?php  
	$buffer = ob_get_contents();  
	  
	ob_end_clean();   
	  
	return $buffer;  
	}  

}

?>