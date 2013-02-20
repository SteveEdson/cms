<?php
namespace Blocks;

/**
 *
 */
class FieldsService extends BaseApplicationComponent
{
	private $_groupsById;
	private $_fetchedAllGroups = false;

	private $_fieldsById;
	private $_fieldsByHandle;
	private $_fetchedAllFields;

	// Groups
	// ======

	/**
	 * Returns all field groups.
	 *
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getAllGroups($indexBy = null)
	{
		if (!$this->_fetchedAllGroups)
		{
			$groupRecords = FieldGroupRecord::model()->ordered()->findAll();
			$this->_groupsById = FieldGroupModel::populateModels($groupRecords, 'id');
			$this->_fetchedAllGroups = true;
		}

		if ($indexBy == 'id')
		{
			$groups = $this->_groupsById;
		}
		else if (!$indexBy)
		{
			$groups = array_values($this->_groupsById);
		}
		else
		{
			$groups = array();
			foreach ($this->_groupsById as $group)
			{
				$groups[$group->$indexBy] = $group;
			}
		}

		return $groups;
	}

	/**
	 * Returns a field group by its ID.
	 *
	 * @param int $groupId
	 * @return FieldGroupModel|null
	 */
	public function getGroupById($groupId)
	{
		if (!isset($this->_groupsById) || !array_key_exists($groupId, $this->_groupsById))
		{
			$groupRecord = FieldGroupRecord::model()->findById($groupId);

			if ($groupRecord)
			{
				$this->_groupsById[$groupId] = FieldGroupModel::populateModel($groupRecord);
			}
			else
			{
				$this->_groupsById[$groupId] = null;
			}
		}

		return $this->_groupsById[$groupId];
	}

	/**
	 * Saves a field group.
	 *
	 * @param FieldGroupModel $group
	 * @return bool
	 */
	public function saveGroup(FieldGroupModel $group)
	{
		$groupRecord = $this->_getGroupRecord($group);
		$groupRecord->name = $group->name;

		if ($groupRecord->validate())
		{
			$groupRecord->save(false);

			// Now that we have an ID, save it on the model & models
			if (!$group->id)
			{
				$group->id = $groupRecord->id;
			}

			return true;
		}
		else
		{
			$group->addErrors($groupRecord->getErrors());
			return false;
		}
	}

	/**
	 * Deletes a field group.
	 *
	 * @param int $groupId
	 * @return bool
	 */
	public function deleteGroupById($groupId)
	{
		$groupRecord = FieldGroupRecord::model()->with('fields')->findById($groupId);

		if (!$groupRecord)
		{
			return false;
		}

		// Manually delete the fields (rather than relying on cascade deletes)
		// so we have a chance to delete the entrycontent columns
		foreach ($groupRecord->fields as $fieldRecord)
		{
			$field = FieldModel::populateModel($fieldRecord);
			$this->deleteField($field);
		}

		$affectedRows = blx()->db->createCommand()->delete('fieldgroups', array('id' => $groupId));
		return (bool) $affectedRows;
	}

	// Fields
	// ======

	/**
	 * Returns all fields.
	 *
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getAllFields($indexBy = null)
	{
		if (!$this->_fetchedAllFields)
		{
			$fieldRecords = FieldRecord::model()->ordered()->findAll();
			$this->_fieldsById = FieldModel::populateModels($fieldRecords, 'id');

			foreach ($this->_fieldsById as $field)
			{
				$this->_fieldsByHandle[$field->handle] = $field;
			}

			$this->_fetchedAllFields = true;
		}

		if ($indexBy == 'id')
		{
			$fields = $this->_fieldsById;
		}
		else if (!$indexBy)
		{
			$fields = array_values($this->_fieldsById);
		}
		else
		{
			$fields = array();
			foreach ($this->_fieldsById as $field)
			{
				$fields[$field->$indexBy] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Returns a field by its ID.
	 *
	 * @param int $fieldId
	 * @return FieldModel|null
	 */
	public function getFieldById($fieldId)
	{
		if (!isset($this->_fieldsById) || !array_key_exists($fieldId, $this->_fieldsById))
		{
			$fieldRecord = FieldRecord::model()->findById($fieldId);

			if ($fieldRecord)
			{
				$field = FieldModel::populateModel($fieldRecord);
				$this->_fieldsById[$field->id] = $field;
				$this->_fieldsByHandle[$field->handle] = $field;
			}
			else
			{
				$this->_fieldsById[$fieldId] = null;
			}
		}

		return $this->_fieldsById[$fieldId];
	}

