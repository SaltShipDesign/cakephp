<?php

use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\Runner\TestSuiteLoader;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestResult;

App::uses('CakeTestLoader', 'TestSuite');
App::uses('CakeFixtureManager', 'TestSuite/Fixture');

class CakeTestRunner extends BaseTestRunner {

	public $testRunner;
	protected $_params;
	protected $_loaderClass;

/**
 * Lets us pass in some options needed for CakePHP's webrunner.
 *
 * @param mixed $loader The test suite loader class name (string).
 * @param array $params list of options to be used for this run
 */
	public function __construct($loader, array $params = []) {
		$this->_params = $params;
		if (is_string($loader) && $loader !== '' && class_exists($loader)) {
			$this->_loaderClass = $loader;
		} else {
			$this->_loaderClass = 'CakeTestLoader';
		}
		$loaderInstance = null;
		if ($loader instanceof TestSuiteLoader) {
			$loaderInstance = $loader;
		} elseif (class_exists($this->_loaderClass)) {
			$tmp = new $this->_loaderClass;
			if ($tmp instanceof TestSuiteLoader) {
				$loaderInstance = $tmp;
			}
		}
		$this->testRunner = new TestRunner($loaderInstance, null);
	}

/**
 * Override BaseTestRunner::getLoader() so PHPUnit's getTest() uses
 * CakePHP's loader (which understands legacy class naming) instead of
 * StandardTestSuiteLoader.
 *
 * @return TestSuiteLoader
 */
	public function getLoader(): TestSuiteLoader {
		if ($this->_loaderClass && class_exists($this->_loaderClass)) {
			$loader = new $this->_loaderClass;
			if ($loader instanceof TestSuiteLoader) {
				return $loader;
			}
		}
		return parent::getLoader();
	}

/**
 * Actually run a suite of tests. Cake initializes fixtures here using the chosen fixture manager
 *
 * @param Test $suite The test suite to run
 * @param array $arguments The CLI arguments
 * @param bool $exit Exits by default or returns the results
 * This argument is ignored if >PHPUnit5.2.0
 *
 * @return void
 */
	public function run(Test $suite, array $arguments = array(), bool $exit = true): void {
		if (isset($arguments['printer'])) {
			static::$versionStringPrinted = true;
		}

		$fixture = $this->_getFixtureManager($arguments);
		$iterator = $suite->getIterator();
		if ($iterator instanceof RecursiveIterator) {
			$iterator = new RecursiveIteratorIterator($iterator);
		}
		foreach ($iterator as $test) {
			if ($test instanceof CakeTestCase) {
				$fixture->fixturize($test);
				$test->fixtureManager = $fixture;
			}
		}

		$this->testRunner->run($suite, $arguments, [], $exit);
		$fixture->shutdown();
	}

// @codingStandardsIgnoreStart PHPUnit overrides don't match CakePHP
/**
 * Create the test result and splice on our code coverage reports.
 *
 * @return TestResult
 */
	protected function createTestResult(): TestResult {
		$result = new TestResult;
		if (!empty($this->_params['codeCoverage'])) {
			if (method_exists($result, 'collectCodeCoverageInformation')) {
				$result->collectCodeCoverageInformation(true);
			}
			if (method_exists($result, 'setCodeCoverage')) {
				$result->setCodeCoverage(new PHP_CodeCoverage());
			}
		}
		return $result;
	}
// @codingStandardsIgnoreEnd

/**
 * Get the fixture manager class specified or use the default one.
 *
 * @param array $arguments The CLI arguments.
 *
 * @return mixed instance of a fixture manager.
 * @throws RuntimeException When fixture manager class cannot be loaded.
 */
	protected function _getFixtureManager(array $arguments) {
		if (!empty($arguments['fixtureManager'])) {
			App::uses($arguments['fixtureManager'], 'TestSuite');
			if (class_exists($arguments['fixtureManager'])) {
				return new $arguments['fixtureManager'];
			}
			throw new RuntimeException(__d('cake_dev', 'Could not find fixture manager %s.', $arguments['fixtureManager']));
		}
		App::uses('AppFixtureManager', 'TestSuite');
		if (class_exists('AppFixtureManager')) {
			return new AppFixtureManager();
		}
		return new CakeFixtureManager();
	}

/**
 * Run failed
 *
 * @param string $message Message
 * @return void
 */
	protected function runFailed(string $message): void {
		$this->testRunner->runFailed($message);
	}
}
