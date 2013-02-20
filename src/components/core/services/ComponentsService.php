<?php
namespace Blocks;

/**
 *
 */
class ComponentsService extends BaseApplicationComponent
{
	/**
	 * @var array The types of components supported by Blocks.
	 */
	public $types;

	/**
	 * @access private
	 * @var array The internal list of components
	 */
	private $_components;

	/**
	 * Returns instances of a component type, indexed by their class handles.
	 *
	 * @param string $type
	 * @return array
	 */
	public function getComponentsByType($type)
	{
		if (!isset($this->_components[$type]))
		{
			if (!isset($this->types[$type]))
			{
				$this->_noComponentTypeExists($type);
			}

			$ctype = $this->types[$type];
			$baseClass = __NAMESPACE__.'\\'.$ctype['baseClass'];

			$this->_components[$type] = array();
			$names = array();

			$filter = '\/'.$ctype['subfolder'].'\/.*'.$ctype['suffix'].'\.php';
			$files = IOHelper::getFolderContents(blx()->path->getComponentsPath(), true, $filter);

			if (is_array($files) && count($files) > 0)
			{
				foreach ($files as $file)
				{
					$class = IOHelper::getFileName($file, false);

					// Add the namespace
					$class = __NAMESPACE__.'\\'.$class;

					// Skip the autoloader
					if (!class_exists($class, false))
					{
						require_once $file;
					}

					// Ignore if we couldn't find the class
					if (!class_exists($class, false))
					{
						continue;
					}

					// Ignore abstract classes and interfaces
					$ref = new \ReflectionClass($class);
					if ($ref->isAbstract() || $ref->isInterface())
					{
						continue;
					}

					// Instantiate it
					$component = new $class;

					// Make sure it implements the correct abstract base class
					if (!$component instanceof $baseClass)
					{
						continue;
					}

					// Save it
					$classHandle = $component->getClassHandle();
					$this->_components[$type][$classHandle] = $component;
					$names[] = $component->getName();
				}
			}

			// Now load any plugin-supplied components
			$pluginComponents = blx()->plugins->getAllComponentsByType($ctype['subfolder']);

			foreach ($pluginComponents as $component)
			{
				if ($component instanceof $baseClass)
				{
					$this->_components[$type][$component->getClassHandle()] = $component;
					$names[] = $component->getName();
				}
			}

			array_multisort($names, $this->_components[$type]);
		}

		return $this->_components[$type];
	}

	/**
	 * Returns a new component instance by its type and class.
	 *
	 * @param string $type
	 * @param string $class
	 * @return BaseComponentType|null
	 */
	public function getComponentByTypeAndClass($type, $class)
	{
		if (!isset($this->types[$type]))
		{
			$this->_noComponentTypeExists($type);
		}

		$class = __NAMESPACE__.'\\'.$class.$this->types[$type]['suffix'];

		if (class_exists($class))
		{
			return new $class;
		}
	}

	/**
	 * Populates a new component instance by its type and model.
	 *
	 * @param string $type
	 * @param BaseComponentModel $model
	 * @return BaseComponentType|null
	 */
	public function populateComponentByTypeAndModel($type, BaseComponentModel $model)
	{
		$component = $this->getComponentByTypeAndClass($type, $model->type);

		if ($component)
		{
			$component->model = $model;

			if ($model->settings)
			{
				$component->setSettings($model->settings);
			}

			if ($model->hasSettingErrors())
			{
				$component->getSettings()->addErrors($model->getSettingErrors());
			}

			return $component;
		}
	}

	/**
	 * Throws a "no component type exists" exception.
	 *
	 * @access private
	 * @param string $type
	 * @throws Exception
	 */
	private function _noComponentTypeExists($type)
	{
		throw new Exception(Blocks::t('No component type exists by the name “{type}”', array('type' => $type)));
	}
}