	/**
	 * Returns a field by its handle.
	 *
	 * @param string $handle
	 * @return FieldModel|null
	 */
	public function getFieldByHandle($handle)
	{
		if (!isset($this->_fieldsByHandle) || !array_key_exists($handle, $this->_fieldsByHandle))
		{
			$fieldRecord = FieldRecord::model()->findByAttributes(array(
				'handle' => $handle
			));

			if ($fieldRecord)
			{
				$field = FieldModel::populateModel($fieldRecord);
				$this->_fieldsById[$field->id] = $field;
				$this->_fieldsByHandle[$field->handle] = $field;
			}
			else
			{
				$this->_fieldsByHandle[$handle] = null;
			}
		}

		return $this->_fieldsByHandle[$handle];
	}

	/**
	 * Returns all the fields in a given group.
	 *
	 * @param int         $groupId
	 * @param string|null $indexBy
	 * @return array
	 */
	public function getFieldsByGroupId($groupId, $indexBy = null)
	{
		$fieldRecords = FieldRecord::model()->ordered()->findAllByAttributes(array(
			'groupId' => $groupId,
		));

		return FieldModel::populateModels($fieldRecords, $indexBy);
	}

	/**
	 * Saves a field.
	 *
	 * @param FieldModel $field
	 * @throws \Exception
	 * @return bool
	 */
	public function saveField(FieldModel $field)
	{
		$fieldRecord = $this->_getFieldRecordById($field->id);
		$isNewField = $fieldRecord->isNewRecord();

		if (!$isNewField)
		{
			$fieldRecord->oldHandle = $fieldRecord->handle;
		}

		$fieldRecord->groupId      = $field->groupId;
		$fieldRecord->name         = $field->name;
		$fieldRecord->handle       = $field->handle;
		$fieldRecord->instructions = $field->instructions;
		$fieldRecord->translatable = $field->translatable;
		$fieldRecord->type         = $field->type;

		$fieldType = $this->populateFieldType($field);
		$preppedSettings = $fieldType->prepSettings($field->settings);
		$fieldRecord->settings = $field->settings = $preppedSettings;
		$fieldType->setSettings($preppedSettings);
		$fieldType->model = $field;

		$recordValidates = $fieldRecord->validate();
		$settingsValidate = $fieldType->getSettings()->validate();

		if ($recordValidates && $settingsValidate)
		{
			$transaction = blx()->db->beginTransaction();
			try
			{
				$fieldType->onBeforeSave();
				$fieldRecord->save(false);

				// Now that we have a field ID, save it on the model
				if (!$field->id)
				{
					$field->id = $fieldRecord->id;
				}

				// Create/alter the content table column
				$column = $fieldType->defineContentAttribute();

				if ($column)
				{
					$column = ModelHelper::normalizeAttributeConfig($column);

					if ($isNewField)
					{
						blx()->db->createCommand()->addColumn('entrycontent', $field->handle, $column);
					}
					else
					{
						blx()->db->createCommand()->alterColumn('entrycontent', $fieldRecord->oldHandle, $column, $field->handle);
					}
				}

				$fieldType->onAfterSave();

				$transaction->commit();
			}
			catch (\Exception $e)
			{
				$transaction->rollBack();
				throw $e;
			}

			return true;
		}
		else
		{
			$field->addErrors($fieldRecord->getErrors());
			$field->addSettingErrors($fieldType->getSettings()->getErrors());
			return false;
		}
	}

