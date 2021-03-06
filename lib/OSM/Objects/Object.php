<?php

/**
 * OSM/Objects/Object.php
 */

/**
 * Description of OSM_Object
 *
 * @author cyrille
 */
class OSM_Objects_Object implements OSM_Objects_IDirty {
	/**
	 *
	 */
	const OBJTYPE_TAG = 'tag';

	/**
	 * @var array
	 */
	protected $_attrs = array();

	/**
	 * @var array
	 */
	protected $_tags = array();

	/**
	 * @var bool
	 */
	protected $_dirty = true;
	protected $_deleted = false;

	/**
	 * @param string $id
	 */
	public function __construct($id=null) {
		if ($id != null )
			$this->setId($id);
		$this->setDirty();
	}

	public function getId() {

		return $this->_attrs['id'];
	}

	public function setId($id) {

		//if (!empty($this->_attrs['id']))
		//	throw new OSM_Exception('Could not change Id, only set it.');
		//throw new OSM_Exception('Could not set positive Id, only negative.');

		$this->_attrs['id'] = $id;
	}

	public function getVersion() {

		return $this->_attrs['version'];
	}

	/**
	 * @return bool
	 */
	public function isDirty() {

		if( $this->_dirty )
			return true ;
		foreach ($this->_tags as $t)
			if ($t->isDirty())
			return true;
		return false ;
	}

	/**
	 * @param bool $dirty
	 */
	public function setDirty($dirty=true) {
		
		$this->_dirty = $dirty;
		
		if( $dirty )
		{
			// 'action' attribute is need by the osm file format.
			if($this->_deleted)
			{
				$this->_attrs["action"] = 'delete';
			}
			else
			{
				$this->_attrs["action"] = 'modify';
			}
		}
		else
		{
			$this->_deleted = false ;
		}
	}

	public function delete() {
		$this->_deleted = true;
		$this->setDirty();
	}

	/**
	 * @return bool
	 */
	public function isDeleted() {
		return $this->_deleted;
	}

	/**
	 * Like findTags but return a bool instead of tags.
	 *
	 * @param array $searchTags
	 * @return bool
	 */
	public function hasTags(array $searchTags) {
		$resultTags = $this->findTags($searchTags);
		if (count($resultTags) == count($searchTags))
			return true;
		return false;
	}

	/**
	 * Return all tags or tags matching $searchTags
	 *
	 * @param array $searchTags Optionnal. If you want to filter the returned tags.
	 * @return OSM_Objects_Tag[]
	 * @see findTag()
	 */
	public function &findTags(array $searchTags=null) {

		if ($searchTags == null)
			return $this->_tags;

		$resultTags = array();
		foreach ($searchTags as $k => $v)
		{
			if (($t = $this->getTag($k, $v)) != null)
			{
				$resultTags[] = $t;
			}
		}
		return $resultTags;
	}

	/**
	 * Retreive a tag by Key.
	 *
	 * A value could be provided to enforce the test.
	 *
	 * @param string $key
	 * @param string $v Optional. If not provided or if an empty string or a '*' the value will not be tested.
	 * @return OSM_Objects_Tag
	 */
	public function getTag($key, $v='') {

		if (array_key_exists($key, $this->_tags))
		{
			if (!empty($v) && $v != '*')
			{
				if ($this->_tags[$key]->getValue() == $v)
					return $this->_tags[$key];
				return null;
			}
			return $this->_tags[$key];
		}
		return null;
	}

	public function setTag($key, $value) {
		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		$this->_tags[$key]->setValue($value);
	}

	/**
	 * @param OSM_Objects_Tag|string $tagOrKey
	 * @param string value
	 */
	public function addTag($tagOrKey, $value=null) {

		if ($tagOrKey instanceof OSM_Objects_Tag)
		{
			if (array_key_exists($tagOrKey->getKey(), $this->_tags))
			{
				throw new OSM_Exception('duplicate tag "' . $tagOrKey->getKey() . '"');
			}
			$tag = $tagOrKey;
		}
		else
		{
			if (array_key_exists($tagOrKey, $this->_tags))
			{
				throw new OSM_Exception('duplicate tag "' . $tagOrKey . '"');
			}
			$tag = new OSM_Objects_Tag($tagOrKey, $value);
		}
		$this->_tags[$tag->getKey()] = $tag;
		$this->setDirty();
	}

	public function addTags(array $tags) {
		if (!is_array($tags))
			throw new OSM_Exception('Invalid array of tags');
		foreach ($tags as $tag)
		{
			if (array_key_exists($tag->getKey(), $this->_tags))
			{
				throw new OSM_Exception('duplicate tag "' . $tag->getKey() . '"');
			}
		}
		foreach ($tags as $tag)
		{
			$this->addTag($tag);
		}
	}

	public function removeTag($key) {

		if (!array_key_exists($key, $this->_tags))
			throw new OSM_Exception('Tag "' . $key . '" not found');
		unset($this->_tags[$key]);
		$this->setDirty();
	}

	public function getAttribute($key) {

		if (array_key_exists($key, $this->_attrs))
			return $this->_attrs[$key];
		return null;
	}

	public function setAttribute($key, $value) {

		return $this->_attrs[$key] = $value;
		$this->setDirty();
	}

	/**
	 *
	 * @param SimpleXMLElement $xmlObj
	 * @return array List of processed children types to avoid reprocessing in sub class.
	 */
	protected function _fromXmlObj(SimpleXMLElement $xmlObj) {

		foreach ($xmlObj->attributes() as $k => $v)
		{
			$this->_attrs[(string) $k] = (string) $v;
		}

		if (!array_key_exists('id', $this->_attrs))
			throw new OSM_Exception(__CLASS__ . ' should must a "id" attribute');

		OSM_ZLog::debug(__METHOD__, 'Got a ' . __CLASS__ . ' with id=', $this->getId());

		foreach ($xmlObj->children() as $child)
		{
			switch ($child->getName())
			{
				case OSM_Objects_Object::OBJTYPE_TAG :
					OSM_ZLog::debug(__METHOD__, 'Found child: ', OSM_Objects_Object::OBJTYPE_TAG);
					$tag = OSM_Objects_Tag::fromXmlObj($child);
					$this->_tags[$tag->getKey()] = $tag;
					break;
			}
		}

		return array(OSM_Objects_Object::OBJTYPE_TAG);
	}

}
