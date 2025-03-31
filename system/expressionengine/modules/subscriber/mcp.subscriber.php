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
 * @category	Modules
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Subscriber_mcp {

	var $value		= '';
	var $LB			= "\r\n";

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Subscriber_mcp( $switch = TRUE )
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		$this->EE->load->dbforge();

		$this->EE->load->helper('form');

		// Updates
		// $this->EE->db->select('module_version');
		// $query = $this->EE->db->get_where('modules', array('module_name' => 'Subscriber'));

		// if ($query->num_rows() > 0)
		// {
		// 	if ( ! $this->EE->db->table_exists('whitelisted'))
		// 	{
		// 		$fields = array(
		// 						'whitelisted_type'  => array(
		// 													'type' 		 => 'varchar',
		// 													'constraint' => '20',
		// 												),
		// 						'whitelisted_value' => array(
		// 													'type' => 'text'
		// 												)
		// 		);

		// 		$this->EE->dbforge->add_field($fields);
		// 		$this->EE->dbforge->create_table('whitelisted');
		// 	}
		// }
	}

	// --------------------------------------------------------------------

	/**
	 * Blacklist Homepage
	 *
	 * @access	public
	 * @return	string
	 */
	function index()
	{
		
		$vars['cp_page_title'] = $this->EE->lang->line('subscriber_module_name');

	
		$vars['subscriberlist'] =  $this->_view_list();
		$vars['elitesubscriberlist'] =  $this->_view_elite_list();

		return $this->EE->load->view('index', $vars, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * View List
	 *
	 * @access	private
	 * @return	mixed
	 */
	function _view_list()
	{
		if ( ! $this->EE->db->table_exists("subscribe_data"))
		{
			show_error('error');
		}

		$vars['cp_page_title'] =  $this->EE->lang->line('subscriber_module_name');
		
		$this->EE->db->select('*');
		$query = $this->EE->db->get('subscribe_data');
		$query = $query->result_array();
		

		return $query;
	}

	function _view_elite_list()
	{
		if ( ! $this->EE->db->table_exists("elite_subscribe_data"))
		{
			show_error('error');
		}

		$vars['cp_page_title'] =  'Elite subscribers List';
		
		$this->EE->db->select('*');
		$query = $this->EE->db->get('elite_subscribe_data');
		$query = $query->result_array();
		

		return $query;
	}

}
// END CLASS

/* End of file mcp.blacklist.php */
/* Location: ./system/expressionengine/modules/blacklist/mcp.blacklist.php */