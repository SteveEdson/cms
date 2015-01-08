<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\templating\twigextensions;

use craft\app\Craft;
use craft\app\helpers\DateTimeHelper;
use craft\app\helpers\DbHelper;
use craft\app\helpers\StringHelper;
use craft\app\helpers\TemplateHelper;
use craft\app\helpers\UrlHelper;
use craft\app\variables\Craft as CraftVariable;

/**
 * Class CraftTwigExtension
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class CraftTwigExtension extends \Twig_Extension
{
	// Public Methods
	// =========================================================================

	/**
	 * Returns the token parser instances to add to the existing list.
	 *
	 * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
	 */
	public function getTokenParsers()
	{
		return [
			new Cache_TokenParser(),
			new Exit_TokenParser(),
			new Header_TokenParser(),
			new Hook_TokenParser(),
			new IncludeResource_TokenParser('includeCss'),
			new IncludeResource_TokenParser('includeCssFile'),
			new IncludeResource_TokenParser('includeCssResource'),
			new IncludeResource_TokenParser('includeHiResCss'),
			new IncludeResource_TokenParser('includeJs'),
			new IncludeResource_TokenParser('includeJsFile'),
			new IncludeResource_TokenParser('includeJsResource'),
			new IncludeTranslations_TokenParser(),
			new Namespace_TokenParser(),
			new Nav_TokenParser(),
			new Paginate_TokenParser(),
			new Redirect_TokenParser(),
			new RequireAdmin_TokenParser(),
			new RequireEdition_TokenParser(),
			new RequireLogin_TokenParser(),
			new RequirePermission_TokenParser(),
			new Switch_TokenParser(),
		];
	}

	/**
	 * Returns a list of filters to add to the existing list.
	 *
	 * @return array An array of filters
	 */
	public function getFilters()
	{
		$translateFilter = new \Twig_Filter_Function('\craft\app\Craft::t');
		$namespaceFilter = new \Twig_Filter_Function('\craft\app\Craft::$app->templates->namespaceInputs');
		$markdownFilter  = new \Twig_Filter_Method($this, 'markdownFilter');

		return [
			'currency'           => new \Twig_Filter_Function('\craft\app\Craft::$app->numberFormatter->formatCurrency'),
			'date'               => new \Twig_Filter_Method($this, 'dateFilter', ['needs_environment' => true]),
			'datetime'           => new \Twig_Filter_Function('\craft\app\Craft::$app->dateFormatter->formatDateTime'),
			'filesize'           => new \Twig_Filter_Function('\craft\app\Craft::$app->formatter->formatSize'),
			'filter'             => new \Twig_Filter_Function('array_filter'),
			'group'              => new \Twig_Filter_Method($this, 'groupFilter'),
			'indexOf'            => new \Twig_Filter_Method($this, 'indexOfFilter'),
			'intersect'          => new \Twig_Filter_Function('array_intersect'),
			'lcfirst'            => new \Twig_Filter_Method($this, 'lcfirstFilter'),
			'literal'            => new \Twig_Filter_Method($this, 'literalFilter'),
			'markdown'           => $markdownFilter,
			'md'                 => $markdownFilter,
			'namespace'          => $namespaceFilter,
			'ns'                 => $namespaceFilter,
			'namespaceInputName' => new \Twig_Filter_Function('\craft\app\Craft::$app->templates->namespaceInputName'),
			'namespaceInputId'   => new \Twig_Filter_Function('\craft\app\Craft::$app->templates->namespaceInputId'),
			'number'             => new \Twig_Filter_Function('\craft\app\Craft::$app->numberFormatter->formatDecimal'),
			'parseRefs'          => new \Twig_Filter_Method($this, 'parseRefsFilter'),
			'percentage'         => new \Twig_Filter_Function('\craft\app\Craft::$app->numberFormatter->formatPercentage'),
			'replace'            => new \Twig_Filter_Method($this, 'replaceFilter'),
			'translate'          => $translateFilter,
			't'                  => $translateFilter,
			'ucfirst'            => new \Twig_Filter_Method($this, 'ucfirstFilter'),
			'ucwords'            => new \Twig_Filter_Function('ucwords'),
			'without'            => new \Twig_Filter_Method($this, 'withoutFilter'),
		];
	}

	/**
	 * Uppercases the first character of a multibyte string.
	 *
	 * @param string $string The multibyte string.
	 *
	 * @return string The string with the first character converted to upercase.
	 */
	public function ucfirstFilter($string)
	{
		return StringHelper::uppercaseFirst($string);
	}

	/**
	 * Lowercases the first character of a multibyte string.
	 *
	 * @param string $string The multibyte string.
	 *
	 * @return string The string with the first character converted to lowercase.
	 */
	public function lcfirstFilter($string)
	{
		return StringHelper::lowercaseFirst($string);
	}

	/**
	 * Returns an array without certain values.
	 *
	 * @param array $arr
	 * @param mixed $exclude
	 *
	 * @return array
	 */
	public function withoutFilter($arr, $exclude)
	{
		$filteredArray = [];

		if (!is_array($exclude))
		{
			$exclude = [$exclude];
		}

		foreach ($arr as $key => $value)
		{
			if (!in_array($value, $exclude))
			{
				$filteredArray[$key] = $value;
			}
		}

		return $filteredArray;
	}

	/**
	 * Parses a string for reference tags.
	 *
	 * @param string $str
	 *
	 * @return \Twig_Markup
	 */
	public function parseRefsFilter($str)
	{
		$str = Craft::$app->elements->parseRefs($str);
		return TemplateHelper::getRaw($str);
	}

	/**
	 * Replaces Twig's |replace filter, adding support for passing in separate
	 * search and replace arrays.
	 *
	 * @param mixed $str
	 * @param mixed $search
	 * @param mixed $replace
	 *
	 * @return mixed
	 */
	public function replaceFilter($str, $search, $replace = null)
	{
		// Are they using the standard Twig syntax?
		if (is_array($search) && $replace === null)
		{
			return strtr($str, $search);
		}
		// Is this a regular expression?
		else if (preg_match('/^\/(.+)\/$/', $search))
		{
			return preg_replace($search, $replace, $str);
		}
		else
		{
			// Otherwise use str_replace
			return str_replace($search, $replace, $str);
		}
	}

	/**
	 * Extending Twig's |date filter so we can run any translations on the output.
	 *
	 * @param \Twig_Environment $env
	 * @param                   $date
	 * @param null              $format
	 * @param null              $timezone
	 *
	 * @return mixed|string
	 */
	public function dateFilter(\Twig_Environment $env, $date, $format = null, $timezone = null)
	{
		// Let Twig do it's thing.
		$value = \twig_date_format_filter($env, $date, $format, $timezone);

		// Get the "words".  Split on anything that is not a unicode letter or number.
		preg_match_all('/[\p{L}\p{N}]+/u', $value, $words);

		if ($words && isset($words[0]) && count($words[0]) > 0)
		{
			foreach ($words[0] as $word)
			{
				// Translate and swap out.
				$translatedWord = Craft::t($word);
				$value = str_replace($word, $translatedWord, $value);
			}
		}

		// Return the translated value.
		return $value;

	}

	/**
	 * Groups an array by a common property.
	 *
	 * @param array  $arr
	 * @param string $item
	 *
	 * @return array
	 */
	public function groupFilter($arr, $item)
	{
		$groups = [];

		$template = '{'.$item.'}';

		foreach ($arr as $key => $object)
		{
			$value = Craft::$app->templates->renderObjectTemplate($template, $object);
			$groups[$value][] = $object;
		}

		return $groups;
	}

	/**
	 * Returns the index of an item in a string or array, or -1 if it cannot be found.
	 *
	 * @param mixed $haystack
	 * @param mixed $needle
	 *
	 * @return int
	 */
	public function indexOfFilter($haystack, $needle)
	{
		if (is_string($haystack))
		{
			$index = strpos($haystack, $needle);
		}
		else if (is_array($haystack))
		{
			$index = array_search($needle, $haystack);
		}
		else if (is_object($haystack) && $haystack instanceof \IteratorAggregate)
		{
			$index = false;

			foreach ($haystack as $i => $item)
			{
				if ($item == $needle)
				{
					$index = $i;
					break;
				}
			}
		}

		if ($index !== false)
		{
			return $index;
		}
		else
		{
			return -1;
		}
	}

	/**
	 * Escapes commas and asterisks in a string so they are not treated as special characters in
	 * [[DbHelper::parseParam()]].
	 *
	 * @param string $value The param value.
	 *
	 * @return string The escaped param value.
	 */
	public function literalFilter($value)
	{
		return DbHelper::escapeParam($value);
	}

	/**
	 * Parses text through Markdown.
	 *
	 * @param string $str
	 *
	 * @return \Twig_Markup
	 */
	public function markdownFilter($str)
	{
		$html = StringHelper::parseMarkdown($str);
		return TemplateHelper::getRaw($html);
	}

	/**
	 * Returns a list of functions to add to the existing list.
	 *
	 * @return array An array of functions
	 */
	public function getFunctions()
	{
		return [
			'actionUrl'            => new \Twig_Function_Function('\Craft\UrlHelper::getActionUrl'),
			'cpUrl'                => new \Twig_Function_Function('\Craft\UrlHelper::getCpUrl'),
			'ceil'                 => new \Twig_Function_Function('ceil'),
			'floor'                => new \Twig_Function_Function('floor'),
			'getCsrfInput'         => new \Twig_Function_Method($this, 'getCsrfInputFunction'),
			'getHeadHtml'          => new \Twig_Function_Method($this, 'getHeadHtmlFunction'),
			'getFootHtml'          => new \Twig_Function_Method($this, 'getFootHtmlFunction'),
			'getTranslations'      => new \Twig_Function_Function('\craft\app\Craft::$app->templates->getTranslations'),
			'max'                  => new \Twig_Function_Function('max'),
			'min'                  => new \Twig_Function_Function('min'),
			'renderObjectTemplate' => new \Twig_Function_Function('\craft\app\Craft::$app->templates->renderObjectTemplate'),
			'round'                => new \Twig_Function_Function('round'),
			'resourceUrl'          => new \Twig_Function_Function('\Craft\UrlHelper::getResourceUrl'),
			'shuffle'              => new \Twig_Function_Method($this, 'shuffleFunction'),
			'siteUrl'              => new \Twig_Function_Function('\Craft\UrlHelper::getSiteUrl'),
			'url'                  => new \Twig_Function_Function('\Craft\UrlHelper::getUrl'),
		];
	}

	/**
	 * Returns getCsrfInput() wrapped in a \Twig_Markup object.
	 *
	 * @return \Twig_Markup
	 */
	public function getCsrfInputFunction()
	{
		$html = Craft::$app->templates->getCsrfInput();
		return TemplateHelper::getRaw($html);
	}

	/**
	 * Returns getHeadHtml() wrapped in a \Twig_Markup object.
	 *
	 * @return \Twig_Markup
	 */
	public function getHeadHtmlFunction()
	{
		$html = Craft::$app->templates->getHeadHtml();
		return TemplateHelper::getRaw($html);
	}

	/**
	 * Returns getFootHtml() wrapped in a \Twig_Markup object.
	 *
	 * @return \Twig_Markup
	 */
	public function getFootHtmlFunction()
	{
		$html = Craft::$app->templates->getFootHtml();
		return TemplateHelper::getRaw($html);
	}

	/**
	 * Shuffles an array.
	 *
	 * @param mixed $arr
	 *
	 * @return mixed
	 */
	public function shuffleFunction($arr)
	{
		if ($arr instanceof \Traversable)
		{
			$arr = iterator_to_array($arr, false);
		}
		else
		{
			$arr = array_merge($arr);
		}

		shuffle($arr);

		return $arr;
	}

	/**
	 * Returns a list of global variables to add to the existing list.
	 *
	 * @return array An array of global variables
	 */
	public function getGlobals()
	{
		// Keep the 'blx' variable around for now
		$craftVariable = new CraftVariable();
		$globals['craft'] = $craftVariable;
		$globals['blx']   = $craftVariable;

		$globals['now']       = DateTimeHelper::currentUTCDateTime();
		$globals['loginUrl']  = UrlHelper::getUrl(Craft::$app->config->getLoginPath());
		$globals['logoutUrl'] = UrlHelper::getUrl(Craft::$app->config->getLogoutPath());

		if (Craft::$app->isInstalled() && !Craft::$app->updates->isCraftDbMigrationNeeded())
		{
			$globals['siteName'] = Craft::$app->getSiteName();
			$globals['siteUrl'] = Craft::$app->getSiteUrl();

			$globals['currentUser'] = Craft::$app->getUser()->getIdentity();

			// Keep 'user' around so long as it's not hurting anyone.
			// Technically deprecated, though.
			$globals['user'] = $globals['currentUser'];

			if (Craft::$app->request->isSiteRequest())
			{
				foreach (Craft::$app->globals->getAllSets() as $globalSet)
				{
					$globals[$globalSet->handle] = $globalSet;
				}
			}
		}
		else
		{
			$globals['siteName'] = null;
			$globals['siteUrl'] = null;
			$globals['user'] = null;
		}

		if (Craft::$app->request->isCpRequest())
		{
			$globals['CraftEdition']  = Craft::$app->getEdition();
			$globals['CraftPersonal'] = Craft::Personal;
			$globals['CraftClient']   = Craft::Client;
			$globals['CraftPro']      = Craft::Pro;
		}

		return $globals;
	}

	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName()
	{
		return 'craft';
	}
}
