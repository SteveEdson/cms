<?php
namespace Blocks;

/**
 *
 */
class SectionRecord extends BaseRecord
{
	/**
	 * @return string
	 */
	public function getTableName()
	{
		return 'sections';
	}

	/**
	 * @return array
	 */
	public function defineAttributes()
	{
		return array(
			'name'          => array(AttributeType::Name, 'required' => true),
			'handle'        => array(AttributeType::Handle, 'maxLength' => 45, 'required' => true),
			'titleLabel'    => array(AttributeType::String, 'required' => true, 'default' => 'Title'),
			'hasUrls'       => array(AttributeType::Bool, 'default' => true),
			'template'      => AttributeType::Template,
			'fieldLayoutId' => AttributeType::Number,
		);
	}

	/**
	 * @return array
	 */
	public function defineRelations()
	{
		return array(
			'fieldLayout' => array(static::BELONGS_TO, 'FieldLayoutRecord', 'onDelete' => static::SET_NULL),
			'locales'     => array(static::HAS_MANY, 'SectionLocaleRecord', 'sectionId'),
			'entries'     => array(static::HAS_MANY, 'EntryRecord', 'sectionId'),
		);
	}

	/**
	 * @return array
	 */
	public function defineIndexes()
	{
		return array(
			array('columns' => array('handle'), 'unique' => true),
		);
	}

	/**
	 * @return array
	 */
	public function scopes()
	{
		return array(
			'ordered' => array('order' => 'name'),
		);
	}
}
