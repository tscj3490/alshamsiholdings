<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
						'pi_name'			=> 'Decrease by one',
						'pi_version'		=> '1.0',
						'pi_author'			=> 'sreehari',
						'pi_author_url'		=> 'http://tektide.com/',
						'pi_description'	=> 'Decrease by one ',
						'pi_usage'			=> decrement::usage()
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

class decrement
{


  function decrement()
  {
	  $this->EE =& get_instance();
	 
	 //$this->return_data(trim($this->EE->TMPL->tagdata)--);
	 
	 $var=trim($this->EE->TMPL->tagdata);
	 $var--;
	 
	 $this->return_data =  $var;
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