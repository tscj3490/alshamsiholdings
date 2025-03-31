<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
						'pi_name'			=> 'Replace space by _',
						'pi_version'		=> '1.0',
						'pi_author'			=> 'sreehari',
						'pi_author_url'		=> 'http://tektide.com/',
						'pi_description'	=> 'Replace space by _',
						'pi_usage'			=> removespace::usage()
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

class removespace
{


  function removespace()
  {
	  $this->EE =& get_instance();
	 
	 //$this->return_data(trim($this->EE->TMPL->tagdata)--);
	 
	 $var=str_replace(' ', '_', trim($this->EE->TMPL->tagdata))  ;
	
	 
	 $this->return_data = $var;
	//$this->return_data = preg_replace("/(<p>)|(<\/p>)/", "", trim());

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