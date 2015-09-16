<?php
/**
 * @version		3.0.1 plugins/j2xml/rebuildlinks/rebuildlinks.php
 * 
 * @package		J2XML
 * @subpackage	plg_j2xml_rebuildlinks
 * @since		3.0
 *
 * @author		Helios Ciancio <info@eshiol.it>
 * @link		http://www.eshiol.it
 * @copyright	Copyright (C) 2015 Helios Ciancio. All Rights Reserved
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License 
 * or other free or open source software licenses.
 */
 
// no direct access
defined('_JEXEC') or die('Restricted access.');

jimport('joomla.plugin.plugin');
jimport('joomla.application.component.helper');
jimport('eshiol.j2xml.version');
jimport('eshiol.j2xml.importer');

use Joomla\Registry\Registry;

class PlgJ2xmlRebuildlinks extends JPlugin
{ 
	protected $_params = null;
	protected $_user_id;
	protected $_importer = null;
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		if (JDEBUG)
			JLog::addLogger(array('text_file' => 'j2xml.php', 'extension' => 'plg_j2xml_rebuildlinks'), JLog::ALL, array('plg_j2xml_rebuildlinks'));		
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));

		if (version_compare(J2XMLVersion::getShortVersion(), '16.1.1') == -1)
			JLog::add(new JLogEntry(JText::_('PLG_J2XML_REBUILDLINKS').' '.JText::_('PLG_J2XML_REBUILDLINKS_MSG_REQUIREMENTS_LIB'),JLOG::WARNING,'plg_j2xml_rebuildlinks'));
		// Get the parameters.
		if (isset($config['params']))
		{
			if ($config['params'] instanceof Registry)
			{
				$this->_params = $config['params'];
			}
			else
			{
				$this->_params = (version_compare(JPlatform::RELEASE, '12', 'ge') ? new Registry : new JRegistry);
				$this->_params->loadString($config['params']);
			}
		}
		
		$user = JFactory::getUser();
		$this->_user_id = $user->get('id');
		
		$lang = JFactory::getLanguage();
		$lang->load('plg_j2xml_rebuildlinks', JPATH_SITE, null, false, false)
			|| $lang->load('plg_j2xml_rebuildlinks', JPATH_ADMINISTRATOR, null, false, false)
			|| $lang->load('plg_j2xml_rebuildlinks', JPATH_SITE, null, true)
			|| $lang->load('plg_j2xml_rebuildlinks', JPATH_ADMINISTRATOR, null, true);	
	}

	/**
	 * Method is called by 
	 *
	 * @access	public
	 */
	public function onBeforeContentExport($context, &$item, $options)
	{
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
		JLog::add(new JLogEntry($context,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
		JLog::add(new JLogEntry(print_r($this->_params, true),JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
		
		if (PHP_SAPI == 'cli')
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_rebuildlinks'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_rebuildlinks'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_rebuildlinks'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_rebuildlinks'));
						
		$this->_prepareLinks($item->introtext);
		$this->_prepareLinks($item->fulltext);
		return true;
	}

	private function _prepareLinks(&$input)
	{
		//$regexp = "<a\s[^>]*href=([\"\']??)index.php\?option=com_content&amp;view=article([^\" >]*?)\\1[^>]*>(.*)<\/a>";
		$regexp = "<a\s[^>]*href=([\"\']??)([^\"\' >]*?)\\1[^>]*>(.*)<\/a>";
		if(preg_match_all("/$regexp/siU", $input, $matches, PREG_SET_ORDER)) 
		{
			foreach($matches as $match) 
			{
				// $match[2] = link address
				// $match[3] = link text
				// JLog::add(new JLogEntry('title:'.$match[3],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$url = str_replace('&amp;', '&', $match[2]);
				// JLog::add(new JLogEntry('url:'.$url,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$link = parse_url($url);
				if (isset($link['host'])) continue;
				if (!isset($link['query'])) continue;
				unset($query);
				foreach (explode('&', $link['query']) as $part)
				{
					if ($part)
					{
						list($key, $value) = explode('=', $part, 2);
						$query[$key] = $value;
					}
				}
				//JLog::add(new JLogEntry('query:'.print_r($query, true),JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				if (!isset($query['option']) || $query['option'] != 'com_content') continue;
				if (!isset($query['view']) || $query['view'] != 'article') continue;
				if (!isset($query['id'])) continue;
				if (!isset($query['catid'])) continue;
				$url = $link['path'].'?'.$link['query'];

				$db = JFactory::getDBO();
				$id = explode(':', $query['id']);
				if (isset($id[1]))
					$path = $id[1];
				else
				{
					$qry = $db->getQuery(true);
					$qry->select($db->quoteName('alias'));
					$qry->from($db->quoteName('#__content'));
					$qry->where($db->quoteName('id').' = '.(int)$id[0]);
					$db->setQuery($qry);
					$path = $db->loadResult();
				}
				
				$catid = explode(':', $query['catid']);
				$qry = $db->getQuery(true);
				$qry->select($db->quoteName('path'));
				$qry->from($db->quoteName('#__categories'));
				$qry->where($db->quoteName('id').' = '.(int)$catid[0]);
				$db->setQuery($qry);
				$path = $db->loadResult().'/'.$path;

				$url .= '&path='.$path;
				if (isset($link['fragment'])) $url .= '#'.$link['fragment'];
				$url = str_replace('&', '&amp;', $url);
				JLog::add(new JLogEntry('url1:'.$match[2],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				JLog::add(new JLogEntry('url2:'.$url,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$input = str_replace($match[2], $url, $input);
			}
		}
	}

	private function _rebuildLinks(&$input, $xml)
	{
		$ret = false;
		//$regexp = "<a\s[^>]*href=([\"\']??)index.php\?option=com_content&amp;view=article([^\" >]*?)\\1[^>]*>(.*)<\/a>";
		$regexp = "<a\s[^>]*href=([\"\']??)([^\"\' >]*?)\\1[^>]*>(.*)<\/a>";
		if(preg_match_all("/$regexp/siU", $input, $matches, PREG_SET_ORDER))
		{
			foreach($matches as $match)
			{
				// $match[2] = link address
				// $match[3] = link text
				// JLog::add(new JLogEntry('title:'.$match[3],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$url = str_replace('&amp;', '&', $match[2]);
				// JLog::add(new JLogEntry('url:'.$url,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$link = parse_url($url);
				if (isset($link['host'])) continue;
				if (!isset($link['query'])) continue;
				unset($query);
				foreach (explode('&', $link['query']) as $part)
				{
					if ($part)
					{
						list($key, $value) = explode('=', $part, 2);
						$query[$key] = $value;
					}
				}
				//JLog::add(new JLogEntry('query:'.print_r($query, true),JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				if (!isset($query['option']) || $query['option'] != 'com_content') continue;
				if (!isset($query['view']) || $query['view'] != 'article') continue;
				if (!isset($query['path']))
				{
					$records = $xml->xpath("//j2xml/content[id = ".$query['id']."]");
					if (!$records)
					{
						JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_REBUILDLINKS_MSG_LINK_NOT_REBUILT', strip_tags($match[3])), JLOG::WARNING, 'plg_j2xml_rebuildlinks'));
						continue;
					}
					$query['path'] = $records[0]->catid.'/'.$records[0]->alias;
				}
				$query['id'] = $this->_importer->getArticleId($query['path']);
				if (!$query['id'])
				{
					JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_REBUILDLINKS_MSG_LINK_NOT_REBUILT', strip_tags($match[3])), JLOG::WARNING, 'plg_j2xml_rebuildlinks'));
					continue;
				}
				$db = JFactory::getDBO();
				$qry = $db->getQuery(true);
				$qry->select($db->quoteName('catid'));
				$qry->from($db->quoteName('#__content'));
				$qry->where($db->quoteName('id').' = '.(int)$query['id']);
				$db->setQuery($qry);
				$query['catid'] = $db->loadResult();

				$qry = $db->getQuery(true);
				$qry->select($db->quoteName('alias'));
				$qry->from($db->quoteName('#__categories'));
				$qry->where($db->quoteName('id').' = '.(int)$query['catid']);
				$db->setQuery($qry);
				$query['catid'] .= ':'.$db->loadResult();
				unset($query['path']);
				unset($query['Itemid']);
				if (!isset($query['id']) && !isset($query['catid'])) continue;
				
				$tmp = array();
				foreach ($query as $k=>$v)
					$tmp[] = "$k=$v";
				$query_string = implode('&amp;', $tmp);
				$url = $link['path'].'?'.$query_string;
				if (isset($link['fragment'])) $url .= '#'.$link['fragment'];
				$input = str_replace($match[2], $url, $input);
				JLog::add(new JLogEntry(JText::sprintf('PLG_J2XML_REBUILDLINKS_MSG_LINK_REBUILT', strip_tags($match[3])), JLOG::INFO, 'plg_j2xml_rebuildlinks'));
				JLog::add(new JLogEntry('url1:'.$match[2],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				JLog::add(new JLogEntry('url2:'.$url,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
				$ret = true;
			}
		}
		return $ret;
	}
	
	/**
	 * Method is called by
	 *
	 * @access	public
	 */
	public function onAfterImport($context, &$xml, $options)
	{
		JLog::add(new JLogEntry(__METHOD__,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
		JLog::add(new JLogEntry($context,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
	
		if (PHP_SAPI == 'cli')
			JLog::addLogger(array('logger' => 'echo', 'extension' => 'plg_j2xml_rebuildlinks'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_rebuildlinks'));
		else
			JLog::addLogger(array('logger' => $options->get('logger', 'messagequeue'), 'extension' => 'plg_j2xml_rebuildlinks'), JLOG::ALL & ~JLOG::DEBUG, array('plg_j2xml_rebuildlinks'));
		
		jimport('eshiol.j2xml.importer');
		$this->_importer = new J2XMLImporter();
		
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		foreach ($xml->xpath("//j2xml/content") as $record)
		{
			$this->_importer->prepareData($record, $data, $options);
			JLog::add(new JLogEntry($data['alias'],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
			JLog::add(new JLogEntry($data['catid'],JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
			$id = $this->_importer->getArticleId($data['catid'].'/'.$data['alias']);
			JLog::add(new JLogEntry($id,JLOG::DEBUG,'plg_j2xml_rebuildlinks'));
			
			$item = JTable::getInstance('content');
			$item->load($id);
			if ($this->_rebuildLinks($item->introtext, $xml) || $this->_rebuildLinks($item->fulltext, $xml))
				$item->store();
		}
		return true;
	}
}
