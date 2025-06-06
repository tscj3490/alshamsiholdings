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
 * ExpressionEngine Moblog Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Update File
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */

class Moblog_upd {

	var $version 			= '3.1';

	function Moblog_upd()
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

		$this->EE->db->insert('modules', array(
			'module_name'		=> 'Moblog',
			'module_version'	=> $this->version,
			'has_cp_backend'	=> 'y'	
		));

		$fields = array(
			'moblog_id'					=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'auto_increment' => TRUE),
			'moblog_full_name'			=> array('type' => 'varchar', 'constraint' => 80, 'default' => ''),
			'moblog_short_name'			=> array('type' => 'varchar', 'constraint' => 20, 'default' => ''),
			'moblog_enabled'			=> array('type' => 'char', 'constraint' => 1, 'default' => 'y'),
			'moblog_file_archive'		=> array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
			'moblog_time_interval'		=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => '0'),
			'moblog_type'				=> array('type' => 'varchar', 'constraint' => 10,	'default' => ''),
			'moblog_gallery_id'			=> array('type' => 'int', 'constraint' => 6, 'default' => '0'),
			'moblog_gallery_category'	=> array('type' => 'int', 'cosntraint' => 10, 'unsigned' => TRUE, 'default' => '0'),
			'moblog_gallery_status'		=> array('type' => 'varchar', 'constraint' => 50, 'default' => ''),
			'moblog_gallery_comments'	=> array('type' => 'varchar', 'constraint' => 10, 'default' => 'y'),
			'moblog_gallery_author'		=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'default' => '1'),
			'moblog_channel_id'			=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => '1'),
			'moblog_categories'			=> array('type' => 'varchar', 'constraint' => 25, 'default' => ''),
			'moblog_field_id'			=> array('type' => 'varchar', 'constraint' => 5, 'default' => ''),
			'moblog_status'				=> array('type' => 'varchar', 'constraint' => 50, 'default' => ''),
			'moblog_author_id'			=> array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'default' => '1'),
			'moblog_sticky_entry'		=> array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
			'moblog_allow_overrides'	=> array('type' => 'char', 'constraint' => 1, 'default' => 'y'),
			'moblog_auth_required'		=> array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
			'moblog_auth_delete'		=> array('type' => 'char', 'constraint' => 1, 'default' => 'n'),
			'moblog_upload_directory'	=> array('type' => 'int', 'constraint' => 4, 'unsigned' => TRUE, 'default' => '1'),
			'moblog_template'			=> array('type' => 'text'),
			'moblog_image_size'			=> array('type' => 'int', 'constraint' => 10, 'default' => '0'),
			'moblog_thumb_size'			=> array('type' => 'int', 'constraint' => 10, 'default' => '0'),
			'moblog_email_type'			=> array('type' => 'varchar', 'constraint' => 10, 'default' => ''),
			'moblog_email_address'		=> array('type' => 'varchar', 'constraint' => 125, 'default' => ''),
			'moblog_email_server'		=> array('type' => 'varchar', 'constraint' => 100, 'default' => ''),
			'moblog_email_login'		=> array('type' => 'varchar', 'constraint' => 125, 'default' => ''),
			'moblog_email_password'		=> array('type' => 'varchar', 'constraint' => 125, 'default' => ''),
			'moblog_subject_prefix'		=> array('type' => 'varchar', 'constraint' => 50, 'default' => ''),
			'moblog_valid_from'			=> array('type' => 'text'),
			'moblog_ignore_text'		=> array('type' => 'text'),
			'moblog_ping_servers'		=> array('type' => 'varchar', 'constraint' => 50, 'default' => ''),
		);
		
		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('moblog_id', TRUE);
		$this->EE->dbforge->create_table('moblogs');
		
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
		// Get the module ID from modules
		$qry = $this->EE->db->select('module_id')
							->get_where('modules', array('module_name' => 'Moblog'));

		// Delete all mentions of the moblog from other tables
		$this->EE->db->delete('module_member_groups', array('module_id' => $query->row('module_id')));
		$this->EE->db->delete('modules', array('module_name' => 'Moblog'));
		$this->EE->db->delete('actions', array('class' => 'Moblog'));
		$this->EE->db->delete('actions', array('class' => 'Moblog_mcp'));

		// Drop the table
		$this->EE->load->dbforge();
		$this->EE->dbforge->drop_table('moblogs');

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @access	public
	 * @return	bool
	 */

	function update($current = '')
	{
		if ($current == $this->version)
		{
			return FALSE;
		}

		/** ----------------------------------
		/**  Update Fields
		/** ----------------------------------*/

		if ($current < 2.0)
		{
			$new_fields = array(
				'moblog_type' => array(
					'alter' => array('type' => 'varchar', 'constraint' => 10, 'default' => ''),
					'after' => 'moblog_time_interval'
				),
				'moblog_gallery_id'	=> array(
					'alter' => array('type' => 'int', 'constraint' => 6, 'default' => '0'),
					'after' => 'moblog_type'
				),
				'moblog_gallery_category' => array(
					'alter' => array('type' => 'int', 'cosntraint' => 10, 'unsigned' => TRUE, 'default' => '0'),
					'after' => 'moblog_gallery_id'
				),
				'moblog_gallery_status' => array(
					'alter' => array('type' => 'varchar', 'constraint' => 50, 'default' => ''),
					'after' => 'moblog_gallery_category'
				),
				'moblog_gallery_comments' => array(
					'alter' => array('type' => 'varchar', 'constraint' => 10, 'default' => 'y'),
					'after' => 'moblog_gallery_status'
				),
				'moblog_gallery_author' => array(
					'alter' => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'default' => '1'),
					'after' => 'moblog_gallery_comments'
				),
				'moblog_ping_servers' => array(
					'alter' => array('type' => 'varchar', 'constraint' => 50, 'default' => '')
				),
				'moblog_allow_overrides' => array(
					'alter' => array('type' => 'char', 'constraint' => 1, 'default' => 'y')
				),
				'moblog_sticky_entry' => array(
					'alter' => array('type' => 'char', 'constraint' => 1, 'default' => 'n')
				),
			);
			
			$this->_add_fields($new_fields);
		}

		if ($current < 3.0)
		{
			$this->EE->load->dbforge();

			// @confrim- should be able to drop is_user_blog as well?
			$this->EE->dbforge->drop_column('moblogs', 'is_user_blog');
			$this->EE->dbforge->drop_column('moblogs', 'user_blog_id');
			$this->EE->dbforge->modify_column('moblogs', array(
				'moblog_weblog_id' => array(
					'name'			=> 'moblog_channel_id',
					'type'			=> 'int',
					'constraint'	=> 4,
					'unsigned'		=> TRUE,
					'default'		=> 1
				)
			));
		}

		if ($current < 3.1)
		{
			// Add new columns
			$new_fields = array(
				'moblog_image_size' => array(
					'alter' => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'default' => '0'),
					'after' => 'moblog_template'
				),
				'moblog_thumb_size' => array(
					'alter' => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'default' => '0'),
					'after' => 'moblog_image_size'
				)
			);
			$this->_add_fields($new_fields);

			// Use upload directory sizes instead of moblog specific sizes
			$this->_convert_to_upload_pref_sizes();

			// Drop unused columns
			$this->_drop_columns(array('moblog_image_width', 'moblog_image_height', 'moblog_resize_image', 'moblog_resize_width', 'moblog_resize_height', 'moblog_create_thumbnail', 'moblog_thumbnail_width', 'moblog_thumbnail_height'));
		}

		return TRUE;
	}
	// END
	

	// ------------------------------------------------------------------------
	
	/**
	 * Adds new columns to moblogs table
	 *
	 * @param	array	$new_fields	The associative array containing the new fields to add
	 */	
	function _add_fields($new_fields)
	{
		$this->EE->load->dbforge();
		
		// Get a list of the current fields
		$existing_fields = $this->EE->db->list_fields('moblogs');

		// Add fields that don't exist
		foreach($new_fields AS $new_field_name => $new_field_data)
		{
			if ( ! array_key_exists($new_field_name, $existing_fields))		
			{
				$after = $new_field_data['after'] ? $new_field_data['after'] : ''; 
				$field = array($new_field_name => $new_field_data['alter']);
				
				$this->EE->dbforge->add_column('moblogs', $field, $after);
			}
		}
	}	
		
	// ------------------------------------------------------------------------

	/**
	 * Drops columns from the moblogs table
	 *
	 * @param	array	$columns	Array of columns to drop
	 */
	function _drop_columns($columns)
	{
		$this->EE->load->dbforge();
		
		// Delete old fields
		foreach($existing_fields AS $existing_field)
		{
			if (array_key_exists($existing_field, $deleted_fields))
			{
				$this->EE->dbforge->drop_column('moblogs', $existing_field);
			}
		}
	}	


	// ------------------------------------------------------------------------
	
	/**
	 * Converts moblog image sizes to upload preference sizes
	 */
	function _convert_to_upload_pref_sizes()
	{
		$image_id = 0;
		$thumb_id = 0;

		// Figure out existing sizes and if they're valid or not (not equal to 0)
		$qry = $this->EE->db->get('moblogs');

		// If they are valid, figure uot the upload directory moblog_upload_directory
		foreach ($qry->result() as $row)
		{
			if ($row->moblog_resize_image == 'y')
			{
				$image_id = $this->_create_new_upload_size(
					$row->moblog_short_name.'_image', 
					$row->moblog_image_width,
					$row->moblog_image_height,
					$row->moblog_upload_directory
				);
			}		
			
			if ($row->moblog_create_thumbnail == 'y')
			{
				$thumb_id = $this->_create_new_upload_size(
					$row->moblog_short_name.'_thumb', 
					$row->moblog_thumbnail_width, 
					$row->moblog_thumbnail_height,
					$row->moblog_upload_directory
				);
			}		

			// Make those the image_size and thumb_size
			$this->EE->db->update(
				'moblogs', 
				array(
					'moblog_image_size' => $image_id,
					'moblog_thumb_size' => $thumb_id
				),
				array('moblog_id' => $row->moblog_id)
			);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Creates a new image size given a size name and the moblog's settings as
	 *	a row from the database
	 *
	 * @param	string	$size_name	The name for the size under upload preferences	
	 * @param	integer	$width		Width of the new size
	 * @param	integer	$height		Height of the new size
	 * @return	
	 */
	private function _create_new_upload_size($size_name, $width, $height, $upload_id)
	{
		// Check to see if upload size already exists for upload_location, height and width
		$this->EE->db->where(array(
			'upload_location_id'	=> $upload_id,
			'width'					=> $width,
			'height'				=> $height
		));

		$qry = $this->EE->db->get('file_dimensions');

		if ( ! $qry->num_rows())
		{
			$this->EE->db->insert('file_dimensions', array(
				'upload_location_id'	=> $upload_id,
				'title'					=> $size_name,
				'short_name'			=> $size_name,
				'resize_type'			=> 'constrain', // Default to constrain, not crop
				'width'					=> $width,
				'height'				=> $height,
				'watermark_id'			=> 0
			));

			return $this->EE->db->insert_id();
		}

		return $qry->row('id');
	}
}
// END CLASS

/* End of file upd.moblog.php */
/* Location: ./system/expressionengine/modules/moblog/upd.moblog.php */
