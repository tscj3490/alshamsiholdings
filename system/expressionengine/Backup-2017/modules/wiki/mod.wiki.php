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
 * ExpressionEngine Wiki Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		ExpressionEngine Dev Team
 * @link		http://expressionengine.com
 */
class Wiki {

	var $version				= '2.3';
	
	var $base_path				= '';
	var $profile_path			= '';
	var $base_url				= '';
	var $seg_parts				= array();
	var $admins					= array('1');
	var $users					= array('1', '5');
	var $conditionals			= array();
	var $title					= '';
	var $topic					= '';
	var $revision_id			= '';
	var $theme_path				= '';
	var $theme_url				= '';
	var $image_url				= '';
	
	//  Namespaces
	var $main_ns				= 'Main';
	var $special_ns				= 'Special';  // Deutsch: Spezial
	var $file_ns				= 'File';
	var $category_ns			= 'Category'; // Deutsch: Kategorie
	var $image_ns				= 'Image';
	var $current_namespace		= '';
	var $namespaces				= array();
	
	// Settings
	var $wiki_id				= 1;
	var $label_name				= 'EE Wiki';
	var $use_captchas 			= 'n';
	var $text_format			= 'xhtml';
	var $html_format			= 'safe';
	var $auto_links				= "n";
	var $upload_dir				= '';
	var $valid_upload_dir		= 'n';
	var $can_upload				= 'n';	
	var $moderation_emails		= '';
	var $revision_limit			= 100;
	var $author_limit			= 75;
	var $min_length_keywords	= 3;
	var	$cache_expire			= 2;  // How many hours should we keep wiki search caches?	
	var $cats_separator			= '::';
	var $cats_display_separator = ' -> ';
	var $cats_use_namespaces	= 'n';
	var $cats_assign_parents	= 'y';
	
	// Category Retrieval
	var $temp_array				= array();
	var $cat_array				= array();
	var $show_these				= FALSE;
	var $cat_depth				= 0;
	var $parent_cats			= array();
	
	// Pagination variables
	var $paginate				= FALSE;
	var $pagination_links		= '';
	var $page_next				= '';
	var $page_previous			= '';
	var $current_page			= 1;
	var $total_pages			= 1;
	var $total_rows				=  0;
	var $p_limit				= '';
	var $p_page					= '';
	var $pagination_sql			= '';
	
	var $return_data 			= '';
	
	/** ----------------------------------------
	/**  Constructor
	/** ----------------------------------------*/
	
	function Wiki($return = FALSE)
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();

		if ($return === TRUE)
		{
			return;
		}

		$this->EE->lang->loadfile('wiki');
			
		/** ----------------------------------------
		/**  Update Module Code
		/** ----------------------------------------*/
		
		if (isset($this->EE->TMPL->module_data['Wiki']['version']) && $this->version > $this->EE->TMPL->module_data['Wiki']['version'])
		{
			$this->update_module($this->EE->TMPL->module_data['Wiki']['version']);
		}
		
		/* ----------------------------------------
		/*
		/*  There are five main kinds of pages in the ExpressionEngine Wiki:
		/*
		/*	- Main/Index 
		/*	- Article Page (Including Namespaces)
		/*	- Edit Topic Page
		/*	- History Topic Page
		/*	- Special Page (ex: Uploads, Search Results, Recent Changes)
		/*	
		/*	Now, the {exp:wiki} tag will be put into a template group
		/*  and a template, which is set in base_path="" so that
		/*  we can discover the structure of the URLs and what segments
		/*  to read for what.
		/* ----------------------------------------*/
		
		if (($this->base_path = $this->EE->TMPL->fetch_param('base_path')) === FALSE)
		{
			return $this->return_data = $this->EE->lang->line('basepath_unset');
		}
		
		$this->base_path = rtrim($this->base_path, '/').'/';
		$this->base_url = $this->EE->functions->create_url($this->base_path).'/'; 
		
		/* ----------------------------------------
			Creating this array once is very useful and since I do my sanitization
			again here as well as in the Input class, I am sure that the 
			segments are clean and ready to use on a page.
		/* ----------------------------------------*/
		
		$x	= explode('/', $this->base_path);
		
		$this->seg_parts = explode('/', $this->EE->security->xss_clean(strip_tags(stripslashes($this->EE->uri->query_string))));
		
		/* ----------------------------------------
			Fixes a minor bug in ExpressionEngine where the QSTR variable
			has the template name included when there is no third segment
			on a non-index template - Paul
		/* ----------------------------------------*/
		
		if (isset($x['1']))
		{
			if ($this->seg_parts['0'] == $x['1'])
			{
				array_shift($this->seg_parts);
			}
		}
		
		
		/** ----------------------------------------
		/**  Preferences and Language
		/** ----------------------------------------*/
		
		if ($this->EE->TMPL->fetch_param('wiki') !== FALSE)
		{
			$query = $this->EE->db->query("SELECT * FROM exp_wikis WHERE wiki_short_name = '".$this->EE->db->escape_str($this->EE->TMPL->fetch_param('wiki'))."'");
		}
		else
		{
			$query = $this->EE->db->query("SELECT * FROM exp_wikis WHERE wiki_short_name = 'default_wiki'");
		}
		
		if ($query->num_rows() == 0)
		{
			return $this->return_data = 'No Valid Wiki Specified';
		}
		
		foreach($query->row_array() as $field => $value)
		{
			if ($field != 'wiki_id')
			{
				$field = substr($field, 5);
			}
			
			if ($field == 'users' OR $field == 'admins')
			{
				$value = explode('|', $value);
			}
			
			$this->{$field} = $value;
		}
		
		/** ------------------------------------
		/**  Retrieve Our Namespaces
		/** ------------------------------------*/