	/**
	 * Deletes a field by its ID.
	 *
	 * @param int $fieldId
	 * @return bool
	 */
	public function deleteFieldById($fieldId)
	{
		$fieldRecord = FieldRecord::model()->findById($fieldId);

		if (!$fieldRecord)
		{
			return false;
		}

		$field = FieldModel::populateModel($fieldRecord);
		return $this->deleteField($field);
	}

	/**
	 * Deletes a field.
	 *
	 * @param FieldModel $field
	 * @return bool
	 */
	public function deleteField(FieldModel $field)
	{
		// De we need to delete the entrycontent column?
		$fieldType = $this->populateFieldType($field);
		$column = $fieldType->defineContentAttribute();

		if ($column)
		{
			blx()->db->createCommand()->dropColumn('entrycontent', $field->handle);
		}

		// Delete the row in fields
		$affectedRows = blx()->db->createCommand()->delete('fields', array('id' => $field->id));

		return (bool) $affectedRows;
	}

	// Layouts
	// =======

	/**
	 * Returns a field layout by its ID.
	 *
	 * @param int $layoutId
	 * @return FieldLayoutModel|null
	 */
	public function getLayoutById($layoutId)
	{
		$layoutRecord = FieldLayoutRecord::model()->with('tabs', 'fields')->findById($layoutId);

		if ($layoutRecord)
		{
			return FieldLayoutModel::populateModel($layoutRecord);
		}
	}

	/**
	 * Returns a field layout by its type.
	 *
	 * @param string $type
	 * @return FieldLayoutModel
	 */
	public function getLayoutByType($type)
	{
		$layoutRecord = FieldLayoutRecord::model()->with('tabs', 'fields')->findByAttributes(array(
			'type' => $type
		));

		if ($layoutRecord)
		{
			return FieldLayoutModel::populateModel($layoutRecord);
		}
		else
		{
			return new FieldLayoutModel();
		}
	}

	/**
	 * Assembles a field layout from post data.
	 *
	 * @param bool $createTabs Whether to create tabs, or just assign the fields directly to the layout.
	 * @return FieldLayoutModel
	 */
	public function assembleLayoutFromPost($createTabs = true)
	{
		$postedFieldLayout = blx()->request->getPost('fieldLayout', array());
		$requiredFields = blx()->request->getPost('requiredFields', array());

		$tabs = array();
		$fields = array();

		$tabSortOrder = 0;

		foreach ($postedFieldLayout as $tabName => $fieldIds)
		{
			$tabFields = array();

			foreach ($fieldIds as $fieldSortOrder => $fieldId)
			{
				$field = array(
					'fieldId'   => $fieldId,
					'required'  => in_array($fieldId, $requiredFields),
					'sortOrder' => ($fieldSortOrder+1),
				);

				$tabFields[] = $field;
			}

			$fields = array_merge($fields, $tabFields);

			if ($createTabs)
			{
				$tabSortOrder++;

				$tabs[] = array(
					'name'      => urldecode($tabName),
					'sortOrder' => $tabSortOrder,
					'fields'    => $tabFields,
				);
			}
		}

		$layout = new FieldLayoutModel();
		$layout->setTabs($tabs);
		$layout->setFields($fields);

		return $layout;
	}

