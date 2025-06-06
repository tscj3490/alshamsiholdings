<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * ExpressionEngine String Helper
 *
 * @package		ExpressionEngine
 * @subpackage	Helpers
 * @category	Helpers
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */

// ------------------------------------------------------------------------


 /**
 * Unique Marker
 *
 * The template library and some of our modules temporarily replace
 * pieces of code with a random string. These need to be unique per
 * request to avoid potential security issues.
 * 
 * @access	public
 * @param	string	marker identifier
 * @return	string
 */	
function unique_marker($ident)
{
	static $rand;
	
	if ( ! $rand)
	{
		$rand = random_string('alnum', 32);
	}
	
	return $rand.$ident;
}

/* End of file EE_string_helper.php */
/* Location: ./system/expressionengine/helpers/EE_string_helper.php */