		$namespace_query = $this->EE->db->query("SELECT * FROM exp_wiki_namespaces WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."' ORDER BY namespace_name"); 
		
		if ($namespace_query->num_rows() > 0)
		{
			foreach($namespace_query->result_array() as $row)
			{
				$this->namespaces[strtolower($row['namespace_name'])] = array($row['namespace_name'], $row['namespace_label']);
				
				if (isset($this->seg_parts['0']) && strcasecmp($this->prep_title(substr($this->seg_parts['0'], 0, strlen($row['namespace_label'].':'))), $row['namespace_label'].':') == 0)
				{
					$this->admins = explode('|', $row['namespace_admins']);
					$this->users  = explode('|', $row['namespace_users']); 
				}
			}
		}
		
		if ($this->EE->TMPL->fetch_param('profile_path') !== FALSE)
		{
			$this->profile_path = $this->EE->functions->remove_double_slashes('/'.$this->EE->TMPL->fetch_param('profile_path').'/'.$this->EE->config->item('profile_trigger').'/');
		}
		else
		{
			$this->profile_path = $this->EE->functions->remove_double_slashes('/'.$this->EE->config->item('profile_trigger').'/');
		}
		
		/** ----------------------------------------
		/**  Namespaces Localization
		/** ----------------------------------------*/
		
		$this->main_ns		= (isset($this->EE->lang->language['main_ns']))		? $this->EE->lang->line('main_ns') 	 : $this->main_ns;
		$this->file_ns		= (isset($this->EE->lang->language['file_ns']))		? $this->EE->lang->line('file_ns') 	 : $this->file_ns;
		$this->image_ns		= (isset($this->EE->lang->language['image_ns']))		? $this->EE->lang->line('image_ns') 	 : $this->image_ns;
		$this->special_ns 	= (isset($this->EE->lang->language['special_ns']))	? $this->EE->lang->line('special_ns')	 : $this->special_ns;
		$this->category_ns	= (isset($this->EE->lang->language['category_ns']))	? $this->EE->lang->line('category_ns') : $this->category_ns;
		
		/* ----------------------------------------
		/*  Category namespace actually has articles so it is put into the 
		/*	namespaces array. However, instead of the localized name we use 
		/*  the relatively simple 'category' in the page_namespace field.
		/* ---------------------------------------*/
		
		$this->namespaces['category'] = array('category', $this->category_ns);
		
		/** ----------------------------------------
		/**  Tag Settings
		/** ----------------------------------------*/
		
		if ( ! in_array('1', $this->admins)) $this->admins[] = "1";
		
		if ( ! in_array('1', $this->users)) $this->users[] = "1";
			
		foreach($this->admins as $key => $value)
		{
			if (in_array($value, array('2', '3', '4')))
			{
				unset($this->admins[$key]);
			}
		}
		
		foreach($this->users as $key => $value)
		{
			if (in_array($value, array('2', '3', '4')))
			{
				unset($this->users[$key]);
			}
		}
		
		/** ----------------------------------------
		/**  Valid Upload Directory?
		/** ----------------------------------------*/
		
		if ( ! empty($this->upload_dir) && is_numeric($this->upload_dir))
		{
			$query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_upload_prefs 
								 WHERE id = '".$this->EE->db->escape_str($this->upload_dir)."'");
								 
			if ($query->row('count')  > 0)
			{
				$this->valid_upload_dir = 'y';
				$this->can_upload = 'y';
				
				if (in_array($this->EE->session->userdata['group_id'], array(2, 3, 4)))
				{
					$this->can_upload = 'n'; 
				}
				elseif ($this->EE->session->userdata['group_id'] != 1)
				{		 	
					$query = $this->EE->db->query("SELECT upload_id FROM exp_upload_no_access WHERE member_group = '".$this->EE->session->userdata['group_id']."'");
				
					if ($query->num_rows() > 0)
					{
						foreach($query->result_array() as $row)
						{
							if ($query->row('upload_id') == $this->upload_dir)
							{
								$this->can_upload = 'n';
								break;
							}
						}
					}
				}
			}
		}
		
		
		/** ----------------------------------------
		/**  Set theme, load file helper
		/** ----------------------------------------*/

		$this->EE->load->helper('file');
		$this->theme_path = PATH_THEMES.'wiki_themes/default/';
		$this->image_url = $this->EE->config->slash_item('theme_folder_url').'wiki_themes/default/images/';
		$this->theme_url = $this->EE->config->slash_item('theme_folder_url').'wiki_themes/default/';
		
		if ($this->EE->TMPL->fetch_param('theme') !== FALSE && $this->EE->TMPL->fetch_param('theme') != '' && $this->EE->TMPL->fetch_param('theme') != 'default')
		{
			$theme = $this->EE->security->sanitize_filename($this->EE->TMPL->fetch_param('theme'));

			if (is_dir(PATH_THEMES.'/wiki_themes/'.$theme))
			{
				$this->theme_path = PATH_THEMES.'wiki_themes/'.$theme.'/';
				$this->image_url = $this->EE->config->slash_item('theme_folder_url').'wiki_themes/'.$theme.'/images/';
				$this->theme_url = $this->EE->config->slash_item('theme_folder_url').'wiki_themes/'.$theme.'/';
			}
			else
			{
				$this->EE->TMPL->log_item('Wiki module: theme not found - "'.htmlentities($theme).'"');
			}
		}
		
		/** ----------------------------------------
		/**  Editing Article
		/** ----------------------------------------*/
		
		if ($this->EE->input->post('editing') == 'y' && $this->EE->input->post('preview') === FALSE)
		{
			return $this->edit_article();
		}
		
		/** ----------------------------------------
		/**  Displaying Page
		/** ----------------------------------------*/
		
		$this->return_data = str_replace(array('{module_version}'), array($this->version), $this->_fetch_template('wiki_page.html'));
		$this->return_data = $this->active_members($this->return_data);

		/* -------------------------------------
		/*  'wiki_start' hook.
		/*  - Allows page template to be modified prior to article processing
		/*  - Added 1.6.0
		*/  
			if ($this->EE->extensions->active_hook('wiki_start') === TRUE)
			{
				$this->return_data = $this->EE->extensions->universal_call('wiki_start', $this);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		/*
		/* -------------------------------------*/
		
		/** ----------------------------------------
		/**  Determine Page to Show
		/** ----------------------------------------*/
		
		// Index Page
		if (count($this->seg_parts) == 0 OR $this->EE->uri->query_string == '' OR $this->EE->uri->query_string == 'index')
		{
			$this->title = 'index';
			$this->article('index');
		}
		
		// File Page
		elseif (substr($this->seg_parts['0'], 0, strlen($this->file_ns.':')) == $this->file_ns.':')
		{
			$this->title = $this->seg_parts['0'];
			$this->current_namespace = $this->file_ns;
			$this->file(substr($this->seg_parts['0'], strlen($this->file_ns.':')));
		}
		
		// Image
		elseif (substr($this->seg_parts['0'], 0, strlen($this->image_ns.':')) == $this->image_ns.':')
		{
			$this->title = $this->seg_parts['0'];
			$this->current_namespace = $this->image_ns;
			$this->image(substr($this->seg_parts['0'], strlen($this->image_ns.':')));
		}
		
		// Special Page
		elseif (substr($this->seg_parts['0'], 0, strlen($this->special_ns.':')) == $this->special_ns.':')
		{
			$this->title = $this->seg_parts['0'];
			$this->current_namespace = $this->special_ns;
			$this->special(substr($this->seg_parts['0'], strlen($this->special_ns.':')));
		}
		
		// Download!
		
		elseif (isset($this->seg_parts['0']) && strlen($this->seg_parts['0']) == 32 && preg_match("/^[a-z0-9]+$/i", $this->seg_parts['0']))
		{
			$this->display_attachment();
			exit;
		}
		
		// Typical Boring Article.  Yawn!
		else
		{
			if (in_array($this->seg_parts['0'], array('edit', 'history', 'revision', 'noredirect')))
			{
				$this->title = 'index';
				
				if ($this->seg_parts['0'] == 'noredirect')
				{
					$this->article('index');
				}
				else
				{
					$this->{$this->seg_parts['0']}('index');
				}
			}
			else
			{
				$this->title = $this->seg_parts['0'];
				
				if ($this->valid_title($this->title) != $this->title)
				{
					$this->redirect('', $this->title);
				}
			
				if (isset($this->seg_parts['1']) && $this->seg_parts['1'] == 'edit')
				{
					$this->edit($this->title);
				}
				elseif (isset($this->seg_parts['1']) && $this->seg_parts['1'] == 'history')
				{
					$this->history($this->title);
				}
				elseif (isset($this->seg_parts['1']) && $this->seg_parts['1'] == 'revision')
				{
					$this->revision($this->title);
				}
				else
				{
					$this->article($this->title);
				}
			}
		}
	
		if ($this->can_upload == 'y')
		{
			$this->return_data = $this->_allow_if('uploads', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_deny_if('uploads', $this->return_data);
		}

		/** ----------------------------------------
		/**  Global Tags
		/** ----------------------------------------*/
		
		if (preg_match_all("/\{wiki:custom_namespaces_list(.*?)\}(.*?)\{\/wiki:custom_namespaces_list\}/s", $this->return_data, $matches))
		{
			for($i = 0, $s = count($matches[0]); $i < $s; ++$i)
			{
				$output = '';
				
				if (count($this->namespaces) > 0)
				{	
					foreach($this->namespaces as $name => $label)
					{
						$selected = (isset($this->seg_parts['1']) && strtolower($this->seg_parts['1']) == $name) ? 'selected="selected"' : '';
						$output .= str_replace(array('{namespace_short_name}', '{namespace_label}', '{namespace_selected}'), array($label['0'], $label['1'], $selected), $matches['2'][$i]);
					}
				}
				
				$this->return_data = str_replace($matches['0'][$i], $output, $this->return_data);
			}
		}
		
		if (preg_match("/\{wiki:categories_list(.*?)\}(.*?)\{\/wiki:categories_list\}/s", $this->return_data))
		{
			$this->categories_list();
		}
		
		/** ----------------------------------------
		/**  Global Conditionals
		/** ----------------------------------------*/
		
		if ($this->EE->session->userdata('member_id') == 0)
		{
			$this->return_data = $this->_deny_if('logged_in', $this->return_data);
			$this->return_data = $this->_allow_if('logged_out', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_allow_if('logged_in', $this->return_data);
			$this->return_data = $this->_deny_if('logged_out', $this->return_data);
		}
		
		if (in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_deny_if('cannot_admin', $this->return_data);
			$this->return_data = $this->_allow_if('can_admin', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_allow_if('cannot_admin', $this->return_data);
			$this->return_data = $this->_deny_if('can_admin', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Global Variables
		/** ----------------------------------------*/
		
		
		$link = $this->create_url($this->current_namespace, $this->topic);

		// Load the XML Helper
		$this->EE->load->helper('xml');
	
		$data = array(	'{charset}' 				=> $this->EE->config->item('output_charset'),
						'{wiki_name}'				=> $this->label_name,
						'{title}'					=> xml_convert($this->prep_title($this->title)),
						'{url_title}'				=> xml_convert($this->valid_title($this->title)),
						'{topic}'					=> xml_convert($this->prep_title($this->topic)),
						'{namespace}'				=> xml_convert($this->current_namespace),
						'{special_namespace}'		=> xml_convert($this->special_ns),
						'{main_namespace}'			=> xml_convert($this->main_ns),
						'{file_namespace}'			=> xml_convert($this->file_ns),
						'{category_namespace}'		=> xml_convert($this->category_ns),
						'{image_namespace}'			=> xml_convert($this->image_ns),
						
						'{revision_id}'				=> $this->revision_id,
						'{screen_name}'				=> $this->prep_screen_name($this->EE->session->userdata('screen_name')),
						
						'{path:wiki_home}'			=> $this->EE->functions->create_url($this->base_path),
						'{path:wiki_base_url}'		=> $this->base_url,
						'{path:article_history}'	=> $link.'/history',
						'{path:view_article}'		=> $link,
						'{path:edit_article}'		=> $link.'/edit',
						
						'{path:logout}'				=> $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$this->EE->functions->fetch_action_id('Member', 'member_logout'),
						'{path:your_control_panel}'	=> $this->EE->functions->create_url($this->profile_path.'profile'),
						'{path:your_profile}'		=> $this->EE->functions->create_url($this->profile_path.$this->EE->session->userdata('member_id')),
						'{path:login}'				=> $this->EE->functions->create_url($this->profile_path.'login'),
						'{path:register}'			=> $this->EE->functions->create_url($this->profile_path.'register'),
						'{path:memberlist}'			=> $this->EE->functions->create_url($this->profile_path.'memberlist'),
						'{path:forgot}'				=> $this->EE->functions->create_url($this->profile_path.'forgot_password'),
						'{path:private_messages}'	=> $this->EE->functions->create_url($this->profile_path.'messages/view_folder/1'),
						
						'{path:image_url}'			=> $this->image_url,
						'{path:theme_url}'			=> $this->theme_url,
						'{text_format}'				=> ucwords(str_replace('_', ' ', $this->text_format))
					);
					
		/** -------------------------------------
		/**  Parse URI segments
		/** -------------------------------------*/
		
		// This code lets admins fetch URI segments which become
		// available as:  {segment_1} {segment_2}		
				
		for ($i = 1; $i < 9; $i++)
		{
			$data[LD.'segment_'.$i.RD] = $this->EE->uri->segment($i);
		}

		/** -------------------------------------
		/**  Parse Snippets
		/** -------------------------------------*/
		
		foreach ($this->EE->config->_global_vars as $key => $val)
		{
			$data[LD.$key.RD] = $val; 
		}
		
		/** -------------------------------------
		/**  Parse manual variables
		/** -------------------------------------*/
					
		if (count($this->EE->TMPL->global_vars) > 0)
		{
			foreach ($this->EE->TMPL->global_vars as $key => $val)
			{
				$data[LD.$key.RD] = $val; 
			}
		}
				
		/* -------------------------------------
		/*  We reset some of these because in $data we are converting them
		/*  for display purposes and we would rather have the conditionals
		/*  use the unmodified versions
		/* -------------------------------------*/
		
		$this->conditionals['title']		= $this->title;
		$this->conditionals['topic']		= $this->topic;
		$this->conditionals['namespace']	= $this->current_namespace;
		
		$this->return_data = $this->prep_conditionals($this->return_data, array_merge($data, $this->conditionals));
		$this->return_data = str_replace(array_keys($data), array_values($data), $this->return_data);
			
		/** ----------------------------------------
		/**  Cleanup
		/** ----------------------------------------*/
		
		$this->return_data = $this->_deny_if('redirected', $this->return_data);
		$this->return_data = $this->_deny_if('redirect_page', $this->return_data);
			
	}

	// --------------------------------------------------------------------
	
	/**
	 * Fetch Template
	 *
	 * Fetches a template fragment
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _fetch_template($which)
	{
		return read_file($this->theme_path.$which);
	}

	// --------------------------------------------------------------------
	

	/** ----------------------------------------
	/**  Redirect for the Wiki
	/** ----------------------------------------*/
	
	function redirect($namespace='', $title)
	{
		$this->EE->functions->redirect($this->create_url($namespace, $title));
		exit;
	}

	
	
	/** ----------------------------------------
	/**  Creat URL for the Wiki
	/** ----------------------------------------*/
	
	function create_url($namespace='', $title)
	{
		if ($namespace == '' && stristr($title, ':') && count($this->namespaces) > 0)
		{
			foreach($this->namespaces as $possible)
			{
				if (substr($title, 0, strlen($possible['1'].':')) == $possible['1'].':')
				{
					$namespace = $possible['1'];
					$title = substr($title, strlen($possible['1'].':'));
					break;
				}
			}
		}
		
		if ($namespace != '')
		{
			/* 
				Convert any colons back because of Category articles
			*/
			
			$link = $this->EE->functions->create_url($this->base_path.
					urlencode($this->valid_title($namespace)).
					':'.
					str_replace('%3A', ':', urlencode($this->valid_title($title))));
		}
		else
		{
			$link = $this->EE->functions->create_url($this->base_path.
					urlencode($this->valid_title($title)));
		}
	
		return $link;
	}

	
	
	/** ----------------------------------------
	/**  Prep Screen Name for Display
	/** ----------------------------------------*/
	
	function prep_screen_name($str)
	{
		return str_replace(array('<', '>', '{', '}', '\'', '"', '?'), array('&lt;', '&gt;', '&#123;', '&#125;', '&#146;', '&quot;', '&#63;'), $str);
	}

	
	/** ----------------------------------------
	/**  Prep Title Display
	/** ----------------------------------------*/
	
	function prep_title($str)
	{
		if ($this->EE->config->item('word_separator') == 'dash')
		{
			return str_replace(array('-', $this->cats_separator), array(' ', $this->cats_display_separator), $str);
		}
		else
		{
			return str_replace(array('_', $this->cats_separator), array(' ', $this->cats_display_separator), $str);
		}
	}

	
	
	/** ----------------------------------------
	/**  Create Valid Topic Name
	/** ----------------------------------------*/
	
	function valid_title($str)
	{
		// Remove all numeric entities
		$str = preg_replace('/&#x([0-9a-f]{2,5});{0,1}|&#([0-9]{2,4});{0,1}/', '', $str);
			
		$trans = array();

		$trans["#[^a-z0-9\-\_@&\'\"!\.:\+\x{A1}-\x{44F}\s]#iu"] = '';
		
		// Use dash or underscore as separator		
		$replace = ($this->EE->config->item('word_separator') == 'dash') ? '-' : '_';
		
		$trans = array_merge($trans, array(
											'/\s+/'					=> $replace,
											"/{$replace}+/"			=> $replace,
											"/{$replace}$/"			=> $replace,
											"/^{$replace}/"			=> $replace
										   ));
										
		
		return preg_replace(array_keys($trans), array_values($trans), urldecode($str));
	}

	
	/** ----------------------------------------
	/**  Take Namespace's Short Name and Convert to Label
	/** ----------------------------------------*/
	
	function namespace_label($short_name)
	{
		if ($short_name != '')
		{
			$short_name = strtolower($short_name);
			$short_name = (isset($this->namespaces[$short_name])) ? $this->namespaces[$short_name]['1'] : '';
		}
		
		return $short_name;
	}

	
	
	/** ----------------------------------------
	/**  Encode EE Tags
	/** ----------------------------------------*/
	
	function encode_ee_tags($str)
	{
		return str_replace(array('{','}'), array('&#123;','&#125;'), $str);
	}

	
	
	/* ----------------------------------------
	/*  Topic Request!
	/*
	/*  - Title = Namespace:Topic
	/*  - If no namespace, then Title and Topic are
	/*  the same thing
	/* ----------------------------------------*/
	
	function topic_request($title)
	{
		$title = $this->valid_title($title);
		
		$xsql = " AND page_namespace = '' ";
		
		// In the beginning, these are the same thing
		$this->topic = $title;
		$this->title = $title;
		
		if (stristr($title, ':') && count($this->namespaces) > 0)
		{
			$parts = explode(':', $title, 2);
			
			/* In PHP 5.1 this loop was consistently faster than array_search() */
			
			foreach($this->namespaces as $name => $label)
			{
				if (strcasecmp($label['1'], $this->prep_title($parts['0'])) == 0)
				{
					$xsql		 = " AND LOWER(page_namespace) = '".$this->EE->db->escape_str($name)."' ";
					$this->topic = substr($this->topic, strlen($label['1'].':'));
					$this->current_namespace = $label['1'];
					
					$this->title = $this->current_namespace.':'.$this->topic;
					
					break;	
				}
			}
		}
		
		return $this->EE->db->query("SELECT * FROM exp_wiki_page 
							WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."' 
							AND LOWER(page_name) = '".strtolower($this->EE->db->escape_str($this->topic))."' 
							{$xsql}");
	}

	

	/** ----------------------------------------
	/**  Load Image
	/** ----------------------------------------*/
	
	function image($topic, $return=FALSE)
	{
		if ($return === FALSE)
		{
			$this->title = $this->image_ns.':'.$topic;
		}
		
		/*
		No way to show the image if we do not have a valid upload directory
		because we need the URL for that directory.
		*/
		
		if ($this->valid_upload_dir != 'y') 
		{
			if ($return === TRUE) return FALSE;
			
			$this->redirect($this->file_ns, $topic);
		}
		
		/** ----------------------------------------
		/**  Does File Exist? Is It An Image?
		/** ----------------------------------------*/
		
		$query = $this->EE->db->query("SELECT * FROM exp_wiki_uploads
							 WHERE file_name = '".$this->EE->db->escape_str($topic)."'");
		
		if ($query->num_rows() == 0)
		{
			if ($return === TRUE) return FALSE;
			
			$this->redirect($this->file_ns, $topic);
		}
		
		$x = explode('/',$query->row('file_type') );
		
		if ($x['0'] != 'image')
		{
			if ($return === TRUE) return FALSE;
			
			$this->redirect($this->file_ns, $topic);
		}
		
		/** ----------------------------------------
		/**  Create Our URL
		/** ----------------------------------------*/
		
		if ($return === TRUE)
		{
			$file_url = $this->base_url.$query->row('file_hash');
		}
		else
		{
			$results = $this->EE->db->query("SELECT url FROM exp_upload_prefs 
									WHERE id = '".$this->EE->db->escape_str($this->upload_dir)."'");
							 
			$file_url  = (substr($results->row('url') , -1) == '/') ? $results->row('url')  : $results->row('url') .'/';
			$file_url .= $query->row('file_name') ;
		}
		
		/* ----------------------------------------
		/*  Display Our Image
		/*  - Now in the future we might be clever and obfuscate the location
		/*  - of images, if it is requested, by using fopen to get the image
		/*  - data and displaying it instead of doing a redirect to the URL
		/* ----------------------------------------*/
		
		if ($return === TRUE)
		{
			return array('url'	=> $file_url, 
						'width'  => $query->row('image_width') , 
						'height' => $query->row('image_height') ,
						'name'	 => $query->row('file_name') );
		}
		
		$this->EE->functions->redirect($file_url);
		exit;
	}



	/** ----------------------------------------
	/**  File
	/** ----------------------------------------*/
	
	function file($topic)
	{
		$this->title = $this->file_ns.':'.$topic;
		$this->topic = $topic;
		
		/** ----------------------------------------
		/**  Delete File?  Admins Only!
		/** ----------------------------------------*/
		
		if (isset($this->seg_parts['1']) && strtolower($this->seg_parts['1']) == 'delete')
		{
			if ($this->can_upload == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				$query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_wiki_uploads
							 		 WHERE file_name = '".$this->EE->db->escape_str($topic)."'");
							 		 
				if ($query->row('count')  > 0)
				{
					// Delete from file system??  Pretty much have to- nuked it
					$this->EE->load->model('file_model');
					$this->EE->file_model->delete_files_by_name($this->upload_dir, $topic);
					
					// The hook clears out wiki_uploads and the db cache

					$this->redirect($this->special_ns, 'Files');
				}
			}
		}
		
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_allow_if('file_page', $this->return_data);
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_file.html'), $this->return_data);
		
		$query = $this->EE->db->query("SELECT u.*, m.member_id, m.screen_name, m.email, m.url
							 FROM exp_wiki_uploads u, exp_members m
							 WHERE u.file_name = '".$this->EE->db->escape_str($topic)."'
							 AND u.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
							 AND u.upload_author = m.member_id");
		
		/** ----------------------------------------
		/**  Does File Exist?  What Kind?
		/** ----------------------------------------*/
		
		if ($query->num_rows() == 0)
		{
			$this->return_data = $this->_deny_if('file_exists', $this->return_data);
			return;
		}
		else
		{
			$this->return_data = $this->_allow_if('file_exists', $this->return_data);
		}
		
		$x = explode('/',$query->row('file_type') );
		
		if ($x['0'] == 'image')
		{
			$this->return_data = $this->_allow_if('is_image', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_deny_if('is_image', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Date Formats
		/** ----------------------------------------*/
		
		if (preg_match_all("/".LD."(upload_date)\s+format=[\"'](.*?)[\"']".RD."/s", $this->return_data, $matches))
		{
			for ($j = 0; $j < count($matches['0']); $j++)
			{	
				switch ($matches['1'][$j])
				{
					case 'upload_date' 		: $upload_date[$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
				}
			}
			
			foreach($upload_date as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['upload_date'], FALSE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
		
		/** ----------------------------------------
		/**  Parse Variables
		/** ----------------------------------------*/
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$summary = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($query->row('upload_summary') ), 
												  array(
														'text_format'	=> $this->text_format,
														'html_format'	=> $this->html_format,
														'auto_links'	=> $this->auto_links,
														'allow_img_url' => 'y'
													  )
												));
		
		$delete_url = '';
		
		if ($this->valid_upload_dir != 'y') 
		{
			$file_url = $query->row('file_name') ;
		}
		else
		{
			$file_url = $this->base_url.$query->row('file_hash');
			
			if (in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				$delete_url = $this->base_url.$this->file_ns.':'.$query->row('file_name').'/delete';
			}
		}
	
		$this->conditionals['summary']		= $summary;
		$this->conditionals['delete_url']	= $delete_url;
		$this->conditionals = array_merge($this->conditionals, $query->row_array());
		
									
		/** ----------------------------------------
		/**  Can User Edit File?
		/** ----------------------------------------*/
		
		if(in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		$this->return_data = str_replace(array('{file_url}','{delete_url}','{file_name}','{summary}', '{image_width}', '{image_height}', '{file_type}'), 
										 array($file_url, $delete_url, $query->row('file_name') , $summary, $query->row('image_width') , $query->row('image_height') , $query->row('file_type') ), 
										 $this->return_data);
	
	}

	
	/** ----------------------------------------
	/**  Special
	/** ----------------------------------------*/
	
	function special($topic)
	{
		$this->topic = $topic;
		$this->title = $this->special_ns.':'.$topic;
		
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_allow_if('special_page', $this->return_data);
		
		/* -------------------------------------
		/*  'wiki_special_page' hook.
		/*  - Allows complete takeover of special pages
		/*  - Added 1.6.0
		*/  
			$edata = $this->EE->extensions->universal_call('wiki_special_page', $this, $topic);
			if ($this->EE->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------*/
		
		switch(strtolower($topic))
		{
			case 'recentchanges' :
				$this->recent_changes();
			break;
			
			case 'search_results' :
				$this->search_results();
			break;
			
			case 'random_page' :
				$this->random_page();
			break;
			
			case 'categories' :
				$this->categories();
			break;
			
			case 'files' :
				$this->files();
			break;
			
			case 'find_page' :
				$this->find_page();
			break;
			
			case 'uploads' :
				$this->upload_form();
			break;
			
			case 'recentchanges_rss' :
				$this->EE->output->out_type = 'feed';
				$this->EE->TMPL->template_type = 'feed';
				$this->return_data = $this->_fetch_template('wiki_special_rss.html');
				$this->recent_changes('rss');
			break;
			
			case 'recentchanges_atom' :
				$this->EE->output->out_type = 'feed';
				$this->EE->TMPL->template_type = 'feed';
				$this->return_data = $this->_fetch_template('wiki_special_atom.html');
				$this->recent_changes('atom');
			break;
			
			case 'titles' :
				$this->title_list();
			break;
			
			case 'associated_pages' :
				$this->associated_pages();
			break;
			
			case 'uncategorized' :
				$this->uncategorized_pages();
			break;
			
			case 'css' :
				$this->wiki_css();
			break;
			
			default:
				$this->return_data = str_replace('{wiki:page}', '', $this->return_data);
			break;
		}
		
		$this->return_data = $this->_deny_if('can_edit', $this->return_data);
		$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Wiki CSS
	 *
	 * Outputs the default Wiki CSS theme file with proper headers
	 *
	 * @access	public
	 * @return	void
	 */
	function wiki_css()
	{
		// reset!  We just want the CSS
		$this->return_data = $this->_fetch_template('wiki_style.css');

		if ($this->return_data === FALSE)
		{
			// no point
			exit;
		}

		// replace image paths
		$this->return_data = str_replace('{path:image_url}', $this->image_url, $this->return_data);

		$last_modified = filemtime($this->theme_path.'wiki_style.css');
		$this->EE->load->library('stylesheet');
		$this->EE->stylesheet->_send_css($this->return_data, $last_modified);
	}

	// --------------------------------------------------------------------
	
	
	
	/** ---------------------------------------
	/**  Uncategorized Pages
	/** ---------------------------------------*/
	
	function uncategorized_pages()
	{	
		return $this->title_list('uncategorized_pages');
	}
	/* END */
	
	
	/** ----------------------------------------
	/**  Recent Changes Processing
	/** ----------------------------------------*/
	
	function title_list($type = '')
	{
		/** ---------------------------------------
		/**  Initialize the correct template and do any
		/**  prep work needed prior to our title query
		/** ---------------------------------------*/
		
		switch ($type)
		{			
			case 'uncategorized_pages' :
				$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_uncategorized_pages.html'), $this->return_data);
				
				/** ---------------------------------------
				/**  Get categorized page ids
				/** ---------------------------------------*/

				$query = $this->EE->db->query("SELECT DISTINCT(exp_wiki_category_articles.page_id)
									FROM exp_wiki_category_articles
									LEFT JOIN exp_wiki_page ON exp_wiki_page.page_id = exp_wiki_category_articles.page_id
									WHERE exp_wiki_page.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");

				if ($query->num_rows() > 0)
				{
					$page_ids = array();

					foreach ($query->result() as $row)
					{
						$page_ids[] = $row->page_id;
					}
				
					$xsql = " AND p.page_id NOT IN (".implode(',', $page_ids).")
						  	AND p.page_redirect = '' ";
				}
			break;
			
			default :
				$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_titles.html'), $this->return_data);	
			break;
			
		}
		
		if ( ! preg_match("/\{wiki:title_list(.*?)\}(.*?)\{\/wiki:title_list\}/s", $this->return_data, $match))
		{
			return $this->return_data = '';
		}
		
		if ( ! preg_match("/\{articles\}(.*?)\{\/articles\}/s", $match['2'], $topics))
		{
			return $this->return_data = '';
		}
		
		/** ----------------------------------------
		/**  Parameters
		/** ----------------------------------------*/
		
		$columns = 3;
		
		if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
		{
			$columns = (isset($params['columns']) && is_numeric($params['columns'])) ? $params['columns'] : $limit;
		}
		
		/** ----------------------------------------
		/**  Date Formats
		/** ----------------------------------------*/
		
		$dates = $this->parse_dates($this->return_data);
		
		/** ----------------------------------------
		/**  Our Query
		/** ----------------------------------------*/

		if ( ! isset($xsql))
		{
			$xsql = '';
		}
			
		if (isset($this->seg_parts['1']) && isset($this->namespaces[strtolower($this->seg_parts['1'])]))
		{
			$xsql = "AND LOWER(p.page_namespace) = '".$this->EE->db->escape_str(strtolower($this->seg_parts['1']))."'";
		}
		else
		{
			$xsql = "AND p.page_namespace = ''";
		}
		
		$results = $this->EE->db->query("SELECT r.*, 
								m.member_id, m.screen_name, m.email, m.url, 
								p.page_namespace, p.page_name AS topic
								FROM exp_wiki_revisions r, exp_members m, exp_wiki_page p
								WHERE p.last_updated = r.revision_date
								{$xsql}
								AND m.member_id = r.revision_author
								AND r.page_id = p.page_id
								AND r.revision_status = 'open'
								AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								ORDER BY p.page_name");
								
		if ($results->num_rows() == 0)
		{
			if (preg_match("|".LD."if\s+no_results".RD."(.*?)".LD."\/if".RD."|s",$match['2'], $block))
			{
				$this->return_data = str_replace($match['0'], $block['1'], $this->return_data);
			}
			else
			{
				$this->return_data = str_replace($match['0'], str_replace($topics['0'], '', $match['2']), $this->return_data);
			}
			
			return;
		}
		
		/** ----------------------------------------
		/**  Template Parts
		/** ----------------------------------------*/
		
		$our_template	= $topics['1'];
		$row_start		= '';
		$row_end		= '';
		$row_blank		= '';
		$row_column 	= '';
		
		foreach(array('row_start', 'row_end', 'row_blank', 'row_column') as $val)
		{
			if (preg_match("/\{".preg_quote($val)."\}(.*?)\{\/".preg_quote($val)."\}/s", $our_template, $matching))
			{
				$$val = $matching['1'];
			}
		}
		
		$template = $row_column;
		
		/** ----------------------------------------
		/**  Parsing of the Recent Changes Tag Pair
		/** ----------------------------------------*/
		
		$parse_article = stristr($template, '{article}');
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$titles = $row_start;
		$i = 0;
		$count = 0;
		
		// added in 1.6 for {switch} variable and for future use
		$vars = $this->EE->functions->assign_variables($template);
		$this->EE->load->helper('url');
		
		foreach($results->result_array() as $row)
		{
			$count++;
			
			$titles .= ($i % $columns != 0) ? '' : $row_end.$row_start; ++$i;
			
			$temp = $template;
			
			if (isset($this->seg_parts['1']) && isset($this->namespaces[strtolower($this->seg_parts['1'])]))
			{
				$title = $row['topic'];
			}
			else
			{
				$title	= ($row['page_namespace'] != '') ? $this->namespace_label($row['page_namespace']).':'.$row['topic'] : $row['topic'];
			}
			
			$link	= $this->create_url($this->namespace_label($row['page_namespace']), $row['topic']);

			$data = array(	'{title}'				=> $this->prep_title($title),
							'{revision_id}'			=> $row['revision_id'],
							'{page_id}'				=> $row['page_id'],
							'{author}'				=> $row['screen_name'],
							'{path:author_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{path:member_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{email}'				=> $this->EE->typography->encode_email($row['email']),
							'{url}'					=> prep_url($row['url']),
							'{revision_notes}'		=> $row['revision_notes'],
							'{path:view_article}'	=> $link,
							'{content}'				=> $row['page_content'],
							'{count}'				=> $count);
							
			if ($parse_article !== FALSE)
			{					
				$data['{article}'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($row['page_content'], FALSE), 
																  array(
																		'text_format'	=> $this->text_format,
																		'html_format'	=> $this->html_format,
																		'auto_links'	=> $this->auto_links,
																		'allow_img_url' => 'y'
																	  )
																));
			}
			
			$temp = $this->prep_conditionals($temp, array_merge($data, $this->conditionals));

			if (isset($dates['last_updated']))
			{
				foreach($dates['last_updated'] as $key => $value)
				{
					$temp_date = $value['0'];
					
					// Do this once here, to save energy
					$row['revision_date'] = $this->EE->localize->set_localized_time($row['revision_date']);
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], FALSE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}

			foreach ($vars['var_single'] as $key => $val)
			{
				/** ----------------------------------------
				/**  parse {switch} variable
				/** ----------------------------------------*/
				if (preg_match("/^switch\s*=.+/i", $key))
				{
					$sparam = $this->EE->functions->assign_parameters($key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
				}
			}
								
			$titles .= str_replace(array_keys($data), array_values($data), $temp);
		}
		
		while($i % $columns != 0)
		{
			$titles .= $row_blank; ++$i;
		}
		
		$titles .= $row_end;
		
		$this->return_data = str_replace($match['0'], str_replace($topics['0'], $titles, $match['2']), $this->return_data);
		
	}

	
	
	/** ----------------------------------------
	/**  Recent Changes Processing
	/** ----------------------------------------*/
	
	function recent_changes($type='')
	{
		/** ----------------------------------------
		/**  Load Template, Check for Valid Tag
		/** ----------------------------------------*/
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_recent_changes.html'), $this->return_data);
		
		if ( ! preg_match("/\{wiki:recent_changes(.*?)\}(.*?)\{\/wiki:recent_changes\}/s", $this->return_data, $match))
		{
			return $this->return_data = '';
		}
		
		/** ----------------------------------------
		/**  Parameters
		/** ----------------------------------------*/
		
		$parameters['limit']	= 10;
		$parameters['paginate']	= 'bottom';
		
		if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
		{
			$parameters['limit']	= (isset($params['limit']) && is_numeric($params['limit'])) ? $params['limit'] : $parameters['limit'];
			$parameters['paginate']	= (isset($params['paginate'])) ? $params['paginate'] : $parameters['paginate'];
		}
		
		/** ----------------------------------------
		/**  Date Formats
		/** ----------------------------------------*/
		
		$dates = $this->parse_dates($this->return_data);
		
		/** ----------------------------------------
		/**  Our Query
		/** ----------------------------------------*/
		
		if (isset($this->seg_parts['1']) && $this->seg_parts['1'] != '' && ! preg_match("/^P[0-9]+$/", $this->seg_parts['1']))
		{
			$query = $this->topic_request($this->seg_parts['1']);
			
			$sql = "FROM exp_wiki_revisions r, exp_members m, exp_wiki_page p
					WHERE p.page_id = '".(($query->num_rows() > 0) ? $query->row('page_id')  : '0')."'
					AND r.page_id = p.page_id
					AND r.revision_status = 'open'
					AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
					AND m.member_id = r.revision_author
					ORDER BY r.revision_date DESC";
		}
		else
		{	
			$sql = "FROM exp_wiki_revisions r, exp_members m, exp_wiki_page p
					WHERE p.last_updated = r.revision_date
					AND m.member_id = r.revision_author
					AND r.page_id = p.page_id
					AND r.revision_status = 'open'
					AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
					ORDER BY p.last_updated DESC";
		}
				
		$results = $this->EE->db->query("SELECT COUNT(*) AS count ".$sql);
								
		if ($results->row('count')  == 0)
		{
			return $this->return_data = '';
		}
				
		$this->pagination($results->row('count') , $parameters['limit'], $this->base_url.$this->special_ns.':Recentchanges/');
						
		// Pagination code removed, rerun template preg_match()
		if ($this->paginate === TRUE)
		{
			preg_match("/\{wiki:recent_changes(.*?)\}(.*?)\{\/wiki:recent_changes\}/s", $this->return_data, $match);
		}
		else
		{
			$this->pagination_sql .= " LIMIT ".$parameters['limit'];
		}
		
		$results = $this->EE->db->query("SELECT r.*, 
								m.member_id, m.screen_name, m.email, m.url, 
								p.page_namespace, p.page_name AS topic ".
								$sql.
								$this->pagination_sql);
		
		/** ----------------------------------------
		/**  Global Last Updated
		/** ----------------------------------------*/
		
		if (isset($dates['last_updated']))
		{
			foreach($dates['last_updated'] as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $results->row('revision_date') , TRUE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
		
		if (isset($dates['gmt_last_updated']))
		{
			foreach($dates['gmt_last_updated'] as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $results->row('revision_date') , FALSE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
		
		/** ----------------------------------------
		/**  Parsing of the Recent Changes Tag Pair
		/** ----------------------------------------*/
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE,
				'encode_email'	=> ($type == 'rss' OR $type == 'atom') ? FALSE : TRUE)
				);
		
		$changes = '';
		$count = 0;
		
		// added in 1.6 for {switch} variable and for future use
		$vars = $this->EE->functions->assign_variables($match['2']);
		$this->EE->load->helper('url');
		
		foreach($results->result_array() as $row)
		{
			$count++;
			$temp = $match['2'];
			
			$title	= ($row['page_namespace'] != '') ? $this->namespace_label($row['page_namespace']).':'.$row['topic'] : $row['topic'];
			$link	= $this->create_url($this->namespace_label($row['page_namespace']), $row['topic']);

			$data = array(	'{title}'				=> $this->prep_title($title),
							'{revision_id}'			=> $row['revision_id'],
							'{page_id}'				=> $row['page_id'],
							'{author}'				=> $row['screen_name'],
							'{path:author_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{path:member_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{email}'				=> ($type == 'rss' OR $type == 'atom') ? $row['email'] : $this->EE->typography->encode_email($row['email']), // No encoding for RSS/Atom
							'{url}'					=> prep_url($row['url']),
							'{revision_notes}'		=> $row['revision_notes'],
							'{path:view_article}'	=> $link,
							'{content}'				=> $row['page_content'],
							'{count}'				=> $count);
							
			$data['{article}'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($row['page_content']), 
															  array(
																	'text_format'	=> $this->text_format,
																	'html_format'	=> $this->html_format,
																	'auto_links'	=> $this->auto_links,
																	'allow_img_url' => 'y'
																  )
															));
							
			$temp = $this->prep_conditionals($temp, array_merge($data, $this->conditionals));
			
			if (isset($dates['revision_date']))
			{
				foreach($dates['revision_date'] as $key => $value)
				{
					$temp_date = $value['0'];
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], TRUE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}
			
			if (isset($dates['gmt_revision_date']))
			{
				foreach($dates['gmt_revision_date'] as $key => $value)
				{
					$temp_date = $value['0'];
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], FALSE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}
			
			foreach ($vars['var_single'] as $key => $val)
			{
				/** ----------------------------------------
				/**  parse {switch} variable
				/** ----------------------------------------*/
				if (preg_match("/^switch\s*=.+/i", $key))
				{
					$sparam = $this->EE->functions->assign_parameters($key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
				}
				
				if ($key == 'absolute_count')
				{
					$temp = $this->EE->TMPL->swap_var_single($key, $count + ($this->current_page * $parameters['limit']) - $parameters['limit'], $temp);
				}
			}
			
			$changes .= str_replace(array_keys($data), array_values($data), $temp);
		}
		
		/** ----------------------------------------
		/**  Pagination
		/** ----------------------------------------*/
		
		if ($this->paginate === TRUE)
		{
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);
			
			if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_previous == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			 	}
			
			
			if (preg_match("/".LD."if next_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_next == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			}
				
			switch ($parameters['paginate'])
			{
				case "top"	: $changes  = $this->paginate_data.$changes;
					break;
				case "both"	: $changes  = $this->paginate_data.$changes.$this->paginate_data;
					break;
				default		: $changes .= $this->paginate_data;
					break;
			}
		}
		
		$this->return_data = str_replace($match['0'], $changes, $this->return_data);
		
		$ex = explode("/", str_replace(array('http://', 'www.'), '', $this->EE->functions->create_url($this->base_path)));
		
		$this->return_data = str_replace(array('{trimmed_url}', '{language}'), array(current($ex), $this->EE->config->item('xml_lang')), $this->return_data);
	}

	


	/** ----------------------------------------
	/**  Our List of Categories
	/** ----------------------------------------*/
	
	function categories_list()
	{
		$this->show_these = FALSE;
		$this->categories('', TRUE);
	}
	
	function categories($page_id='', $list=FALSE)
	{
		/** ----------------------------------------
		/**  Load Template, Check for Valid Tag
		/** ----------------------------------------*/
		
		if ($page_id == '' && $list == FALSE)
		{
			$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_categories.html'), $this->return_data);
		}
		
		if ($list === TRUE)
		{
			if ( ! preg_match_all("/\{wiki:categories_list\s(.*?)\}(.*?)\{\/wiki:categories_list\}/s", $this->return_data, $matches))
			{
				return;
			}
		}
		else
		{
			if ( ! preg_match_all("/\{wiki:categories\s(.*?)\}(.*?)\{\/wiki:categories\}/s", $this->return_data, $matches))
			{
				return $this->return_data = '';
			}
		}
		
		for($i=0, $s = count($matches[0]); $i < $s; ++$i)
		{
			$match = array($matches[0][$i], $matches[1][$i], $matches[2][$i]);
			
			/** ----------------------------------------
			/**  Parameters
			/** ----------------------------------------*/
			
			$limit		= 10;
			$backspace	= '';
			$show_empty	= 'y';
			$style		= '';
			
			if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
			{
				$limit		= (isset($params['limit']) && is_numeric($params['limit'])) ? $params['limit'] : $limit;
				$backspace	= (isset($params['backspace']) && is_numeric($params['backspace'])) ? $params['backspace'] : $backspace;
				$show_empty	= (isset($params['show_empty'])) ? $params['show_empty'] : $show_empty;
				$style		= (isset($params['style'])) ? $params['style'] : $style;
			}
			
			/** ----------------------------------------
			/**  Our Query
			/** ----------------------------------------*/
			
			$namespace = '';
			
			if ($page_id == '' && isset($this->seg_parts['1']) && count($this->namespaces) > 0)
			{	
				if (isset($this->namespaces[strtolower($this->seg_parts['1'])]))
				{
					$namespace = $this->namespaces[strtolower($this->seg_parts['1'])]['0'];
				}
			}
			
			$categories = $this->retrieve_categories($namespace, $page_id, $show_empty);
									
			if ($categories === FALSE OR count($categories) == 0)
			{
				$output = '';
			}
			else
			{
				$output = $this->parse_categories($categories, $match['2'], $style, $backspace);
			}
			
			$this->return_data = str_replace($match['0'], $output, $this->return_data);
		}
	}

		
	/** ----------------------------------------
	/**  Parsing of the Categories
	/** ----------------------------------------*/
	
	function parse_categories($categories, $template, $style, $backspace, $ancestry=array())
	{
		$output = ($style == 'nested') ? "<ul id='nav_categories'>\n" : '';

		// added in 1.6 for {switch} and {count} variables and for future use
		$vars = $this->EE->functions->assign_variables($template);
		$count = 0;
		
		foreach($categories as $key => $category_data)
		{	
			if ($this->show_these !== FALSE && ! in_array($category_data['0']['cat_id'], $this->show_these))
			{
				continue;
			}
			
			$count++;
			$children = array();
			
			if ($this->show_these !== FALSE)
			{
				foreach($category_data['1'] as $key2 => $cat)
				{
					if (in_array($cat['data']['cat_id'], $this->show_these))
					{
						$children[$key2] = $cat;
					}
				}
			}
			else
			{
				$children = $category_data['1'];
			}
			
			$output .= $this->category_process($template, $category_data['0'], $ancestry, '0', '0', '0', (count($children) > 0) ? 'y' : 'n', $style);
			
			foreach ($vars['var_single'] as $k => $v)
			{
				/** ----------------------------------------
				/**  parse {switch} variable
				/** ----------------------------------------*/
				if (preg_match("/^switch\s*=.+/i", $k))
				{
					$sparam = $this->EE->functions->assign_parameters($k);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$output = $this->EE->TMPL->swap_var_single($k, $sw, $output);
				}
				
				if ($k == 'count')
				{
					$output = $this->EE->TMPL->swap_var_single($k, $count, $output);
				}
			}
			
			$last_depth  = 0;
			
			foreach($children as $key2 => $cat)
			{
				$has_children = 'n';
				$next_depth	  = 0;
				$count++;

				// If the next array member has this category as its parent,
				// then we have kids!  Get the cigars!
				if (isset($children[$key2+1]))
				{
					if ($children[$key2+1]['data']['parent_id'] == $cat['data']['cat_id'])
					{
						$has_children = 'y';
					}
					
					$next_depth = $children[$key2+1]['depth'];
				}
			
				$output .= $this->category_process( $template, 
													$cat['data'], 
													$cat['parents'],
													$cat['depth'],
													$last_depth,
													$next_depth,
													$has_children,
													$style);
				
				foreach ($vars['var_single'] as $k => $v)
				{
					/** ----------------------------------------
					/**  parse {switch} variable
					/** ----------------------------------------*/
					if (preg_match("/^switch\s*=.+/i", $k))
					{
						$sparam = $this->EE->functions->assign_parameters($k);

						$sw = '';

						if (isset($sparam['switch']))
						{
							$sopt = explode("|", $sparam['switch']);

							$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
						}

						$output = $this->EE->TMPL->swap_var_single($k, $sw, $output);
					}
					
					if ($k == 'count')
					{
						$output = $this->EE->TMPL->swap_var_single($k, $count, $output);
					}
				}
													
				$last_depth	 = $cat['depth'];
			}
		}
		
		if ($style == 'nested')
		{
			$output .= "</ul>\n";
		}
		
		if ($backspace != '')
		{
			$output = substr($output, 0, - $backspace);
		}
		
		return $output;
	}

	
	/** -------------------------------------------
	/**  Process a Category for Output
	/** -------------------------------------------*/
	
	function category_process($template, $data, $parents, $depth, $last_depth='0', $next_depth='0', $children='n', $style='')
	{
		if ($this->show_these !== FALSE && ! in_array($data['cat_id'], $this->show_these))
		{
			return '';
		}
		
		$cdata = array(	'{category_name}'		=> $this->prep_title($data['cat_name']),
						'{category_id}'			=> $data['cat_id'],
						'{parent_id}'			=> $data['parent_id'],
						'{depth}'				=> $depth,
						'{last_depth}'			=> $last_depth,
						'{next_depth}'			=> $next_depth,
						'{path:view_category}'	=> $this->base_url.
													$this->category_ns.':'.
													((count($parents) > 0) ? implode($this->cats_separator, $parents).$this->cats_separator : '').
													$data['cat_name']);
							
		$this->conditionals['children']		= ($children == 'y') ? 'TRUE' : 'FALSE';
		$this->conditionals['first_child']	= ($depth > $last_depth) ? 'TRUE' : 'FALSE';
		$this->conditionals['last_child']	= ($depth > $next_depth) ? 'TRUE' : 'FALSE';
			
		$template = $this->prep_conditionals($template, array_merge($cdata, $this->conditionals));
		$template = str_replace(array_keys($cdata), array_values($cdata), $template);
		
		if ($style == 'nested')
		{
			$template = str_repeat("\t", ($depth == 0) ? 1 : $depth+1)."<li>".trim($template);
			
			if ($children == "y")
			{
				$template .= str_repeat("\t", $depth+1)."<ul>\n";
			}
			else
			{
				$template .= "</li>\n";
			}
			
			if ($depth > $next_depth)
			{
				for($i=$depth-$next_depth; $i > 0; --$i)
				{
					$template .= str_repeat("\t", $i+$next_depth)."</ul>\n";
					$template .= str_repeat("\t", $i+$next_depth)."</li>\n";
				}
			}
			
			return $template;
		}
		else
		{
			return $template;
		}
	}

	
	
	/** -------------------------------------------
	/**  Retrieve Wiki Categories
	/** -------------------------------------------*/
	
	function retrieve_categories($namespace, $page_id='', $show_empty='y')
	{
		/** --------------------------------------
		/**  Find Assigned Cats and Only Fetch Those
		/** --------------------------------------*/
		
		$xsql = '';
		
		if ($page_id != '' OR $show_empty == 'n' OR $show_empty == 'no')
		{
			$this->show_these = array();
			
			if ($page_id != '')
			{
				$query = $this->EE->db->query("SELECT cat_id FROM exp_wiki_category_articles WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
			}
			else
			{
				$query = $this->EE->db->query("SELECT DISTINCT cat_id FROM exp_wiki_category_articles");
			}
			
			if ($query->num_rows() == 0)
			{
				return FALSE;
			}
			
			foreach($query->result_array() as $row)
			{
				$this->show_these[] = $row['cat_id'];
			}
		}
		
		$sql = "SELECT * FROM exp_wiki_categories
				WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
				ORDER BY parent_id, cat_name";
		
		$query = $this->EE->db->query($sql);
		
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		return $this->structure_categories($query);
	}

	
	/* -------------------------------------------
	/*  Structure Wiki Categories
	/*
	/*  For the categories in the category array:
	/*	data => category data ($row)
	/*	depth => 0 (1, 2, etc.)
	/* -------------------------------------------*/
	
	function structure_categories($query, $start_cat='0')
	{	
		$this->temp_array = array();
		$parents = array();
				
		foreach ($query->result_array() as $row)
		{	
			$this->temp_array[$row['cat_id']] = array($row['cat_id'], $row['parent_id'], $row);
					
			if ($row['parent_id'] > 0 && ! isset($this->temp_array[$row['parent_id']]))
			{
				$parents[$row['parent_id']] = '';
			}
			
			unset($parents[$row['cat_id']]);			  
		}
		
		$categories  = array();
		$last_parent = 0;
			
		foreach($this->temp_array as $k => $v) 
		{			
			$this->cat_array = array();
			$this->cat_depth = 0;
		
			// If a child is missing its parent, then we assign it to the most
			// recent top level parent.
			if (isset($parents[$v['1']]))
			{
				$v['1'] = $last_parent;
			}
			
			if ($start_cat != $v['1'])
			{
				continue;
			}
			
			$last_parent = $k;
			$p_cats = array($v['2']['cat_name']);
			
			// If we are only showing some of the categories, collect all of the parent
			// category names to send to process_subcategories
			if ($start_cat != 0)
			{
				$this->find_parents($k, $k);
				$p_cats = array_reverse($this->parent_cats[$k]);
			}

			$this->process_subcategories($k, $p_cats);
			$categories[] = array($v['2'], $this->cat_array);
		}
		
		unset($this->temp_array);
		unset($this->cat_array);
		return $categories;
	}

	

	/** -------------------------------------------
	/**  Process Subcategories
	/** -------------------------------------------*/
		
	function process_subcategories($parent_id, $parents=array())
	{		
		$this->cat_depth++;
		foreach($this->temp_array as $key => $val) 
		{
			if ($parent_id == $val['1'])
			{
				$this->cat_array[] = array('data' => $val['2'], 'depth' => $this->cat_depth, 'parents' => $parents);
				$this->process_subcategories($key, array_merge($parents, array($val['2']['cat_name'])));
			}
		}
		$this->cat_depth--;
	}

	/** -------------------------------------------
    /**  Find Parent Categories
    /** -------------------------------------------*/

	function find_parents($cat_id, $base_cat)
	{	
		foreach ($this->temp_array as $v)
		{
			if ($cat_id == $v['0'])
			{
				$this->parent_cats[$base_cat][] = $v['2']["cat_name"];
				
				if ($v['2']["parent_id"] != 0)
				{
					$this->find_parents($v['2']["parent_id"], $base_cat);
				}				
			}
		}
	}
   /* END */
	
	
	/** ----------------------------------------
	/**  Edit
	/** ----------------------------------------*/
	
	function edit($title)
	{
		/** ----------------------------------------
		/**  Revision Edit
		/** ----------------------------------------*/
		
		if (preg_match("|revision\/([0-9]+)|i", $this->EE->uri->query_string, $url))
		{
			$revision_id = $url['1'];
			
			$this->edit_revision($revision_id, $title);
			return;
		}
		
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_allow_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		$this->return_data = $this->_deny_if('old_revision', $this->return_data);
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_edit.html'), $this->return_data);
		
		$query = $this->topic_request($title);

		/* -------------------------------------
		/*  'edit_wiki_article_form_start' hook.
		/*  - Allows complete takeover of the wiki article edit form
		/*  - Added 1.6.0
		*/  
			$edata = $this->EE->extensions->universal_call('edit_wiki_article_form_start', $this, $title, $query);
			if ($this->EE->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------*/
		
		/** ----------------------------------------
		/**  Locked Article?
		/** ----------------------------------------*/
		
		if ($query->num_rows() == 0 OR $query->row('page_locked')  != 'y')
		{
			$this->return_data = $this->_deny_if('locked', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_allow_if('locked', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Moderated Article?
		/** ----------------------------------------*/
		
		if ($query->num_rows() == 0 OR $query->row('page_moderated')  != 'y')
		{
			$this->return_data = $this->_deny_if('moderated', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_allow_if('moderated', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Revision?
		/** ----------------------------------------*/
		
		if (preg_match("|revision\/([0-9]+)|i", $this->EE->uri->query_string, $url))
		{
			$revision_id = $url['1'];
		}
		
		/* ----------------------------------------
		/*  Can User Edit Article?
		/*  
		/*  If a Revision, No One Can Edit
		/*  If New Topic, Users and Admins Can Edit
		/*  If Unlocked Topic, Users and Admins Can Edit
		/*  If Locked Topic, Only Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if (isset($revision_id))
		{
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		elseif($query->num_rows() == 0 && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->num_rows() == 0)
		{
    		$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);			
		}
		elseif($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Current Revision's Content
		/** ----------------------------------------*/
		
		if ($query->num_rows() > 0)
		{
			if ($query->row('page_redirect')  != '')
			{
				$content = '#REDIRECT [['.$query->row('page_redirect') .']]';
			}
			else
			{
				$results = $this->EE->db->query("SELECT page_content
										FROM exp_wiki_revisions
										WHERE page_id = '".$query->row('page_id') ."'
										AND revision_status = 'open'
										AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
										ORDER BY revision_date DESC LIMIT 1");
				
				$content = ($results->num_rows() == 0) ? '' :  $results->row('page_content') ;
			}
			
			$this->conditionals['redirect_page'] = $query->row('page_redirect') ;
			$redirect_page = $query->row('page_redirect') ;
		}
		else
		{
			$content = '';
			$redirect_page = '';
			$this->conditionals['redirect_page'] = '';
		}
		
		/** ----------------------------------------
		/**  Bits of Data
		/** ----------------------------------------*/
		
		$data['action']			= $this->base_url.$title;
		$data['id']				= 'edit_article_form';
		$data['onsubmit']		= "if (is_preview) { this.action = '".$this->base_url.$title."/edit/'; }";
		
		$data['hidden_fields']	= array(
										'title'		=> $title,
										'editing'	=> 'y'
									  );
									  
		$this->files();

		$preview = '';
		$revision_notes = '';
		$rename = '';
		
		/** ---------------------------------------
		/**  Preview?
		/** ---------------------------------------*/
		
		if ($this->EE->input->post('preview') === FALSE OR ! isset($_POST['article_content']))
		{
			$this->return_data = $this->_deny_if('preview', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_allow_if('preview', $this->return_data);
			
			$this->EE->load->library('typography');
			$this->EE->typography->initialize(array(
						'parse_images'	=> FALSE,
						'parse_smileys'	=> FALSE)
						);
			
			$preview = $this->convert_curly_brackets($this->EE->typography->parse_type($this->wiki_syntax($_POST['article_content']), 
													  array(
															'text_format'   => $this->text_format,
															'html_format'   => $this->html_format,
															'auto_links'    => $this->auto_links,
															'allow_img_url' => 'y'
														  )
													));
													
			$content 		= $_POST['article_content'];
			$revision_notes	= (isset($_POST['revision_notes'])) ? $_POST['revision_notes'] : '';
			$rename 		= (isset($_POST['rename'])) ? $_POST['rename'] : '';
			$redirect_page	= (isset($_POST['redirect'])) ? $_POST['redirect'] : $redirect_page;
		}
		
		// Load the form helper
		$this->EE->load->helper('form');
		
		$this->return_data = str_replace(array(
												'{form_declaration:wiki:edit}',
												'{content}',
												'{preview}',
												'{redirect_page}',
												'{path:redirect_page}',
												'{revision_notes}',
												'{rename}'
												), 
										array(
												$this->EE->functions->form_declaration($data),
												$this->encode_ee_tags(form_prep($content)),
												$preview,
												$this->encode_ee_tags(form_prep($this->prep_title($redirect_page))),
												$this->EE->functions->create_url($this->base_path).$this->valid_title($redirect_page),
												$this->encode_ee_tags(form_prep($revision_notes)),
												$this->encode_ee_tags(form_prep($rename))
												), 
										$this->return_data);
		
		/* -------------------------------------
		/*  'edit_wiki_article_form_end' hook.
		/*  - Allows edit page to be modified
		/*  - Added 1.6.0
		*/  
			if ($this->EE->extensions->active_hook('edit_wiki_article_form_end') === TRUE)
			{
				$this->return_data = $this->EE->extensions->universal_call('edit_wiki_article_form_end', $this, $query);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		/*
		/* -------------------------------------*/
	}

	
	
	
	/** ----------------------------------------
	/**  Edit Revision
	/** ----------------------------------------*/
	
	function edit_revision($revision_id, $title)
	{
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_allow_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_edit.html'), $this->return_data);
		
		$query = $this->topic_request($title);
		
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		/** ----------------------------------------
		/**  Locked Article?
		/** ----------------------------------------*/
		
		if ($query->row('page_locked')  != 'y')
		{
			$this->return_data = $this->_deny_if('locked', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_allow_if('locked', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Moderated Article?
		/** ----------------------------------------*/
		
		if ($query->row('page_moderated')  != 'y')
		{
			$this->return_data = $this->_deny_if('moderated', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_allow_if('moderated', $this->return_data);
		}
		
		/* ----------------------------------------
		/*  Can User Edit Revision?
		/*  
		/*  If Unlocked Topic, Users and Admins Can Edit
		/*  If Locked Topic, Only Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Current Revision's Content
		/** ----------------------------------------*/
		
		$results = $this->EE->db->query("SELECT page_content, revision_date, revision_notes, page_redirect  
							   FROM exp_wiki_page p LEFT JOIN  exp_wiki_revisions r ON r.page_id = p.page_id
							   WHERE p.page_id = '".$query->row('page_id')."'
							   AND revision_id = '".$this->EE->db->escape_str($revision_id)."'
							   AND p.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
							   ORDER BY revision_date DESC LIMIT 1");
									
		if ($results->row('revision_date')  < $query->row('last_updated') )
		{
			$this->return_data = $this->_allow_if('old_revision', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_deny_if('old_revision', $this->return_data);
		}
			
		$content = ($results->num_rows() == 0) ? '' :  $results->row('page_content');
		$revision_notes = ($results->num_rows() == 0) ? '' :  $results->row('revision_notes');
		$redirect = ($results->num_rows() == 0) ? '' :  $results->row('page_redirect');		
		
		$this->conditionals['redirect_page'] = '';
		
		/** ----------------------------------------
		/**  Bits of Data
		/** ----------------------------------------*/
		
		$data['action']			= $this->base_url.$title;
		$data['id']				= 'edit_revision_form';
		
		$data['hidden_fields']	= array(
										'title'		=> $title,
										'editing'	=> 'y'
									  );
									  
		$this->files();

		// Load the form helper
		$this->EE->load->helper('form');

		$this->return_data = str_replace(array('{form_declaration:wiki:edit}', '{content}', '{redirect_page}', '{revision_notes}', '{rename}'), array($this->EE->functions->form_declaration($data), $this->encode_ee_tags(form_prep($content)), $redirect, $revision_notes, ''),
										$this->return_data);
	}

	
	
	/** ----------------------------------------
	/**  History
	/** ----------------------------------------*/
	
	function history($title)
	{
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_allow_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_history.html'), $this->return_data);
		
		$query = $this->topic_request($title);
		
		if ($query->num_rows() > 0)
		{
			$xsql = (in_array($this->EE->session->userdata['group_id'], $this->admins)) ? '' : " AND r.revision_status = 'open' ";
				
			$results = $this->EE->db->query("SELECT r.*, m.screen_name
									FROM exp_wiki_revisions r, exp_members m
									WHERE r.page_id = '".$query->row('page_id') ."'
									AND r.revision_author = m.member_id
									AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
									{$xsql}
									ORDER BY r.revision_date DESC");
		}
		
		if ($query->num_rows() == 0)
		{
			$this->return_data = $this->_deny_if('history', $this->return_data);
			$this->return_data = $this->_allow_if('no_history', $this->return_data);
		}
		elseif ($results->num_rows() == 0)
		{
			$this->return_data = $this->_deny_if('history', $this->return_data);
			$this->return_data = $this->_allow_if('no_history', $this->return_data);
		}
		else
		{
			$this->return_data = $this->_allow_if('history', $this->return_data);
			$this->return_data = $this->_deny_if('no_history', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Redirects
		/** ----------------------------------------*/
		
		if ($query->num_rows() > 0 && $query->row('page_redirect')  != '')
		{
			// There should be no revisions
		}
		
		/** ----------------------------------------
		/**  Locked Article?
		/** ----------------------------------------*/
		
		if ($query->num_rows() == 0 OR $query->row('page_locked')  != 'y')
		{
			$this->return_data = $this->_deny_if('locked', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_allow_if('locked', $this->return_data);
		}
		
		/* ----------------------------------------
		/*  Can User Edit Article?
		/*  
		/*  If New Topic, Users and Admins Can Edit
		/*  If Unlocked Topic, Users and Admins Can Edit
		/*  If Locked Topic, Only Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if($query->num_rows() == 0 && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Current Revision's Content
		/** ----------------------------------------*/
								
		if (preg_match("/\{wiki:revisions.*?\}(.*?)\{\/wiki:revisions\}/s", $this->return_data, $match))
		{
			if ($query->num_rows() == 0)
			{
				$this->return_data = str_replace($match['0'], '', $this->return_data);
			}
			else
			{	
				if ($results->num_rows() == 0) 		
				{
					$this->return_data = str_replace($match['0'], '', $this->return_data);
					return;
				}
				
				if (preg_match("/\{revision_date.*?format=[\"|'](.*?)[\"|'].*?\}/", $match['1'], $date))
				{
					$date_format = ($date['1'] == '') ? array() : $this->EE->localize->fetch_date_params(str_replace(array(LD, RD), '', $date['1']));
				}
				
				/** ---------------------------------
				/**  Parse Our Results
				/** ---------------------------------*/
				
				$revisions = '';
				$count = 0;
				$vars = $this->EE->functions->assign_variables($match['1']);
					
				foreach ($results->result_array() as $row)
				{
					$count++;
					$temp = $match['1'];
					
					if ($row['revision_notes'] == '')
					{
						$temp = $this->_deny_if('notes', $temp);
					}
					else
					{
						$temp = $this->_allow_if('notes', $temp);
					}
					
					$data = array(	'{revision_author}' 	=> $this->prep_screen_name($row['screen_name']),
									'{revision_notes}'		=> $row['revision_notes'],
									'{revision_status}'		=> $row['revision_status'],
									'{path:member_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['revision_author']),
									'{path:revision_link}'	=> $this->base_url.$title.'/revision/'.$row['revision_id'],
									'{path:close_revision}'	=> $this->base_url.$title.'/revision/'.$row['revision_id'].'/close',
									'{path:open_revision}'	=> $this->base_url.$title.'/revision/'.$row['revision_id'].'/open',
									'{count}'				=> $count);
									
					$temp = $this->prep_conditionals($temp, $data);
				
					$temp = str_replace(array_keys($data), array_values($data), $temp);
					
					foreach ($vars['var_single'] as $key => $val)
					{
						/** ----------------------------------------
						/**  parse {switch} variable
						/** ----------------------------------------*/
						if (preg_match("/^switch\s*=.+/i", $key))
						{
							$sparam = $this->EE->functions->assign_parameters($key);

							$sw = '';

							if (isset($sparam['switch']))
							{
								$sopt = explode("|", $sparam['switch']);

								$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
							}

							$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
						}
					}
										
					if (isset($date_format))
					{
						$temp_date = $date['1'];
						
						foreach ($date_format as $dvar)
						{
							$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], TRUE), $temp_date);		
						}
							
						$temp = str_replace($date['0'], $temp_date, $temp);
					}
		
					$revisions .= $temp;
				}
						
				if (preg_match("/\{wiki:revisions.+?backspace=[\"|'](.+?)[\"|']/", $this->return_data, $backspace))
				{
					$revisions = substr($revisions, 0, - $backspace['1']);
				}
										
				$this->return_data = str_replace($match['0'], $revisions, $this->return_data);
			}
		}
	}

	
	
	/** ----------------------------------------
	/**  New Article
	/** ----------------------------------------*/
	
	function new_article($title, $original_page='')
	{
		$this->title = $title;
		
		$this->return_data = $this->_allow_if('new_article', $this->return_data);
		$this->return_data = $this->_allow_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_article.html'), $this->return_data);
		$this->return_data = $this->_deny_if('categories', $this->return_data);
		
		/* ----------------------------------------
		/*  Can User Edit Article?
		/*  
		/*  If New Topic, Users and Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if(in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		if ($original_page != '')
		{
			$this->conditionals['original_page'] = $original_page;
			
			$this->return_data = $this->_allow_if('redirected', $this->return_data);
			
			$this->return_data = str_replace(array('{original_page}', '{path:original_page}'), 
											 array($this->prep_title($original_page), $this->base_url.$original_page.'/noredirect'),
											 $this->return_data);
		}
		else
		{
			$this->return_data = $this->_deny_if('redirected', $this->return_data);
		}
		
		if ($this->current_namespace == $this->category_ns && 
			(stristr($this->return_data, '{/wiki:category_subcategories}') OR stristr($this->return_data, '{wiki:category_articles}'))
			)
		{
			$this->category_page();
		}
		
		$this->conditionals['author'] = '';
		
		$this->return_data = str_replace(array('{author}', '{article}', '{content}'), '', $this->return_data);
	}

	
	
	/** ----------------------------------------
	/**  Article
	/** ----------------------------------------*/
	
	function article($title)
	{
		$redirects = array();
		
		$query = $this->topic_request($title);
		
		if ($query->num_rows() == 0)
		{
			return $this->new_article($title);
		}
		
		/* -------------------------------------
		/*  'wiki_article_start' hook.
		/*  - Allows takeover of wiki article display
		/*  - Added 1.6.0
		*/  
			$edata = $this->EE->extensions->universal_call('wiki_article_start', $this, $title, $query);
			if ($this->EE->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------*/
		
		/** ----------------------------------------
		/**  Cancel Redirect?
		/** ----------------------------------------*/
		
		if ($query->row('page_redirect')  != '' && preg_match("|".preg_quote($title)."/noredirect|i", $this->EE->uri->uri_string, $url))
		{
			$this->return_data = $this->_deny_if('new_article', $this->return_data);
			$this->return_data = $this->_allow_if('article', $this->return_data);
			$this->return_data = $this->_deny_if('revision', $this->return_data);
			$this->return_data = $this->_deny_if('edit_article', $this->return_data);
			$this->return_data = $this->_deny_if('article_history', $this->return_data);
			$this->return_data = $this->_deny_if('special_page', $this->return_data);
			$this->return_data = $this->_deny_if('file_page', $this->return_data);
			$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_article.html'), $this->return_data);
			
			/* ----------------------------------------
			/*  Can User Edit Article?
			/*  
			/*  If Unlocked Topic, Users and Admins Can Edit
			/*  If Locked Topic, Only Admins Can Edit
			/*  Everyone Else, No EDIT!
			/* ----------------------------------------*/
			
			if($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
			{
				$this->return_data = $this->_allow_if('can_edit', $this->return_data);
				$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
			}
			elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				$this->return_data = $this->_allow_if('can_edit', $this->return_data);
				$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
			}
			else
			{	
				$this->return_data = $this->_deny_if('can_edit', $this->return_data);
				$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
			}
			
			$this->return_data = $this->_allow_if('redirect_page', $this->return_data);
			$this->return_data = $this->_deny_if('redirected', $this->return_data);
			
			$this->conditionals['redirect_page'] = $query->row('page_redirect') ;
			$this->conditionals['author']		 = ''; // No author for redirect
			
			if ($this->current_namespace == $this->category_ns && 
				(stristr($this->return_data, '{/wiki:category_'))
				)
			{
				$this->return_data = preg_replace("/\{wiki:category_(.*?)\}(.*?)\{\/wiki:category_(.*?)\}/s", '', $this->return_data); 
			}
			
			$this->return_data = str_replace(array('{author}', '{article}', '{content}', '{redirect_page}', '{path:redirect_page}'), 
											 array('', '', '', $this->prep_title($query->row('page_redirect') ), $this->base_url.$this->valid_title($query->row('page_redirect'))), 
											 $this->return_data);

			/* -------------------------------------
			/*  'wiki_article_end' hook.
			/*  - Allows article page to be modified
			/*  - Added 1.6.0
			*/  
				if ($this->EE->extensions->active_hook('wiki_article_end') === TRUE)
				{
					$this->return_data = $this->EE->extensions->universal_call('wiki_article_end', $this, $query);
					if ($this->EE->extensions->end_script === TRUE) return;
				}
			/*
			/* -------------------------------------*/
										
			return;
		}
		
		/** ----------------------------------------
		/**  Follow the Redirects
		/** ----------------------------------------*/
		
		if ($query->row('page_redirect')  != '')
		{	
			$original_page = $title;
		
			while($query->row('page_redirect')  != '')
			{
				$redirects[] = $query->row('page_id') ;
				$redirect_page = $query->row('page_redirect') ;
				
				$query = $this->topic_request($query->row('page_redirect') );
				
				if ($query->num_rows() == 0)
				{
					return $this->new_article($redirect_page, $title);
				}
				elseif(in_array($query->row('page_id') , $redirects))
				{
					break;
				}
			}
		}
		
		/** ----------------------------------------
		/**  Display Our Article
		/** ----------------------------------------*/
		
		$results = $this->EE->db->query("SELECT r.*, m.screen_name
								FROM exp_wiki_revisions r, exp_members m
								WHERE m.member_id = r.revision_author
								AND r.page_id = '".$query->row('page_id') ."'
								AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								AND r.revision_status = 'open'
								ORDER BY r.revision_date DESC LIMIT 1");
		
		if ($results->num_rows() == 0)
		{
			return $this->new_article($title);
		}
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$article = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($results->row('page_content') ), 
												  array(
														'text_format'	=> $this->text_format,
														'html_format'	=> $this->html_format,
														'auto_links'	=> $this->auto_links,
														'allow_img_url' => 'y'
													  )
												));
									
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_allow_if('article', $this->return_data);
		$this->return_data = $this->_deny_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_article.html'), $this->return_data);
		
		if ($query->row('has_categories')  == 'y')
		{
			$this->return_data = $this->_allow_if('categories', $this->return_data);
			
			if (stristr($this->return_data, '{/wiki:categories'))
			{
				$this->categories($query->row('page_id') );
			}
		}
		else
		{
			$this->return_data = $this->_deny_if('categories', $this->return_data);
		}
		
		/* ----------------------------------------
		/*  Can User Edit Article?
		/*  
		/*  If Unlocked Topic, Users and Admins Can Edit
		/*  If Locked Topic, Only Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		if (isset($original_page))
		{
			$this->return_data = $this->_allow_if('redirected', $this->return_data);
			
			$this->conditionals['original_page'] = $original_page;
			
			$this->return_data = str_replace(array('{original_page}', '{path:original_page}'), 
											 array($this->prep_title($original_page), $this->base_url.$original_page.'/noredirect'),
											 $this->return_data);
		}
		else
		{
			$this->return_data = $this->_deny_if('redirected', $this->return_data);
		}
		
		$this->conditionals['author'] = $results->row('screen_name') ;
		
		if ($this->current_namespace == $this->category_ns && 
			(stristr($this->return_data, '{/wiki:category_subcategories}') OR stristr($this->return_data, '{wiki:category_articles}'))
			)
		{
			$this->category_page();
		}
		
		$this->return_data = str_replace(array('{author}', '{article}', '{content}'), array($results->row('screen_name') , $article, $results->row('page_content') ), $this->return_data);
		
		/* -------------------------------------
		/*  'wiki_article_end' hook.
		/*  - Allows article page to be modified
		/*  - Added 1.6.0
		*/  
			if ($this->EE->extensions->active_hook('wiki_article_end') === TRUE)
			{
				$this->return_data = $this->EE->extensions->universal_call('wiki_article_end', $this, $query);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		/*
		/* -------------------------------------*/
	}
	/* END */
	
	
	/** ---------------------------------------
	/**  Associated Pages, i.e. "What Links Here?"
	/** ---------------------------------------*/
	
	function associated_pages()
	{	
		if ( ! isset($this->seg_parts['1']))
		{
			return;
		}
		
		$article_title = $this->prep_title($this->valid_title($this->EE->security->xss_clean(strip_tags($this->seg_parts['1']))));

		$this->return_data = str_replace(LD.'wiki:page'.RD, $this->_fetch_template('wiki_special_associated_pages.html'), $this->return_data);
		$this->return_data = str_replace(LD.'article_title'.RD, $article_title, $this->return_data);
		$this->return_data = str_replace(LD.'path:view_orig_article'.RD, $this->create_url('', $article_title), $this->return_data);
				
		if (preg_match("/\{wiki:associated_pages(.*?)\}(.*?)\{\/wiki:associated_pages\}/s", $this->return_data, $match))
		{
			$no_results = '';
			$header = '';
			$footer = '';
			
			if (preg_match("|".LD."if\s+no_results".RD."(.*?)".LD."\/if".RD."|s",$match['2'], $block))
			{
				$no_results = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
		
			if (preg_match("|".LD."header".RD."(.*?)".LD."\/header".RD."|s",$match['2'], $block))
			{
				$header = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			if (preg_match("|".LD."footer".RD."(.*?)".LD."\/footer".RD."|s",$match['2'], $block))
			{
				$footer = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			// The last line of this query deserves some commenting.
			// MySQL uses a POSIX regex implementation, one in particular that uses [[:>:]] to match the null
			// string at the end of a word, i.e. the word boundary.  There is no ereg_quote(), but preg_quote()
			// escapes all of the necessary characters.
			$query = $this->EE->db->query("SELECT p.page_name, n.namespace_label
								FROM exp_wiki_page AS p
								LEFT JOIN exp_wiki_namespaces AS n ON n.namespace_name = p.page_namespace
								LEFT JOIN exp_wiki_revisions AS r ON r.revision_id = p.last_revision_id
								WHERE r.page_content REGEXP '".$this->EE->db->escape_str(preg_quote('[['.$article_title))."[[:>:]].*".$this->EE->db->escape_str(preg_quote(']]'))."'
								AND p.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");

			if ($query->num_rows() == 0)
			{
				$this->return_data = str_replace($match['0'], $no_results, $this->return_data);
				return;
			}
		
			$output = '';
			$count = 0;
			$vars = $this->EE->functions->assign_variables($match['2']);
			
			foreach ($query->result() as $row)
			{
				$temp = $match['2'];
				$title = ($row->namespace_label != '') ? $row->namespace_label.':'.$row->page_name : $row->page_name;
				
				$data = array(
								'title'				=> $this->prep_title($title),
								'count'				=> ++$count,
								'path:view_article'	=> $this->base_url.$title
							);
				
				foreach ($vars['var_single'] as $key => $val)
				{
					/** ----------------------------------------
					/**  parse {switch} variable
					/** ----------------------------------------*/

					if (preg_match("/^switch\s*=.+/i", $key))
					{
						$sparam = $this->EE->functions->assign_parameters($key);

						$sw = '';

						if (isset($sparam['switch']))
						{
							$sopt = explode("|", $sparam['switch']);

							$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
						}

						$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
					}
					
					if (isset($data[$key]))
					{
						$temp = $this->EE->TMPL->swap_var_single($key, $data[$key], $temp);
					}
				}
				
				$output .= $temp;
			}
			
			$this->return_data = str_replace($match['0'], $header.$output.$footer, $this->return_data);
		}
	}
	/* END */
	
		
	/** ----------------------------------------
	/**  Determine What Category
	/** ----------------------------------------*/
	
	function determine_category($topic)
	{
		$cats = explode($this->cats_separator, strtolower($topic));
		
		$parent_id = 0;
		
		/* ----------------------------------------
		/*  First We Find Our Category Based on Its Ancestory
		/*
		/*  - Basically, we retrieve all of the categories for the category names
		/*  in the topic.  As we allow nesting of categories, we might have
		/*  categories with the same name so we have to go through the categories
		/*  following the nesting to find the correct category at the bottom.
		/* ----------------------------------------*/
		
		$xsql = " AND LOWER(cat_name) IN ('";
		
		foreach($cats as $cat)
		{
			$xsql .= $this->EE->db->escape_str($this->valid_title($cat))."','";
		}
		
		$xsql = substr($xsql, 0, -2).") ";
		
		$query = $this->EE->db->query("SELECT cat_id, parent_id, cat_name
							FROM exp_wiki_categories
							WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
							{$xsql}
							ORDER BY parent_id, cat_name");
		
		$ancestry = array();
		
		if ($query->num_rows() > 0)
		{
			while(count($cats) > 0)
			{
				$current = array_shift($cats);
				$found = 'n';
				
				foreach($query->result_array() as $row)
				{
					if (strtolower($this->valid_title($row['cat_name'])) == $current)
					{
						if (( ! isset($cat_id) && $row['parent_id'] == 0) OR (isset($cat_id) && $cat_id == $row['parent_id']))
						{
							$parent_id		= $row['parent_id'];
							$cat_id			= $row['cat_id'];
							$ancestry[]		= $row['cat_name'];
							
							$found = 'y';
							continue;
						}
					}
				}
				
				if ($found == 'n' OR ! isset($cat_id))
				{
					$cat_id		= 0;
					$parent_id	= 0;
					break;
				}
			}
		}
		else
		{
			$cat_id = 0;
		}
		
		return array('cat_id' => $cat_id, 'parent_id' => $parent_id, 'ancestry' => $ancestry);
	}

	
	
	/** ----------------------------------------
	/**  Category Page Processing
	/** ----------------------------------------*/
	
	function category_page()
	{
		$cat_data = $this->determine_category($this->topic);

		extract($cat_data);
		
		/** ----------------------------------------
		/**  Display All of the Subcategories for a Category
		/** ----------------------------------------*/
	
		if (preg_match("/\{wiki:category_subcategories(.*?)\}(.*?)\{\/wiki:category_subcategories\}/s", $this->return_data, $match))
		{
			/** ----------------------------------------
			/**  Parameters and Variables
			/** ----------------------------------------*/
			
			$no_results = '';
			$header		= '';
			$footer		= '';
			$backspace	= '';
			$style		= '';
		
			if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
			{
				$backspace	= (isset($params['backspace']) && is_numeric($params['backspace'])) ? $params['backspace'] : $backspace;
				$style		= (isset($params['style'])) ? $params['style'] : $style;
			}
			
			if (preg_match("|".LD."if\s+no_results".RD."(.*?)".LD."\/if".RD."|s",$match['2'], $block))
			{
				$no_results = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
		
			if (preg_match("|".LD."header".RD."(.*?)".LD."\/header".RD."|s",$match['2'], $block))
			{
				$header = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			if (preg_match("|".LD."footer".RD."(.*?)".LD."\/footer".RD."|s",$match['2'], $block))
			{
				$footer = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			/** ----------------------------------------
			/**  Parsing and Output
			/** ----------------------------------------*/
			
			$data = $no_results;
			$subs = 0;
		
			if ($cat_id !== 0)
			{
				$query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_wiki_categories
									 WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
									 AND parent_id = '".$this->EE->db->escape_str($cat_id)."'
									 ORDER BY parent_id, cat_name");
				
				if ($query->row('count')  > 0)
				{
					$subs = $query->row('count') ;
					
					$query = $this->EE->db->query("SELECT * FROM exp_wiki_categories
									 WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
									 ORDER BY parent_id, cat_name");
									 
					$data  = $header;
					$data .= $this->parse_categories($this->structure_categories($query, $cat_id), $match['2'], 'nested', 0, $ancestry);
					$data .= $footer;
				}
			}
			
			$this->conditionals['subcategory_total'] = $subs;
			$this->return_data = str_replace($match['0'], str_replace('{subcategory_total}', $subs, $data), $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Display All of Articles for the Category
		/** ----------------------------------------*/
	
		if (preg_match("/\{wiki:category_articles(.*?)\}(.*?)\{\/wiki:category_articles\}/s", $this->return_data, $match))
		{
			/** ----------------------------------------
			/**  Parameters and Variables
			/** ----------------------------------------*/
			
			$no_results = '';
			$header		= '';
			$footer		= '';
			
			$parameters['backspace'] = '';
			$parameters['limit']	 = 100;
			$parameters['paginate']  = 'bottom';
			
			if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
			{
				$parameters['backspace'] = (isset($params['backspace']) && is_numeric($params['backspace'])) ? $params['backspace'] : $parameters['backspace'];
				$parameters['limit']	 = (isset($params['limit'])) ? $params['limit'] : $parameters['limit'];
				$parameters['paginate']	= (isset($params['paginate'])) ? $params['paginate'] : $parameters['paginate'];
			}
			
			if (preg_match("|".LD."if\s+no_results".RD."(.*?)".LD."\/if".RD."|s",$match['2'], $block))
			{
				$no_results = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
		
			if (preg_match("|".LD."header".RD."(.*?)".LD."\/header".RD."|s",$match['2'], $block))
			{
				$header = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			if (preg_match("|".LD."footer".RD."(.*?)".LD."\/footer".RD."|s",$match['2'], $block))
			{
				$footer = $block['1'];
				$match['2'] = str_replace($block['0'],'', $match['2']);
			}
			
			/** ----------------------------------------
			/**  Parsing and Output
			/** ----------------------------------------*/
			
			$data = $no_results;
			$articles_total = 0;
		
			if ($cat_id !== 0)
			{
				$sql = "FROM exp_wiki_category_articles ca, exp_wiki_page p, exp_wiki_revisions r, exp_members m
						WHERE ca.cat_id = '".$this->EE->db->escape_str($cat_id)."'
						AND ca.page_id = p.page_id
						AND p.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
						AND p.page_id = r.page_id
						AND p.last_updated = r.revision_date
						AND m.member_id = r.revision_author
					 	AND r.revision_status = 'open'";
					 	
				$query = $this->EE->db->query("SELECT COUNT(p.page_id) AS count ".$sql);
				
				if ($query->row('count')  > 0)
				{
					$articles_total = $query->row('count') ;
					
					$this->pagination($query->row('count') , $parameters['limit'], $this->base_url.$this->category_ns.':'.$this->topic);
						
					// Pagination code removed, rerun template preg_match()
					if ($this->paginate === TRUE)
					{
						preg_match("/\{wiki:category_articles(.*?)\}(.*?)\{\/wiki:category_articles\}/s", $this->return_data, $match);
						
						if (preg_match("|".LD."if\s+no_results".RD."(.*?)".LD."\/if".RD."|s",$match['2'], $block))
						{
							$no_results = $block['1'];
							$match['2'] = str_replace($block['0'],'', $match['2']);
						}

						if (preg_match("|".LD."header".RD."(.*?)".LD."\/header".RD."|s",$match['2'], $block))
						{
							$header = $block['1'];
							$match['2'] = str_replace($block['0'],'', $match['2']);
						}

						if (preg_match("|".LD."footer".RD."(.*?)".LD."\/footer".RD."|s",$match['2'], $block))
						{
							$footer = $block['1'];
							$match['2'] = str_replace($block['0'],'', $match['2']);
						}
					}
					else
					{
						$this->pagination_sql .= " LIMIT ".$parameters['limit'];
					}
					 	
					$query = $this->EE->db->query("SELECT r.*, m.member_id, m.screen_name, m.email, m.url, p.page_namespace, p.page_name AS topic ".
										$sql.
										" ORDER BY topic ".
										$this->pagination_sql);
				
					$data = $header;
				
					$data .= $this->parse_results($match, $query, $parameters, $this->parse_dates($match['2']));
					
					$data .= $footer;
				}
			}
			
			$this->conditionals['articles_total'] = $articles_total;
			$this->return_data = str_replace($match['0'], str_replace('{articles_total}', $articles_total, $data), $this->return_data);
		}
	}

	
	/** ----------------------------------------
	/**  Parse Dates Out of String
	/** ----------------------------------------*/
	
	function parse_dates($str)
	{
		$dates = array();
		
		if (preg_match_all("/".LD."(gmt_last_updated|gmt_revision_date|last_updated|revision_date)\s+format=[\"'](.*?)[\"']".RD."/s", $this->return_data, $matches))
		{
			for ($j = 0; $j < count($matches['0']); $j++)
			{	
				switch ($matches['1'][$j])
				{
					case 'gmt_last_updated' 	: $dates['gmt_last_updated'][$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
					case 'last_updated' 		: $dates['last_updated'][$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
					case 'gmt_revision_date'	: $dates['gmt_revision_date'][$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
					case 'revision_date'		: $dates['revision_date'][$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
				}
			}
		}
	
		return $dates;
	}

	
	
	
	/** ----------------------------------------
	/**  Revision
	/** ----------------------------------------*/
	
	function revision($title)
	{
		$redirects = array();
		
		$query = $this->topic_request($title);
		
		if ($query->num_rows() == 0)
		{
			return $this->article($title);
		}
		
		/** ----------------------------------------
		/**  Do Not Follow Redirects
		/** ----------------------------------------*/
		
		if ($query->row('page_redirect')  != '')
		{	
			
		}
		
		/** ----------------------------------------
		/**  Display Our Revision
		/** ----------------------------------------*/
		
		if (preg_match("|revision\/([0-9]+)|i", $this->EE->uri->query_string, $url))
		{
			$revision_id = $url['1'];
			
			if (preg_match("|revision\/".$revision_id."\/([a-z]+)|i", $this->EE->uri->query_string, $url))
			{
				switch($url['1'])
				{
					case 'edit' :
						$this->edit_revision($revision_id, $title);
						return;
					break;
					case 'open' :
						$this->open_close_revision($title, $revision_id, 'open');	
					break;
					case 'close' :
						$this->open_close_revision($title, $revision_id, 'closed');
					break;
				}
			}
		}
		else
		{
			return $this->article($title);
		}
		
		$xsql = (in_array($this->EE->session->userdata['group_id'], $this->admins)) ? '' : " AND r.revision_status = 'open' ";
		
		$results = $this->EE->db->query("SELECT r.*, m.screen_name
								FROM exp_wiki_revisions r, exp_members m
								WHERE m.member_id = r.revision_author
								AND r.page_id = '".$query->row('page_id') ."'
								AND r.revision_id = '".$this->EE->db->escape_str($revision_id)."'
								AND r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								{$xsql}
								ORDER BY r.revision_date DESC LIMIT 1");
								
		if ($results->num_rows() == 0)
		{
			return $this->article($title);
		}
		
		$this->return_data = $this->_deny_if('new_article', $this->return_data);
		$this->return_data = $this->_deny_if('article', $this->return_data);
		$this->return_data = $this->_allow_if('revision', $this->return_data);
		$this->return_data = $this->_deny_if('edit_article', $this->return_data);
		$this->return_data = $this->_deny_if('article_history', $this->return_data);
		$this->return_data = $this->_deny_if('special_page', $this->return_data);
		$this->return_data = $this->_deny_if('file_page', $this->return_data);
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_article.html'), $this->return_data);
		
		if ($query->row('has_categories')  == 'y')
		{
			$this->return_data = $this->_allow_if('categories', $this->return_data);
			
			if (stristr($this->return_data, '{/wiki:categories'))
			{
				$this->categories($query->row('page_id') );
			}
		}
		else
		{
			$this->return_data = $this->_deny_if('categories', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Date Formats
		/** ----------------------------------------*/
		
		if (preg_match_all("/".LD."(revision_date)\s+format=[\"'](.*?)[\"']".RD."/s", $this->return_data, $matches))
		{
			$revision_date = array();
			
			for ($j = 0; $j < count($matches['0']); $j++)
			{	
				switch ($matches['1'][$j])
				{
					case 'revision_date'  : $revision_date[$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
				}
			}
			
			foreach($revision_date as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $results->row('revision_date') , TRUE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
								
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$article = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($results->row('page_content') ), 
												  array(
														'text_format'	=> $this->text_format,
														'html_format'	=> $this->html_format,
														'auto_links'	=> $this->auto_links,
														'allow_img_url' => 'y'
													  )
												));
												
		/* ----------------------------------------
		/*  Can User Edit Article?
		/*  
		/*  If Unlocked Topic, Users and Admins Can Edit
		/*  If Locked Topic, Only Admins Can Edit
		/*  Everyone Else, No EDIT!
		/* ----------------------------------------*/
		
		if($query->row('page_locked')  != 'y' && (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins)))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		elseif($query->row('page_locked')  == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		$this->return_data = preg_replace('/\{wiki:(category_articles|category_subcategories)[^\}]*\}.*?\{\/wiki:\\1\}/si', '', $this->return_data);
		
		$this->revision_id = $revision_id;
		
		$this->return_data = str_replace(array('{article}', '{content}'), array($article, $results->row('page_content') ), $this->return_data);
	}

	
	
	/** ----------------------------------------
	/**  Active Members
	/** ----------------------------------------*/
	
	function active_members($str)
	{
		if ( ! preg_match("/\{wiki:active_members.*?\}(.*?)\{\/wiki:active_members\}/s", $str, $match))
		{
			return $str;
		}
		
		if (count($this->EE->stats->statdata('current_names')) == 0) 		
		{
			return str_replace($match['0'], '', $str);
		}
		
		/** ---------------------------------
		/**  Parse the Names Out Into Template
		/** ---------------------------------*/
		
		$names = '';
				
		foreach ($this->EE->stats->statdata('current_names') as $k => $v)
		{
			$temp = $match['1'];
		
			if ($v['1'] == 'y')
			{
				if ($this->EE->session->userdata['group_id'] == 1)
				{
					$temp = str_replace('{name}', $v['0'].'*', $temp);
				}
				elseif ($this->EE->session->userdata['member_id'] == $k)
				{
					$temp = str_replace('{name}', $v['0'].'*', $temp);
				}
				else
				{
					continue;
				}
			}
			else
			{
				$temp = str_replace('{name}', $v['0'], $temp);
			}
			
			$temp = str_replace('{path:member_profile}', $this->EE->functions->create_url($this->profile_path.$k), $temp);

			$names .= $temp;
		}
				
		if (preg_match("/\{wiki:active_members.+?backspace=[\"|'](.+?)[\"|']/", $str, $backspace))
		{
			$names = substr($names, 0, - $backspace['1']);
		}
					
		return str_replace($match['0'], $names, $str);
	}

	
	
	/* -------------------------------------
	/*  Conditional Helpers
	/*  - Since we are putting the wiki into a template
	/*	then I thought we might want to use the already existing
	/*  conditional parser and evaluator to do conditionals for us.
	/* -------------------------------------*/	 		

	function _deny_if($cond, $str)
	{
		$this->conditionals[$cond] = 'FALSE';
		return preg_replace("/\{if\s+".$cond."\}/si", "{if FALSE}", $str);
	}
	
	function _allow_if($cond, $str)
	{
		$this->conditionals[$cond] = 'TRUE';
		return preg_replace("/\{if\s+".$cond."\}/si", "{if TRUE}", $str);
	}

	
	
	/** -------------------------------------
	/**  Edit Article
	/** -------------------------------------*/
	function edit_article()
	{
		if ($this->EE->input->post('editing') === FALSE OR $this->EE->input->get_post('title') === FALSE OR $this->EE->input->get_post('title') == '' OR $this->EE->input->get_post('article_content') === FALSE)
		{
			return $this->EE->output->show_user_error('general', array($this->EE->lang->line('invalid_permissions')));
		}
		
		if ( ! in_array($this->EE->session->userdata['group_id'], $this->users) && ! in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			return $this->EE->output->show_user_error('general', array($this->EE->lang->line('invalid_permissions')));
		}
		
		/** -------------------------------------
		/**  Edit Limit
		/** -------------------------------------*/
		
		$this->edit_limit();
		
  		// Secure Forms check
	  	// If the hash is not found we'll simply reload the page.
		if ($this->EE->security->secure_forms_check($this->EE->input->post('XID')) == FALSE)
		{
			$this->redirect('', $this->EE->input->get_post('title'));
		}
	  
		/** -------------------------------------
		/**  Process Edit Form
		/** -------------------------------------*/
		
		$query = $this->topic_request($this->valid_title($this->EE->input->get_post('title')));

		if ($query->num_rows() == 0)
		{
			$current_name = strtolower($this->current_namespace);
			$key = '';
			
			foreach ($this->namespaces as $name => $label)
			{
				if ($current_name == strtolower($label['1']))
				{
					$key = $label['0'];
					break;
				}
			}
			
			$data = array('page_name'		=> $this->topic,
						  'page_namespace'	=> $key,  // Namespace's Short Name from Label
						  'last_updated'	=> $this->EE->localize->now,
						  'wiki_id'			=> $this->wiki_id);
			
			if (in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				if ($this->EE->input->get_post('delete_article') == 'y' && $this->current_namespace == $this->category_ns)
				{
					$cat_data = $this->determine_category($this->topic);

					if ($cat_data['cat_id'] != 0)
					{
						$results = $this->EE->db->query("SELECT page_id FROM exp_wiki_category_articles WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						
						if ($results->num_rows() > 0)
						{
							foreach($results->result_array() as $row)
							{
								$count = $this->EE->db->query("SELECT (COUNT(*) - 1) AS count FROM exp_wiki_category_articles WHERE page_id = '".$this->EE->db->escape_str($row['page_id'])."'");
								
								if ($count->row('count')  == 0)
								{
									$this->EE->db->query("UPDATE exp_wiki_page SET has_categories = 'n' WHERE page_id = '".$this->EE->db->escape_str($row['page_id'])."'");
								}
							}
						}
						
						$this->EE->db->query("DELETE FROM exp_wiki_category_articles WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						$this->EE->db->query("DELETE FROM exp_wiki_categories WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						$this->EE->db->query("UPDATE exp_wiki_categories SET parent_id = '0' WHERE parent_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
					}
				}
				elseif ($this->EE->input->get_post('delete_article') == 'y')
				{
					$this->redirect('', $this->title);
				}
				
				if ($this->EE->input->get_post('lock_article') == 'y')
				{
					$data['page_locked'] = 'y';
				}
				
				if ($this->EE->input->get_post('moderate_article') == 'y')
				{
					$data['page_moderated'] = 'y';
				}
			}
			
			if ($this->EE->input->get_post('redirect') !== FALSE)
			{
				$data['page_redirect'] = $this->valid_title($this->EE->input->get_post('redirect'));
			}
			
			$data['last_updated'] = $this->EE->localize->now;
		
			$this->EE->db->query($this->EE->db->insert_string('exp_wiki_page', $data));
			
			$page_id = $this->EE->db->insert_id();
		}
		else
		{
			$page_id = $query->row('page_id') ;
			
			if ($this->EE->input->get_post('delete_article') == 'y' && in_array($this->EE->session->userdata['group_id'], $this->admins))
			{	
				if ($this->current_namespace == $this->category_ns)
				{
					$cat_data = $this->determine_category($this->topic);

					if ($cat_data['cat_id'] != 0)
					{
						$results = $this->EE->db->query("SELECT page_id FROM exp_wiki_category_articles WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						
						if ($results->num_rows() > 0)
						{
							foreach($results->result_array() as $row)
							{
								$count = $this->EE->db->query("SELECT (COUNT(*) - 1) AS count FROM exp_wiki_category_articles WHERE page_id = '".$this->EE->db->escape_str($row['page_id'])."'");
								
								if ($count->row('count')  == 0)
								{
									$this->EE->db->query("UPDATE exp_wiki_page SET has_categories = 'n' WHERE page_id = '".$this->EE->db->escape_str($row['page_id'])."'");
								}
							}
						}
					
						$this->EE->db->query("DELETE FROM exp_wiki_category_articles WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						$this->EE->db->query("DELETE FROM exp_wiki_categories WHERE cat_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
						$this->EE->db->query("UPDATE exp_wiki_categories SET parent_id = '0' WHERE parent_id = '".$this->EE->db->escape_str($cat_data['cat_id'])."'");
					}
				}
			
				$this->EE->db->query("DELETE FROM exp_wiki_page WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
				$this->EE->db->query("DELETE FROM exp_wiki_revisions WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
				$this->EE->db->query("DELETE FROM exp_wiki_category_articles WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
		
				$this->redirect('', $this->title);
			}
			
			if ($query->row('page_locked')  == 'y' && ! in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				return $this->EE->output->show_user_error('general', array($this->EE->lang->line('invalid_permissions')));
			}
			
			if ($query->row('page_moderated')  == 'y' && ! in_array($this->EE->session->userdata['group_id'], $this->admins))
			{
				$data = array('last_updated' => $query->row('last_updated') );
			}
			else
			{
				$data = array('last_updated' => $this->EE->localize->now);
			}
			
			if ($this->EE->input->get_post('redirect') !== FALSE)
			{
				$data['page_redirect'] = $this->valid_title($this->EE->input->get_post('redirect'));
			}
			
			if (in_array($this->EE->session->userdata['group_id'], $this->admins))
			{	
				$data['page_locked'] = ($this->EE->input->get_post('lock_article') == 'y') ? 'y' : 'n';
				$data['page_moderated'] = ($this->EE->input->get_post('moderate_article') == 'y') ? 'y' : 'n';
				
				if ($this->EE->input->get_post('rename') !== FALSE && $this->EE->input->get_post('rename') != '')
				{
					// Default
					$this->topic			 = $this->valid_title($this->EE->input->get_post('rename'));
					$this->title			 = $this->topic;
					$this->current_namespace = '';
					$data['page_name']		 = $this->topic;
					$data['page_namespace']  = '';
								
					if (stristr($this->EE->input->get_post('rename'), ':') && count($this->namespaces) > 0)
					{
						$parts = explode(':', $this->EE->input->get_post('rename'), 2);
						
						foreach($this->namespaces as $name => $label)
						{
							if ($label['1'] == $parts['0'])
							{
								$data['page_namespace']  = $name;
								$data['page_name'] = $this->valid_title(substr($this->EE->input->get_post('rename'), strlen($label['1'].':')));
								$this->title			 = $label['1'].':'.$data['page_name'];
								$this->topic			 = $data['page_name'];
								$this->current_namespace = $label['1'];
								break;	
							}
						}
					}
					
					$t_query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_wiki_page WHERE page_name = '".$this->EE->db->escape_str($data['page_name'])."' AND LOWER(page_namespace) = '".$this->EE->db->escape_str($data['page_namespace'])."'");

					if ($t_query->row('count')  > 0)
					{
						return $this->EE->output->show_user_error('general', array($this->EE->lang->line('duplicate_article')));
					}
				}
			}
			
			$this->EE->db->query($this->EE->db->update_string('exp_wiki_page', $data, "page_id = '".$this->EE->db->escape_str($page_id)."'"));
		}
		
		/** -------------------------------------
		/**  Process Revision a Bit and Insert
		/** -------------------------------------*/
		
		if (isset($data['page_redirect']) && preg_match("|\#REDIRECT \[\[.*?\]\]|s", $this->EE->input->get_post('article_content'), $match))
		{
			$content = str_replace($match['0'], '', $this->EE->input->get_post('article_content'));
		}
		else
		{
			$content = $this->EE->input->get_post('article_content');
		}
		
		$revision = array(	'page_id'			=> $page_id,
							'wiki_id'			=> $this->wiki_id,
							'revision_date'		=> $this->EE->localize->now,
							'revision_author'	=> $this->EE->session->userdata['member_id'],
							'revision_notes'	=> ($this->EE->input->get_post('revision_notes') !== FALSE) ? $this->EE->input->get_post('revision_notes') : '',
							'page_content'		=> $this->EE->security->xss_clean($content)
						  );
		
		if ($query->num_rows() > 0 && $query->row('page_moderated')  == 'y' && ! in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$revision['revision_status'] = 'closed';
		}
		else
		{
			$revision['revision_status'] = 'open';
		}
						  
		$this->EE->db->query($this->EE->db->insert_string('exp_wiki_revisions', $revision));
		
		$revision['revision_id'] = $this->EE->db->insert_id();
				
		/** -------------------------------------
		/**  Check and Add Categories - But Not For Categories Namespace
		/** -------------------------------------*/
		
		if ($revision['revision_status'] == 'open')
		{
			$cats = $this->check_categories($page_id, $revision['page_content'], $this->current_namespace);
		}
		
		/** ---------------------------------------
		/**  Update last_revision_id
		/** ---------------------------------------*/
		
		$this->EE->db->query($this->EE->db->update_string('exp_wiki_page', array('last_revision_id' => $revision['revision_id']), array('page_id' => $page_id)));
		
		/** -------------------------------------
		/**  Moderator Notifications?
		/** -------------------------------------*/
						  
		if ($revision['revision_status'] == 'closed' && trim($this->moderation_emails) != '')
		{
			/** ----------------------------
			/**  Send Emails to Moderators
			/** ----------------------------*/
			
			$replyto = ($this->EE->session->userdata['email'] == '') ? $this->EE->config->item('webmaster_email') : $this->EE->session->userdata['email'];
			
			$link = $this->create_url($this->current_namespace, $this->topic);
			
			$revision['author']				 = $this->EE->session->userdata['screen_name'];
			$revision['email']				 = $this->EE->session->userdata['email'];
			$revision['title']				 = $this->title;
			$revision['content']			 = $this->EE->security->xss_clean($content);
			$revision['path:view_article']	 = $link;
			$revision['path:view_revision']	 = $link.'/revision/'.$revision['revision_id'];
			$revision['path:open_revision']	 = $link.'/revision/'.$revision['revision_id'].'/open';
			$revision['path:close_revision'] = $link.'/revision/'.$revision['revision_id'].'/close';
			
			$this->EE->load->library('typography');
			$this->EE->typography->initialize(array(
						'parse_images'	=> FALSE,
						'parse_smileys'	=> FALSE)
						);
			
			$revision['article'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($this->EE->security->xss_clean($content)), 
																  array(
																		'text_format'	=> $this->text_format,
																		'html_format'	=> $this->html_format,
																		'auto_links'	=> $this->auto_links,
																		'allow_img_url' => 'y'
																	  )
																));
			
			$subject = $this->EE->functions->var_swap($this->_fetch_template('wiki_email_moderation_subject.html'), $revision);
			$message = $this->EE->functions->var_swap($this->_fetch_template('wiki_email_moderation_message.html'), $revision);
				 
			$this->EE->load->library('email');

			// Load the text helper
			$this->EE->load->helper('text');
			
			$sent = array();
			
			foreach (explode(',', $this->moderation_emails) as $addy)
			{
				if (in_array($addy, $sent))
				{	
					continue;
				}
				
				$this->EE->email->EE_initialize();	
				$this->EE->email->wordwrap = false;
				$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));	
				$this->EE->email->to($addy); 
				$this->EE->email->reply_to($replyto);
				$this->EE->email->subject($subject);	
				$this->EE->email->message(entities_to_ascii($message));		
				$this->EE->email->send();
				
				$sent[] = $addy;
			}
		}
		
		/* -------------------------------------
		/*  'edit_wiki_article_end' hook.
		/*  - Add more things to do for wiki articles
		/*  - Added 1.6.0
		*/  
			$edata = $this->EE->extensions->universal_call('edit_wiki_article_end', $this, $query);
			if ($this->EE->extensions->end_script === TRUE) return;
		/*
		/* -------------------------------------*/
		
		$query = $this->EE->db->query("SELECT COUNT(revision_id) AS count FROM exp_wiki_revisions 
							 WHERE page_id = '".$this->EE->db->escape_str($page_id)."'
							 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");
		
		if ($query->row('count')  > $this->revision_limit)
		{
			$query = $this->EE->db->query("SELECT revision_id FROM exp_wiki_revisions 
								 WHERE page_id = '".$this->EE->db->escape_str($page_id)."' 
								 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								 LIMIT $this->revision_limit, 1");
			
			if ($query->num_rows() > 0)
			{
				$this->EE->db->query("DELETE FROM exp_wiki_revisions 
							WHERE page_id = '".$this->EE->db->escape_str($page_id)."'
							AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
							AND revision_id < '".$query->row('revision_id') ."'");
			}
		}
		
		// Clear wiki cache
		$this->EE->functions->clear_caching('db');

		$this->redirect($this->current_namespace, $this->topic);
	}

	
	
	/* -------------------------------------
	/*  Check String for Category Tags.
	/*	- If category does not exist create
	/*  - Insert Found Categories into exp_wiki_category_articles table
	/* -------------------------------------*/  
	
	function check_categories($page_id, $str, $namespace='')
	{
		$all_cats	= array();
		$cats_found	= array();
		
		$str = preg_replace("/\[code\](.+?)\[\/code\]/si", '', $str);

		// Old preg_match_all before we added support for alternate text links, e.g. [[Category:Foo | Bar]]
    	//if (preg_match_all("|\[\[".preg_quote($this->category_ns)."(ID)*\:([^\|])*?.*?\]\]|", $str, $matches))
		if (preg_match_all("/\[\[Category(ID)*\:([^\||\]]*)/", $str, $matches))		
		{
			if ($this->cats_use_namespaces == 'n')
			{
				$namespace = '';
			}
		
			for($i=0, $s = count($matches['0']); $i < $s; ++$i)
			{
				/* -------------------------------------
				/*  Takes the Categories from the last loop and adds them
				/*  to those we are inserting.  Because of the nesting, we
				/*  do it this way so that we do not have the exact same code
				/*  many many times throughout the loop
				/* -------------------------------------*/ 
				
				if (count($cats_found) > 0)
				{
					if ($this->cats_assign_parents == 'n')
					{
						$all_cats[] = array_pop($cats_found);
					}
					else
					{
						$all_cats = array_merge($all_cats, $cats_found);
					}
				}
			
				$cats_found = array();
				
				// Let's trim it as | can result in trailing space
				$matches['2'][$i] = trim($matches['2'][$i]);
				
				if ($matches['2'][$i] == '')
				{
					continue;
				}
				
				/** -------------------------------------
				/**  Category ID specified directly
				/** -------------------------------------*/
				
				if ($matches['1'][$i] != '')
				{
					$query = $this->EE->db->query("SELECT cat_id
									 	FROM exp_wiki_categories
									 	WHERE cat_id = '".$this->EE->db->escape_str($matches['2'][$i])."'
									 	AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");
									 	
					if ($query->num_rows() > 0)
					{
						$cats_found[] = $query->row('cat_id') ;
					}
					
					continue;
				}
				
				/** -------------------------------------
				/**  Check for Nested Categories
				/** -------------------------------------*/
				
				if (stristr($matches['2'][$i], $this->cats_separator))
				{
					$cats = explode($this->cats_separator, 
									preg_replace("/".preg_quote($this->cats_separator.$this->cats_separator)."+/", 
												 $this->cats_separator, 
												 $matches['2'][$i]));
				}
				else
				{
					$cats = array($matches['2'][$i]);
				}
				
				/* -----------------------------------------------
				/*  Check for Parent Category
				/*  - If the parent category DOES NOT exist, then we are 
				/*  starting a new branch of category so they are all new.
				/*  - If the parent category DOES exist, then we have to
				/*  cycle throw the kids to see if they exist or not too.
				/* ----------------------------------------------*/ 
				
				$query = $this->EE->db->query("SELECT cat_id
									 FROM exp_wiki_categories
									 WHERE cat_name = '".$this->EE->db->escape_str($this->valid_title($cats['0']))."'
									 AND parent_id = '0' 
									 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."' 
									 LIMIT 1");
									 
				if ($query->num_rows() == 0)
				{
					$data = array(	'cat_name'		=> $this->valid_title($cats['0']),
									'parent_id'		=> 0,
									'wiki_id'		=> $this->wiki_id,
									'cat_namespace'	=> '');
									
					$this->EE->db->query($this->EE->db->insert_string('exp_wiki_categories', $data));
					$parent_cat = $this->EE->db->insert_id();
					$cats_found[] = $parent_cat;
					
					if (count($cats) > 1)
					{
						array_shift($cats);
						
						foreach($cats as $cat)
						{
							if (trim($cat) == '')
							{	
								continue(2);
							}
							
							$data['cat_name']		= $this->valid_title($cat);
							$data['parent_id']		= $parent_cat;
							$data['wiki_id']		= $this->wiki_id;
							$data['cat_namespace']	= '';
									
							$this->EE->db->query($this->EE->db->insert_string('exp_wiki_categories', $data));
							
							$parent_cat = $this->EE->db->insert_id();
							$cats_found[] = $parent_cat;
						}
					}
				}
				elseif (count($cats) == 1)
				{
					$parent_cat = $query->row('cat_id') ;
					$cats_found[] = $parent_cat;
				}
				elseif (count($cats) > 1)
				{
					array_shift($cats);
					$parent_cat = $query->row('cat_id') ;
					$cats_found[] = $parent_cat;
					
					foreach($cats as $cat)
					{
						if (trim($cat) == '')
						{
							continue(2);
						}
					
						$query = $this->EE->db->query("SELECT cat_id
											 FROM exp_wiki_categories
											 WHERE cat_name = '".$this->EE->db->escape_str($this->valid_title($cat))."'
											 AND parent_id = '".$this->EE->db->escape_str($parent_cat)."'
											 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
											 LIMIT 1");
											 
						if ($query->num_rows() > 0)
						{
							$parent_cat = $query->row('cat_id') ;
							$cats_found[] = $parent_cat;
							continue;
						}
						
						$data['cat_name']		= $this->valid_title($cat);
						$data['parent_id']		= $parent_cat;
						$data['wiki_id']		= $this->wiki_id;
						$data['cat_namespace']	= '';
									
						$this->EE->db->query($this->EE->db->insert_string('exp_wiki_categories', $data));
							
						$parent_cat = $this->EE->db->insert_id();
						$cats_found[] = $parent_cat;
					}
				}
	 		}
		}
		
		/* -------------------------------------
		/*  Takes the Categories from the final loop and adds them
		/*  to those we are inserting.
		/* -------------------------------------*/ 
		
		if (count($cats_found) > 0)
		{
			if ($this->cats_assign_parents == 'n')
			{
				$all_cats[] = array_pop($cats_found);
			}
			else
			{
				$all_cats = array_merge($all_cats, $cats_found);
			}
		}
		
		/** -------------------------------------
		/**  Insert Fresh Categories!
		/** -------------------------------------*/
		
		$this->EE->db->query("DELETE FROM exp_wiki_category_articles WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
		
		if (count($all_cats) > 0)
		{
			$cats_insert = '';
			
			foreach(array_unique($all_cats) as $cat_id)
			{
				$cats_insert .= "('{$page_id}', '{$cat_id}'),";
			}
		
			$this->EE->db->query("INSERT INTO exp_wiki_category_articles (page_id, cat_id) VALUES ".substr($cats_insert,0,-1));
			$this->EE->db->query("UPDATE exp_wiki_page SET has_categories = 'y' WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
		}
		else
		{
			$this->EE->db->query("UPDATE exp_wiki_page SET has_categories = 'n' WHERE page_id = '".$this->EE->db->escape_str($page_id)."'");
		}
		
		return $all_cats;
	}

	
	
	/** -------------------------------------
	/**  Prep Conditionals
	/** -------------------------------------*/
	function prep_conditionals($str, $data)
	{
		if (count($data) == 0) return $str;
		
		$cleaned = array();
		
		foreach($data as $key => $value)
		{
			$cleaned[str_replace(array(RD,LD), '', $key)] = $value;
		}
		
		return $this->EE->functions->prep_conditionals($str, $cleaned);
	}
 

	/** -------------------------------------
	/**  New Page Creator
	/** -------------------------------------*/
	function find_page()
	{
  		// Secure Forms check
	  	// If the hash is not found we'll simply reload the page.
		if ($this->EE->security->secure_forms_check($this->EE->input->post('XID')) == FALSE)
		{
			$this->redirect('', 'index');
		}	  

		if ($this->EE->input->post('title') !== FALSE && $this->EE->input->get_post('title') != '')
		{
			$title = $this->valid_title($this->EE->security->xss_clean(strip_tags($this->EE->input->post('title'))));
			
			$this->redirect('', $title);
		}
		else
		{
			$this->redirect('', 'index');
		}
		
		exit;
	}

	
	
	/** -------------------------------------
	/**  Random Page Redirect
	/** -------------------------------------*/
	function random_page()
	{
		/* 
		Had a bit of an ongoing debate on whether to include redirected
		pages or not in the random page function.  Ultimately, I decided
		not to because it will really just be displaying the same exact
		content and I think this feature is more about showing content that
		is "new" to the user. -Paul 
		*/
		
		$query = $this->EE->db->query("SELECT page_name, page_namespace
							 FROM exp_wiki_page
						 	 WHERE (page_redirect = '' OR page_redirect IS NULL)
							 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
							 ORDER BY rand() LIMIT 1");

		$this->redirect($this->namespace_label($query->row('page_namespace') ), $query->row('page_name') );
	}


	/** -------------------------------------
	/**  Search Some Content!
	/** -------------------------------------*/
	function search_results($keywords='')
	{
		/** ----------------------------------------
		/**  Check for Pagination
		/** ----------------------------------------*/
		
		$search_paginate = FALSE;
		
		if ($this->EE->input->get_post('keywords') === FALSE && $keywords == '')
		{
			if ( ! isset($this->seg_parts['1']) OR strlen($this->seg_parts['1']) < 20)
			{
				return $this->return_data = '';
			}
								
			$this->EE->db->where('wiki_search_id', $this->seg_parts['1']);
			$query = $this->EE->db->get('wiki_search');
								 
			if ($query->num_rows() > 0)
			{
				$search_paginate = TRUE;
				$paginate_sql	 = $query->row('wiki_search_query') ;
				$paginate_hash	 = $query->row('wiki_search_id') ; 
				$keywords		 = $query->row('wiki_search_keywords') ;
			}
		}
		
		/** ----------------------------------------
		/**  Work Up the Keywords A Bit, Know What I'm Saying?
		/** ----------------------------------------*/
		
		$keywords = ($this->EE->input->get_post('keywords') !== FALSE) ? $this->EE->input->get_post('keywords') : $keywords;
		
		// Load the search helper so we can filter the keywords
		$this->EE->load->helper('search');
		
		$keywords = $this->EE->functions->encode_ee_tags(sanitize_search_terms($keywords), TRUE);
		
		if ($keywords == '')
		{
			$this->redirect('', 'index');
		}
		elseif(strlen($keywords) < $this->min_length_keywords)
		{
			return $this->EE->output->show_user_error('general', array(str_replace("%x", $this->min_length_keywords, $this->EE->lang->line('search_min_length'))));
		}
		
		$this->return_data = str_replace(array('{wiki:page}', '{keywords}'), 
										 array($this->_fetch_template('wiki_special_search_results.html'), stripslashes($keywords)), 
										 $this->return_data);
		
		/** ----------------------------------------
		/**  Parse Results Tag Pair
		/** ----------------------------------------*/
		
		if ( ! preg_match("/\{wiki:search_results(.*?)\}(.*?)\{\/wiki:search_results\}/s", $this->return_data, $match))
		{
			return $this->return_data = '';
		}
		
		/** ----------------------------------------
		/**  Parameters
		/** ----------------------------------------*/
		
		$parameters['limit']	= 20;
		$parameters['switch1']	= '';
		$parameters['switch2']	= '';
		$parameters['paginate']	= 'bottom';
		
		if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
		{
			$parameters['limit'] = (isset($params['limit']) && is_numeric($params['limit'])) ? $params['limit'] : $parameters['limit'];
			$parameters['paginate']	= (isset($params['paginate'])) ? $params['paginate'] : $parameters['paginate'];
			
			if (isset($params['switch']))
			{
				if (strpos($params['switch'], '|') !== FALSE)
				{
					$x = explode("|", $params['switch']);
					
					$parameters['switch1'] = $x['0'];
					$parameters['switch2'] = $x['1'];
				}
				else
				{
					$parameters['switch1'] = $params['switch'];
				}
			}	
		}
		
		
		/* ----------------------------------------
		/*  Date Formats
		/*	- Those GMT dates are not typical for results, but I thought it might 
		/*  be the case that there will be dynamic RSS/Atom searches in the 
		/*  future so I added them just in case.
		/* ----------------------------------------*/
		
		$dates = $this->parse_dates($this->return_data);
		
		// Secure Forms check
	  	// If the hash is not found we'll simply reload the page.
	  
		if ($this->EE->config->item('secure_forms') == 'y' && $search_paginate === FALSE)
		{
			if ($this->EE->security->secure_forms_check($this->EE->input->post('XID')) == FALSE)
			{
				$this->redirect('', $this->EE->input->get_post('title'));
			}
		}
		
		/** ----------------------------------------
		/**  Our Query
		/** ----------------------------------------*/
		
		if ($search_paginate === TRUE)
		{
			$sql = $paginate_sql;
		}
		else
		{
			$sql =	"FROM exp_wiki_revisions r, exp_members m, exp_wiki_page p
					 WHERE p.page_id = r.page_id
					 AND p.last_updated = r.revision_date
					 AND p.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
					 AND (";
		
			/** -------------------------------------
			/**  Get our keywords into search terms
			/** -------------------------------------*/
			
			$terms = array();
			$keywords = stripslashes($keywords);
			$nsql = '';
			
			if (stristr(strtolower($keywords), 'namespace:'))
			{
				$namespaces = array('Category' => 'category');

				$nquery = $this->EE->db->query("SELECT namespace_label, namespace_name FROM exp_wiki_namespaces");

				if ($nquery->num_rows() > 0)
				{
					foreach ($nquery->result_array() as $row)
					{
						$namespaces[$row['namespace_label']] = $row['namespace_name'];
					}
				}

				foreach ($namespaces as $key => $val)
				{
					if (preg_match("/namespace:\s*(\-)*\s*[\'\"]?(".preg_quote($key, '/').")[\'\"]?/", $keywords, $nmatch))
					{
						$keywords = str_replace($nmatch['0'], '', $keywords);
						
						$compare = ($nmatch['1'] == "-") ? '!=' : '=';
						$nsql = "AND p.page_namespace {$compare} '".$namespaces[$nmatch['2']]."' \n";
					}
				}				
			}
			
			// in case they searched with only "namespace:namespace_label" and no keywords
			if (trim($keywords) == '')
			{
				return $this->EE->output->show_user_error('general', array($this->EE->lang->line('no_search_terms')));				
			}
			
			if (preg_match_all("/\-*\"(.*?)\"/", $keywords, $matches))
			{
				for($m=0; $m < count($matches['1']); $m++)
				{
					$terms[] = trim(str_replace('"','',$matches['0'][$m]));
					$keywords = str_replace($matches['0'][$m],'', $keywords);
				}	
			}

			if (trim($keywords) != '')
			{
				$terms = array_merge($terms, preg_split("/\s+/", trim($keywords)));
  			}
  			
  			$not_and = (count($terms) > 2) ? ') AND (' : 'AND';
  			rsort($terms);
			
			/** -------------------------------------
			/**  Log Search Terms
			/** -------------------------------------*/
			
			$this->EE->functions->log_search_terms(implode(' ', $terms), 'wiki');
			
			/** -------------------------------------
			/**  Search in content and article title
			/** -------------------------------------*/
			$mysql_function	= (substr($terms['0'], 0,1) == '-') ? 'NOT LIKE' : 'LIKE';	
			$search_term	= (substr($terms['0'], 0,1) == '-') ? substr($terms['0'], 1) : $terms['0'];
			$connect		= ($mysql_function == 'LIKE') ? 'OR' : 'AND';

			$sql .= "\n(r.page_content {$mysql_function} '%".$this->EE->db->escape_like_str($search_term)."%' ";
			$sql .= "{$connect} p.page_name {$mysql_function} '%".$this->EE->db->escape_like_str($search_term)."%') ";

			for ($i=1; $i < count($terms); $i++) 
			{
				$mysql_criteria	= ($mysql_function == 'NOT LIKE' OR substr($terms[$i], 0,1) == '-') ? $not_and : 'AND';
				$mysql_function	= (substr($terms[$i], 0,1) == '-') ? 'NOT LIKE' : 'LIKE';
				$search_term	= (substr($terms[$i], 0,1) == '-') ? substr($terms[$i], 1) : $terms[$i];
				$connect		= ($mysql_function == 'LIKE') ? 'OR' : 'AND';
				
				$sql .= "{$mysql_criteria} (r.page_content {$mysql_function} '%".$this->EE->db->escape_like_str($search_term)."%' ";
				$sql .= "{$connect} p.page_name {$mysql_function} '%".$this->EE->db->escape_like_str($search_term)."%') ";
			}
			
			// close it up, and add our namespace clause
			$sql .= "\n) \n{$nsql}";

			$sql .= "AND m.member_id = r.revision_author
					 AND r.revision_status = 'open'
					 ORDER BY r.revision_date";
		}

		$query = $this->EE->db->query("SELECT COUNT(*) AS count ".$sql);
								
		if ($query->row('count')  == 0)
		{
			$this->return_data = $this->_deny_if('results', $this->return_data);
			$this->return_data = $this->_allow_if('no_results', $this->return_data);
			$this->return_data = str_replace($match['0'], '', $this->return_data);
			return;
		}
		else
		{
			$this->return_data = $this->_allow_if('results', $this->return_data);
			$this->return_data = $this->_deny_if('no_results', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Store Pagination Hash and Query and do Garbage Collection
		/** ----------------------------------------*/
		
		if ($query->row('count')  > $parameters['limit'] && $search_paginate === FALSE)
		{
			$paginate_hash = $this->EE->functions->random('md5');
			$search_data = array('wiki_search_id' => $paginate_hash, 'search_date' => time(), 'wiki_search_query' => $sql, 'wiki_search_keywords' => $keywords);
			
			$this->EE->db->insert('wiki_search', $search_data);

			// Clear old search results
			$expire = time() - ($this->cache_expire * 3600);
			
			$this->EE->db->where('search_date <', $expire);
			$this->EE->db->delete('wiki_search');			
		}
		
		$base_paginate = $this->base_url.$this->special_ns.':Search_results/';
		
		if (isset($paginate_hash))
		{
			$base_paginate .= $paginate_hash.'/';
		}
		
		$this->pagination($query->row('count') , $parameters['limit'], $base_paginate);
		
		/** ----------------------------------------
		/**  Rerun Query This Time With Our Data
		/** ----------------------------------------*/
		
		if ($this->paginate === TRUE)
		{
			// Now that the Paginate code is removed, we run this again
			preg_match("/\{wiki:search_results(.*?)\}(.*?)\{\/wiki:search_results\}/s", $this->return_data, $match);
		}
		else
		{
			$this->pagination_sql .= " LIMIT ".$parameters['limit'];
		}
		
		$query = $this->EE->db->query("SELECT r.*, m.member_id, m.screen_name, m.email, m.url, p.page_namespace, p.page_name AS topic ".$sql.$this->pagination_sql);
		
		/** ----------------------------------------
		/**  Global Last Updated
		/** ----------------------------------------*/
		
		if (isset($dates['last_updated']))
		{
			foreach($dates['last_updated'] as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $results->row('revision_date') , TRUE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
		
		if (isset($dates['gmt_last_updated']))
		{
			foreach($dates['gmt_last_updated'] as $key => $value)
			{
				$temp_date = $value['0'];
						
				foreach ($value['1'] as $dvar)
				{
					$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $results->row('revision_date') , FALSE), $temp_date);		
				}
							
				$this->return_data = str_replace($key, $temp_date, $this->return_data);
			}
		}
		
		/** ----------------------------------------
		/**  Parsing of the Results
		/** ----------------------------------------*/
		
		$results = $this->parse_results($match, $query, $parameters, $dates);
		
		$this->return_data = str_replace($match['0'], $results, $this->return_data);
	}

	
	/* ----------------------------------------
	/*  Parsing of the Results
	/*  - Use for Search and Category Page Articles
	/* ----------------------------------------*/
	
	function parse_results($match, $query, $parameters, $dates)
	{
		if (preg_match("|".LD."letter_header".RD."(.*?)".LD."\/letter_header".RD."|s",$match['2'], $block))
		{
			$letter_header = $block['1'];
			$match['2'] = str_replace($block['0'],'', $match['2']);
		}

		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$results = '';
		$i = 0;
		$last_letter = '';
		$count = 0;
		
		// added in 1.6 for {switch} variable and for future use
		$vars = $this->EE->functions->assign_variables($match['2']);
		$this->EE->load->helper('url');
		
		foreach($query->result_array() as $row)
		{
			$temp = $match['2'];
			$count++;
			
			$title	= ($row['page_namespace'] != '') ? $this->namespace_label($row['page_namespace']).':'.$row['topic'] : $row['topic'];
			$link	= $this->create_url($this->namespace_label($row['page_namespace']), $row['topic']);
			
			$data = array(	'{title}'				=> $this->prep_title($title),
							'{revision_id}'			=> $row['revision_id'],
							'{page_id}'				=> $row['page_id'],
							'{author}'				=> $row['screen_name'],
							'{path:author_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{email}'				=> $this->EE->typography->encode_email($row['email']),
							'{url}'					=> prep_url($row['url']),
							'{revision_notes}'		=> $row['revision_notes'],
							'{path:view_article}'	=> $link,
							'{content}'				=> $row['page_content'],
							'{count}'				=> $count);
			
			if (isset($parameters['switch1']))
			{
				$data['{switch}'] = ($i++ % 2) ? $parameters['switch1'] : $parameters['switch2'];
			}

			if (isset($letter_header))
			{	
				$this_letter = (function_exists('mb_strtoupper')) ? mb_strtoupper(substr($row['topic'], 0, 1), $this->EE->config->item('charset')) : strtoupper(substr($row['topic'], 0));
				
				if ($last_letter != $this_letter)
				{
					$temp = str_replace('{letter}', $this_letter, $letter_header).$temp;
					$last_letter = $this_letter;
				}
			}
			
			if (strpos($temp, '{article}') !== FALSE)
			{
				$data['{article}'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($row['page_content']), 
																  array(
																		'text_format'	=> $this->text_format,
																		'html_format'	=> $this->html_format,
																		'auto_links'	=> $this->auto_links,
																		'allow_img_url' => 'y'
																	  )
																));
			}
			
			if (strpos($temp, '{excerpt}') !== FALSE)
			{
				if ( ! isset($data['{article}']))
				{
					$data['{article}'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($row['page_content']), 
																	  array(
																			'text_format'	=> $this->text_format,
																			'html_format'	=> $this->html_format,
																			'auto_links'	=> $this->auto_links,
																			'allow_img_url' => 'y'
																		  )
																	));
				}
				
				$excerpt = trim(strip_tags($data['{article}']));
				
				if (strpos($excerpt, "\r") !== FALSE OR strpos($excerpt, "\n") !== FALSE)
				{
					$excerpt = str_replace(array("\r\n", "\r", "\n"), " ", $excerpt);
				}
				
				$data['{excerpt}'] = $this->EE->functions->word_limiter($excerpt, 50);
			}
			
			$temp = $this->prep_conditionals($temp, array_merge($data, $this->conditionals));
			
			if (isset($dates['revision_date']))
			{
				foreach($dates['revision_date'] as $key => $value)
				{
					$temp_date = $value['0'];
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], TRUE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}
			
			if (isset($dates['gmt_revision_date']))
			{
				foreach($dates['gmt_revision_date'] as $key => $value)
				{
					$temp_date = $value['0'];
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['revision_date'], FALSE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}
			
			foreach ($vars['var_single'] as $key => $val)
			{
				/** ----------------------------------------
				/**  parse {switch} variable
				/** ----------------------------------------*/
				if (preg_match("/^switch\s*=.+/i", $key))
				{
					$sparam = $this->EE->functions->assign_parameters($key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
				}
				
				if ($key == 'absolute_count')
				{
					$temp = $this->EE->TMPL->swap_var_single($key, $count + ($this->current_page * $parameters['limit']) - $parameters['limit'], $temp);
				}
			}
			
			$results .= str_replace(array_keys($data), array_values($data), $temp);
		}

		/** ----------------------------------------
		/**  Pagination
		/** ----------------------------------------*/

		if ($this->paginate === TRUE)
		{
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);
			
			if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_previous == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			 }
			
			
			if (preg_match("/".LD."if next_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_next == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			}
				
			switch ($parameters['paginate'])
			{
				case "top"	: $results  = $this->paginate_data.$results;
					break;
				case "both"	: $results  = $this->paginate_data.$results.$this->paginate_data;
					break;
				default		: $results .= $this->paginate_data;
					break;
			}
		}
		
		return $results;
	}



	/** ----------------------------------------
	/**  List Of Files
	/** ----------------------------------------*/
	
	function files()
	{
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_files.html'), $this->return_data);
		
		/** ----------------------------------------
		/**  Parse Results Tag Pair
		/** ----------------------------------------*/
		
		if ( ! preg_match("/\{wiki:files(.*?)\}(.*?)\{\/wiki:files\}/s", $this->return_data, $match))
		{
			return $this->return_data = '';
		}
		
		/** ----------------------------------------
		/**  Parameters
		/** ----------------------------------------*/
		
		$limit = 20;
		$paginate = 'bottom';
		$orderby = 'file_name';
		$sort = 'asc';
		$switch1 = '';
		$switch2 = '';
		
		if (trim($match['1']) != '' && ($params = $this->EE->functions->assign_parameters($match['1'])) !== FALSE)
		{
			$limit = (isset($params['limit']) && is_numeric($params['limit'])) ? $params['limit'] : $limit;
			$paginate = (isset($params['paginate']) && is_numeric($params['paginate'])) ? $params['paginate'] : $paginate;
			$orderby = (isset($params['orderby'])) ? $params['orderby'] : $orderby;
			$sort = (isset($params['sort'])) ? $params['sort'] : $sort;
			
			if (isset($params['switch']))
			{
				if (strpos($params['switch'], '|') !== FALSE)
				{
					$x = explode("|", $params['switch']);
					
					$switch1 = $x['0'];
					$switch2 = $x['1'];
				}
				else
				{
					$switch1 = $params['switch'];
				}
			}	
		}
		
		/** ----------------------------------------
		/**  Pagination
		/** ----------------------------------------*/
		
		$sql =	"SELECT u.*, 
				 m.member_id, m.screen_name, m.email, m.url
				 FROM exp_wiki_uploads u, exp_members m
				 WHERE m.member_id = u.upload_author
				 AND u.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
				 ORDER BY u.{$orderby} {$sort}
				 LIMIT {$limit}";
		
		if (stristr($this->return_data, 'paginate}'))
		{
			$query = $this->EE->db->query("SELECT COUNT(*) AS count FROM exp_wiki_uploads WHERE wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");
			
			$this->pagination($query->row('count') , $limit, $this->base_url.$this->special_ns.':Files/');
			
			if ($this->paginate === TRUE)
			{
				// Not that the Paginate code is removed, we run this again
				preg_match("/\{wiki:files(.*?)\}(.*?)\{\/wiki:files\}/s", $this->return_data, $match);
				
				$sql =	"SELECT u.*, 
						 m.member_id, m.screen_name, m.email, m.url
						 FROM exp_wiki_uploads u, exp_members m
						 WHERE m.member_id = u.upload_author
						 AND u.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
						 ORDER BY u.{$orderby} {$sort} ".$this->pagination_sql;
			}
		}
		
		/** ----------------------------------------
		/**  Date Formats
		/** ----------------------------------------*/
		
		if (preg_match_all("/".LD."(upload_date)\s+format=[\"'](.*?)[\"']".RD."/s", $this->return_data, $matches))
		{
			for ($j = 0; $j < count($matches['0']); $j++)
			{	
				switch ($matches['1'][$j])
				{
					case 'upload_date' 		: $upload_date[$matches['0'][$j]] = array($matches['2'][$j], $this->EE->localize->fetch_date_params($matches['2'][$j]));
						break;
				}
			}
		}
		
		/** ----------------------------------------
		/**  Our Query
		/** ----------------------------------------*/
		
		$query = $this->EE->db->query($sql);
								
		if ($query->num_rows() == 0)
		{
			$this->return_data = $this->_deny_if('files', $this->return_data);
			$this->return_data = $this->_allow_if('no_files', $this->return_data);
			$this->return_data = str_replace($match['0'], '', $this->return_data);
			return;
		}
		else
		{
			$this->return_data = $this->_allow_if('files', $this->return_data);
			$this->return_data = $this->_deny_if('no_files', $this->return_data);
		}
		
		/** ----------------------------------------
		/**  Display Some Files, Captain Proton!
		/** ----------------------------------------*/
		
		$this->EE->load->library('typography');
		$this->EE->typography->initialize(array(
				'parse_images'	=> FALSE,
				'parse_smileys'	=> FALSE)
				);
		
		$files = '';
		$count = 0;
		
		// added in 1.6 for {switch} variable and for future use
		$vars = $this->EE->functions->assign_variables($match['2']);
		$this->EE->load->helper('url');
		
		foreach($query->result_array() as $row)
		{
			$count++;
			$temp = $match['2'];
			
			$data = array(	'{file_name}'			=> $row['file_name'],
							'{path:view_file}'		=> $this->base_url.$this->file_ns.':'.$row['file_name'],
							'{file_type}'			=> $row['file_type'],
							'{author}'				=> $row['screen_name'],
							'{path:author_profile}'	=> $this->EE->functions->create_url($this->profile_path.$row['member_id']),
							'{email}'				=> $this->EE->typography->encode_email($row['email']),
							'{url}'					=> prep_url($row['url']),
							'{count}'				=> $count);
							
			$x = explode('/',$row['file_type']);
		
			if ($x['0'] == 'image')
			{
				$temp = $this->_allow_if('is_image', $temp);
			}
			else
			{
				$temp = $this->_deny_if('is_image', $temp);
			}
			
			$data['{summary}'] = $this->convert_curly_brackets($this->EE->typography->parse_type( $this->wiki_syntax($row['upload_summary']), 
															  array(
																	'text_format'	=> $this->text_format,
																	'html_format'	=> $this->html_format,
																	'auto_links'	=> $this->auto_links,
																	'allow_img_url' => 'y'
																  )
															));
							
			$temp = $this->prep_conditionals($temp, array_merge($data, $this->conditionals));
			
			if (isset($upload_date))
			{
				foreach($upload_date as $key => $value)
				{
					$temp_date = $value['0'];
							
					foreach ($value['1'] as $dvar)
					{
						$temp_date = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $row['upload_date'], FALSE), $temp_date);		
					}
								
					$data[$key] = $temp_date;
				}
			}
			
			foreach ($vars['var_single'] as $key => $val)
			{
				/** ----------------------------------------
				/**  parse {switch} variable
				/** ----------------------------------------*/
				if (preg_match("/^switch\s*=.+/i", $key))
				{
					$sparam = $this->EE->functions->assign_parameters($key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);

						$sw = $sopt[($count-1 + count($sopt)) % count($sopt)];
					}

					$temp = $this->EE->TMPL->swap_var_single($key, $sw, $temp);
				}
				
				if ($key == 'absolute_count')
				{
					$temp = $this->EE->TMPL->swap_var_single($key, $count + ($this->current_page * $parameters['limit']) - $parameters['limit'], $temp);
				}
			}
			
			$files .= str_replace(array_keys($data), array_values($data), $temp);
		}
		
		/** ----------------------------------------
		/**  Pagination - Files
		/** ----------------------------------------*/

		if ($this->paginate === TRUE)
		{
			$this->paginate_data = str_replace(LD.'current_page'.RD, $this->current_page, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'total_pages'.RD,	$this->total_pages, $this->paginate_data);
			$this->paginate_data = str_replace(LD.'pagination_links'.RD, $this->pagination_links, $this->paginate_data);
			
			if (preg_match("/".LD."if previous_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_previous == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if previous_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_previous, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_previous, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			 }
			
			
			if (preg_match("/".LD."if next_page".RD."(.+?)".LD.'\/'."if".RD."/s", $this->paginate_data, $matches))
			{
				if ($this->page_next == '')
				{
					 $this->paginate_data = preg_replace("/".LD."if next_page".RD.".+?".LD.'\/'."if".RD."/s", '', $this->paginate_data);
				}
				else
				{
					$matches['1'] = preg_replace("/".LD.'path.*?'.RD."/", 	$this->page_next, $matches['1']);
					$matches['1'] = preg_replace("/".LD.'auto_path'.RD."/",	$this->page_next, $matches['1']);
			
					$this->paginate_data = str_replace($matches['0'], $matches['1'], $this->paginate_data);
				}
			}
				
			switch ($paginate)
			{
				case "top"	: $files  = $this->paginate_data.$files;
					break;
				case "both"	: $files  = $this->paginate_data.$files.$this->paginate_data;
					break;
				default		: $files .= $this->paginate_data;
					break;
			}
		}
		
		$this->return_data = str_replace($match['0'], $files, $this->return_data);
	}

	
	
	/** ----------------------------------------
	/**  Upload Form
	/** ----------------------------------------*/
	
	function upload_form()
	{
		/** -------------------------------------
		/**  In the Beginning...
		/** -------------------------------------*/
		
		$this->return_data = str_replace('{wiki:page}', $this->_fetch_template('wiki_special_upload_form.html'), $this->return_data);
		
		if ($this->can_upload != 'y') 
		{
			return;
		}
		
		$this->EE->db->where('id', $this->upload_dir);
		$query = $this->EE->db->get('upload_prefs');
				
		/** -------------------------------------
		/**  Uploading
		/** -------------------------------------*/
		
		if ($this->EE->input->post('upload') == 'y')
		{
			if( ! in_array($this->EE->session->userdata('group_id'), $this->users) && ! in_array($this->EE->session->userdata('group_id'), $this->admins))
			{
				return FALSE;
			}
			
			$this->EE->lang->loadfile('upload');
		
			// Secure Forms
			
			if ($this->EE->config->item('secure_forms') == 'y')
			{
				$this->EE->db->select('COUNT(*) as count');
				$this->EE->db->where('ip_address', $this->EE->input->ip_address());
				$this->EE->db->where('date >', 'UNIX_TIMESTAMP()-7200');
				$results = $this->EE->db->get('security_hashes');				
			
				if ($results->row('count')  == 0)
				{
					$this->redirect($this->special_ns, 'Uploads');
				}	
			}
			
			/** -------------------------------------
			/**  Edit Limit
			/** -------------------------------------*/
			
			$this->edit_limit();
			
			/** -------------------------------------
			/**  Upload File
			/** -------------------------------------*/

			$filename = $this->EE->input->get_post('new_filename');

			if ($filename !== FALSE && $filename != '')
			{
				$new_name = $this->valid_title($this->EE->security->sanitize_filename(strip_tags($filename)));
			}
			elseif ( ! is_uploaded_file($_FILES['userfile']['tmp_name']))
			{
				$new_name = $this->valid_title($this->EE->security->sanitize_filename(strip_tags($_FILES['userfile']['name'])));
			}
						
			$server_path = $query->row('server_path');



			switch($query->row('allowed_types'))
			{
				case 'all' : $allowed_types = '*';
					break;
				case 'img' : $allowed_types = 'jpg|jpeg|png|gif';
					break;
				default :
					$allowed_types = $query->row('allowed_types');
			}

			// Upload the image
			$config = array(
					'file_name'		=> $new_name,
					'upload_path'	=> $server_path,
					'allowed_types'	=> $allowed_types,
					'max_size'		=> round($query->row('max_size')/1024, 2),
					'max_width'		=> $query->row('max_width'),
					'max_height'	=> $query->row('max_height'),
				);
			
			if ($this->EE->config->item('xss_clean_uploads') == 'n')
			{
				$config['xss_clean'] = FALSE;
			}
			else
			{
				$config['xss_clean'] = ($this->EE->session->userdata('group_id') == 1) ? FALSE : TRUE;
			}

			$this->EE->load->library('upload', $config);

			if (file_exists($server_path.$new_name))
			{
				return $this->EE->output->show_user_error('general', array(
								$this->EE->lang->line('file_exists')
					)
				);
			}

			if (strlen($new_name) > 60)
			{
				return $this->EE->output->show_user_error('general', array(
								$this->EE->lang->line('filename_too_long')
					)
				);
			}

			if ($this->EE->upload->do_upload() === FALSE)
			{
				return $this->EE->output->show_user_error('general', 
							array($this->EE->lang->line($this->EE->upload->display_errors())));
			}
			
			$file_data = $this->EE->upload->data();
			
			@chmod($file_data['full_path'], DIR_WRITE_MODE);
			
			$data = array(	'wiki_id'				=> $this->wiki_id,
							'file_name'				=> $new_name,
							'upload_summary'		=> ($this->EE->input->get_post('summary') !== FALSE) ? $this->EE->security->xss_clean($this->EE->input->get_post('summary')) : '',
							'upload_author'			=> $this->EE->session->userdata('member_id'),
							'upload_date'			=> $this->EE->localize->now,
							'image_width'			=> $file_data['image_width'],
							'image_height'			=> $file_data['image_height'],
							'file_type'				=> $file_data['file_type'],
							'file_size'				=> $file_data['file_size'],
							'file_hash'				=> $this->EE->functions->random('md5')
						 );
			
			$file_data['uploaded_by_member_id']	= $this->EE->session->userdata('member_id');
			$file_data['modified_by_member_id'] = $this->EE->session->userdata('member_id');
			$file_data['rel_path'] = $new_name;
			
			$this->EE->load->library('filemanager');
			$this->EE->filemanager->xss_clean_off();
			$saved = $this->EE->filemanager->save_file($server_path.$new_name, $this->upload_dir, $file_data, FALSE);
			
			// If it can't save to filemanager, we need to error and nuke the file
			if ( ! $saved['status'])
			{
				@unlink($file_data['full_path']);
				
				return $this->EE->output->show_user_error('general', 
							array($this->EE->lang->line($saved['message'])));
			}			
			

			$this->EE->db->insert('wiki_uploads', $data);			
			
			if ($this->EE->config->item('secure_forms') == 'y')
			{
				$this->EE->db->where('hash', $_POST['XID']);
				$this->EE->db->where('ip_address', $this->EE->input->ip_address());
				$this->EE->db->or_where('date', 'UNIX_TIMESTAMP()-7200');
				$this->EE->db->delete('security_hashes');				
			}
			
			$this->redirect($this->file_ns, $new_name);
		}
		
		/** ----------------------------------------
		/**  Can User Edit Articles and Thus Upload?
		/** ----------------------------------------*/
		
		if (in_array($this->EE->session->userdata['group_id'], $this->users) OR in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$this->return_data = $this->_allow_if('can_edit', $this->return_data);
			$this->return_data = $this->_deny_if('cannot_edit', $this->return_data);
		}
		else
		{	
			$this->return_data = $this->_deny_if('can_edit', $this->return_data);
			$this->return_data = $this->_allow_if('cannot_edit', $this->return_data);
		}
		
		$file_types = 'images';
		
		if ($query->row('allowed_types')  == 'all')
		{
			include(APPPATH.'config/mimes.php');			
	
			foreach ($mimes as $key => $val)
			{
				$file_types .= ', '.$key;
			}
		}
		
		$this->conditionals['file_types'] = $file_types;
		
		/** ----------------------------------------
		/**  Bits of Data
		/** ----------------------------------------*/
		
		$data['action']			= $this->base_url.$this->special_ns.':Uploads/';
		$data['enctype']		= 'multi';
		$data['id']				= 'upload_file_form';
		
		$data['hidden_fields']	= array('upload' => 'y');
		
		$this->return_data = str_replace(array('{form_declaration:wiki:uploads}', '{file_types}'), 
										array($this->EE->functions->form_declaration($data), $file_types), 
										$this->return_data);
	}

	
	/** -------------------------------------
	/**  Pagination
	/** -------------------------------------*/
	
	function pagination($count, $limit, $base_path)
	{
		if (preg_match("/".LD."paginate".RD."(.+?)".LD."\/paginate".RD."/s", $this->return_data, $match))
		{ 
			$this->paginate		 = TRUE;
			$this->paginate_data = $match['1'];
						
			$this->return_data = str_replace($match['0'], '', $this->return_data);
			
			if ($this->EE->uri->query_string != '' && preg_match("#^P(\d+)|/P(\d+)#", $this->EE->uri->query_string, $match))
			{					
				$this->p_page = (isset($match['2'])) ? $match['2'] : $match['1'];	
					
				$base_path = $this->EE->functions->remove_double_slashes(str_replace($match['0'], '', $base_path));
			}
			
			$this->p_page = ($this->p_page == '' OR ($limit > 1 AND $this->p_page == 1)) ? 0 : $this->p_page;
				
			if ($this->p_page > $count)
			{
				$this->p_page = 0;
			}
								
			$this->current_page = floor(($this->p_page / $limit) + 1);
				
			$this->total_pages = intval(floor($count / $limit));
			
			/** ----------------------------------------
			/**  Create the pagination
			/** ----------------------------------------*/
			
			if ($count % $limit) 
			{
				$this->total_pages++;
			}
			
			if ($count > $limit)
			{
				$this->EE->load->library('pagination');
				
				if (strpos($base_path, SELF) === FALSE && $this->EE->config->item('site_index') != '')
				{
					$base_path .= SELF;
				}	

				$config['base_url']		= $base_path;
				$config['prefix']		= 'P';
				$config['total_rows'] 	= $count;
				$config['per_page']		= $limit;
				$config['cur_page']		= $this->p_page;
				$config['first_link'] 	= $this->EE->lang->line('pag_first_link');
				$config['last_link'] 	= $this->EE->lang->line('pag_last_link');
				$config['first_url'] 	= rtrim($base_path, '/');

				// Allows $config['cur_page'] to override
				$config['uri_segment'] = 0;

				$this->EE->pagination->initialize($config);
				$this->pagination_links = $this->EE->pagination->create_links();
								
				if ((($this->total_pages * $limit) - $limit) > $this->p_page)
				{
					$this->page_next = $base_path.'P'.($this->p_page + $limit);
				}
				
				if (($this->p_page - $limit ) >= 0) 
				{						
					$this->page_previous = $base_path.'P'.($this->p_page - $limit);
				}
				
				$this->pagination_sql = " LIMIT ".$this->p_page.', '.$limit;
			}
			else
			{
				$this->p_page = '';
			}
		}
	}

	
	/* -------------------------------------
	/*  Edit Limit
	/*  - Not specifying wiki_id in here because
	/*  that would allow spammers to harass people with 
	/*  multiple wikis even more, and I simply cannot allow that
	/* -------------------------------------*/
	
	function edit_limit()
	{
		if ( ! in_array($this->EE->session->userdata['group_id'], $this->admins))
		{	
			$query = $this->EE->db->query("SELECT COUNT(revision_id) AS count FROM exp_wiki_revisions 
								 WHERE revision_author = '".$this->EE->db->escape_str($this->EE->session->userdata['member_id'])."'
								 AND revision_date > '".($this->EE->localize->now-24*60*60)."'");
			
			$query2 = $this->EE->db->query("SELECT COUNT(wiki_upload_id) AS count FROM exp_wiki_uploads 
								  WHERE upload_author = '".$this->EE->db->escape_str($this->EE->session->userdata['member_id'])."'
								  AND upload_date > '".($this->EE->localize->now-24*60*60)."'");
								 
			if (($query2->row('count')  + $query->row('count') ) > $this->author_limit)
			{
				return $this->EE->output->show_user_error('general', array($this->EE->lang->line('submission_limit')));
			}
		}
	}

	
	/** -------------------------------------
	/**  Open or Close a Revision
	/** -------------------------------------*/
	
	function open_close_revision($title, $revision_id, $new_status)
	{
		if (in_array($this->EE->session->userdata['group_id'], $this->admins))
		{
			$query = $this->EE->db->query("SELECT r.page_id, r.page_content, p.page_namespace FROM exp_wiki_revisions r, exp_wiki_page p
								 WHERE r.revision_id = '".$this->EE->db->escape_str($revision_id)."'
								 ANd p.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								 AND r.page_id = p.page_id");
								 
			if ($query->num_rows() > 0)
			{
				$page_id = $query->row('page_id') ;
				
				if ($new_status == 'open')
				{
					$cats = $this->check_categories($page_id, $query->row('page_content') , $query->row('page_namespace') );
				}
				
				$this->EE->db->query("UPDATE exp_wiki_revisions 
							SET revision_status = '".$this->EE->db->escape_str($new_status)."' 
							WHERE revision_id = '".$this->EE->db->escape_str($revision_id)."'
							AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");
							
				$query = $this->EE->db->query("SELECT revision_date, page_id
									 FROM exp_wiki_revisions
									 WHERE page_id = '".$this->EE->db->escape_str($page_id)."'
									 AND revision_status = 'open'
									 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
									 ORDER BY revision_date DESC LIMIT 1");
									 
				$date = ($query->num_rows() == 0) ? 0 :  $query->row('revision_date') ;
				
				$this->EE->db->query($this->EE->db->update_string('exp_wiki_page', array('last_updated' => $date), "page_id='".$this->EE->db->escape_str($page_id)."'"));
			}			
		}
		
		// Clear wiki cache
		$this->EE->functions->clear_caching('db');

		$this->redirect('', $title);
	}
	
	
	/** -------------------------------------
	/**  Prevents EE Tags and Variables from being parsed
	/** -------------------------------------*/
	
	function convert_curly_brackets($str)
	{
		/** ------------------------------------
		/**  Protect <script> tags
		/** ------------------------------------*/
		
		$protected = array();
		$this->EE->load->helper('string');
		
		$front_protect = unique_marker('wiki_front_protect');
		$back_protect  = unique_marker('wiki_back_protect');
		
		if (stristr($str, '<script') && preg_match_all("/<script.*?".">.*?<\/script>/is", $str, $matches))
		{
			for($i=0, $s=count($matches['0']); $i < $s; ++$i)
			{
				$protected[$front_protect.$i.$back_protect] = $matches['0'][$i];
			}
			
			$str = str_replace(array_values($protected), array_keys($protected), $str);
		}
		
		/** ------------------------------------
		/**  Encode all other curly brackets
		/** ------------------------------------*/
	
		$str = str_replace(array(LD, RD), array('&#123;', '&#125;'), $str);
		
		/** ------------------------------------
		/**  Convert back and return
		/** ------------------------------------*/
		
		if (count($protected) > 0)
		{
			$str = str_replace(array_keys($protected), array_values($protected), $str);
		}
		
		return $str;
	}

	
	/** ------------------------------------
	/**  Display Attachment
	/** ------------------------------------*/
	
	function display_attachment()
	{
		if ( ! isset($this->seg_parts['0']) OR strlen($this->seg_parts['0']) != 32)
		{
			exit;
		}

		$query = $this->EE->db->query("SELECT file_name, image_width, image_height, file_type, file_hash FROM exp_wiki_uploads 
							 WHERE file_hash = '".$this->EE->db->escape_str($this->seg_parts['0'])."' 
							 AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");

		if ($query->num_rows() == 0)
		{
			exit;
		}
		
		/** ----------------------------------------
		/**  Create Our URL
		/** ----------------------------------------*/
		
		$results = $this->EE->db->query("SELECT server_path FROM exp_upload_prefs 
								WHERE id = '".$this->EE->db->escape_str($this->upload_dir)."'");
							 
		$filepath  = (substr($results->row('server_path') , -1) == '/') ? $results->row('server_path')  : $results->row('server_path') .'/';
		$filepath .= $query->row('file_name') ;
			
		if ( ! file_exists($filepath))
		{
			exit;
		}
		
		/** ----------------------------------------
		/**  Is It An Image?
		/** ----------------------------------------*/
		
		$x = explode('/',$query->row('file_type') );
		
		if ($x['0'] == 'image')
			$attachment = '';
		else
			$attachment = (isset($_SERVER['HTTP_USER_AGENT']) AND strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) ? "" : " attachment;";

		header('Content-Disposition: '.$attachment.' filename="'.$query->row('file_name') .'"');		
		header('Content-Type: '.$query->row('file_type') );
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.filesize($filepath));
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $this->EE->localize->now).' GMT'); 
		header("Cache-Control: public");	

		if ( ! $fp = @fopen($filepath, FOPEN_READ))
		{
			exit;
		}
		
		// success, so let's make remove this request from the tracker so login redirects don't go here
		array_shift($this->EE->session->tracker);
		$this->EE->functions->set_cookie('tracker', serialize($this->EE->session->tracker), '0');
		
		fpassthru($fp);
		@fclose($fp);
		exit;
	}

	
	
	/** -------------------------------------
	/**  Wiki Syntax Manager
	/** -------------------------------------*/
	function wiki_syntax($str, $existence_check=TRUE, $allow_embeds=TRUE)
	{
		/* ------------------------------------
		/*	We don't want BBCode parsed if it's within code examples so we'll 
		/*	convert the brackets
		/* ------------------------------------*/
		
		if (preg_match_all("/\[code\](.+?)\[\/code\]/si", $str, $matches))
		{	  		
			for ($i = 0; $i < count($matches['1']); $i++)
			{				
				$str  = str_replace($matches['0'][$i], '[code]'.str_replace(array('[', ']'), array('&#91;', '&#93;'), $matches['1'][$i]).'[/code]', $str);
			}			
		}
		
		/** ------------------------------------
		/**  Automatic Wiki Linking
		/** ------------------------------------*/
		
		$regular = array();
		$display_title = array();
		if (preg_match_all("/\[\[(.*?)\]\]/i", $str, $matches))
		{	
			for($i=0, $s=count($matches['0']); $i < $s; ++$i)
			{
				/* 
				If colon at the beginning, then we remove as it was indicating
				that it was a link and not something to be processed on edit
				*/
				
				if (substr($matches['1'][$i], 0, 1) == ':')
				{
					$matches['1'][$i] = substr($matches['1'][$i], 1);
				}
			
				if (stristr($matches['1'][$i], ':'))
				{
					$x = explode(':', $matches['1'][$i], 2);
					$title = $this->valid_title($this->EE->security->xss_clean(strip_tags($x['1'])));
				
					switch($x['0'])
					{
						case $this->category_ns :
							if (($pipe_pos = strpos($matches['1'][$i], '|')) !== FALSE)
							{
								$link = trim(substr($matches['1'][$i], 0, $pipe_pos));
								$display = trim(substr($matches['1'][$i], $pipe_pos + 1));
								$title = $this->EE->db->escape_str($this->valid_title($this->EE->security->xss_clean(strip_tags($link))));
								$matches['1'][$i] = '[url="'.$this->base_url.$title.'" title="'.$title.'"]'.
													$this->prep_title($display).
													'[/url]';
							}
							else
							{
								$matches['1'][$i] = '[url="'.$this->base_url.$this->category_ns.':'.$title.'" title="'.$this->category_ns.':'.$title.'"]'.
													$this->category_ns.':'.$this->prep_title($title).
													'[/url]';								
							}
						break;
						case $this->category_ns.'ID' :
						
							$query = $this->EE->db->query("SELECT cat_name FROM exp_wiki_categories WHERE cat_id = '".$this->EE->db->escape_str($x['1'])."' AND wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'");
							
							if ($query->num_rows() == 0) continue(2);
							
							$title = $query->row('cat_name') ;
							
							$matches['1'][$i] = '[url="'.$this->base_url.$this->category_ns.':'.$title.'" title="'.$this->category_ns.':'.$title.'"]'.
												$this->category_ns.':'.$this->prep_title($title).
												'[/url]';
						break;
						case $this->image_ns :
							if ($data = $this->image($x['1'], TRUE))
							{
								if ($this->html_format == 'all')
								{
									$matches['1'][$i] = '<img src="'.$data['url'].'" alt="'.$data['name'].'" width="'.$data['width'].'" height="'.$data['height'].'" />';
								}
								else
								{
									$matches['1'][$i] = '[img]'.$data['url'].'[/img]';
								}
							}
						break;
						default			:
							if (($pipe_pos = strpos($matches['1'][$i], '|')) !== FALSE)
							{
								$link = trim(substr($matches['1'][$i], 0, $pipe_pos));
								$display_title[$i] = trim(substr($matches['1'][$i], $pipe_pos + 1));
								$regular[$i] = $this->valid_title($this->EE->security->xss_clean(strip_tags($link)));
							}
							else
							{
								$regular[$i] = $this->valid_title($matches['1'][$i]);								
							}
						break;
					}
				}
				else
				{	
					if (($pipe_pos = strpos($matches['1'][$i], '|')) !== FALSE)
					{
						$link = trim(substr($matches['1'][$i], 0, $pipe_pos));
						$display_title[$i] = trim(substr($matches['1'][$i], $pipe_pos + 1));
						$regular[$i] = $this->valid_title($this->EE->security->xss_clean(strip_tags($link)));
					}
					else
					{
						$regular[$i] = $this->valid_title($this->EE->security->xss_clean(strip_tags($matches['1'][$i])));
					}
				}
			}
			
			/** ------------------------------------
			/**  Adds a Bit of CSS for Non-Existent Pages
			/** ------------------------------------*/
		
			if (count($regular) > 0)
			{
				$exists = array();
				$replace = ($this->EE->config->item('word_separator') == 'dash') ? '-' : '_';

				if ($existence_check == TRUE)
				{	
					// Most...annoying...query...ever.
					$query = $this->EE->db->query("SELECT wn.namespace_label, wp.page_name
										FROM exp_wiki_page wp
										LEFT JOIN exp_wiki_namespaces wn ON wp.page_namespace = wn.namespace_name
										WHERE wp.wiki_id = '" . $this->EE->db->escape_str($this->wiki_id) . "'
										AND
										(
											wn.wiki_id = wp.wiki_id
											OR
											wn.namespace_name IS NULL
										)
										AND
										(
											LOWER(CONCAT_WS(':', REPLACE(wn.namespace_label, ' ', '{$replace}'), wp.page_name)) IN ('".strtolower(implode("','", $this->EE->db->escape_str($regular)))."')
											OR
											LOWER(wp.page_name) IN ('".strtolower(implode("','", $this->EE->db->escape_str($regular)))."')	)
										");

					if (isset($query) && $query->num_rows() > 0)
					{
						foreach($query->result_array() as $row)
						{
							$e_link = ($row['namespace_label'] != '') ? $row['namespace_label'].':'.$row['page_name'] : $row['page_name'];
							$exists[strtolower($this->valid_title($e_link))] = $this->valid_title($e_link);
						}
					}					
				}

				$this->EE->load->helper('form');
				
				foreach($regular as $key => $title)
				{
					$article = FALSE;
					
					if (isset($exists[strtolower($title)]))
					{
						$title = $exists[strtolower($title)];
						$article = TRUE;
					}
					
					$display = (isset($display_title[$key])) ? $display_title[$key] : $title;
					$css = ($article) ? '' : 'class="noArticle"';
					$matches['1'][$key] = '[url="'.$this->base_url.urlencode($title).'" '.$css.' title="'.form_prep($title).'"]'.$this->prep_title($display).'[/url]';
				}
			}
			
			$str = str_replace($matches['0'], $matches['1'], $str);
		}
		
		/** ------------------------------------
		/**  Embeds
		/** ------------------------------------*/
		
		if ($allow_embeds === TRUE && preg_match_all("@\{embed=(\042|\047)([^\\1]*?)\\1\}@", $str, $matches))
		{
			$pages = array();
			
			foreach($matches['2'] as $val)
			{
				if (stristr($val, ':'))
				{
					$x = explode(':', $val, 2);
					
					$pages[] = "(n.namespace_label = '".$this->EE->db->escape_str($x['0'])."' AND p.page_name = '".$this->EE->db->escape_str($x['1'])."')";
				}
				else
				{
					$pages[] = "p.page_name = '".$this->EE->db->escape_str($val)."'";
				}
			}
			
			$query = $this->EE->db->query("SELECT r.page_content, n.namespace_label, p.page_name, p.page_namespace
								FROM exp_wiki_revisions r, exp_wiki_page p
								LEFT JOIN exp_wiki_namespaces as n ON (p.page_namespace = n.namespace_name)
								WHERE r.wiki_id = '".$this->EE->db->escape_str($this->wiki_id)."'
								AND r.revision_status = 'open'
								AND (".implode(" OR ", $pages).")
								AND p.last_updated = r.revision_date
								AND r.page_id = p.page_id");
								
			if ($query->num_rows() > 0)
			{					
				foreach($query->result_array() as $row)
				{					
					if ($row['page_namespace'] != '')
					{
						$row['page_name'] = $row['namespace_label'].':'.$row['page_name'];
					}
					
					$str = str_replace(array('{embed="'.$row['page_name'].'"}', "{embed='".$row['page_name']."'}"), 
										$this->wiki_syntax($row['page_content'], TRUE, FALSE), 
										$str);
				}
			}
			
			$str = str_replace($matches['0'], '', $str);
		}
		
		
		/* ------------------------------------
		/*	We don't want BBCode parsed if it's within code examples so we'll 
		/*	convert the brackets
		/* ------------------------------------*/
		
		if (preg_match_all("/\[code\](.+?)\[\/code\]/si", $str, $matches))
		{	  		
			for ($i = 0; $i < count($matches['1']); $i++)
			{				
				$str  = str_replace($matches['0'][$i], '[code]'.str_replace(array('&#91;', '&#93;'), array('[', ']'), $matches['1'][$i]).'[/code]', $str);
			}			
		}
		
		return $str;
	}

	
	
	/** ------------------------------------
	/**  Update Module
	/** ------------------------------------*/
	
	function update_module($current='')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		if ($current < '1.1')
		{
			$this->EE->db->query("ALTER TABLE `exp_wikis` DROP `wiki_namespaces_list`");
			$this->EE->db->query("CREATE TABLE `exp_wiki_namespaces` (
  						`namespace_id` int(6) NOT NULL auto_increment,
  						`wiki_id` int(10) UNSIGNED NOT NULL,
  						`namespace_name` varchar(100) NOT NULL,
  						`namespace_label` varchar(150) NOT NULL,
  						`namespace_users` TEXT,
  						`namespace_admins` TEXT,
  						PRIMARY KEY `namespace_id` (`namespace_id`),
  						KEY `wiki_id` (`wiki_id`))");
		
			/* -------------------------------
			/*  The Category NS needs a non-changing short name, so we use 
			/*  'category'.  Prior to this it was using the Label, so we need
			/*  to do a conversion for any category articles already in the 
			/*  exp_wiki_page database table.
			/* -------------------------------*/
			
			$this->EE->lang->loadfile('wiki');
			
			$this->category_ns = (isset($this->EE->lang->language['category_ns']))	? $this->EE->lang->line('category_ns') : $this->category_ns;
				
			$this->EE->db->query("UPDATE exp_wiki_page SET page_namespace = 'category' WHERE page_namespace = '".$this->EE->db->escape_str($this->category_ns)."'");
		}
		
		if ($current < '1.2')
		{
			$this->EE->db->query("ALTER TABLE `exp_wiki_page` ADD `last_revision_id` INT(10) NOT NULL AFTER `last_updated`");
			
			// Multiple table UPDATES are not supported until 4.0 and subqueries not until 4.1
			if (version_compare(mysql_get_server_info(), '4.1-alpha', '>='))
			{
				$this->EE->db->query("UPDATE exp_wiki_page, exp_wiki_revisions
									SET exp_wiki_page.last_revision_id = 
										(SELECT MAX(exp_wiki_revisions.revision_id)
										FROM exp_wiki_revisions
										WHERE exp_wiki_revisions.page_id = exp_wiki_page.page_id)
									WHERE exp_wiki_page.page_id = exp_wiki_revisions.page_id");
			}
			else
			{
				// Slower, loopy-er method for older servers
				$query = $this->EE->db->query("SELECT MAX(revision_id) AS last_revision_id, page_id FROM exp_wiki_revisions GROUP BY page_id");
					
				foreach ($query->result() as $row)
				{
					$this->EE->db->query($this->EE->db->update_string('exp_wiki_page', array('last_revision_id' => $row->last_revision_id), "page_id = '{$row->page_id}'"));
				}
			}
						
		}
		
		$this->EE->db->query("UPDATE exp_modules 
					SET module_version = '".$this->EE->db->escape_str($this->version)."' 
					WHERE module_name = 'Wiki'");
	}


}
/* END Class */

/* End of file mod.wiki.php */
/* Location: ./system/expressionengine/modules/wiki/mod.wiki.php */