	/**
	 * Saves a field layout.
	 *
	 * @param FieldLayoutModel $layout
	 * @param bool $saveTabs Whether to save tab records.
	 * @return bool
	 */
	public function saveLayout(FieldLayoutModel $layout, $saveTabs = true)
	{
		// First save the layout
		$layoutRecord = new FieldLayoutRecord();
		$layoutRecord->type = $layout->type;
		$layoutRecord->save(false);
		$layout->id = $layoutRecord->id;

		if ($saveTabs)
		{
			foreach ($layout->getTabs() as $tab)
			{
				$tabRecord = new FieldLayoutTabRecord();
				$tabRecord->layoutId  = $layout->id;
				$tabRecord->name      = $tab->name;
				$tabRecord->sortOrder = $tab->sortOrder;
				$tabRecord->save(false);
				$tab->id = $tabRecord->id;

				foreach ($tab->getFields() as $field)
				{
					$fieldRecord = new FieldLayoutFieldRecord();
					$fieldRecord->layoutId  = $layout->id;
					$fieldRecord->tabId     = $tab->id;
					$fieldRecord->fieldId   = $field->fieldId;
					$fieldRecord->required  = $field->required;
					$fieldRecord->sortOrder = $field->sortOrder;
					$fieldRecord->save(false);
					$field->id = $fieldRecord->id;
				}
			}
		}
		else
		{
			foreach ($layout->getFields() as $field)
			{
				$fieldRecord = new FieldLayoutFieldRecord();
				$fieldRecord->layoutId  = $layout->id;
				$fieldRecord->fieldId   = $field->fieldId;
				$fieldRecord->required  = $field->required;
				$fieldRecord->sortOrder = $field->sortOrder;
				$fieldRecord->save(false);
				$field->id = $fieldRecord->id;
			}
		}

		return true;
	}

	/**
	 * Deletes a field layout by its ID.
	 *
	 * @param int $layoutId
	 * @return bool
	 */
	public function deleteLayoutById($layoutId)
	{
		$affectedRows = blx()->db->createCommand()->delete('fieldlayouts', array('id' => $layoutId));
		return (bool) $affectedRows;
	}

	/**
	 * Deletes field layouts of a given type.
	 *
	 * @param string $type
	 * @return bool
	 */
	public function deleteLayoutsByType($type)
	{
		$affectedRows = blx()->db->createCommand()->delete('fieldlayouts', array('type' => $type));
		return (bool) $affectedRows;
	}

	// Fieldtypes
	// ==========

	/**
	 * Returns all installed fieldtypes.
	 *
	 * @return array
	 */
	public function getAllFieldTypes()
	{
		return blx()->components->getComponentsByType(ComponentType::Field);
	}

	/**
	 * Gets a fieldtype.
	 *
	 * @param string $class
	 * @return BaseFieldType|null
	 */
	public function getFieldType($class)
	{
		return blx()->components->getComponentByTypeAndClass(ComponentType::Field, $class);
	}

	/**
	 * Populates a fieldtype by a field model.
	 *
	 * @param FieldModel $field
	 * @param EntryModel|null $entry
	 * @return BaseFieldType|null
	 */
	public function populateFieldType(FieldModel $field, $entry = null)
	{
		$fieldType = blx()->components->populateComponentByTypeAndModel(ComponentType::Field, $field);

		if ($fieldType)
		{
			$fieldType->entry = $entry;
			return $fieldType;
		}
	}

	// Private methods
	// ===============

	/**
	 * Gets a field group record or creates a new one.
	 *
	 * @access private
	 * @param FieldGroupModel $group
	 * @throws Exception
	 * @return FieldGroupRecord
	 */
	private function _getGroupRecord(FieldGroupModel $group)
	{
		if ($group->id)
		{
			$groupRecord = FieldGroupRecord::model()->findById($group->id);

			if (!$groupRecord)
			{
				throw new Exception(Blocks::t('No field group exists with the ID “{id}”', array('id' => $group->id)));
			}
		}
		else
		{
			$groupRecord = new FieldGroupRecord();
		}

		return $groupRecord;
	}

	/**
	 * Gets a field record by its ID or creates a new one.
	 *
	 * @access private
	 * @param int $fieldId
	 * @return FieldRecord
	 */
	private function _getFieldRecordById($fieldId = null)
	{
		if ($fieldId)
		{
			$fieldRecord = FieldRecord::model()->findById($fieldId);

			if (!$fieldRecord)
			{
				throw new Exception(Blocks::t('No field exists with the ID “{id}”', array('id' => $fieldId)));
			}
		}
		else
		{
			$fieldRecord = new FieldRecord();
		}

		return $fieldRecord;
	}
}
