<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
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
 * ExpressionEngine Subscriber Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Update File
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */

class Subscriber_upd {

	var $version	= '3.0';

	function Subscriber_upd()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
	}

	// --------------------------------------------------------------------

	/**
	 * Module Installer
	 *
	 * @access	public
	 * @return	bool
	 */

	function install()
	{
		$this->EE->load->dbforge();

		$data = array(
			'module_name' 	 => 'Subscriber',
			'module_version' => $this->version,
			'has_cp_backend' => 'y'
		);

		$this->EE->db->insert('modules', $data);		

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 *
	 * @access	public
	 * @return	bool
	 */
	function uninstall()
	{
		$this->EE->load->dbforge();

		$this->EE->db->select('module_id');
		$query = $this->EE->db->get_where('modules', array('module_name' => 'Subscriber'));
		$module_id_row = $query->row();
		$module_id = $module_id_row->module_id;

		$this->EE->db->where('module_id', $module_id);
		$this->EE->db->delete('module_member_groups');

		$this->EE->db->where('module_name', 'Subscriber');
		$this->EE->db->delete('modules');

		$this->EE->db->where('class', 'Subscriber');
		$this->EE->db->delete('actions');

		$this->EE->db->where('class', 'Subscriber_mcp');
		$this->EE->db->delete('actions');
	

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @access	public
	 * @return	bool
	 */
	function update($current='')
	{
		if ($current < 3.0)
		{
			$this->EE->load->dbforge();

			$sql = array();

			foreach($sql as $query)
			{
				$this->EE->db->query($query);
			}

			return TRUE;
		}

		return FALSE;
	}
}

// END CLASS

/* End of file upd.blacklist.php */
/* Location: ./system/expressionengine/modules/blacklist/upd.blacklist.php */