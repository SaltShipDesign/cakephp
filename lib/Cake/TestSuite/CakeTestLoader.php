<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\Util\FileLoader;

class CakeTestLoader implements TestSuiteLoader {

/**
 * @throws Exception
 */
	public function load(string $suiteClassFile): ReflectionClass {
		$suiteClassName = basename($suiteClassFile, '.php');
		$loadedClasses = get_declared_classes();

		if (!class_exists($suiteClassName, false)) {
			/* @noinspection UnusedFunctionResultInspection */
			FileLoader::checkAndLoad($suiteClassFile);

			$loadedClasses = array_values(
				array_diff(get_declared_classes(), $loadedClasses),
			);

			if (empty($loadedClasses)) {
				throw new Exception(
					sprintf(
						'Class %s could not be found in %s',
						$suiteClassName,
						$suiteClassFile,
					),
				);
			}
		}

		if (!class_exists($suiteClassName, false)) {
			$offset = 0 - strlen($suiteClassName);

			foreach ($loadedClasses as $loadedClass) {
				// @see https://github.com/sebastianbergmann/phpunit/issues/5020
				if (stripos(substr($loadedClass, $offset - 1), '\\' . $suiteClassName) === 0 ||
					stripos(substr($loadedClass, $offset - 1), '_' . $suiteClassName) === 0) {
					$suiteClassName = $loadedClass;

					break;
				}
			}
		}

		if (!class_exists($suiteClassName, false)) {
			throw new Exception(
				sprintf(
					'Class %s could not be found in %s',
					$suiteClassName,
					$suiteClassFile,
				),
			);
		}

		try {
			$class = new ReflectionClass($suiteClassName);
			// @codeCoverageIgnoreStart
		} catch (ReflectionException $e) {
			throw new Exception(
				$e->getMessage(),
				$e->getCode(),
				$e,
			);
		}
		// @codeCoverageIgnoreEnd

		if ($class->isSubclassOf(TestCase::class)) {
			if ($class->isAbstract()) {
				throw new Exception(
					sprintf(
						'Class %s declared in %s is abstract',
						$suiteClassName,
						$suiteClassFile,
					),
				);
			}

			return $class;
		}

		if ($class->hasMethod('suite')) {
			try {
				$method = $class->getMethod('suite');
				// @codeCoverageIgnoreStart
			} catch (ReflectionException $e) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is abstract',
						$suiteClassName,
						$suiteClassFile,
					),
				);
			}

			if (!$method->isPublic()) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is not public',
						$suiteClassName,
						$suiteClassFile,
					),
				);
			}

			if (!$method->isStatic()) {
				throw new Exception(
					sprintf(
						'Method %s::suite() declared in %s is not static',
						$suiteClassName,
						$suiteClassFile,
					),
				);
			}
		}

		return $class;
	}

/**
 * Reload
 *
 * @param ReflectionClass $aClass The class
 * @return ReflectionClass
 */
	public function reload(ReflectionClass $aClass): ReflectionClass {
		return $aClass;
	}

/**
 * Generates the base path to a set of tests based on the parameters.
 *
 * @param array $params The path parameters.
 *
 * @return string The base path.
 */
	protected static function _basePath(array $params): ?string {
		$result = null;
		if (!empty($params['core'])) {
			$result = CORE_TEST_CASES;
		} elseif (!empty($params['plugin'])) {
			if (!CakePlugin::loaded($params['plugin'])) {
				try {
					CakePlugin::load($params['plugin']);
					$result = CakePlugin::path($params['plugin']) . 'Test' . DS . 'Case';
				} catch (MissingPluginException $e) {
				}
			} else {
				$result = CakePlugin::path($params['plugin']) . 'Test' . DS . 'Case';
			}
		} elseif (!empty($params['app'])) {
			$result = APP_TEST_CASES;
		}
		return $result;
	}

/**
 * Get the list of files for the test listing.
 *
 * @param array $params Path parameters
 *
 * @return array
 */
	public static function generateTestList(array $params): array {
		$directory = static::_basePath($params);
		$fileList = static::_getRecursiveFileList($directory);

		$testCases = array();
		foreach ($fileList as $testCaseFile) {
			$case = str_replace($directory . DS, '', $testCaseFile);
			$case = str_replace('Test.php', '', $case);
			$testCases[$testCaseFile] = $case;
		}
		sort($testCases);
		return $testCases;
	}

/**
 * Gets a recursive list of files from a given directory and matches then against
 * a given fileTestFunction, like isTestCaseFile()
 *
 * @param string $directory The directory to scan for files.
 *
 * @return array
 */
	protected static function _getRecursiveFileList(string $directory = '.'): array {
		$fileList = array();
		if (!is_dir($directory)) {
			return $fileList;
		}

		$files = new RegexIterator(
			new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)),
			'/.*Test.php$/'
		);

		foreach ($files as $file) {
			$fileList[] = $file->getPathname();
		}
		return $fileList;
	}
}
