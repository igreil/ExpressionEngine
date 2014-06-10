<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0 
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Spam Module
 *
 * @package		ExpressionEngine
 * @subpackage	Extensions
 * @category	Extensions
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */

require_once PATH_MOD . 'spam/libraries/Spam_training.php';

class Spam_core {

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$training = new Spam_training();
		$this->classifier = $training->load_classifier();
	}

	// --------------------------------------------------------------------

	/**
	 * Returns true if the string is classified as spam
	 * 
	 * @param string $source 
	 * @access public
	 * @return boolean
	 */
	public function classify($source)
	{
		return $this->classifier->classify($source, 'spam');
	}

	// --------------------------------------------------------------------

	/**
	 * Filter content for spam
	 * 
	 * @param  array  An array of strings to classify
	 * @access public
	 * @return bool   Returns true if spam
	 */
	public function filter_content($data)
	{
		// Since we're using Naive Bayes, our assumption
		// of statistical independece means classifying all the content lumped 
		// together should give the same result as classifying each separately.
		$content = implode(' ', $data);

		if ( ! empty($content))
		{
			if ($this->classify($content) === TRUE)
			{
				$this->moderate_content('comment', $data);
				return TRUE;
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Store flagged spam to await moderation. We store a serialized array of any
	 * data we might need as well as a class and method name. If an entry that was
	 * caught by the spam filter is manually flagged as ham, the spam module will
	 * call the stored method with the unserialzed data as the argument. You must
	 * provide a method to handle re-inserting this data.
	 * 
	 * @param string $class    The class to call when re-inserting a false positive
	 * @param string $method   The method to call when re-inserting a false positive
	 * @param string $content  Array of content data
	 * @access public
	 * @return void
	 */
	public function moderate_content($class, $method, $content)
	{
		$data = array(
			'class' => $class,
			'method' => $method,
			'content' => serialize($content)
		);
		ee()->db->insert('spam_trap', $data);
	}

}

/* End of file Spam_core.php */
/* Location: ./system/expressionengine/modules/spam/Spam_core.php */
