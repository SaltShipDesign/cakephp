<?php
/**
 * CakeTestSuiteDispatcher controls dispatching TestSuite web based requests.
 *
 * CakePHP(tm) Tests <https://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       Cake.TestSuite
 * @since         CakePHP(tm) v 1.3
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Path to the tests directory of the app.
 */
if (!defined('TESTS')) {
	define('TESTS', APP . 'Test' . DS);
}

/**
 * Path to the test cases directory of CakePHP.
 */
if (!defined('CORE_TEST_CASES')) {
	define('CORE_TEST_CASES', CAKE . 'Test' . DS . 'Case');
}

/**
 * Path to the test cases directory of the app.
 */
if (!defined('APP_TEST_CASES')) {
	define('APP_TEST_CASES', TESTS . 'Case');
}

class CakeTestSuiteDispatcher {

/**
 * 'Request' parameters
 *
 * @var array
 */
	public $params = [
		'codeCoverage' => false,
		'case' => null,
		'core' => false,
		'app' => false,
		'plugin' => null,
		'output' => 'html',
		'show' => 'groups',
		'show_passes' => false,
		'filter' => false,
		'fixture' => null,
	];

/**
 * Baseurl for the request
 *
 * @var string
 */
	protected $_baseUrl;

/**
 * Base dir of the request. Used for accessing assets.
 *
 * @var string
 */
	protected $_baseDir;

/**
 * boolean to set auto parsing of params.
 *
 * @var bool
 */
	protected $_paramsParsed = false;

/**
 * reporter instance used for the request
 *
 * @var CakeBaseReporter||null
 */
	protected static $_Reporter = null;

/**
 * Constructor
 */
	public function __construct() {
		$this->_baseUrl = $_SERVER['PHP_SELF'];
		$dir = rtrim(dirname($this->_baseUrl), '\\');
		$this->_baseDir = ($dir === '/') ? $dir : $dir . '/';
	}

/**
 * Runs the actions required by the URL parameters.
 *
 * @return void
 */
	public function dispatch() {
		$this->_checkPHPUnit();
		$this->_parseParams();

		if ($this->params['case']) {
			$value = $this->_runTestCase();
		} else {
			$value = $this->_testCaseList();
		}

		$output = ob_get_clean();
		echo $output;
		return $value;
	}

/**
 * Static method to initialize the test runner, keeps global space clean
 *
 * @return void
 */
	public static function run() {
		$dispatcher = new CakeTestSuiteDispatcher();
		$dispatcher->dispatch();
	}

/**
 * Checks that PHPUnit is installed. Will exit if it doesn't
 *
 * @return void
 */
	protected function _checkPHPUnit() {
		$found = $this->loadTestFramework();
		if (!$found) {
			$baseDir = $this->_baseDir;
			include CAKE . 'TestSuite' . DS . 'templates' . DS . 'phpunit.php';
			exit();
		}
	}

/**
 * Checks for the existence of the test framework files
 *
 * @return bool true if found, false otherwise
 */
	public function loadTestFramework() {
		if (!class_exists('\\PHPUnit\\Framework\\TestCase')) {
			return false;
		}
		static::aliasLegacyClasses();
		App::uses('CakeTestCase', 'TestSuite');
		App::uses('ControllerTestCase', 'TestSuite');
		App::uses('CakeTestModel', 'TestSuite/Fixture');
		App::uses('CakeTestFixture', 'TestSuite/Fixture');
		App::uses('CakeFixtureManager', 'TestSuite/Fixture');
		return true;
	}

/**
 * Map PHPUnit 3/4-era class names onto the modern PSR-4 ones. Older CakePHP 2.x
 * test files reference these legacy names; aliasing keeps them working under
 * PHPUnit 6+ without touching every test file.
 *
 * @return void
 */
	public static function aliasLegacyClasses() {
		$aliases = [
			'PHPUnit_Framework_AssertionFailedError' => 'PHPUnit\\Framework\\AssertionFailedError',
			'PHPUnit_Framework_Constraint_IsAnything' => 'PHPUnit\\Framework\\Constraint\\IsAnything',
			'PHPUnit_Framework_Constraint_Not' => 'PHPUnit\\Framework\\Constraint\\LogicalNot',
			'PHPUnit_Framework_Constraint_PCREMatch' => 'PHPUnit\\Framework\\Constraint\\RegularExpression',
			'PHPUnit_Framework_Error' => 'PHPUnit\\Framework\\Error\\Error',
			'PHPUnit_Framework_Error_Notice' => 'PHPUnit\\Framework\\Error\\Notice',
			'PHPUnit_Framework_Error_Warning' => 'PHPUnit\\Framework\\Error\\Warning',
			'PHPUnit_Framework_MockObject_MockObject' => 'PHPUnit\\Framework\\MockObject\\MockObject',
			'PHPUnit_Framework_Test' => 'PHPUnit\\Framework\\Test',
			'PHPUnit_Framework_TestCase' => 'PHPUnit\\Framework\\TestCase',
			'PHPUnit_Framework_TestResult' => 'PHPUnit\\Framework\\TestResult',
			'PHPUnit_Framework_TestSuite' => 'PHPUnit\\Framework\\TestSuite',
			'PHPUnit_Runner_Version' => 'PHPUnit\\Runner\\Version',
			'PHPUnit_TextUI_ResultPrinter' => 'PHPUnit\\TextUI\\DefaultResultPrinter',
		];
		foreach ($aliases as $legacy => $modern) {
			if (!class_exists($legacy, false) && class_exists($modern)) {
				class_alias($modern, $legacy);
			}
		}
	}

/**
 * Checks for the xdebug extension required to do code coverage. Displays an error
 * if xdebug isn't installed.
 *
 * @return void
 */
	protected function _checkXdebug() {
		if (!extension_loaded('xdebug')) {
			$baseDir = $this->_baseDir;
			include CAKE . 'TestSuite' . DS . 'templates' . DS . 'xdebug.php';
			exit();
		}
	}

/**
 * Generates a page containing a list of test cases that could be run.
 *
 * @return void
 */
	protected function _testCaseList() {
		$command = new CakeTestSuiteCommand('', $this->params);
		$Reporter = $command->handleReporter($this->params['output']);
		$Reporter->paintDocumentStart();
		$Reporter->paintTestMenu();
		$Reporter->testCaseList();
		$Reporter->paintDocumentEnd();
	}

/**
 * Sets the params, calling this will bypass the auto parameter parsing.
 *
 * @param array $params Array of parameters for the dispatcher
 * @return void
 */
	public function setParams($params) {
		$this->params = $params;
		$this->_paramsParsed = true;
	}

/**
 * Parse URL params into a 'request'
 *
 * @return void
 */
	protected function _parseParams() {
		if (!$this->_paramsParsed) {
			if (!isset($_SERVER['SERVER_NAME'])) {
				$_SERVER['SERVER_NAME'] = '';
			}
			foreach ($this->params as $key => $value) {
				if (isset($_GET[$key])) {
					$this->params[$key] = $_GET[$key];
				}
			}
			if (isset($_GET['code_coverage'])) {
				$this->params['codeCoverage'] = true;
				$this->_checkXdebug();
			}
		}
		if (empty($this->params['plugin']) && empty($this->params['core'])) {
			$this->params['app'] = true;
		}
		$this->params['baseUrl'] = $this->_baseUrl;
		$this->params['baseDir'] = $this->_baseDir;
	}

/**
 * Runs a test case file.
 *
 * @return void
 */
	protected function _runTestCase() {
		$commandArgs = array(
			'case' => $this->params['case'],
			'core' => $this->params['core'],
			'app' => $this->params['app'],
			'plugin' => $this->params['plugin'],
			'codeCoverage' => $this->params['codeCoverage'],
			'showPasses' => !empty($this->params['show_passes']),
			'baseUrl' => $this->_baseUrl,
			'baseDir' => $this->_baseDir,
		);

		$options = array(
			'--filter', $this->params['filter'],
			'--output', $this->params['output'],
			'--fixture', $this->params['fixture']
		);
		restore_error_handler();

		try {
			static::time();
			$command = new CakeTestSuiteCommand('CakeTestLoader', $commandArgs);
			$command->run(array_merge(
				[$options],
				['--bootstrap', 'Test/bootstrap.php', $commandArgs['case']],
			));
		} catch (MissingConnectionException $exception) {
			ob_end_clean();
			$baseDir = $this->_baseDir;
			include CAKE . 'TestSuite' . DS . 'templates' . DS . 'missing_connection.php';
			exit();
		}
	}

/**
 * Sets a static timestamp
 *
 * @param bool $reset to set new static timestamp.
 *
 * @return int timestamp
 */
	public static function time(bool $reset = false): int {
		static $now;
		if ($reset || !$now) {
			$now = time();
		}
		return $now;
	}

/**
 * Returns formatted date string using static time
 * This method is being used as formatter for created, modified and updated fields in Model::save()
 *
 * @param string $format format to be used.
 *
 * @return string formatted date
 */
	public static function date(string $format): string {
		return date($format, static::time());
	}
}
