<?php

use PHPUnit\Runner\BaseTestRunner;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestResult;

App::uses('CakeFixtureManager', 'TestSuite/Fixture');

class CakeTestRunner extends BaseTestRunner {

	public $testRunner;
	protected $_params;

/**
 * Lets us pass in some options needed for CakePHP's webrunner.
 *
 * @param mixed $loader The test suite loader
 * @param array $params list of options to be used for this run
 */
	public function __construct($loader, array $params = []) {
		$this->_params = $params;
		$this->testRunner = new TestRunner($loader, null);
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
