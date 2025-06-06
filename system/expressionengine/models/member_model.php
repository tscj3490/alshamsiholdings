<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
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
 * ExpressionEngine Member Model
 *
 * @package		ExpressionEngine
 * @subpackage	Core
 * @category	Model
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Member_model extends CI_Model {
	
	/**
	 * Get Username
	 *
	 * Get a username from a member id
	 *
	 * @access	public
	 * @param	int
	 * @param	string
	 */
	function get_username($id = '', $field = 'screen_name')
	{
		if ($id == '')
		{
			// no id, return false
			return FALSE;
		}

		$this->db->select('username, screen_name');
		$this->db->where('member_id', $id);
		$member_info = $this->db->get('members');

		if ($member_info->num_rows() != 1)
		{
			// no match, return false
			return FALSE;
		}
		else
		{
			$member_name = $member_info->row();
			if ($field == 'username')
			{
				return $member_name->username;
			}
			else
			{
				return $member_name->screen_name;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get Upload Groups
	 *
	 * @access	public
	 * @return	mixed
	 */
	function get_upload_groups()
	{
		$this->db->select('group_id, group_title');
		$this->db->from('member_groups');
		$this->db->where("group_id != '1' AND group_id != '2' AND group_id != '3' AND group_id != '4'");
		$this->db->where('site_id', $this->config->item('site_id'));
		$this->db->order_by('group_title');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Memmbers
	 *
	 * Get a collection of members
	 *
	 * @access	public
	 * @param	int
	 * @param	int
	 * @param	int
	 * @param	string
	 * @return	mixed
	 */
	function get_members($group_id = '', $limit = '', $offset = '', $search_value = '', $order = array(), $column = 'all')
	{
		if ($group_id !== '')
		{
			$this->db->where("members.group_id", $group_id);
		}

		if ($limit != '')
		{
			$this->db->limit($limit);
		}

		if ($offset != '')
		{
			$this->db->offset($offset);
		}

		if ($search_value != '')
		{
			if ($column == 'all')
			{
				$this->db->where("(`exp_members`.`screen_name` LIKE '%".$this->db->escape_like_str($search_value)."%' OR `exp_members`.`username` LIKE '%".$this->db->escape_like_str($search_value)."%' OR `exp_members`.`email` LIKE '%".$this->db->escape_like_str($search_value)."%')", NULL, TRUE);		
			}
			else
			{
				$this->db->like('members.'.$column, $search_value);
			}
		}

		if (is_array($order) && count($order) > 0)
		{
			foreach ($order as $key => $val)
			{
				$this->db->order_by($key, $val);
			}
		}
		else
		{
			$this->db->order_by('join_date');
		}

		$this->db->select("members.username, members.member_id, members.screen_name, members.email, members.join_date, members.last_visit, members.group_id, members.member_id, members.in_authorlist");
		$this->db->from("members");


		$members = $this->db->get();

		if ($members->num_rows() == 0)
		{
			return FALSE;
		}
		else
		{
			return $members;
		}
	}

	// --------------------------------------------------------------------

	/**
	 *	Count Members
	 *
	 *	@access public
	 *	@return int
	 */
	function get_member_count($group_id = FALSE)
	{
		$member_ids = array();

		if ($group_id != '')
		{
			$this->db->select('member_id');
			$this->db->where('group_id', $group_id);
			$query = $this->db->get('members');

			foreach($query->result() as $member)
			{
				$member_ids[] = $member->member_id;
			}

			// no member_ids in that group?	 Might as well return now
			if (count($member_ids) < 1)
			{
				return FALSE;
			}
		}

		// now run the query for the actual results
		if ($group_id)
		{
			$this->db->where_in("members.member_id", $member_ids);
		}

		$this->db->select("COUNT(*) as count");
		$this->db->from("member_groups");
		$this->db->from("members");
		$this->db->where("members.group_id = " .$this->db->dbprefix("member_groups.group_id"));
		$this->db->where("member_groups.site_id", $this->config->item('site_id'));

		$members = $this->db->get();

		return ($members->num_rows() == 0) ? FALSE : $members->row('count');
	}

	// --------------------------------------------------------------------

	/**
	 * Get All Member Fields
	 *
	 * @access	public
	 * @param	array	// associative array of where 	
	 * @param	bool	// restricts to public fields for non-superadmins
	 * @return	object
	 */
	function get_all_member_fields($additional_where = array(), $restricted = TRUE)
	{
		// Extended profile fields
		$this->db->from('member_fields');

		if ($restricted == TRUE && $this->session->userdata('group_id') != 1)
		{
			$this->db->where('m_field_public', 'y');
		}
		
		foreach ($additional_where as $where)
		{
			foreach ($where as $field => $value)
			{
				if (is_array($value))
				{
					$this->db->where_in($field, $value);
				}
				else
				{
					$this->db->where($field, $value);
				}
			}
		}		

		$this->db->order_by('m_field_order');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member Data
	 *
	 * @access	public
	 * @return	object
	 */
	function get_all_member_data($id)
	{
		$this->db->from('member_data');
		$this->db->where('member_id', $id);

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member Data
	 *
	 * This function retuns author data for a single member
	 *
	 * @access	public
	 * @param	integer		Member Id
	 * @param	array		Optional fields to return
	 * @return	mixed
	 */
	function get_member_data($member_id = FALSE, $fields = array())
	{
		if (count($fields) >= 1)
		{
			$this->db->select($fields);
		}

		$this->db->where('member_id', (int) $member_id);
		return $this->db->get('members');
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member Ignore List
	 *
	 * This function retuns author data for a single member
	 *
	 * @access	public
	 * @param	integer		Member Id
	 * @return	object
	 */
	function get_member_ignore_list($member_id = FALSE)
	{
		$query = $this->get_member_data($this->id, array('ignore_list'));

		$ignored = ($query->row('ignore_list')	== '') ? array('') : explode('|', $query->row('ignore_list'));

		$this->db->select('screen_name, member_id');
		$this->db->where_in('member_id', $ignored);
		$this->db->order_by('screen_name');

		return $this->db->get('members');
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member Quicklinks
	 *
	 * This function retuns an array of the users quick links
	 *
	 * @access	public
	 * @param	integer		Member Id
	 * @return	array
	 */
	function get_member_quicklinks($member_id = FALSE)
	{
		$query = $this->get_member_data($member_id, array('quick_links'));

		$i = 1;

		$quicklinks = array();

		if (count($query->row('quick_links')) != 0 AND $query->row('quick_links') != '')
		{
			foreach (explode("\n", $query->row('quick_links') ) as $row)
			{
				$x = explode('|', $row);

				$quicklinks[$i]['title'] = (isset($x['0'])) ? $x['0'] : '';
				$quicklinks[$i]['link'] = (isset($x['1'])) ? $x['1'] : '';
				$quicklinks[$i]['order'] = (isset($x['2'])) ? $x['2'] : '';

				$i++;
			}
		}

		return $quicklinks;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member Emails
	 *
	 * By default fetches member_id, email, and screen_name.  Additional fields and
	 * WHERE clause can be specified by using the array arguments
	 *
	 * @access	public
	 * @param	array
	 * @param	array	array of associative field => value arrays
	 * @return	object
	 */
	function get_member_emails($additional_fields = array(), $additional_where = array())
	{
		if ( ! is_array($additional_fields))
		{
			$additional_fields = array($additional_fields);
		}

		if ( ! isset($additional_where[0]))
		{
			$additional_where = array($additional_where);
		}

		if (count($additional_fields) > 0)
		{
			$this->db->select(implode(',', $additional_fields));
		}

		$this->db->select("m.member_id, m.screen_name, m.email");
		$this->db->from("members AS m");
		$this->db->join('member_groups AS mg', 'mg.group_id = m.group_id');
		$this->db->where('mg.site_id', $this->config->item('site_id'));

		foreach ($additional_where as $where)
		{
			foreach ($where as $field => $value)
			{
				if (is_array($value))
				{
					$this->db->where_in($field, $value);
				}
				else
				{
					$this->db->where($field, $value);
				}
			}
		}

		$this->db->order_by('member_id');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Create member
	 *
	 * This function creates a new member
	 *
	 * @access	public
	 * @param	array
	 * @param	mixed // custom member data optional
	 * @return	int		member id
	 */
	function create_member($data = array(), $cdata = FALSE)
	{
		// Insert into the main table
		$this->db->insert('members', $data);

		// grab insert id
		$member_id = $this->db->insert_id();

		// Create a record in the custom field table
		if ($cdata)
		{
			$this->db->insert('member_data', array_merge(array('member_id' => $member_id), $cdata));
		}
		else
		{
			$this->db->insert('member_data', array('member_id' => $member_id));
		}

		// Create a record in the member homepage table
		$this->db->insert('member_homepage', array('member_id' => $member_id));

		return $member_id;
	}

	// --------------------------------------------------------------------

	/**
	 * Update member
	 *
	 * This function updates a member
	 *
	 * @access	public
	 * @param	int
	 * @param	array
	 * @return	void
	 */
	function update_member($member_id = '', $data = array(), $additional_where = array())
	{
		$default_null = array('bday_y',	'bday_m', 'bday_d');
		
		foreach($default_null as $val)
		{
			if (isset($data[$val]) && $data[$val] == '')
			{
				$data[$val] = NULL;
			}
		}

		if ( ! isset($additional_where[0]))
		{
			$additional_where = array($additional_where);
		}

		foreach ($additional_where as $where)
		{
			foreach ($where as $field => $value)
			{
				if (is_array($value))
				{
					$this->db->where_in($field, $value);
				}
				else
				{
					$this->db->where($field, $value);
				}
			}
		}

		$this->db->where('member_id', $member_id);
		$this->db->update('members', $data);
	}


	// --------------------------------------------------------------------

	/**
	 * Update Member Group
	 *
	 * This function updates a member group
	 *
	 * @access	public
	 * @param	int
	 * @param	array
	 * @return	void
	 */
	function update_member_group($member_group_id = '')
	{
		// for later use
	}

	// --------------------------------------------------------------------

	/**
	 * Update member data
	 *
	 * This function updates a member's data
	 *
	 * @access	public
	 * @param	int
	 * @param	array
	 * @return	void
	 */
	function update_member_data($member_id = '', $data = array(), $additional_where = array())
	{
		if ( ! isset($additional_where[0]))
		{
			$additional_where = array($additional_where);
		}

		foreach ($additional_where as $where)
		{
			foreach ($where as $field => $value)
			{
				if (is_array($value))
				{
					$this->db->where_in($field, $value);
				}
				else
				{
					$this->db->where($field, $value);
				}
			}
		}

		$this->db->where('member_id', $member_id);
		$this->db->update('member_data', $data);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete member
	 *
	 * This function deletes all member data, and all communications from said member
	 * stored on the system, and returns the id for further use
	 *
	 * @access	public
	 * @param	int		member id
	 * @return	int		memeber id
	 */
	function delete_member($member_id = '')
	{
		// member data
		$this->db->delete('members', array('member_id' => $member_id));
		$this->db->delete('member_data', array('member_id' => $member_id));
		$this->db->delete('member_homepage', array('member_id' => $member_id));

		// mail and communications
		$this->db->delete('message_copies', array('sender_id' => $member_id));
		$this->db->delete('message_folders', array('member_id' => $member_id));
		$this->db->delete('message_listed', array('member_id' => $member_id));

		return $member_id;
	}

	// --------------------------------------------------------------------

	/**
	 * Remove From Author List
	 *
	 * Turns on the preference to make a member part of the authorlist
	 *
	 * @access	public
	 * @param	integer
	 * @return	void
	 */
	function delete_from_authorlist($member_ids = array())
	{
		if ( ! is_array($member_ids))
		{
			$member_ids = array($member_ids);
		}

		$this->db->where_in('member_id', $member_ids);
		$this->db->set('in_authorlist', 'n'); 
		$this->db->update('members');
	}

	// --------------------------------------------------------------------

	/**
	 * Update Author List
	 *
	 * Turns on the preference to make a member part of the authorlist
	 *
	 * @access	public
	 * @param	array
	 * @return	void
	 */
	function update_authorlist($member_ids = array())
	{
		if ( ! is_array($member_ids))
		{
			$member_ids = array($member_ids);
		}

		$this->db->where_in('member_id', $member_ids);
		$this->db->set('in_authorlist', 'y'); 
		$this->db->update('members');
	}

	// --------------------------------------------------------------------

	/**
	 * Get Author Groups
	 *
	 * This function retuns an array if group ids for member groups
	 * who are listed as authors for a channel
	 *
	 * @access	public
	 * @param	integer
	 * @return	array
	 */
	function get_author_groups($channel_id = '')
	{
		$this->db->select('member_groups.group_id');
		$this->db->join("channel_member_groups", "member_groups.group_id = channel_member_groups.group_id", 'left');
		$this->db->where('member_groups.include_in_authorlist', 'y');
		$this->db->where("channel_member_groups.channel_id", $channel_id);
		$this->db->or_where("member_groups.group_id", 1);
		$results = $this->db->get('member_groups');

		$group_ids = array();

		foreach ($results->result() as $result)
		{
			$group_ids[] = $result->group_id;
		}

		return $group_ids;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Authors
	 *
	 * This function returns a set of members who are authors in a set channel
	 *
	 * @access	public
	 * @param	integer
	 * @return	mixed
	 */
	function get_authors($author_id = FALSE, $limit = FALSE, $offset = FALSE)
	{
		$this->db->select('members.member_id, members.group_id, 
						members.username, members.screen_name, members.in_authorlist, member_groups.*');
		$this->db->from('members');
		$this->db->join("member_groups", "members.group_id = members.group_id", 'left');
		
		if ($author_id)
		{
			$this->db->where('members.member_id !=', $author_id);
		}
		
		$this->db->where('('.$this->db->dbprefix('members').'.in_authorlist = "y" OR
		 						'.$this->db->dbprefix('member_groups').'.include_in_authorlist = "y")');
		$this->db->where('members.group_id = '.$this->db->dbprefix('member_groups').'.group_id');
		$this->db->where('member_groups.site_id', $this->config->item('site_id'));
	
		$this->db->order_by('members.screen_name', 'ASC');
		$this->db->order_by('members.username', 'ASC');
		
		if ($limit)
		{
			$this->db->limit($limit, $offset);
		}
		
		return $this->db->get();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Get Authors Simple
	 *
	 * This function returns a set of members who are authors in a set channel- member group data is omitted
	 *
	 * @access	public
	 * @param	integer
	 * @return	mixed
	 */
	function get_authors_simple($author_id = FALSE, $limit = FALSE, $offset = FALSE)
	{
		$group_ids = array();
			
		$this->db->select('group_id');
		$this->db->where('include_in_authorlist', 'y');
		$this->db->where('site_id', $this->config->item('site_id'));
		$group_query = $this->db->get('member_groups');
			
		if ($group_query->num_rows() > 0)
		{
			foreach($group_query->result() as $group)
			{
				$group_ids[] = $group->group_id;
			}
		}
			
		$this->db->select('members.member_id, members.group_id, 
							members.username, members.screen_name, members.in_authorlist');
		$this->db->from('members');
		
		if ($author_id)
		{
			$this->db->where('members.member_id !=', $author_id);
		}
		
		$this->db->where('in_authorlist', "y");
		
		if (count($group_ids) > 0)
		{
			$this->db->or_where_in('group_id', $group_ids);	
		}		
	
		$this->db->order_by('members.screen_name', 'ASC');
		$this->db->order_by('members.username', 'ASC');
		
		if ($limit)
		{
			$this->db->limit($limit, $offset);
		}
		
		return $this->db->get();
	}



	// --------------------------------------------------------------------

	/**
	 * Get Member Groups
	 *
	 * Returns only the title and id by default, but additional fields can be passed
	 * and automatically added to the query either as a string, or as an array.	 
	 * This allows the same function to be used for "lean" and for larger queries.
	 *
	 * @access	public
	 * @param	array
	 * @param	array	array of associative field => value arrays
	 * @return	mixed
	 */
	function get_member_groups($additional_fields = array(), $additional_where = array(), $limit = '', $offset = '')
	{
		if ( ! is_array($additional_fields))
		{
			$additional_fields = array($additional_fields);
		}

		if ( ! isset($additional_where[0]))
		{
			$additional_where = array($additional_where);
		}

		if (count($additional_fields) > 0)
		{
			$this->db->select(implode(',', $additional_fields));
		}

		$this->db->select("group_id, group_title");
		$this->db->from("member_groups");
		$this->db->where("site_id", $this->config->item('site_id'));

		if ($limit != '')
		{
			$this->db->limit($limit);
		}

		if ($offset !='')
		{
			$this->db->offset($offset);
		}

		foreach ($additional_where as $where)
		{
			foreach ($where as $field => $value)
			{
				if (is_array($value))
				{
					$this->db->where_in($field, $value);
				}
				else
				{
					$this->db->where($field, $value);
				}
			}
		}

		$this->db->order_by('group_id, group_title');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Delete Member Group
	 *
	 * Deletes a member group, and optionally reassigns its members to another group
	 *
	 * @access	public
	 * @param	int		The group to be deleted
	 * @param	int		The group to reassign members to
	 * @return	void
	 */
	function delete_member_group($group_id = '', $reassign_group = FALSE)
	{
		if ($reassign_group !== FALSE)
		{
			// reassign current members to new group
			$this->db->set(array('group_id'=>$reassign_group));
			$this->db->where('group_id', $group_id);
			$this->db->update('members');
		}

		// remove the group
		$this->db->delete('member_groups', array('group_id' => $group_id));

		// remove them from uploads table
		$this->db->delete('upload_no_access', array('member_group' => $group_id));
	}

	// --------------------------------------------------------------------

	/**
	 * Count Members
	 *
	 * @access	public
	 * @param	int
	 * @return	int
	 */
	function count_members($group_id = '', $search_value = '', $search_field = '')
	{
		$valid_fields = array('all', 'screen_name', 'username', 'email');
		$search_in = 'screen_name';
		
		if ($group_id !== '')
		{
			$this->db->where("members.group_id", $group_id);
		}

		if ($search_value != '')
		{
			if (in_array($search_field, $valid_fields))
			{
				$search_in = $search_field;
			}

			if ($search_in == 'all')
			{
				$this->db->where("(`exp_members`.`screen_name` LIKE '%".$this->db->escape_like_str($search_value)."%' OR `exp_members`.`username` LIKE '%".$this->db->escape_like_str($search_value)."%' OR `exp_members`.`email` LIKE '%".$this->db->escape_like_str($search_value)."%')", NULL, TRUE);			
			}
			else
			{			
				$this->db->like($search_in, $search_value);
			}
		}

		return $this->db->count_all_results('members');
	}


	// --------------------------------------------------------------------

	/**
	 * Count Recrods
	 *
	 * @access	public
	 * @param	table
	 * @return	int
	 */
	function count_records($table = '')
	{
		return $this->db->count_all($table);
	}

	// --------------------------------------------------------------------

	/**
	 * Count Member Entries
	 *
	 * @access	public
	 * @param	array
	 * @return	int
	 */
	function count_member_entries($member_ids = array())
	{
		if ( ! is_array($member_ids))
		{
			$member_ids = array($member_ids);
		}

		$this->db->select('entry_id');
		$this->db->from('channel_titles');
		$this->db->where_in('author_id', $member_ids);

		return $this->db->count_all_results();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Members Group Ids
	 *
	 * Provided a string or an array of member ids, returns an array 
	 * of unique group ids that they belong to
	 *
	 * @access	public
	 * @param	array
	 * @return	mixed
	 */
	function get_members_group_ids($member_ids = array())
	{
		if ( ! is_array($member_ids))
		{
			$member_ids = array($member_ids);
		}

		$this->db->select("group_id");
		$this->db->from("members");
		$this->db->where_in("member_id", $member_ids);

		$groups = $this->db->get();

		// superadmins are always viable
		$group_ids[] = 1;

		if ($groups->num_rows() > 0)
		{
			foreach($groups->result() as $group)
			{
				$group_ids[] = $group->group_id;
			}
		}

		$group_ids = array_unique($group_ids);

		return $group_ids;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Custom Member Fields
	 *
	 * This function retuns all custom member fields
	 *
	 * @access	public
	 * @param	an optional member id to restrict the search on
	 * @return	object
	 */
	function get_custom_member_fields($member_id = '')
	{
		if ($member_id != '')
		{
			$this->db->where('m_field_id', $member_id);
		}

		$this->db->select('m_field_id, m_field_order, m_field_label, m_field_name');
		$this->db->from('member_fields');
		$this->db->order_by('m_field_order');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Member By Screen Name
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed
	 */
	function get_member_by_screen_name($screen_name = '')
	{
		$this->db->select('member_id');
		$this->db->from('members');
		$this->db->where('screen_name', $screen_name);

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/*
	 * Get IP Members
	 *
	 * Used in search of ip addresses within members table
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed
	 */
	function get_ip_members($ip_address = '', $limit = 10, $offset = 0)
	{
		$this->db->select('member_id, username, screen_name, ip_address, email, join_date');
		$this->db->like('ip_address', $ip_address, 'both');
		$this->db->from('members');
		$this->db->order_by('screen_name');
		$this->db->limit($limit);
		$this->db->offset($offset);

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Get Group Members
	 *
	 * Returns members of a group
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed
	 */ 
	function get_group_members($group_id, $order_by = 'join_date')
	{

		$this->db->select('member_id, username, screen_name, email, join_date');
		$this->db->where('group_id', $group_id);
		$this->db->from('members');
		$this->db->order_by($order_by, 'desc');

		return $this->db->get();
	}

	// --------------------------------------------------------------------

	/**
	 * Check Duplicate
	 *
	 * Checks for duplicated member fields
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed
	 */
	function check_duplicate($value = '', $field = 'username')
	{

		$this->db->like($field, $value);
		$this->db->from('members');

		if ($this->db->count_all_results() == 0)
		{
			// no duplicates
			return FALSE;
		}
		else
		{
			// duplicates found
			return TRUE;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get Theme List
	 *
	 * Show file listing as a pull-down
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	string
	 */ 
	function get_theme_list($path = '')
	{
		if ($path == '')
		{
			return;
		}

		$themes = array();

		if ($fp = @opendir($path))
		{ 

			while (false !== ($file = readdir($fp)))
			{
				if (@is_dir($path.$file) && strpos($file, '.') === FALSE)
				{
					$themes[$file] = ucwords(str_replace("_", " ", $file));
				}
			}

			closedir($fp); 
		} 

		return $themes;
	}

	// --------------------------------------------------------------------

	/**
	 * Get Profile Templates
	 *
	 * Returns an array of profile themes with the name as key, and the humanized
	 * name as the value
	 *
	 * @access	public
	 * @param	string	The path to the themes
	 * @return	array
	 */ 
	function get_profile_templates($path = PATH_MBR_THEMES)
	{
		$themes = array();
		$this->load->helper('directory');

		foreach (directory_map($path, TRUE) as $file)
		{
			if (is_dir($path.$file) AND strncmp('.', $file, 1) != 0)
			{
				$themes[$file] = ucfirst(str_replace("_", " ", $file));
			}
		}
		
		return $themes;
	}

	// --------------------------------------------------------------------

	/**
	 * Insert Group Layout
	 *
	 * Inserts layout information for member groups for the publish page, saved as
	 * a serialized array.
	 *
	 * @access	public
	 * @param	mixed	Member group
	 * @param	int		Field group
	 * @param	array	The layout of the fields
	 * @return	bool
	 */
	function insert_group_layout($member_groups = array(), $channel_id = '', $layout_info = array())
	{
		if ( ! is_array($member_groups))
		{
			$member_groups = array($member_groups);
		}

		$error_count = 0; // assume no errors so far

		foreach ($member_groups as $member_group)
		{
			// remove all data already in there
			$this->delete_group_layout($member_group, $channel_id);

			// Remove layout function on the CP works by passing an empty array
			if (count($layout_info) > 0)
			{
				$this->db->set("site_id", $this->config->item('site_id'));
				$this->db->set("channel_id", $channel_id);
				$this->db->set("field_layout", serialize($layout_info));
				$this->db->set("member_group", $member_group);

				if ( ! $this->db->insert('layout_publish'))
				{
					$error_count++;
				}
			}
		}
		
		if ($error_count > 0)
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Delete Group Layout
	 *
	 * Removes layout information for member groups for the publish page.
	 *
	 * @access	public
	 * @param	mixed	Member group
	 * @param	int		Field group
	 * @return	void
	 */
	function delete_group_layout($member_group = '', $channel_id = '')
	{
		$this->db->where("site_id", $this->config->item('site_id'));
		$this->db->where("channel_id", $channel_id);
		
		if ($member_group != '')
		{
			$this->db->where("member_group", $member_group);
		}
		
		$this->db->delete('layout_publish');
	}

	// --------------------------------------------------------------------

	/**
	 * Get Group Layout
	 *
	 * Gets layout information for member groups for the publish page
	 *
	 * @access	public
	 * @param	int Member group
	 * @param	int		Field group
	 * @return	array
	 */
	function get_group_layout($member_group = '', $channel_id = '')
	{
		$this->load->model('layout_model');
		
		return $this->layout_model->get_layout_settings(array(
			'site_id' => $this->config->item('site_id'),
			'channel_id' => $channel_id,
			'member_group' => $member_group
		));
	}

	// --------------------------------------------------------------------

	/**
	 * Get All Group Layouts
	 *
	 * Gets layout information for member groups for the publish page
	 *
	 * @access	public
	 * @param	int Member group
	 * @param	int		Field group
	 * @return	array
	 */
	function get_all_group_layouts($channel_id = array())
	{
		if ( ! is_array($channel_id))
		{
			$channel_id = array($channel_id);
		}

		if ( ! empty($channel_id))
		{
			$this->db->where_in("channel_id", $channel_id);	
		}

		$layout_data = $this->db->get('layout_publish');

		if ($layout_data->num_rows() > 0)
		{
			$returned_data = $layout_data->result_array();
		}
		else
		{
			$returned_data = array();
		}

		return $returned_data;
	}

	// --------------------------------------------------------------------


	/**
	 * Localization Default
	 *
	 * This function retuns author data for a single member
	 *
	 * @access	public
	 * @return	array
	 */
	function get_localization_default($get_id = FALSE)
	{
		$this->db->select('member_id, timezone, daylight_savings, time_format');
		$this->db->where('localization_is_site_default', 'y');
		$query = $this->db->get('members');

		if ($query->num_rows() == 1)
		{
			$config = array(
							'default_site_timezone' => $query->row('timezone'),
							'default_site_dst'		=> $query->row('daylight_savings')
							);
							
			if ($get_id)
			{
				$config['member_id'] = $query->row('member_id');
			}				
		}
		else
		{
			$config = array(
							'default_site_timezone' => '',
							'default_site_dst'		=> ''
							);
			if ($get_id)
			{
				$config['member_id'] = '';
			}
		}

		return $config;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Get Notepad Content
	 *
	 * Returns the contents of a user's notepad
	 *
	 * @access	public
	 * @return	array
	 */
	function get_notepad_content($id = '')
	{
		$id = $id ? $id : $this->session->userdata('member_id');
		
		$this->db->select('notepad');
		$this->db->from('members');
		$this->db->where('member_id', (int) $id);
		$notepad_query = $this->db->get();

		if ($notepad_query->num_rows() > 0)
		{
			$notepad_result = $notepad_query->row();
			return $notepad_result->notepad;
		}

		return '';
	}

	// --------------------------------------------------------------------

	/**
	 * Can Access Module
	 *
	 * @access	public
	 * @return	boolean
	 */
	function can_access_module($module, $group_id = '')
	{	
		// Superadmin sees all		
		if ($this->session->userdata('group_id') === 1)
		{
			return TRUE;
		}
		
		if ( ! $group_id)
		{
			$group_id = $this->session->userdata('group_id');
		}

		$this->db->select('modules.module_id, module_member_groups.group_id');
		$this->db->where('LOWER('.$this->db->dbprefix.'modules.module_name)', strtolower($module));
		$this->db->join('module_member_groups', 'module_member_groups.module_id = modules.module_id');
		$this->db->where('module_member_groups.group_id', $group_id);
		
		$query = $this->db->get('modules');

		return ($query->num_rows() === 0) ? FALSE : TRUE;
	}
	
}

/* End of file member_model.php */
/* Location: ./system/expressionengine/models/member_model.php */